<?php

namespace App\Http\Controllers;
use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Jobs\ProcessTurnitinJob;
use Illuminate\Support\Facades\Http;

class TurnitinController extends Controller
{
    public function checkPayment($invoice_id) {
        $isoDateTime = Carbon::now()->toIso8601String();
        $dateTimeFinal = substr($isoDateTime, 0, 19) . "Z";

        $getUrl = 'https://api.doku.com';
        $targetPath = '/orders/v1/status/'. $invoice_id;
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

        if(isset($responseData['error'])) {
            return false;
        }

        return $responseData['transaction']['status'];
    }

    public function showInvoice($invoice_id)
    {
        $paymentStatus = 'SUCCESS';

        // $paymentStatus = $this->checkPayment($invoice_id);
        
        // if(!$paymentStatus) {
        //     return response()->json(['status' => 'error'], 200);
        // }

        if($paymentStatus == 'SUCCESS') {
            // ProcessTurnitinJob::dispatch($invoice_id);
        }
        
        // return response()->json(['status' => $paymentStatus], 200);
        return view('invoice', ['status' => $paymentStatus]);

    }
}
