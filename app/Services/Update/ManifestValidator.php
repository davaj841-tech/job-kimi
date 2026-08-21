<?php

declare(strict_types=1);

namespace App\Services\Update;

use RuntimeException;

final class ManifestValidator
{
    /**
     * @param  array<string, mixed>  $manifest
     * @return array{ok: bool, errors: list<string>, checks: array<string, bool>}
     */
    public function validate(array $manifest, string $currentVersion): array
    {
        $errors = [];
        $checks = [
            'manifest' => true,
            'version' => false,
            'php' => false,
            'laravel' => false,
            'compatibility' => false,
        ];

        $required = [
            'application',
            'version',
            'minimum_version',
            'release_date',
            'release_type',
            'php',
            'laravel',
            'backup_required',
            'migration_required',
            'maintenance_mode',
            'files',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $manifest)) {
                $errors[] = "فیلد الزامی manifest وجود ندارد: {$key}";
                $checks['manifest'] = false;
            }
        }

        $appName = (string) ($manifest['application'] ?? '');
        if ($appName !== '' && strcasecmp($appName, (string) config('version.name', 'JobAzmoon')) !== 0) {
            $errors[] = 'نام برنامه در manifest با این نصب هم‌خوان نیست.';
            $checks['manifest'] = false;
        }

        $version = (string) ($manifest['version'] ?? '');
        $minimum = (string) ($manifest['minimum_version'] ?? '');

        if (! SemVer::isValid($version)) {
            $errors[] = 'نسخه هدف معتبر نیست (انتظار MAJOR.MINOR.PATCH).';
        } else {
            $checks['version'] = true;
            if (! SemVer::greaterThan($version, $currentVersion)) {
                $errors[] = "نسخه هدف ({$version}) باید جدیدتر از نسخه فعلی ({$currentVersion}) باشد.";
                $checks['version'] = false;
            } else {
                // Compatibility policy: major bumps require release_type=major.
                try {
                    [$tMajor] = SemVer::parse($version);
                    [$cMajor] = SemVer::parse($currentVersion);
                    $releaseType = strtolower((string) ($manifest['release_type'] ?? ''));
                    if ($tMajor > $cMajor && $releaseType !== 'major') {
                        $errors[] = 'پرش Major فقط با release_type=major مجاز است.';
                        $checks['compatibility'] = false;
                    }
                } catch (\InvalidArgumentException) {
                    $errors[] = 'نسخه برای مقایسه Major نامعتبر است.';
                }
            }
        }

        if ($minimum !== '' && SemVer::isValid($minimum)) {
            if (! SemVer::greaterOrEqual($currentVersion, $minimum)) {
                $errors[] = "حداقل نسخه لازم {$minimum} است؛ نسخه فعلی {$currentVersion} است.";
                $checks['compatibility'] = false;
            } else {
                $checks['compatibility'] = true;
            }
        } else {
            $errors[] = 'minimum_version نامعتبر است.';
        }

        $phpReq = (string) ($manifest['php'] ?? '');
        if ($phpReq === '' || ! version_compare(PHP_VERSION, ltrim($phpReq, '>= '), '>=')) {
            // support "8.2" or ">=8.2"
            $normalized = preg_replace('/[^0-9.]/', '', $phpReq) ?: '8.2';
            if (version_compare(PHP_VERSION, $normalized, '>=')) {
                $checks['php'] = true;
            } else {
                $errors[] = "نسخه PHP ناکافی است (لازم: {$phpReq}، فعلی: ".PHP_VERSION.').';
            }
        } else {
            $checks['php'] = true;
        }

        $laravelReq = (string) ($manifest['laravel'] ?? '11');
        $laravelInstalled = $this->laravelVersion();
        $laravelNormalized = preg_replace('/[^0-9.]/', '', $laravelReq) ?: '11';
        if (version_compare($laravelInstalled, $laravelNormalized, '>=')) {
            $checks['laravel'] = true;
        } else {
            $errors[] = "نسخه Laravel ناکافی است (لازم: {$laravelReq}، فعلی: {$laravelInstalled}).";
        }

        if (! empty($manifest['composer_required']) && config('update.require_composer_blocks_install', true)) {
            $errors[] = 'این بسته به Composer نیاز دارد و روی cPanel بدون SSH قابل نصب نیست.';
        }

        if (! is_array($manifest['files'] ?? null)) {
            $errors[] = 'فهرست files باید آرایه باشد.';
            $checks['manifest'] = false;
        }

        if (isset($manifest['deleted_files']) && ! is_array($manifest['deleted_files'])) {
            $errors[] = 'deleted_files باید آرایه باشد.';
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'checks' => $checks,
        ];
    }

    private function laravelVersion(): string
    {
        return \Illuminate\Foundation\Application::VERSION;
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function assertValid(array $manifest, string $currentVersion): void
    {
        $result = $this->validate($manifest, $currentVersion);
        if (! $result['ok']) {
            throw new RuntimeException(implode(' ', $result['errors']));
        }
    }
}
