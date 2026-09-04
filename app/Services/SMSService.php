<?php

namespace App\Services;

use App\Services\Sms\SmsGatewayInterface;
use App\Services\Sms\SmsResult;
use App\Services\Sms\SmsService as CoreSmsService;

/**
 * Thin alias kept for existing jobs/controllers that inject SMSService.
 */
class SMSService
{
    public function __construct(protected CoreSmsService $sms) {}

    public function gateway(): SmsGatewayInterface
    {
        return $this->sms->gateway();
    }

    public function sendSMS(string $mobile, string $message, string $messageType = 'transactional'): bool
    {
        return $this->sms->sendSMS($mobile, $message, $messageType);
    }

    public function sendOtp(string $mobile, string $code): bool
    {
        return $this->sms->sendOtp($mobile, $code);
    }

    public function queue(string $mobile, string $message, string $messageType = 'transactional'): bool
    {
        return $this->sms->queue($mobile, $message, $messageType);
    }

    public function sendDetailed(string $mobile, string $message, string $messageType = 'transactional'): SmsResult
    {
        return $this->sms->sendDetailed($mobile, $message, $messageType);
    }
}
