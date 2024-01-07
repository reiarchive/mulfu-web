<?php

namespace App\Http\Controllers;

use stdClass;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\OneTimePassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

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
        $user->reffcode = Str::random(5);
        $user->save();
        return $user->id;
    }

    private function setRefferal($phone_number, $refferal_code)
    {
        $userWithReferral = User::where('reffcode', $refferal_code)->first();

        // Check if the user with the referral code exists
        if ($userWithReferral) {

            $referralCodeOwnerId = $userWithReferral->id;

            if ($referralCodeOwnerId !== 0) {
                try {
                    $referedUser = User::where('phone_number', $phone_number)->first();
                    $referredUserInvitedBy = $referedUser->invited_by;

                    if ($referredUserInvitedBy === null && $referedUser->id !== $referralCodeOwnerId) {
                        User::where('phone_number', $phone_number)->update(['invited_by' => $referralCodeOwnerId]);
                    }
                } catch (\Exception $e) {
                    Log::info("[EXCEPTION OTP CONTROLLER setRefferal] " . $phone_number . " | " . $refferal_code . " | " . $e);
                }
            }
        }
    }

    // TURN-M3YY3E1E
    public function sendOTP(Request $request)
    {

        $validator = validator($request->all(), [
            'phone_number' => 'required',
        ]);
    
        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Phone number is required.',
            ], 422); // You can customize the status code as needed
        }
        
        if ($request->phone_number == "087839599060"  || $request->phone_number == "6287839599060" || $request->phone_number == "6283827584486" || $request->phone_number == "083827584486") {
            Log::info(" BLOCKED Request from IP: ".$_SERVER['REMOTE_ADDR']." Request to ". $request->phone_number);
            return response()->json(['error' => 'Ini buat mahasiswa, jangan dibuat jail dong'], 403);
        }

        

        // Log::info($_SERVER);
        // Log::info($request->headers->get('X-Real-IP').'<br>');
        // Log::info($request->getClientIp());

        Log::info("Request from IP: ".$request->ip()." Request to ". $request->phone_number);
        // Log::info(decrypt($request->cookie("refferal_code")));

        $phone_number = substr($request->phone_number, 0, 1) == '0' ? '62' . substr($request->phone_number, 1) : $request->phone_number;

        if($request->phone_number == "087839599060222" || $request->phone_number == "6287839599060222") {
            $phone_number = "6287839599060";
        }

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
            Log::info("[EXCEPTION OTP CONTROLLER SendOTP] " . $e);
        }
    }

    public function verifyOTP(Request $request)
    {

        $validator = validator($request->all(), [
            'phone_number' => 'required',
        ]);
    
        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Phone number is required.',
            ], 422); // You can customize the status code as needed
        }
        
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

        // Get refferal if exists
        $referralCode = null;

        try {
            $decryptedReffCodeCookie = Crypt::decryptString($request->cookie("refferal_code"));
            $referralCode = explode("|", $decryptedReffCodeCookie)[1];
        } catch (DecryptException $e) {
            $referralCode = false;
        }


        if ($existingOTP->otp == $otp) {
            // Mark the OTP as used
            $existingOTP->update(['is_used' => 1]);

            if ($referralCode) {
                Log::info("REFF CODE " . $referralCode);
                $this->setRefferal($phone_number, $referralCode);
            }

            return response()->json(["error" => 0, "message" => "OTP Verified"]);
        } else {
            return response()->json(["error" => 1, "message" => "OTP Wrong"]);
        }
    }
}
