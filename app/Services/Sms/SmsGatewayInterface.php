<?php

namespace App\Services\Sms;

interface SmsGatewayInterface
{
    public function name(): string;

    public function send(string $mobile, string $message): bool;

    public function sendDetailed(string $mobile, string $message, string $messageType = 'transactional'): SmsResult;
}
