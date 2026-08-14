<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth captcha: Cloudflare Turnstile when keys exist, otherwise math challenge.
 */
class VerifyAuthCaptcha
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) Setting::getFilled('turnstile_secret_key', config('services.turnstile.secret', ''));
        $siteKey = (string) Setting::getFilled('turnstile_site_key', config('services.turnstile.site_key', ''));

        if ($secret !== '' && $siteKey !== '') {
            return $this->verifyTurnstile($request, $next, $secret);
        }

        return $this->verifyMath($request, $next);
    }

    private function verifyTurnstile(Request $request, Closure $next, string $secret): Response
    {
        $token = $request->input('turnstile_token') ?: $request->header('X-Turnstile-Token');

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'تایید امنیتی (کپچا) الزامی است.',
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

    private function verifyMath(Request $request, Closure $next): Response
    {
        $id = (string) $request->input('captcha_id', '');
        $answer = trim((string) $request->input('captcha_answer', ''));

        if ($id === '' || $answer === '') {
            return response()->json([
                'success' => false,
                'message' => 'پاسخ کپچا الزامی است.',
                'errors' => ['captcha_answer' => ['پاسخ کپچا را وارد کنید.']],
            ], 422);
        }

        $expected = Cache::pull("math_captcha:{$id}");

        if ($expected === null) {
            return response()->json([
                'success' => false,
                'message' => 'کپچا منقضی شده است. دوباره تلاش کنید.',
                'errors' => null,
            ], 422);
        }

        if ((string) $expected !== $answer) {
            return response()->json([
                'success' => false,
                'message' => 'پاسخ کپچا نادرست است.',
                'errors' => ['captcha_answer' => ['پاسخ کپچا نادرست است.']],
            ], 422);
        }

        return $next($request);
    }
}
