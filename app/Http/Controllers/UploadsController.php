<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\FileData;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\TotalPriceModel;
use App\Models\UserTransaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UploadsController extends Controller
{
    public function setPayment($invoice_number, $request_id, $price, $total_items)
    {
        $currentDateTime = Carbon::now();

        // Add 24 hours to the current date and time
        $futureDateTime = $currentDateTime->addHours(24);

        // Format the date and time as "yyyyMMddHHmmss"
        $formattedDateTime = $futureDateTime->format('YmdHis');

        $requestBody = [
            'order' => [
                'amount' => $price,
                'invoice_number' => $invoice_number, // Change to your business logic
                'currency' => 'IDR',
                'callback_url' => 'https://mulfu.co/invoice/' . $invoice_number,
                'line_items' => [
                    [
                        'name' => 'Plagiarism Check',
                        'price' => 1000,
                        'quantity' => $total_items,
                    ],
                ],
            ],
            'payment' => [
                'payment_due_date' => 3,
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
        // var_dump($responseData);

        if ($responseData['message'][0] == 'SUCCESS') {
            return ['url' => $responseData['response']['payment']['url'], 'callback' => $responseData['response']['order']['callback_url']];
        } else {
            Log::info($responseData);
            return false;
        }
    }

    public function getUserId($phone_number)
    {
        $user = User::where('phone_number', $phone_number)->first();

        if (!$user) {
            return false;
        }

        return $user->id;
    }

    public function uploadTurnitinFiles(Request $request)
    {
        $totalPrice = 0;

        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'files.*' => 'required|mimes:pdf,docx|max:2048000',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // $userId = $this->getUserId($request->phone_number);
        $tx_id = 'TURN-' . strtoupper(Str::random(4)) . strtoupper(Str::random(4));
        $request_id = str_replace('-', '', Str::uuid()->toString());

        foreach ($request->file('files') as $file) {

            $path = $file->storeAs('uploads', Str::random(20) . '.' . $file->getClientOriginalExtension());

            // Preparing save
            $file_id = UserTransaction::generateRandomId();

            $userTransaction = new UserTransaction();

            $userTransaction->file_location = $path;
            // $userTransaction->user_id = $userId;
            $userTransaction->file_id = $file_id;
            $userTransaction->tx_id = $tx_id;
            $userTransaction->req_id = $request_id;
            $userTransaction->status = 'waiting payment';

            $userTransaction->save();

            $fileData = new FileData();
            $fileData->file_id = $file_id;
            $fileData->real_file_name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $fileData->save();

            $totalPrice += 1000;
        }

        $totalPriceModel = new TotalPriceModel();
        $totalPriceModel->tx_id = $tx_id;
        $totalPriceModel->req_id = $request_id;
        $totalPriceModel->price = $totalPrice;
        $totalPriceModel->save();

        return response()->json(['message' => 'Files uploaded successfully', 'data' => ['request_id' => $request_id, 'price' => $totalPrice]], 200);
    }

    public function setDetailAndMakePayment(Request $request)
    {
        try {
            $userId = $this->getUserId($request->phone_number);


            /* Set destination */
            $transactions = UserTransaction::where('req_id', $request->request_id)->get();

            foreach ($transactions as $transaction) {
                /* Update user_id*/
                $transaction->user_id = $userId;
                $transaction->save();

                /* Set file data */
                $fileDetail = FileData::where('file_id', $transaction->file_id)->first();
                $fileDetail->title = trim($request->file['title']) == "" ? "Turnitin" : trim($request->file['title']);
                $fileDetail->first_author = trim($request->file['first_author']) == "" ? "by" : trim($request->file['first_author']);
                $fileDetail->second_author = trim($request->file['second_author']) == "" ? "Turnitin" : trim($request->file['second_author']);
                $fileDetail->save();
            }

            $price = TotalPriceModel::where("req_id", $request->request_id)->value("price");
            $total_items = $transactions->count();

            $url = $this->setPayment($transactions->first()->tx_id, $transactions->first()->req_id, $price, $total_items);

            if (!$url) {
                return response()->json(['error' => 1], 200);
            }

            return response()->json(['error' => 0, 'url' => $url['url']], 200);
        } catch (\Exception $e) {
            Log::info("[ERROR EXCEPTION] " . $e);
            return response()->json(['error' => 1, 'message' => 'Internal server error'], 200);
        }
    }
}
