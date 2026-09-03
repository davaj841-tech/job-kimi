<?php

namespace App\Services\Sms;

interface SupportsOtpPattern
{
    public function supportsOtpPattern(): bool;

    public function sendOtpPattern(string $mobile, string $code): bool;
}
