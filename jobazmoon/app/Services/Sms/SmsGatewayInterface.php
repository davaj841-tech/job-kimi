<?php

namespace App\Services\Sms;

interface SmsGatewayInterface
{
    public function send(string $mobile, string $message): bool;
}
