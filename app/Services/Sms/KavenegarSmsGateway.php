<?php

namespace App\Services\Sms;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KavenegarSmsGateway implements SmsGatewayInterface
{
    public function send(string $mobile, string $message): bool
    {
        $apiKey = (string) Setting::getFilled('sms_api_key', config('services.kavenegar.api_key'));

        if (blank($apiKey)) {
            if (config('services.sms.allow_log_fallback')) {
                Log::info('Kavenegar OTP skipped (no api key; log fallback)', [
                    'mobile' => $mobile,
                ]);

                return true;
            }

            Log::error('Kavenegar SMS aborted: missing API key');

            return false;
        }

        $response = Http::get("https://api.kavenegar.com/v1/{$apiKey}/sms/send.json", [
            'receptor' => $mobile,
            'message' => $message,
        ]);

        if (! $response->successful()) {
            Log::warning('Kavenegar SMS failed', [
                'status' => $response->status(),
                'mobile' => $mobile,
            ]);
        }

        return $response->successful();
    }
}
