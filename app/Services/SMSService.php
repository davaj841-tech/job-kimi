<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Sms\KavenegarSmsGateway;
use App\Services\Sms\MeliPayamakSmsGateway;
use App\Services\Sms\SmsGatewayInterface;
use Illuminate\Support\Facades\Log;

class SMSService
{
    public function gateway(): SmsGatewayInterface
    {
        return match (Setting::getFilled('sms_gateway', config('services.sms.gateway', 'kavenegar'))) {
            'melipayamak' => new MeliPayamakSmsGateway,
            default => new KavenegarSmsGateway,
        };
    }

    public function sendSMS(string $mobile, string $message): bool
    {
        try {
            return $this->gateway()->send($mobile, $message);
        } catch (\Throwable $e) {
            Log::error('SMS send failed', [
                'error' => $e->getMessage(),
                'mobile' => $mobile,
            ]);

            return false;
        }
    }
}
