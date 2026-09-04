<?php

namespace App\Services\Security;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile siteverify — secret only from config/services (env).
 */
class TurnstileService
{
    public const MAX_TOKEN_LENGTH = 2048;

    /**
     * Admin toggle + site key (settings/env) + secret (env only).
     */
    public function isEnabled(): bool
    {
        if (! Setting::getBool('turnstile_enabled', (bool) config('services.turnstile.enabled', false))) {
            return false;
        }

        return $this->siteKey() !== '' && $this->secret() !== '';
    }

    /**
     * Public site key: Admin Settings → .env/config default (never secret).
     */
    public function siteKey(): string
    {
        return trim((string) Setting::getFilled(
            'turnstile_site_key',
            (string) config('services.turnstile.site_key', '')
        ));
    }

    /**
     * Secret ONLY from environment via config — never Settings DB / frontend.
     */
    public function secret(): string
    {
        return trim((string) config('services.turnstile.secret', ''));
    }

    /**
     * Captcha mode for public SPA: turnstile | math (mutually exclusive).
     *
     * @return array{captcha_mode: string, turnstile_enabled: bool, turnstile_site_key: string, captcha_enabled: bool}
     */
    public function publicCaptchaPayload(): array
    {
        $enabled = $this->isEnabled();

        return [
            'captcha_mode' => $enabled ? 'turnstile' : 'math',
            'turnstile_enabled' => $enabled,
            'turnstile_site_key' => $enabled ? $this->siteKey() : '',
            // Always true for auth.captcha surfaces: math fallback when Turnstile off.
            'captcha_enabled' => true,
        ];
    }

    /**
     * @return list<string>
     */
    public function allowedHostnames(): array
    {
        $configured = config('services.turnstile.hostnames', []);
        $hosts = is_array($configured) ? $configured : [];

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $hosts[] = $appHost;
        }

        if (app()->environment(['local', 'testing'])) {
            $hosts[] = 'localhost';
            $hosts[] = '127.0.0.1';
        }

        $normalized = [];
        foreach ($hosts as $host) {
            $host = strtolower(trim((string) $host));
            if ($host !== '') {
                $normalized[$host] = $host;
            }
        }

        return array_values($normalized);
    }

    public function extractToken(Request $request): ?string
    {
        $token = $request->input('turnstile_token')
            ?: $request->input('cf-turnstile-response')
            ?: $request->header('X-Turnstile-Token');

        if (! is_string($token)) {
            return null;
        }

        $token = trim($token);
        if ($token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            return null;
        }

        return $token;
    }

    /**
     * @return array{ok: bool, message: string, code?: string}
     */
    public function verify(Request $request, ?string $expectedAction = null): array
    {
        $secret = $this->secret();
        if ($secret === '') {
            return [
                'ok' => false,
                'message' => 'تایید امنیتی در دسترس نیست. بعداً تلاش کنید.',
                'code' => 'misconfigured',
            ];
        }

        $token = $this->extractToken($request);
        if ($token === null) {
            return [
                'ok' => false,
                'message' => 'تایید امنیتی (کپچا) الزامی است.',
                'code' => 'missing_token',
            ];
        }

        $hostnames = $this->allowedHostnames();
        if ($hostnames === []) {
            Log::warning('Turnstile hostname allowlist is empty; refusing verification.');

            return [
                'ok' => false,
                'message' => 'تایید امنیتی در دسترس نیست. بعداً تلاش کنید.',
                'code' => 'misconfigured',
            ];
        }

        $timeout = max(3, (int) config('services.turnstile.timeout', 10));

        try {
            $response = Http::asForm()
                ->timeout($timeout)
                ->connectTimeout(min(5, $timeout))
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Turnstile siteverify connection failed.', [
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'ارتباط با سرویس امنیتی برقرار نشد. دوباره تلاش کنید.',
                'code' => 'upstream_unavailable',
            ];
        } catch (\Throwable $e) {
            Log::warning('Turnstile siteverify unexpected error.', [
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'تایید امنیتی ناموفق بود. دوباره تلاش کنید.',
                'code' => 'upstream_error',
            ];
        }

        if (! $response->successful()) {
            Log::warning('Turnstile siteverify HTTP error.', [
                'status' => $response->status(),
            ]);

            return [
                'ok' => false,
                'message' => 'ارتباط با سرویس امنیتی برقرار نشد. دوباره تلاش کنید.',
                'code' => 'upstream_http',
            ];
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        if (! ($payload['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => 'تایید امنیتی ناموفق بود. دوباره تلاش کنید.',
                'code' => 'rejected',
            ];
        }

        $hostname = strtolower(trim((string) ($payload['hostname'] ?? '')));
        if ($hostname === '' || ! in_array($hostname, $hostnames, true)) {
            return [
                'ok' => false,
                'message' => 'تایید امنیتی نامعتبر است.',
                'code' => 'hostname',
            ];
        }

        if ($expectedAction !== null && $expectedAction !== '') {
            $action = (string) ($payload['action'] ?? '');
            if ($action !== $expectedAction) {
                return [
                    'ok' => false,
                    'message' => 'تایید امنیتی نامعتبر است.',
                    'code' => 'action',
                ];
            }
        }

        $challengeTs = (string) ($payload['challenge_ts'] ?? '');
        if ($challengeTs !== '') {
            $maxAge = max(60, (int) config('services.turnstile.max_token_age_seconds', 300));
            try {
                $issuedAt = new \DateTimeImmutable($challengeTs);
                $age = time() - $issuedAt->getTimestamp();
                if ($age < -60 || $age > $maxAge) {
                    return [
                        'ok' => false,
                        'message' => 'تایید امنیتی منقضی شده است. دوباره تلاش کنید.',
                        'code' => 'expired',
                    ];
                }
            } catch (\Throwable) {
                return [
                    'ok' => false,
                    'message' => 'تایید امنیتی نامعتبر است.',
                    'code' => 'challenge_ts',
                ];
            }
        }

        return ['ok' => true, 'message' => ''];
    }
}
