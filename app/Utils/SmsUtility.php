<?php

namespace App\Utils;

use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Log;

class SmsUtility
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public static function phone_number_verification($user)
    {
        
        if (!$user || empty($user->phone) || empty($user->verification_code)) {
            Log::warning('Phone verification skipped: missing user, phone, or verification code.');
            return;
        }

        $smsTemplate = SmsTemplate::where('identifier', 'phone_number_verification')->first();
        // dd($smsTemplate,'Nishad');

        if (!$smsTemplate) {
            Log::error('SMS template not found for phone_number_verification');
            return;
        }

        $smsBody = str_replace(
            ['[[code]]', '[[site_name]]'],
            [$user->verification_code, config('app.name')],
            $smsTemplate->sms_body
        );

        // dd($smsBody,'Nishad');

        try {
            sendSMS($user->phone, config('app.name'), $smsBody, $smsTemplate->template_id);
        } catch (\Exception $e) {
            Log::error('SMS sending failed: ' . $e->getMessage(), ['phone' => $user->phone]);
        }
    }



    public static function password_reset($user = '')
    {
        $sms_template   = SmsTemplate::where('identifier', 'password_reset')->first();
        $sms_body       = $sms_template->sms_body;
        $sms_body       = str_replace('[[code]]', $user->verification_code, $sms_body);
        $template_id    = $sms_template->template_id;
        try {
            sendSMS($user->phone, env('APP_NAME'), $sms_body, $template_id);
        } catch (\Exception $e) {
        }
    }

    public static function order_placement($phone = '', $order = '')
    {
        $sms_template   = SmsTemplate::where('identifier', 'order_placement')->first();
        $sms_body       = $sms_template->sms_body;
        $sms_body       = str_replace('[[order_code]]', $order->id, $sms_body);
        $template_id    = $sms_template->template_id;
        try {
            sendSMS($phone, env('APP_NAME'), $sms_body, $template_id);
        } catch (\Exception $e) {
        }
    }

    public static function delivery_status_change($phone = '', $order)
    {
        $sms_template   = SmsTemplate::where('identifier', 'delivery_status_change')->first();
        $sms_body       = $sms_template->sms_body;
        $delivery_status = translate(ucfirst(str_replace('_', ' ', $order->delivery_status)));

        $sms_body       = str_replace('[[delivery_status]]', $delivery_status, $sms_body);
        $sms_body       = str_replace('[[order_code]]', $order->code, $sms_body);
        $template_id    = $sms_template->template_id;

        try {
            sendSMS($phone, env('APP_NAME'), $sms_body, $template_id);
        } catch (\Exception $e) {
        }
    }

    public static function payment_status_change($phone = '', $order = '')
    {
        $sms_template   = SmsTemplate::where('identifier', 'payment_status_change')->first();
        $sms_body       = $sms_template->sms_body;
        $sms_body       = str_replace('[[payment_status]]', $order->payment_status, $sms_body);
        $sms_body       = str_replace('[[order_code]]', $order->code, $sms_body);
        $template_id    = $sms_template->template_id;
        try {
            sendSMS($phone, env('APP_NAME'), $sms_body, $template_id);
        } catch (\Exception $e) {
        }
    }

    public static function assign_delivery_boy($phone = '', $code = '')
    {
        $sms_template   = SmsTemplate::where('identifier', 'assign_delivery_boy')->first();
        $sms_body       = $sms_template->sms_body;
        $sms_body       = str_replace('[[order_code]]', $code, $sms_body);
        $template_id    = $sms_template->template_id;
        try {
            sendSMS($phone, env('APP_NAME'), $sms_body, $template_id);
        } catch (\Exception $e) {
        }
    }
}
