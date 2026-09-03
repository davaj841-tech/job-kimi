<?php

namespace App\Support;

final class SmsMobileMask
{
    public static function mask(?string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile) ?? '';
        if (strlen($digits) < 7) {
            return '***';
        }

        return substr($digits, 0, 4).str_repeat('*', max(0, strlen($digits) - 6)).substr($digits, -2);
    }
}
