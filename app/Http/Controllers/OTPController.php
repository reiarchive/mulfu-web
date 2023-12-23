<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\OneTimePassword;
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
        $phone_number = $request->phone_number;

        if (!User::where("phone_number", $phone_number)->exists()) {
            $userId = $this->createUser($phone_number);
        } else {
            $userId = User::where("phone_number", $phone_number)->value('id');
        }

        $userOTP = $this->makeOTP($userId);
        try {
            Redis::publish('sendOTP', json_encode(["phone_number" => $phone_number, "otp" => (string)$userOTP]));

            $prefix = config('database.redis.options.prefix');
            $channel = $prefix . 'sendOTP';

            return response()->json(["error" => 0, "message" => "OTP Successfully Sent $channel"]);
        } catch (\Exception $e) {
            var_dump($e);
        }
    }

    public function verifyOTP(Request $request)
    {
        $phone_number = $request->phone_number;
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
