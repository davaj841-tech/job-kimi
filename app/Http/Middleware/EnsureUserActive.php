<?php

namespace App\Http\Middleware;

use App\Services\Auth\OtpAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->isActiveAccount()) {
            app(OtpAuthService::class)->revokeCurrentToken($user, $request);

            return response()->json([
                'success' => false,
                'message' => 'حساب کاربری غیرفعال است.',
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
