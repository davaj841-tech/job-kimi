<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Helpers\IpHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth: strip forwarded headers when the direct peer is not a trusted proxy.
 */
final class TrustProxies
{
    /** @var list<string> */
    private const FORWARDED_SERVER_KEYS = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED_HOST',
        'HTTP_X_FORWARDED_PORT',
        'HTTP_X_FORWARDED_PROTO',
        'HTTP_X_FORWARDED_PREFIX',
        'HTTP_CF_CONNECTING_IP',
        'HTTP_TRUE_CLIENT_IP',
    ];

    public function __construct(
        private readonly IpHelper $ipHelper
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $remoteAddr = $request->server->get('REMOTE_ADDR');

        if (! is_string($remoteAddr) || ! $this->ipHelper->isTrustedProxy($remoteAddr)) {
            $this->stripForwardedHeaders($request);
        }

        return $next($request);
    }

    private function stripForwardedHeaders(Request $request): void
    {
        foreach (self::FORWARDED_SERVER_KEYS as $key) {
            $request->server->remove($key);
        }

        $request->headers->remove('X-Forwarded-For');
        $request->headers->remove('X-Forwarded-Host');
        $request->headers->remove('X-Forwarded-Port');
        $request->headers->remove('X-Forwarded-Proto');
        $request->headers->remove('X-Forwarded-Prefix');
        $request->headers->remove('CF-Connecting-IP');
        $request->headers->remove('True-Client-IP');
    }
}
