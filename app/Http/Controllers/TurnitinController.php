<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Models\UserTransaction;
use App\Jobs\ProcessTurnitinJob;
use App\Models\TurnitinAvailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\UserTransactionController;

class TurnitinController extends Controller
{

    protected $userTransactionController;

    public function __construct(UserTransactionController $userTransactionController)
    {
        $this->userTransactionController = $userTransactionController;
    }

    public function checkPayment($invoice_id)
    {
        $isoDateTime = Carbon::now()->toIso8601String();
        $dateTimeFinal = substr($isoDateTime, 0, 19) . "Z";

        $getUrl = 'https://api.doku.com';
        $targetPath = '/orders/v1/status/' . $invoice_id;
        $url = $getUrl . $targetPath;

        // Prepare signature component
        $componentSignature = "Client-Id:" . env('DOKU_CLIENT_ID') . "\n" .
            "Request-Id:" . $invoice_id . "\n" .
            "Request-Timestamp:" . $dateTimeFinal . "\n" .
            "Request-Target:" . $targetPath;

        // Generate signature
        $signature = base64_encode(hash_hmac('sha256', $componentSignature, env('DOKU_SECRET_KEY'), true));

        $headers = [
            'Content-Type' => 'application/json',
            'Client-Id' => env('DOKU_CLIENT_ID'),
            'Request-Id' => $invoice_id,
            'Request-Timestamp' => $dateTimeFinal,
            'Signature' => 'HMACSHA256=' . $signature,
        ];

        // Send HTTP request
        $response = Http::withHeaders($headers)->get($url);

        $responseData = $response->json();
        // var_dump($responseData);

        if (isset($responseData['error'])) {
            return false;
        }

        return $responseData['transaction']['status'];
    }

    public function showInvoice($invoice_id)
    {
        $invoice = UserTransaction::with('user')->where(['user_transactions.tx_id' => $invoice_id])->get();

        // $invoice = UserTransaction::where(['tx_id' => $invoice_id])->get();

        if ($invoice->isEmpty()) {
            return response()->json(['status' => 'error', 'message' => 'Invoice tidak ditemukan'], 200);
        }

        if ($invoice[0]['status'] == 'waiting payment') {

            $paymentStatus = $this->checkPayment($invoice_id);

            if (!$paymentStatus) {
                return response()->json(['status' => 'error'], 200);
            }

            if ($paymentStatus == 'SUCCESS') {

                $changeStatus = $this->userTransactionController->setStatus($invoice_id, 'waiting payment', 'paid');

                if ($changeStatus) {
                    ProcessTurnitinJob::dispatch($invoice_id);
                }
            }
        }

        // $getInvoice = UserTransaction::where(['tx_id' => $invoice_id])->get();
        // var_dump($invoice[0]);

        return view('invoice', ['status' => $invoice, 'phone_number' => $invoice[0]->user->phone_number]);
    }

    public function changeProcess(Request $request)
    {

        $file_id = $request->file_id;
        // UPDATE FROM turnitin_availables SET used_by = "NON REUSEABLE ERROR" WHERE used_by = $file_id

        try {

            $existingRecord = TurnitinAvailable::where('used_by', $file_id)->exists();

            // if(!$existingRecord) {
            //     return response()->json(["error" => 1, "message" => "file id not found"]);
            // }

            TurnitinAvailable::where('used_by', $file_id)->update(['used_by' => 'NON REUSABLE ERROR']);

            // Begin a database transaction
            DB::beginTransaction();

            // Find a random available process that is not already in use
            $randomProcess = TurnitinAvailable::where('is_used', 0)->inRandomOrder()->first();

            // Check if a process is available
            if ($randomProcess) {
                // Update the process to mark it as used
                $randomProcess->update(['is_used' => 1, 'used_by' => $file_id]);

                // Log the process change
                Log::info("Changing process $file_id to " . $randomProcess->class_id);

                // Commit the transaction
                DB::commit();

                // Return success response
                return response()->json(["error" => 0, "email" => $randomProcess->username, "domain" => $randomProcess->domain, "process_id" => $randomProcess->class_id]);
            } else {
                // Log if no process is available
                return response()->json(["error" => 1, "message" => "No available process found"]);
                Log::info("No available process found");
            }

        } catch (\Exception $e) {
            // Handle exceptions and log the error
            Log::error("Error: " . $e->getMessage());

            // Rollback the transaction in case of an exception
            DB::rollBack();
        }

        // Return an error response if something went wrong
        return response()->json(["error" => 1, "message" => "Unable to process request"]);
    }
}
