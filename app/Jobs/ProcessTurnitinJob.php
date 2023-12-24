<?php

namespace App\Jobs;

use App\Models\FileData;

use Illuminate\Bus\Queueable;
use App\Models\UserTransaction;
use App\Models\TurnitinAvailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessTurnitinJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($invoice_id)
    {
        $this->invoice_id = trim($invoice_id);
    }

    /**
     * Execute the job.
     *
     * @return void
     */

    private function sendInvoice($phone_number)
    {
        $requestId = uniqid();
        $messageSendInvoice = str_replace('{{ nomor_file }}', $this->invoice_id, env("MESSAGE_SEND_INVOICE"));
        Redis::publish('send_message', json_encode(["phone_number" => $phone_number, "message" => $messageSendInvoice, "request_id" => $requestId]));

        return true;
    }
    public function handle()
    {

        Log::info('Job processing with invoice id ' . $this->invoice_id);
        // $getInvoice = UserTransaction::with('user')->where('tx_id', $invoice_id)->get()->first();

        // $userTransactions = UserTransaction::withwhere(['tx_id' => $this->invoice_id, 'status' => 'paid'])->get();
        $userTransactions = UserTransaction::with('user')->where(['user_transactions.tx_id' => $this->invoice_id, 'user_transactions.status' => 'paid'])->get();

        if ($userTransactions->isEmpty()) {
            Log::info("[TX_ID : " . $this->invoice_id . "] Transaksi tidak ada atau tidak tersedia");
            return false;
        }

        $this->sendInvoice($userTransactions[0]->user->phone_number);

        Log::info($userTransactions);
        
        foreach ($userTransactions as $userTransaction) {

            Log::info('Job processing : ' . $userTransaction);

            try {
                
                $fileId = $userTransaction->file_id;
                $fileDetailData = FileData::where('file_id', $fileId)->first();

                $userTransaction->status = "processing";
                $userTransaction->save();

                DB::beginTransaction();

                $randomProcess = TurnitinAvailable::where('is_used', 0)->inRandomOrder()->lockForUpdate()->first();

                if ($randomProcess) {

                    $postData = [
                        'process' => $randomProcess->class_id,
                        'fileId' => $fileId,
                        'phoneNumber' => $userTransaction->user->phone_number,
                        'file' => [
                            'title' => $fileDetailData['title'],
                            'first_author' => $fileDetailData['first_author'],
                            'second_author' => $fileDetailData['second_author'],
                        ]
                    ];
                    LOG::info($postData);

                    $response = Http::post('http://34.126.149.13/turnitin/process', $postData);

                    // $data = $response->json();
                    $randomProcess->update(['is_used' => 1, 'used_by' => $fileId]);

                    DB::commit();
                } else {
                    Log::info("ROLLBACK");
                    DB::rollBack();
                }
            } catch (\Exception $e) {
                Log::info($e);
            }
        }
    }
}
