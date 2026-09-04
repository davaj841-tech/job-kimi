<?php

namespace App\Http\Middleware;

use App\Services\Security\TurnstileService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auth / public captcha: Turnstile when admin-enabled + configured, else math challenge.
 * Never runs Turnstile and math together. Does not replace rate limiting.
 */
class VerifyAuthCaptcha
{
    public function __construct(
        protected TurnstileService $turnstile
    ) {}

    public function handle(Request $request, Closure $next, ?string $action = null): Response
    {
        if ($this->turnstile->isEnabled()) {
            return $this->verifyTurnstile($request, $next, $action);
        }

        // Compatibility: captcha_enabled=false does not disable verification on these routes.
        return $this->verifyMath($request, $next);
    }

    private function verifyTurnstile(Request $request, Closure $next, ?string $action): Response
    {
        $result = $this->turnstile->verify($request, $action);

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'errors' => null,
            ], 422);
        }

        return $next($request);
    }

    private function verifyMath(Request $request, Closure $next): Response
    {
        // Ignore Turnstile fields when in math mode (no dual verification).
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
