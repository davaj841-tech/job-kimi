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
            'atlas' => self::theme('atlas', 'اطلس — حرفه‌ای', 'سرمه‌ای و نارنجی سازمانی.', '#f97316', '#0f2744', '#f8fafc', 'navy', true),
            'editorial' => self::theme('editorial', 'تحریریه — مجله‌ای', 'کاغذی گرم و آجری.', '#c2410c', '#7c2d12', '#f6f1e8', 'paper', false),
            'studio' => self::theme('studio', 'استودیو — تیره', 'تیره شیشه‌ای با قرمز زنده.', '#e11d48', '#0b1a2e', '#0f172a', 'dark', true),
            'minimal' => self::theme('minimal', 'مینیمال — خلوت', 'سفید و زغالی، بدون شلوغی.', '#0f172a', '#334155', '#ffffff', 'search', false),
            'emerald' => self::theme('emerald', 'زمرد — طبیعت', 'سبز جنگلی و طلایی.', '#d97706', '#065f46', '#ecfdf5', 'split', true),
            'ocean' => self::theme('ocean', 'اقیانوس — آبی', 'فیروزه‌ای روی آبی عمیق.', '#06b6d4', '#0e7490', '#ecfeff', 'navy', true),
            'royal' => self::theme('royal', 'سلطنتی — بنفش', 'بنفش فاخر و طلایی.', '#f59e0b', '#4c1d95', '#f5f3ff', 'navy', true),
            'rose' => self::theme('rose', 'رز — نرم', 'صورتی ملایم و گوشه‌های نرم.', '#e11d48', '#9f1239', '#fff1f2', 'paper', false),
            'sand' => self::theme('sand', 'شنزار — گرم', 'خاکی و کهربایی.', '#ea580c', '#78350f', '#fffbeb', 'split', false),
            'midnight' => self::theme('midnight', 'نیمه‌شب — نئون', 'سیاه عمیق و آبی الکتریکی.', '#38bdf8', '#020617', '#020617', 'dark', true),
            'coral' => self::theme('coral', 'مرجان — زنده', 'مرجانی روشن روی سرمه‌ای.', '#fb7185', '#1e3a5f', '#fff1f2', 'navy', true),
            'lime' => self::theme('lime', 'لیمو — تازه', 'سبز لیمویی و زغال.', '#65a30d', '#1a2e05', '#f7fee7', 'split', true),
            'indigo' => self::theme('indigo', 'نیلی — مدرن', 'نیلی و فیروزه‌ای سرد.', '#22d3ee', '#312e81', '#eef2ff', 'navy', true),
            'coffee' => self::theme('coffee', 'قهوه — کلاسیک', 'قهوه‌ای گرم و کرم.', '#b45309', '#44403c', '#faf7f2', 'paper', false),
            'cherry' => self::theme('cherry', 'گیلاس — رسمی', 'زرشکی تیره و کرم.', '#be123c', '#4c0519', '#fff7ed', 'navy', true),
            'glacier' => self::theme('glacier', 'یخچال — روشن', 'یخی و آبی کم‌رنگ.', '#0284c7', '#0c4a6e', '#f0f9ff', 'search', false),
            'sunset' => self::theme('sunset', 'غروب — پرانرژی', 'سرخابی و نارنجی غروب.', '#f43f5e', '#7c2d12', '#fff7ed', 'dark', true),
            'olive' => self::theme('olive', 'زیتون — آرام', 'زیتونی و کرم خاکی.', '#ca8a04', '#3f6212', '#f7fee7', 'split', false),
            'graphite' => self::theme('graphite', 'گرافیت — صنعتی', 'خاکستری فلزی و کهربایی.', '#facc15', '#18181b', '#f4f4f5', 'dark', true),
            'blossom' => self::theme('blossom', 'شکوفه — لطیف', 'یاسی و صورتی شکوفه.', '#c084fc', '#6b21a8', '#fdf4ff', 'paper', false),
        ];
    }

    /**
     * @return array{id: string, title: string, desc: string, primary: string, secondary: string, page: string, hero: string, dark_hero: bool}
     */
    private static function theme(
        string $id,
        string $title,
        string $desc,
        string $primary,
        string $secondary,
        string $page,
        string $hero,
        bool $darkHero
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'desc' => $desc,
            'primary' => $primary,
            'secondary' => $secondary,
            'page' => $page,
            'hero' => $hero,
            'dark_hero' => $darkHero,
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
