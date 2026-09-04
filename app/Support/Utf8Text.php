<?php

namespace App\Support;

/**
 * Sanitize and safely truncate strings for MySQL utf8/utf8mb4 columns.
 */
final class Utf8Text
{
    public static function sanitize(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Drop invalid UTF-8 sequences (lone high bytes like \xDB from mid-cut / bad sources).
        $encoded = json_encode($value, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE);
        if (is_string($encoded)) {
            $decoded = json_decode($encoded);
            if (is_string($decoded)) {
                $value = $decoded;
            }
        }

        if (function_exists('iconv')) {
            $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            if (is_string($cleaned)) {
                $value = $cleaned;
            }
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            $value = is_string($converted) ? $converted : '';
        }

        $value = str_replace("\u{FFFD}", '', $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;

        return $value;
    }

    /**
     * Truncate by characters without splitting multi-byte sequences.
     * Uses ASCII "..." so MySQL never sees a broken prefix + U+2026.
     */
    public static function limit(string $value, int $maxChars, string $end = '...'): string
    {
        $value = self::sanitize($value);
        $end = self::sanitize($end);

        if ($maxChars <= 0) {
            return '';
        }

        if (mb_strlen($value, 'UTF-8') <= $maxChars) {
            return $value;
        }

        $endLen = mb_strlen($end, 'UTF-8');
        $cutAt = max(0, $maxChars - $endLen);
        $cut = mb_substr($value, 0, $cutAt, 'UTF-8');

        return self::sanitize(rtrim($cut, " \t\n\r\0\x0B،,.")).$end;
    }
}
