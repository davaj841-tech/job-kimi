<?php

namespace App\Services\Sms;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeliPayamakSmsGateway implements SmsGatewayInterface
{
    /** ارسال پیامک از طریق ملی‌پیامک — تنظیمات از جدول settings */
    public function send(string $mobile, string $message): bool
    {
        $apiKey = Setting::get('sms_api_key');

        if (blank($apiKey)) {
            Log::info('MeliPayamak OTP (no api key)', ['mobile' => $mobile, 'message' => $message]);

            return true;
        }

        $response = Http::asForm()->post('https://rest.payamak-panel.com/api/SendSMS/SendSMS', [
            'username' => $apiKey,
            'password' => Setting::get('sms_password', ''),
            'to' => $mobile,
            'from' => Setting::get('sms_from', ''),
            'text' => $message,
        ]);

        return $response->successful();
    }
}
