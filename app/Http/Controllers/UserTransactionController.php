<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserTransactionController extends Controller
{
    public function setStatus($invoice_id, $from, $to)
    {

        DB::beginTransaction();

        try {
            $userTransactions = UserTransaction::where(['tx_id' => $invoice_id, 'status' => $from])
                ->lockForUpdate()
                ->get();

            if ($userTransactions->isNotEmpty()) {
                
                foreach ($userTransactions as $userTransaction) {
                    $userTransaction->status = $to;
                    $userTransaction->save();
                }

                // ProcessTurnitinJob::dispatch($validatedData['TRANSACTIONID']);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            Log::info("Sudah di proses");
            DB::rollBack();
            return false;
        }
    }
}
