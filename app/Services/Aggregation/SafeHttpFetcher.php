<?php

namespace App\Services\Aggregation;

use App\Models\JobSource;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * HTTP client that only fetches administrator-whitelisted domains.
 * Validates each redirect hop and resolved IPs before connecting.
 */
class SafeHttpFetcher
{
    public function __construct(
        protected JobSourceManager $sources,
        protected int $timeoutSeconds = 30,
        protected int $maxBytes = 2_000_000,
        protected int $maxRedirects = 3,
    ) {}

    public function get(string $url, ?JobSource $source = null): Response
    {
        $current = $url;
        $response = null;

        for ($hop = 0; $hop <= $this->maxRedirects; $hop++) {
            $this->assertUrlAllowed($current, $source);
            $this->assertResolvedIpSafe($current);

            $response = Http::timeout($this->timeoutSeconds)
                ->connectTimeout(min(15, $this->timeoutSeconds))
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => true,
                ])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; JobAzmoonAggregator/1.0; +https://jobazmoon.local)',
                    'Accept' => 'application/json, application/rss+xml, application/atom+xml, application/xml, text/xml, text/html;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.8',
                ])
                ->get($current);

            $contentLength = $response->header('Content-Length');
            if (is_numeric($contentLength) && (int) $contentLength > $this->maxBytes) {
                throw new RuntimeException('Response Content-Length exceeds size limit.');
            }

            if (! $response->redirect()) {
                break;
            }

            if ($hop === $this->maxRedirects) {
                throw new RuntimeException('Too many redirects.');
            }

            $location = $response->header('Location');
            if (! is_string($location) || trim($location) === '') {
                throw new RuntimeException('Redirect without Location header.');
            }

            $current = $this->absolutizeUrl($location, $current);
        }

        if (! $response) {
            throw new RuntimeException('Empty HTTP response.');
        }

        $body = $response->body();
        if (strlen($body) > $this->maxBytes) {
            throw new RuntimeException('Response body exceeds size limit.');
        }

        return $response;
    }

    public function assertUrlAllowed(string $url, ?JobSource $source = null): void
    {
        $parts = parse_url($url);
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = Str::lower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('Only http/https URLs with a host are allowed.');
        }

        // Reject credentials in URL (user:pass@host).
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('URLs with embedded credentials are not allowed.');
        }

        if ($this->isBlockedHost($host)) {
            throw new RuntimeException("Blocked host: {$host}");
        }

        if ($source) {
            $allowed = Str::lower((string) $source->domain);
            if ($allowed === '' || ($host !== $allowed && ! Str::endsWith($host, '.'.$allowed))) {
                throw new RuntimeException("URL host [{$host}] is outside source domain [{$allowed}].");
            }

            return;
        }

        if (! $this->sources->isDomainAllowed($host)) {
            throw new RuntimeException("Host [{$host}] is not on the administrator allowlist.");
        }
    }

    /**
     * Resolve hostname and reject private/reserved destination IPs.
     */
    public function assertResolvedIpSafe(string $url): void
    {
        $host = Str::lower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === '') {
            throw new RuntimeException('URL host is required.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isBlockedIp($host)) {
                throw new RuntimeException("Blocked IP: {$host}");
            }

            return;
        }

        $ips = [];
        $aRecords = @gethostbynamel($host);
        if (is_array($aRecords)) {
            $ips = array_merge($ips, $aRecords);
        }

        // Best-effort AAAA lookup when available.
        if (function_exists('dns_get_record')) {
            $aaaa = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaa)) {
                foreach ($aaaa as $row) {
                    if (! empty($row['ipv6'])) {
                        $ips[] = $row['ipv6'];
                    }
                }
            }
        }

        foreach (array_unique($ips) as $ip) {
            if ($this->isBlockedIp((string) $ip)) {
                throw new RuntimeException("Host [{$host}] resolved to blocked IP [{$ip}].");
            }
        }
    }

    public function isBlockedHost(string $host): bool
    {
        $host = Str::lower(trim($host));
        if ($host === '') {
            return true;
        }

        // Bracketed IPv6 from URLs.
        $host = trim($host, '[]');

        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0', '[::1]'], true)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isBlockedIp($host);
        }

        return Str::endsWith($host, '.local')
            || Str::endsWith($host, '.internal')
            || Str::endsWith($host, '.localhost')
            || $host === 'metadata.google.internal'
            || $host === 'metadata'
            || Str::endsWith($host, '.metadata.google.internal');
    }

    public function isBlockedIp(string $ip): bool
    {
        $ip = trim($ip, '[]');

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        // Explicit cloud metadata / link-local.
        if ($ip === '169.254.169.254' || str_starts_with($ip, '169.254.')) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $normalized = Str::lower($ip);

            // IPv4-mapped / IPv4-compatible forms (e.g. ::ffff:127.0.0.1).
            if (preg_match('/(?:^::ffff:|^::)(\d{1,3}(?:\.\d{1,3}){3})$/i', $normalized, $m)) {
                return $this->isBlockedIp($m[1]);
            }

            // ::1, unique-local (fc00::/7), link-local (fe80::/10).
            if ($normalized === '::1'
                || str_starts_with($normalized, 'fc')
                || str_starts_with($normalized, 'fd')
                || str_starts_with($normalized, 'fe80')) {
                return true;
            }
        }

        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    protected function absolutizeUrl(string $location, string $base): string
    {
        $location = trim($location);
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('Cannot resolve relative redirect.');
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        if (str_starts_with($location, '//')) {
            return $parts['scheme'].':'.$location;
        }

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = $parts['path'] ?? '/';
        $dir = rtrim(Str::beforeLast($path, '/'), '/');

        return $origin.$dir.'/'.$location;
    }
}
