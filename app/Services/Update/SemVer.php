<?php

declare(strict_types=1);

namespace App\Services\Update;

use InvalidArgumentException;

final class SemVer
{
    public static function isValid(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+$/', $version);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function parse(string $version): array
    {
        if (! self::isValid($version)) {
            throw new InvalidArgumentException("Invalid semantic version: {$version}");
        }

        [$major, $minor, $patch] = array_map('intval', explode('.', $version));

        return [$major, $minor, $patch];
    }

    public static function compare(string $a, string $b): int
    {
        [$am, $an, $ap] = self::parse($a);
        [$bm, $bn, $bp] = self::parse($b);

        return [$am, $an, $ap] <=> [$bm, $bn, $bp];
    }

    public static function greaterThan(string $a, string $b): bool
    {
        return self::compare($a, $b) > 0;
    }

    public static function greaterOrEqual(string $a, string $b): bool
    {
        return self::compare($a, $b) >= 0;
    }

    public static function current(): string
    {
        $fromFile = base_path('storage/app/updates/CURRENT_VERSION');
        if (is_file($fromFile)) {
            $v = trim((string) file_get_contents($fromFile));
            if (self::isValid($v)) {
                return $v;
            }
        }

        $config = (string) config('version.current', '1.0.0');

        return self::isValid($config) ? $config : '1.0.0';
    }

    public static function writeCurrent(string $version): void
    {
        if (! self::isValid($version)) {
            throw new InvalidArgumentException("Invalid semantic version: {$version}");
        }

        $dir = storage_path('app/updates');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dir.DIRECTORY_SEPARATOR.'CURRENT_VERSION', $version."\n");

        if (app()->environment('testing')) {
            return;
        }

        $configPath = config_path('version.php');
        if (is_file($configPath) && is_writable($configPath)) {
            $contents = file_get_contents($configPath);
            if (is_string($contents)) {
                $updated = preg_replace(
                    "/('current'\\s*=>\\s*)'[^']*'/",
                    '${1}\''.$version.'\'',
                    $contents,
                    1
                );
                if (is_string($updated)) {
                    file_put_contents($configPath, $updated);
                }
            }
        }
    }
}
