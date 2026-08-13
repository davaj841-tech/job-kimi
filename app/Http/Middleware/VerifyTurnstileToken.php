<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = Setting::get('captcha_enabled', 'false') === 'true'
            || Setting::get('turnstile_enabled', 'false') === 'true'
            || filled(config('services.turnstile.secret'));

        if (! $enabled) {
            return $next($request);
        }

        // Prefer Admin Settings; fall back to .env via config/services.php
        $secret = Setting::getFilled('turnstile_secret_key', config('services.turnstile.secret'));
        if (! $secret) {
            return $next($request);
        }

        $token = $request->input('turnstile_token') ?: $request->header('X-Turnstile-Token');

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'تایید امنیتی (Captcha) الزامی است.',
                'errors' => null,
            ], 422);
        }

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        if (! ($response->json('success') ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'تایید امنیتی ناموفق بود. دوباره تلاش کنید.',
                'errors' => null,
            ], 422);
        }

        return $next($request);
    }
}
