<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Utils\SmsUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;


class OTPVerificationController extends Controller
{
    public function send_code($user)
    {
        if ($user->email) {
            $array['view'] = 'emails.invoice';
            $array['subject'] = "Verification code for Beauty Booth Qatar";
            $array['from'] = env('MAIL_FROM_ADDRESS');
            $email = $user->email;


            // Mail::send('emails.otp', ['array' => $array, "code" => $user->verification_code], function ($message) use ($email) {
            //     $message->from('notification@beautyboothqa.com', env('APP_NAME'));
            //     $message->to($email)->subject("Verification code for Beauty Booth Qatar");
            // });

            try {
                Mail::send('emails.otp', ['array' => $array, 'code' => $user->verification_code], function ($message) use ($email) {
                    $message->from(config('mail.from.address'), config('mail.from.name'));
                    $message->to($email)->subject("Verification code for Beauty Booth BD");
                });
            } catch (\Exception $e) {
                Log::error('Mail send failed: ' . $e->getMessage());
                return response()->json(['error' => 'Failed to send email', 'details' => $e->getMessage()], 500);
            }
        } else {
            try{
             
                SmsUtility::phone_number_verification($user);
            } catch (\Exception $e) {
                Log::error('SMS send failed: ' . $e->getMessage());
                return response()->json(['error' => 'Failed to send SMS', 'details' => $e->getMessage()], 500);
            }
        }
    }
}
