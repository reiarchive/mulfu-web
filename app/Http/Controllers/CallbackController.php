<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\DokuCallback;
use Illuminate\Http\Request;
use App\Models\UserTransaction;
use App\Jobs\ProcessTurnitinJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CallbackController extends Controller
{

    protected $userTransactionController;

    public function __construct(UserTransactionController $userTransactionController)
    {
        $this->userTransactionController = $userTransactionController;
    }
    private function insertLOG($data)
    {
        Log::info($data);

        $dokuCallback = new DokuCallback();

        $dokuCallback->CUSTOMERPAN = $data['CUSTOMERPAN'];
        $dokuCallback->TRANSACTIONID = $data['TRANSACTIONID'];
        $dokuCallback->TXNDATE = $data['TXNDATE'];
        $dokuCallback->TERMINALID = $data['TERMINALID'];
        $dokuCallback->ISSUERID = $data['ISSUERID'];
        $dokuCallback->ISSUERNAME = $data['ISSUERNAME'];
        $dokuCallback->AMOUNT = $data['AMOUNT'];
        $dokuCallback->TXNSTATUS = $data['TXNSTATUS'];
        $dokuCallback->WORDS = $data['WORDS'];
        $dokuCallback->CUSTOMERNAME = $data['CUSTOMERNAME'];
        $dokuCallback->ORIGIN = $data['ORIGIN'];
        $dokuCallback->CONVENIENCEFEE = $data['CONVENIENCEFEE'];
        $dokuCallback->ACQUIRER = $data['ACQUIRER'];
        $dokuCallback->MERCHANTPAN = $data['MERCHANTPAN'];
        $dokuCallback->INVOICE = $data['INVOICE'];
        $dokuCallback->REFERENCEID = $data['REFERENCEID'];

        $dokuCallback->save();
    }

    private function addBalanceToRefferal($data)
    {
        $queryResult = UserTransaction::where('tx_id', $data['TRANSACTIONID']);

        // Get the count of records
        $transactionCount = $queryResult->count();

        // If there is at least one record with the same tx_id
        if ($transactionCount > 0) {
            // Get the first record with the same tx_id
            $firstTransaction = $queryResult->first();

            // Get the user_id from the first record
            $userId = $firstTransaction->user_id;

            // Retrieve the 'invited_by' from the 'users' model based on user_id
            $invitedBy = User::where('id', $userId)->value('invited_by');
            Log::info("Proses update balance");

            if ($invitedBy !== null) {
                // Assuming you have a method named 'addBalance' in your User model
                $invitedUser = User::find($invitedBy);
                if ($invitedUser) {
                    $invitedUser->increment('balance', $data['AMOUNT'] * 0.25);
                    Log::info("Berhasil update balance");
                }
            }
            // Use $invitedBy as needed
            // ...

        } else {
            return "No transactions found with tx_id: " . $data['TRANSACTIONID'];
        }
    }

    public function qris(Request $request)
    {
        // Log the entire request
        Log::info('Request Data:', ['data' => $request->all()]);

        $validatedData = $request->validate([
            'CUSTOMERPAN' => 'required|string|max:255',
            'TRANSACTIONID' => 'required|string|max:255',
            'TXNDATE' => 'required|date',
            'TERMINALID' => 'required|string|max:255',
            'ISSUERID' => 'required|string|max:255',
            'ISSUERNAME' => 'required|string|max:255',
            'AMOUNT' => 'required|numeric',
            'TXNSTATUS' => 'required|string|max:255',
            'WORDS' => 'required|string|max:255',
            'CUSTOMERNAME' => 'required|string|max:255',
            'ORIGIN' => 'required|string|max:255',
            'CONVENIENCEFEE' => 'required|string|max:255',
            'ACQUIRER' => 'required|string|max:255',
            'MERCHANTPAN' => 'required|string|max:255',
            'INVOICE' => 'required|string|max:255',
            'REFERENCEID' => 'required|string|max:255',
        ]);

        // Check if a record with the same TRANSACTIONID exists
        $existingRecord = DokuCallback::where('TRANSACTIONID', $validatedData['TRANSACTIONID'])->first();

        if ($existingRecord) {

            // If the status is the same, return a response indicating the record already exists
            if ($existingRecord->TXNSTATUS == $validatedData['TXNSTATUS']) {
                return response()->json(['message' => 'Record already exists with the same status']);
            }


            if ($validatedData['TXNSTATUS'] == "S") {

                $changeStatus = $this->userTransactionController->setStatus($validatedData['TRANSACTIONID'], 'waiting payment', 'paid');

                if ($changeStatus) {
                    $this->addBalanceToRefferal($validatedData);
                    ProcessTurnitinJob::dispatch($validatedData['TRANSACTIONID']);
                }
            }

            // If the status is different, update the status
            $existingRecord->update(['TXNSTATUS' => $validatedData['TXNSTATUS']]);
            return response()->json(['message' => 'Status updated successfully']);
        }

        // If no existing record, create a new one
        $this->insertLOG($validatedData);

        $changeStatus = $this->userTransactionController->setStatus($validatedData['TRANSACTIONID'], 'waiting payment', 'paid');

        if ($changeStatus) {
            $this->addBalanceToRefferal($validatedData);
            ProcessTurnitinJob::dispatch($validatedData['TRANSACTIONID']);
            LOG::info("PROSESING CUYY");
        }

        // $this->userTransactionController->setStatus($validatedData['TRANSACTIONID'], 'waiting payment', 'paid');

        // ProcessTurnitinJob::dispatch();
        return response()->json(['message' => 'DokuCallback record created successfully']);
    }
}
