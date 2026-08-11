<?php

namespace App\Support;

final class SiteThemes
{
    public const DEFAULT = 'atlas';

    /**
     * @return array<string, array{id: string, title: string, desc: string, primary: string, secondary: string, page: string, hero: string, dark_hero: bool}>
     */
    public static function all(): array
    {
        return [
            'atlas' => [
                'id' => 'atlas',
                'title' => 'اطلس — حرفه‌ای',
                'desc' => 'سرمه‌ای و نارنجی سازمانی؛ مناسب برند رسمی جاب‌آزمون.',
                'primary' => '#f97316',
                'secondary' => '#0f2744',
                'page' => '#f8fafc',
                'hero' => 'navy',
                'dark_hero' => true,
            ],
            'editorial' => [
                'id' => 'editorial',
                'title' => 'تحریریه — مجله‌ای',
                'desc' => 'کاغذی گرم و آجری؛ حس تحریریه و مطالعه.',
                'primary' => '#c2410c',
                'secondary' => '#7c2d12',
                'page' => '#f6f1e8',
                'hero' => 'paper',
                'dark_hero' => false,
            ],
            'studio' => [
                'id' => 'studio',
                'title' => 'استودیو — تیره مدرن',
                'desc' => 'پس‌زمینه تیره شیشه‌ای با قرمز زنده.',
                'primary' => '#e11d48',
                'secondary' => '#0b1a2e',
                'page' => '#0f172a',
                'hero' => 'dark',
                'dark_hero' => true,
            ],
            'minimal' => [
                'id' => 'minimal',
                'title' => 'مینیمال — خلوت',
                'desc' => 'سفید، زغال و خطوط ساده بدون شلوغی.',
                'primary' => '#0f172a',
                'secondary' => '#334155',
                'page' => '#ffffff',
                'hero' => 'search',
                'dark_hero' => false,
            ],
            'emerald' => [
                'id' => 'emerald',
                'title' => 'زمرد — طبیعت',
                'desc' => 'سبز جنگلی و طلایی؛ حس رشد و قبولی.',
                'primary' => '#d97706',
                'secondary' => '#065f46',
                'page' => '#ecfdf5',
                'hero' => 'split',
                'dark_hero' => true,
            ],
            'ocean' => [
                'id' => 'ocean',
                'title' => 'اقیانوس — آبی',
                'desc' => 'فیروزه‌ای روشن روی آبی عمیق.',
                'primary' => '#06b6d4',
                'secondary' => '#0e7490',
                'page' => '#ecfeff',
                'hero' => 'navy',
                'dark_hero' => true,
            ],
            'royal' => [
                'id' => 'royal',
                'title' => 'سلطنتی — بنفش',
                'desc' => 'بنفش فاخر و طلایی برای حس ویژه.',
                'primary' => '#f59e0b',
                'secondary' => '#4c1d95',
                'page' => '#f5f3ff',
                'hero' => 'navy',
                'dark_hero' => true,
            ],
            'rose' => [
                'id' => 'rose',
                'title' => 'رز — نرم',
                'desc' => 'صورتی ملایم و گوشه‌های نرم.',
                'primary' => '#e11d48',
                'secondary' => '#9f1239',
                'page' => '#fff1f2',
                'hero' => 'paper',
                'dark_hero' => false,
            ],
            'sand' => [
                'id' => 'sand',
                'title' => 'شنزار — گرم',
                'desc' => 'خاکی و کهربایی، گرم و صمیمی.',
                'primary' => '#ea580c',
                'secondary' => '#78350f',
                'page' => '#fffbeb',
                'hero' => 'split',
                'dark_hero' => false,
            ],
            'midnight' => [
                'id' => 'midnight',
                'title' => 'نیمه‌شب — نئون',
                'desc' => 'سیاه عمیق و آبی الکتریکی.',
                'primary' => '#38bdf8',
                'secondary' => '#020617',
                'page' => '#020617',
                'hero' => 'dark',
                'dark_hero' => true,
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

    /**
     * @return array{primary: string, secondary: string, page: string}
     */
    public static function colors(string $id): array
    {
        $theme = self::all()[self::normalize($id)];

        return [
            'primary' => $theme['primary'],
            'secondary' => $theme['secondary'],
            'page' => $theme['page'],
        ];
    }

    public static function sanitizeHex(mixed $value, string $fallback): string
    {
        $hex = strtolower(trim((string) $value));
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $hex) === 1) {
            if (strlen($hex) === 4) {
                return sprintf('#%s%s%s%s%s%s', $hex[1], $hex[1], $hex[2], $hex[2], $hex[3], $hex[3]);
            }

            return $hex;
        }

        return $fallback;
    }
}
