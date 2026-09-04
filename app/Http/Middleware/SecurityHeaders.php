<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));
        $response->headers->remove('X-XSS-Protection');

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $installing = ! is_file(storage_path('installed'))
            || $request->is('install')
            || $request->is('install/*');

        if ($installing) {
            return implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com https://cdn.jsdelivr.net",
                "font-src 'self' https://fonts.gstatic.com data:",
                "img-src 'self' data: https: blob:",
                "connect-src 'self' https://cdn.tailwindcss.com https://cdn.jsdelivr.net",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ]);
        }
        if (app()->environment('testing')) {
            return implode('; ', [
                "default-src 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                'report-uri '.url('/csp-report'),
            ]);
        }

        $isLocal = app()->environment('local');

        $scriptSrc = [
            "'self'",
            "'unsafe-inline'",
            'https://challenges.cloudflare.com',
            'https://browser.sentry-cdn.com',
            'https://js.sentry-cdn.com',
        ];

        if ($isLocal) {
            $scriptSrc[] = "'unsafe-eval'";
        }

        $connectSrc = [
            "'self'",
            'https://api.zarinpal.com',
            'https://payment.zarinpal.com',
            'https://sandbox.zarinpal.com',
            'https://www.zarinpal.com',
            'https://api.kavenegar.com',
            'https://rest.payamak-panel.com',
            'https://*.sentry.io',
            'https://challenges.cloudflare.com',
        ];

        if ($isLocal) {
            array_push(
                $connectSrc,
                'ws:',
                'wss:',
                'http://localhost:5173',
                'http://127.0.0.1:5173',
                'ws://localhost:5173',
                'ws://127.0.0.1:5173',
            );
        }

        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSrc),
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: https: blob:",
            'connect-src '.implode(' ', $connectSrc),
            'frame-src https://challenges.cloudflare.com',
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "manifest-src 'self'",
            "worker-src 'self'",
            'report-uri '.url('/csp-report'),
        ];

        if (app()->environment('production')) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
