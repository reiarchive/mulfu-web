<?php

namespace App\Http\Controllers;

use stdClass;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\OneTimePassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class OTPController extends Controller
{
    private function makeOTP($userId)
    {
        // Check if there is an unused OTP for the given user
        $existingOTP = OneTimePassword::where('user_id', $userId)
            ->where('is_used', 0)
            ->first();

        if ($existingOTP) {
            // If an unused OTP exists, return it
            return $existingOTP->otp;
        }

        // If no unused OTP exists, create a new one
        $newOTP = new OneTimePassword();
        $newOTP->user_id = $userId;
        $newOTP->otp = $this->generateRandomOTP();
        $newOTP->is_used = 0;
        $newOTP->save();

        return $newOTP->otp;
    }

    private function generateRandomOTP($length = 4)
    {
        // Generate a random OTP of the specified length
        return str_pad(mt_rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function createUser($phone_number)
    {
        $user = new User();
        $user->phone_number = $phone_number;
        $user->save();
        return $user->id;
    }

    public function sendOTP(Request $request)
    {
        $phone_number = substr($request->phone_number, 0, 1) == '0' ? '62' . substr($request->phone_number, 1) : $request->phone_number;

        if (!User::where("phone_number", $phone_number)->exists()) {
            $userId = $this->createUser($phone_number);
        } else {
            $userId = User::where("phone_number", $phone_number)->value('id');
        }

        $userOTP = $this->makeOTP($userId);
        try {

            $messageSendOTP = str_replace('{{ otp }}', (string)$userOTP, env("MESSAGE_SEND_OTP"));

            $requestId = uniqid();
            $response = new stdClass();
            Redis::publish('send_message', json_encode(["phone_number" => $phone_number, "message" => $messageSendOTP, "request_id" => $requestId]));

            $pubsub = Redis::pubSubLoop();
            $pubsub->subscribe('receive_message');

            $timeout = now()->addSeconds(5);

            while (now()->lt($timeout)) {

                foreach ($pubsub as $message) {
                    $decodedMessage = json_decode($message->payload);
                    if (is_object($decodedMessage) && property_exists($decodedMessage, 'request_id')) {
                        if ($decodedMessage->request_id == $requestId) {
                            $response = $decodedMessage;
                            $pubsub->unsubscribe();
                        }
                    }
                    break;
                }

                if (count((array)$response) !== 0) {
                    break;
                }
            }


            if ($response->error == 0) {
                return response()->json(["error" => 0, "message" => "OTP Successfully Sent"]);
            }
            
            return response()->json(["error" => 1, "message" => "Nomor whatsapp tidak ditemukan"]);

        } catch (\Exception $e) {
            var_dump($e);
        }
    }

    public function verifyOTP(Request $request)
    {
        $phone_number = substr($request->phone_number, 0, 1) == '0' ? '62' . substr($request->phone_number, 1) : $request->phone_number;

        $otp = $request->otp;

        // Corrected query to use the 'user_id' column
        $existingOTP = OneTimePassword::with("user")
            ->whereHas('user', function ($query) use ($phone_number) {
                $query->where('phone_number', $phone_number);
            })
            ->where('is_used', 0)
            ->first();

        if (!$existingOTP) {
            return response()->json(["error" => 1, "message" => "OTP Failed to verify"]);
        }

        if ($existingOTP->otp == $otp) {
            // Mark the OTP as used
            $existingOTP->update(['is_used' => 1]);

            return response()->json(["error" => 0, "message" => "OTP Verified"]);
        } else {
            return response()->json(["error" => 1, "message" => "OTP Wrong"]);
        }
    }
}
