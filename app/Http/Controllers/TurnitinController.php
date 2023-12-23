<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Models\UserTransaction;
use App\Jobs\ProcessTurnitinJob;
use Illuminate\Support\Facades\DB;
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
}
