<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

final class IpHelper
{
    /**
     * @return list<string>
     */
    public function trustedRanges(): array
    {
        /** @var list<string> $ranges */
        $ranges = collect([
            (string) config('app.trusted_proxies', ''),
            (string) config('app.trusted_proxies_v6', ''),
        ])
            ->flatMap(fn (string $ips): array => array_values(array_filter(array_map('trim', explode(',', $ips)))))
            ->values()
            ->all();

        return $ranges;
    }

    public function isTrustedProxy(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        $ranges = $this->trustedRanges();

        if ($ranges === []) {
            return false;
        }

        return IpUtils::checkIp($ip, $ranges);
    }

    /**
     * Extract the real client IP by walking X-Forwarded-For from the right,
     * stopping at the first address that is not a trusted proxy.
     */
    public function getClientIp(Request $request): ?string
    {
        $remoteAddr = $request->server->get('REMOTE_ADDR');

        if (! is_string($remoteAddr) || filter_var($remoteAddr, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        if (! $this->isTrustedProxy($remoteAddr)) {
            return $remoteAddr;
        }

        $forwardedFor = $request->headers->get('X-Forwarded-For');

        if (is_string($forwardedFor) && $forwardedFor !== '') {
            $ips = array_values(array_filter(array_map('trim', explode(',', $forwardedFor))));

            for ($i = count($ips) - 1; $i >= 0; $i--) {
                $ip = $ips[$i];

                if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    continue;
                }

                if (! $this->isTrustedProxy($ip)) {
                    return $ip;
                }
            }
        }

        $cfConnectingIp = $request->headers->get('CF-Connecting-IP');

        if (is_string($cfConnectingIp) && filter_var($cfConnectingIp, FILTER_VALIDATE_IP) !== false) {
            return $cfConnectingIp;
        }

        return $remoteAddr;
    }
}
