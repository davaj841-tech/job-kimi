<?php

namespace App\Support;

final class QuestionTextNormalizer
{
    public static function normalize(string $text): string
    {
        $text = strip_tags($text);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return mb_strtolower($text);
    }

    public static function fingerprint(string $text): string
    {
        return hash('sha256', self::normalize($text));
    }
}
