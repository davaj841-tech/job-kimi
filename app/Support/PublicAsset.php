<?php

namespace App\Support;

final class PublicAsset
{
    /** Normalize stored logo/favicon to a browser-safe root-relative or absolute URL. */
    public static function url(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $path = parse_url($value, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/storage/')) {
                return $path;
            }

            return $value;
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        // stored as disk path e.g. settings/foo.png
        return '/storage/'.ltrim($value, '/');
    }
}
