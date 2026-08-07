<?php

namespace App\Services\Sms;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function gateway(): SmsGatewayInterface
    {
        // انتخاب درگاه SMS از تنظیمات ادمین
        return match (Setting::get('sms_gateway', 'kavenegar')) {
            'melipayamak' => new MeliPayamakSmsGateway,
            default => new KavenegarSmsGateway,
        };
    }

    public function sendOtp(string $mobile, string $code): bool
    {
        $message = "کد تایید جاب‌آزمون: {$code}";

        try {
            return $this->gateway()->send($mobile, $message);
        } catch (\Throwable $e) {
            Log::error('SMS send failed', ['error' => $e->getMessage(), 'mobile' => $mobile]);

            return false;
        }
    }
}
