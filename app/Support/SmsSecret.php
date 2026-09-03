<?php

namespace App\Support;

/**
 * Detects UI/admin masked secret placeholders so they are never sent to SMS APIs.
 */
final class SmsSecret
{
    public static function isUsable(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $value = trim($value);
        if ($value === '') {
            return false;
        }

        return ! self::isPlaceholder($value);
    }

    public static function isPlaceholder(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || $value === '********' || $value === '****') {
            return true;
        }

        // UUID-style mask: 21e5e8d1-****-****-****-bc1c8ee61403
        if (preg_match('/^[0-9a-f]{8}-\*{4}-\*{4}-\*{4}-[0-9a-f]{12}$/i', $value) === 1) {
            return true;
        }

        // Generic prefix****suffix mask used by admin UI
        return (bool) preg_match('/^[^*]{1,8}\*{4,}[^*]{0,8}$/', $value);
    }
}
