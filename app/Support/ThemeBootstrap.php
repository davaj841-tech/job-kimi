<?php

namespace App\Support;

use App\Models\Setting;

final class ThemeBootstrap
{
    public static function cssFamily(string $fontId): string
    {
        return match (SiteFonts::normalize($fontId)) {
            'vazirmatn' => 'Vazirmatn, Tahoma, sans-serif',
            'shabnam' => 'Shabnam, Tahoma, sans-serif',
            'samim' => 'Samim, Tahoma, sans-serif',
            'sahel' => 'Sahel, Tahoma, sans-serif',
            default => '"Estedad Variable", Estedad, Tahoma, sans-serif',
        };
    }

    public static function hexToRgb(string $hex, string $fallback = '15 39 68'): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return $fallback;
        }
        $n = hexdec($hex);

        return (($n >> 16) & 255).' '.(($n >> 8) & 255).' '.($n & 255);
    }

    public static function mix(string $hex, string $toward, float $amount): string
    {
        $parse = function (string $h): array {
            $rgb = array_map('intval', explode(' ', self::hexToRgb($h, '0 0 0')));

            return $rgb + [0, 0, 0];
        };
        $a = $parse($hex);
        $b = $parse($toward);
        $out = [];
        for ($i = 0; $i < 3; $i++) {
            $out[] = (int) round($a[$i] + ($b[$i] - $a[$i]) * $amount);
        }

        return sprintf('#%02x%02x%02x', $out[0], $out[1], $out[2]);
    }

    public static function payload(): array
    {
        return cache()->remember('public_theme_bootstrap', 60, function () {
            $layout = SiteThemes::normalize(Setting::get('homepage_layout', SiteThemes::DEFAULT));
            $preset = SiteThemes::colors($layout);
            $primary = SiteThemes::sanitizeHex(Setting::get('primary_color', $preset['primary']), $preset['primary']);
            $secondary = SiteThemes::sanitizeHex(Setting::get('secondary_color', $preset['secondary']), $preset['secondary']);
            $font = SiteFonts::normalize(Setting::get('site_font', SiteFonts::DEFAULT));
            $fontSize = SiteFonts::sanitizeSize(Setting::get('site_font_size', 16));
            $page = $preset['page'];
            $darkHero = (bool) (SiteThemes::all()[$layout]['dark_hero'] ?? false);
            $ink2 = self::mix($secondary, '#ffffff', 0.12);
            $brandDark = self::mix($primary, '#000000', 0.18);
            $brandSoft = self::mix($primary, '#ffffff', 0.88);
            $line = self::mix($secondary, '#ffffff', $darkHero ? 0.82 : 0.88);

            return [
                'layout' => $layout,
                'font' => $font,
                'fontSize' => $fontSize,
                'family' => self::cssFamily($font),
                'primary' => $primary,
                'secondary' => $secondary,
                'page' => $page,
                'ink2' => $ink2,
                'brandDark' => $brandDark,
                'brandSoft' => $brandSoft,
                'line' => $line,
                'questions_per_page' => max(1, min(20, (int) Setting::get('exam_questions_per_page', 5))),
            ];
        });
    }

    public static function inlineStyle(): string
    {
        $p = self::payload();

        return implode('', [
            ':root{',
            '--c-brand:'.self::hexToRgb($p['primary'], '249 115 22').';',
            '--c-brand-dark:'.self::hexToRgb($p['brandDark'], '211 47 65').';',
            '--c-brand-soft:'.self::hexToRgb($p['brandSoft'], '255 241 242').';',
            '--c-navy:'.self::hexToRgb($p['secondary'], '15 39 68').';',
            '--c-ink:'.self::hexToRgb($p['secondary'], '15 39 68').';',
            '--c-ink-2:'.self::hexToRgb($p['ink2'], '30 58 95').';',
            '--c-accent:'.self::hexToRgb($p['primary'], '249 115 22').';',
            '--c-page:'.self::hexToRgb($p['page'], '248 250 252').';',
            '--c-line:'.self::hexToRgb($p['line'], '226 232 240').';',
            '--c-surface:255 255 255;',
            '--c-text:30 41 59;',
            '--c-muted:100 116 139;',
            '--c-soft:71 85 105;',
            '--theme-ink:'.$p['secondary'].';',
            '--theme-ink-2:'.$p['ink2'].';',
            '--theme-accent:'.$p['primary'].';',
            '--theme-page:'.$p['page'].';',
            '--font-site:'.$p['family'].';',
            '--font-size-site:'.$p['fontSize'].'px;',
            '}',
            'html{font-family:'.$p['family'].';font-size:'.$p['fontSize'].'px;}',
            'body{background:'.$p['page'].';font-family:'.$p['family'].';}',
        ]);
    }

    public static function forget(): void
    {
        cache()->forget('public_theme_bootstrap');
        cache()->forget('public_settings_payload');
    }
}
