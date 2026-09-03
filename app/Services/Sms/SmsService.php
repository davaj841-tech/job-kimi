<?php

namespace App\Services\Sms;

/**
 * Backward-compatible facade; delegates to {@see SmsManager}.
 */
class SmsService implements SmsServiceInterface
{
    public function __construct(protected SmsManager $manager) {}

    public function gateway(): SmsGatewayInterface
    {
        return $this->manager->gateway();
    }

    public function sendSMS(string $mobile, string $message, string $messageType = 'transactional'): bool
    {
        return $this->manager->sendSMS($mobile, $message, $messageType);
    }

    public function sendOtp(string $mobile, string $code): bool
    {
        return $this->manager->sendOtp($mobile, $code);
    }

    public function sendDetailed(string $mobile, string $message, string $messageType = 'transactional'): SmsResult
    {
        return $this->manager->sendDetailed($mobile, $message, $messageType);
    }

    public function sendOtpDetailed(string $mobile, string $code): SmsResult
    {
        return $this->manager->sendOtpDetailed($mobile, $code);
    }

    public function queue(string $mobile, string $message, string $messageType = 'transactional'): bool
    {
        return $this->manager->queue($mobile, $message, $messageType);
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return $this->manager->health();
    }

    public function isEnabled(): bool
    {
        return $this->manager->isEnabled();
    }

    public function isOtpEnabled(): bool
    {
        return $this->manager->isOtpEnabled();
    }

    public function isTransactionalEnabled(): bool
    {
        return $this->manager->isTransactionalEnabled();
    }
}
