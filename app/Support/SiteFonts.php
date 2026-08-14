<?php

namespace App\Support;

final class SiteFonts
{
    public const DEFAULT = 'estedad';

    /**
     * @return array<string, array{id: string, title: string, sample: string}>
     */
    public static function all(): array
    {
        return [
            'estedad' => [
                'id' => 'estedad',
                'title' => 'استمداد',
                'sample' => 'جاب‌آزمون — آمادگی استخدام',
            ],
            'vazirmatn' => [
                'id' => 'vazirmatn',
                'title' => 'وزیرمتن',
                'sample' => 'جاب‌آزمون — آمادگی استخدام',
            ],
            'shabnam' => [
                'id' => 'shabnam',
                'title' => 'شبنم',
                'sample' => 'جاب‌آزمون — آمادگی استخدام',
            ],
            'samim' => [
                'id' => 'samim',
                'title' => 'صمیم',
                'sample' => 'جاب‌آزمون — آمادگی استخدام',
            ],
            'sahel' => [
                'id' => 'sahel',
                'title' => 'ساحل',
                'sample' => 'جاب‌آزمون — آمادگی استخدام',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_keys(self::all());
    }

    public static function isValid(mixed $id): bool
    {
        return is_string($id) && isset(self::all()[$id]);
    }

    public static function normalize(mixed $id): string
    {
        return self::isValid($id) ? (string) $id : self::DEFAULT;
    }

    public static function sanitizeSize(mixed $value): int
    {
        $n = (int) $value;
        if ($n < 13 || $n > 20) {
            return 16;
        }

        return $n;
    }
}
