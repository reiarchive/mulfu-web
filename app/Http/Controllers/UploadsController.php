<?php

namespace App\Http\Controllers;

use App\Models\UserTransaction;
use App\Models\User;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UploadsController extends Controller
{
    public function setPayment($invoice_number, $request_id)
    {
        $currentDateTime = Carbon::now();

        // Add 24 hours to the current date and time
        $futureDateTime = $currentDateTime->addHours(24);

        // Format the date and time as "yyyyMMddHHmmss"
        $formattedDateTime = $futureDateTime->format('YmdHis');

        $requestBody = [
            'order' => [
                'amount' => 1000,
                'invoice_number' => $invoice_number, // Change to your business logic
                'currency' => 'IDR',
                'callback_url' => 'https://mulfu.co/invoice/'. $invoice_number,
                'auto_redirect' => true,
                'line_items' => [
                    [
                        'name' => 'Plagiarism Check',
                        'price' => 1000,
                        'quantity' => 1,
                    ],
                ],
            ],
            'payment' => [
                'payment_due_date' => 60,
                "payment_method_types" => [
                    "QRIS",
                ]
            ],
            'customer' => [
                'id' => 'CUST-' . rand(1, 1000),
                'name' => 'Dummy',
                'email' => 'dummy@gmail.com',
                'phone' => '0821140908123',
                'address' => 'Jalan jalan',
                'country' => 'ID',
            ],
        ];

        $dateTime = gmdate("Y-m-d H:i:s");

        $isoDateTime = Carbon::now()->toIso8601String();
        $dateTimeFinal = substr($isoDateTime, 0, 19) . "Z";

        $getUrl = 'https://api.doku.com';

        $targetPath = '/checkout/v1/payment';
        $url = $getUrl . $targetPath;

        // Generate digest
        $digestValue = base64_encode(hash('sha256', json_encode($requestBody), true));

        // Prepare signature component
        $componentSignature = "Client-Id:" . env('DOKU_CLIENT_ID') . "\n" .
            "Request-Id:" . $request_id . "\n" .
            "Request-Timestamp:" . $dateTimeFinal . "\n" .
            "Request-Target:" . $targetPath . "\n" .
            "Digest:" . $digestValue;

        // Generate signature
        $signature = base64_encode(hash_hmac('sha256', $componentSignature, env('DOKU_SECRET_KEY'), true));
        echo $signature;

        $headers = [
            'Content-Type' => 'application/json',
            'Client-Id' => env('DOKU_CLIENT_ID'),
            'Request-Id' => $request_id,
            'Request-Timestamp' => $dateTimeFinal,
            'Signature' => 'HMACSHA256=' . $signature,
        ];


        // Send HTTP request
        $response = Http::withHeaders($headers)->post($url, $requestBody);

        $responseData = $response->json();

        if ($responseData['message'][0] == 'SUCCESS') {
            return ['url' => $responseData['response']['payment']['url'], 'callback' => $responseData['response']['order']['callback_url']];
        } else {
            return false;
        }
    }

    public function getUserId($phone_number)
    {
        $user = User::firstOrNew(['phone_number' => $phone_number]);

        if (!$user->exists) {
            $user->phone_number = $phone_number;
            $user->save();
        }

        return $user->id;
    }

    public function uploadTurnitinFiles(Request $request)
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'files.*' => 'required|mimes:pdf,docx|max:2048000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = $this->getUserId($request->phone_number);
 
        // Generate tx_id and req_id
        $tx_id = 'PLAG-' . now()->format('YmdHisu');
        $request_id = str_replace('-', '', Str::uuid()->toString());

        // Process each uploaded file
        foreach ($request->file('files') as $file) {
            $customName = Str::random(20);

            $path = $file->storeAs('uploads', $customName . '.' . $file->getClientOriginalExtension());

            // Preparing save
            $userTransaction = new UserTransaction();

            /*
            * $table->id();
            * $table->string('file_location');
            * $table->integer('user_id');
            * $table->string('file_id');
            * $table->string('tx_id');
            * $table->string('req_id');
            * $table->enum('status', ['waiting payment', 'processing', 'failed', 'success', 'cancel']);
            */

            $userTransaction->file_location = $path;
            $userTransaction->user_id = $userId;
            $userTransaction->file_id = str_replace('-', '', Str::uuid()->toString());
            $userTransaction->tx_id = $tx_id;
            $userTransaction->req_id = $request_id;
            $userTransaction->status = 'waiting payment';

            $userTransaction->save();
        }

        $url = $this->setPayment($tx_id, $request_id);

        if (!$url) {
            return response()->json(['message' => 'Files failed uploaded',], 200);
        }

        return response()->json(['message' => 'Files uploaded successfully', 'url' => $url['url'], 'callback' => $url['callback']], 200);
    }
}
