<?php

namespace App\Services\Aggregation\Support;

use Illuminate\Support\Str;

/**
 * Persian/Arabic text & digit normalization helpers.
 */
class PersianText
{
    /** @var array<string, string> */
    protected static array $arabicToPersian = [
        'ي' => 'ی',
        'ى' => 'ی',
        'ك' => 'ک',
        'ة' => 'ه',
        'ؤ' => 'و',
        'إ' => 'ا',
        'أ' => 'ا',
        'آ' => 'آ',
        'ۀ' => 'ه',
        'ٱ' => 'ا',
    ];

    /** @var array<string, string> */
    protected static array $persianDigits = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strtr($value, self::$arabicToPersian);
        // Remove tatweel / bidi controls / zero-width except ZWNJ kept then normalized
        $value = preg_replace('/[\x{0640}\x{200B}\x{200E}\x{200F}\x{202A}-\x{202E}\x{FEFF}]/u', '', $value) ?? $value;
        $value = str_replace("\u{200C}", '‌', $value); // normalize ZWNJ char
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public static function toEnglishDigits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtr($value, self::$persianDigits);
    }

    public static function normalizeKey(?string $value): ?string
    {
        $value = self::normalize($value);
        if ($value === null) {
            return null;
        }

        $value = self::toEnglishDigits($value) ?? $value;
        $value = Str::lower($value);
        // Remove remaining punctuation for matching keys
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value) === '' ? null : trim($value);
    }
}
