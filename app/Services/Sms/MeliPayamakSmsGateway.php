<?php

namespace App\Services\Sms;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeliPayamakSmsGateway implements SmsGatewayInterface
{
    public function send(string $mobile, string $message): bool
    {
        $username = (string) Setting::getFilled('sms_api_key', config('services.melipayamak.username'));
        $password = (string) Setting::getFilled('sms_password', config('services.melipayamak.password'));
        $from = (string) Setting::getFilled('sms_from', config('services.melipayamak.from'));

        if (blank($username) || blank($password)) {
            if (config('services.sms.allow_log_fallback')) {
                Log::info('MeliPayamak OTP skipped (no credentials; log fallback)', [
                    'mobile' => $mobile,
                ]);

                return true;
            }

            Log::error('MeliPayamak SMS aborted: missing credentials');

            return false;
        }

        $response = Http::asForm()->post('https://rest.payamak-panel.com/api/SendSMS/SendSMS', [
            'username' => $username,
            'password' => $password,
            'to' => $mobile,
            'from' => $from,
            'text' => $message,
        ]);

        if (! $response->successful()) {
            Log::warning('MeliPayamak SMS failed', [
                'status' => $response->status(),
                'mobile' => $mobile,
            ]);
        }

        return $response->successful();
    }
}
