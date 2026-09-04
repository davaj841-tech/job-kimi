<?php

namespace App\Services\Sms;

interface SmsServiceInterface
{
    public function gateway(): SmsGatewayInterface;

    public function sendSMS(string $mobile, string $message, string $messageType = 'transactional'): bool;

    public function sendOtp(string $mobile, string $code): bool;

    public function sendDetailed(string $mobile, string $message, string $messageType = 'transactional'): SmsResult;

    public function sendOtpDetailed(string $mobile, string $code): SmsResult;

    public function queue(string $mobile, string $message, string $messageType = 'transactional'): bool;

    /**
     * @return array<string, mixed>
     */
    public function health(): array;

    public function isEnabled(): bool;

    public function isOtpEnabled(): bool;

    public function isTransactionalEnabled(): bool;
}
