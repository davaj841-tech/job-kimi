<?php

namespace App\Services\Sms;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KavenegarSmsGateway implements SmsGatewayInterface
{
    /** ارسال پیامک از طریق کاوه‌نگار — کلید از جدول settings خوانده می‌شود */
    public function send(string $mobile, string $message): bool
    {
        $apiKey = Setting::get('sms_api_key');

        if (blank($apiKey)) {
            Log::info('Kavenegar OTP (no api key)', ['mobile' => $mobile, 'message' => $message]);

            return true;
        }

        $response = Http::get("https://api.kavenegar.com/v1/{$apiKey}/sms/send.json", [
            'receptor' => $mobile,
            'message' => $message,
        ]);

        return $response->successful();
    }
}
