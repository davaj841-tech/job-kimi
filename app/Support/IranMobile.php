<?php

namespace App\Support;

final class IranMobile
{
    /**
     * Normalize Iranian mobiles to 09XXXXXXXXX.
     * Accepts 0912…, +98912…, 98912…, 912… and Persian/Arabic digits.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        $value = preg_replace('/[\s\-\(\)]+/', '', $value) ?? '';

        if (str_starts_with($value, '0098')) {
            $value = substr($value, 4);
        } elseif (str_starts_with($value, '+98')) {
            $value = substr($value, 3);
        } elseif (str_starts_with($value, '98') && strlen($value) >= 12) {
            $value = substr($value, 2);
        }

        if (str_starts_with($value, '9') && strlen($value) === 10) {
            $value = '0'.$value;
        }

        return self::isValid($value) ? $value : null;
    }

    public static function isValid(?string $value): bool
    {
        return is_string($value) && preg_match('/^09\d{9}$/', $value) === 1;
    }
}
