<?php

namespace App\Services\Aggregation;

use App\Models\JobSource;
use Illuminate\Http\Client\ConnectionException;
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
        protected int $timeoutSeconds = 45,
        protected int $maxBytes = 2_000_000,
        protected int $maxRedirects = 3,
        protected int $connectTimeoutSeconds = 20,
        protected int $retries = 2,
        protected int $retrySleepMs = 1500,
    ) {}

    public function get(string $url, ?JobSource $source = null): Response
    {
        $current = $url;
        $response = null;
        $attempts = max(1, $this->retries + 1);
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->getOnce($current, $source);

                return $response;
            } catch (ConnectionException $e) {
                $lastException = $e;
                if ($attempt >= $attempts) {
                    break;
                }
                usleep(max(0, $this->retrySleepMs) * 1000);
            } catch (RuntimeException $e) {
                // Only retry soft network-ish runtime failures that wrap timeouts.
                if (! $this->isTimeoutMessage($e->getMessage()) || $attempt >= $attempts) {
                    throw $e;
                }
                $lastException = $e;
                usleep(max(0, $this->retrySleepMs) * 1000);
            }
        }

        $detail = $lastException?->getMessage() ?? 'unknown';
        throw new RuntimeException($this->humanizeTimeout($url, $detail), 0, $lastException);
    }

    protected function getOnce(string $url, ?JobSource $source = null): Response
    {
        $current = $url;
        $response = null;
        $connectTimeout = max(3, min($this->connectTimeoutSeconds, $this->timeoutSeconds));

        for ($hop = 0; $hop <= $this->maxRedirects; $hop++) {
            $this->assertUrlAllowed($current, $source);
            $this->assertResolvedIpSafe($current, $source);

            try {
                $response = Http::timeout($this->timeoutSeconds)
                    ->connectTimeout($connectTimeout)
                    ->withOptions([
                        'allow_redirects' => false,
                        'verify' => true,
                    ])
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; JobAzmoonAggregator/1.0; +https://jobazmoon.ir)',
                        'Accept' => 'application/json, application/rss+xml, application/atom+xml, application/xml, text/xml, text/html;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.8',
                    ])
                    ->get($current);
            } catch (ConnectionException $e) {
                throw new RuntimeException($this->humanizeTimeout($current, $e->getMessage()), 0, $e);
            }

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

    protected function isTimeoutMessage(string $message): bool
    {
        return (bool) preg_match('/timed?\s*out|cURL error 28|Connection timed out|Operation timed out/i', $message);
    }

    protected function humanizeTimeout(string $url, string $detail): string
    {
        if (! $this->isTimeoutMessage($detail)) {
            return $detail;
        }

        return 'اتصال به منبع در مهلت مقرر برقرار نشد (timeout). '
            .'سایت مبدأ ممکن است از شبکهٔ سرور در دسترس نباشد یا بسیار کند باشد. '
            .'URL: '.$url;
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
     * Resolve hostname and reject unsafe destination IPs.
     *
     * For administrator-whitelisted JobSource hostnames, RFC1918 (10/8, 172.16/12,
     * 192.168/16) is allowed: many Iranian gov/CDN hosts resolve to private ranges
     * via split-horizon DNS and are reachable from Iranian hosting. Loopback,
     * link-local, and cloud metadata remain blocked always.
     */
    public function assertResolvedIpSafe(string $url, ?JobSource $source = null): void
    {
        $host = Str::lower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === '') {
            throw new RuntimeException('URL host is required.');
        }

        $allowPrivateOrgRanges = $source !== null && ! filter_var($host, FILTER_VALIDATE_IP);

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isBlockedIp($host, allowPrivateOrgRanges: false)) {
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
            if ($this->isBlockedIp((string) $ip, allowPrivateOrgRanges: $allowPrivateOrgRanges)) {
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

    /**
     * @param  bool  $allowPrivateOrgRanges  When true, RFC1918 IPv4 is permitted (whitelisted host DNS).
     */
    public function isBlockedIp(string $ip, bool $allowPrivateOrgRanges = false): bool
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
                return $this->isBlockedIp($m[1], allowPrivateOrgRanges: $allowPrivateOrgRanges);
            }

            // ::1, unique-local (fc00::/7), link-local (fe80::/10).
            if ($normalized === '::1'
                || str_starts_with($normalized, 'fc')
                || str_starts_with($normalized, 'fd')
                || str_starts_with($normalized, 'fe80')) {
                return true;
            }
        }

        // Loopback always blocked (even when RFC1918 is permitted for org CDNs).
        if ($this->isLoopbackIp($ip)) {
            return true;
        }

        if ($allowPrivateOrgRanges && $this->isRfc1918Ip($ip)) {
            return false;
        }

        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    protected function isLoopbackIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return str_starts_with($ip, '127.');
        }

        return Str::lower($ip) === '::1';
    }

    protected function isRfc1918Ip(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        // 10.0.0.0/8
        if (($long & 0xFF000000) === 0x0A000000) {
            return true;
        }
        // 172.16.0.0/12
        if (($long & 0xFFF00000) === 0xAC100000) {
            return true;
        }
        // 192.168.0.0/16
        if (($long & 0xFFFF0000) === 0xC0A80000) {
            return true;
        }

        return false;
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
