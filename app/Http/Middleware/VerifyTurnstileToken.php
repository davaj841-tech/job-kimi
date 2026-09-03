<?php

namespace App\Http\Middleware;

use App\Services\Security\TurnstileService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional Turnstile-only middleware (alias). Prefer auth.captcha for auth forms
 * so math fallback works when Turnstile is disabled.
 */
class VerifyTurnstileToken
{
    public function __construct(
        protected TurnstileService $turnstile
    ) {}

    public function handle(Request $request, Closure $next, ?string $action = null): Response
    {
        if (! $this->turnstile->isEnabled()) {
            return $next($request);
        }

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
}
