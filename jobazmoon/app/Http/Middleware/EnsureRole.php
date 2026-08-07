<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /** @param  string  ...$roles  نقش‌های مجاز: admin|operator|jobseeker|employer */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->values()
            ->all();

        $user = $request->user();

        if (! $user || ! in_array($user->role, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'دسترسی غیرمجاز.',
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
