<?php

namespace App\Services\Aggregation;

use App\Models\JobSource;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Ensures admin-configured URLs stay inside the source's declared domain.
 * Does not weaken SafeHttpFetcher; this is an additional admin-layer guard.
 */
class JobSourceDomainGuard
{
    public function normalizeDomain(?string $hostOrUrl): ?string
    {
        if ($hostOrUrl === null || trim($hostOrUrl) === '') {
            return null;
        }

        $value = trim($hostOrUrl);
        if (str_contains($value, '://')) {
            $scheme = Str::lower((string) parse_url($value, PHP_URL_SCHEME));
            if (! in_array($scheme, ['http', 'https'], true)) {
                return null;
            }
            $host = parse_url($value, PHP_URL_HOST);
        } else {
            $host = $value;
        }

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = Str::lower(trim($host, '[]'));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host !== '' ? $host : null;
    }

    public function assertUrlBelongsToSource(string $url, JobSource $source): void
    {
        $scheme = Str::lower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('فقط آدرس‌های http/https مجاز هستند.');
        }

        $host = $this->normalizeDomain($url);
        $allowed = $this->normalizeDomain($source->domain ?: $source->official_url);

        if ($host === null) {
            throw new InvalidArgumentException('آدرس باید یک URL معتبر http(s) باشد.');
        }

        if ($this->isBlockedHost($host)) {
            throw new InvalidArgumentException("دامنه یا IP مسدود است: {$host}");
        }

        if ($allowed === null) {
            throw new InvalidArgumentException('دامنه منبع تعریف نشده است.');
        }

        if ($this->isBlockedHost($allowed)) {
            throw new InvalidArgumentException("دامنه منبع مسدود است: {$allowed}");
        }

        if ($host !== $allowed && ! Str::endsWith($host, '.'.$allowed)) {
            throw new InvalidArgumentException(
                "دامنه آدرس [{$host}] خارج از دامنه مجاز منبع [{$allowed}] است."
            );
        }
    }

    public function isBlockedHost(string $host): bool
    {
        return app(SafeHttpFetcher::class)->isBlockedHost($host);
    }

    /**
     * Strip sensitive keys from crawler error context before admin display.
     *
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>|null
     */
    public function sanitizeContext(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }

        $blocked = ['password', 'token', 'authorization', 'cookie', 'api_key', 'secret', 'authorization_header'];
        $clean = [];
        foreach ($context as $key => $value) {
            $lower = Str::lower((string) $key);
            foreach ($blocked as $needle) {
                if (str_contains($lower, $needle)) {
                    continue 2;
                }
            }
            $clean[$key] = is_array($value) ? $this->sanitizeContext($value) : $value;
        }

        return $clean;
    }
}
