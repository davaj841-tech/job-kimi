<?php

namespace App\Http\Middleware;

use App\Support\OperatorPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperatorPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'احراز هویت لازم است.',
                'errors' => null,
            ], 401);
        }

        if ($user->role === 'admin') {
            return $next($request);
        }

        $permission = OperatorPermissions::permissionForPath($request->path());

        if ($permission === null) {
            return $next($request);
        }

        if ($permission === '__admin__' || ! OperatorPermissions::allows($user, $permission)) {
            return response()->json([
                'success' => false,
                'message' => 'این بخش برای نقش شما فعال نیست.',
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
