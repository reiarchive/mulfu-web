<?php

namespace App\Jobs;

use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\TurnitinAvailable;
use App\Models\UserTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $this->invoice_id = $invoice_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        Log::info('Job processing with req id ' . $this->invoice_id);

        $userTransactions = UserTransaction::where(['tx_id' => $this->invoice_id, 'status' => 'waiting payment'])->get();

        Log::info($userTransactions);
        foreach ($userTransactions as $userTransaction) {
            Log::info('Job processing : ' . $userTransaction);

            try {
                $fileId = $userTransaction->file_id;

                $userTransaction->status = "processing";
                $userTransaction->save();

                DB::beginTransaction();

                $randomProcess = TurnitinAvailable::where('is_used', 0)->inRandomOrder()->lockForUpdate()->first();

                if ($randomProcess) {

                    $response = Http::post('http://34.126.149.13/turnitin/process', [
                        'process' => $randomProcess->class_id,
                        'fileId' => $fileId
                    ]);

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
