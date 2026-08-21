<?php

namespace App\Http\Middleware;

use App\Support\OperatorPermissions;
use App\Support\StaffRoles;
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

        if (StaffRoles::isSuperAdmin($user)) {
            return $next($request);
        }

        $permission = OperatorPermissions::permissionForPath($request->path());

        if (StaffRoles::isAdmin($user)) {
            if ($permission === '__super_admin__') {
                return $this->deny();
            }

            return $next($request);
        }

        if ($permission === null) {
            return $next($request);
        }

        if (
            in_array($permission, ['__super_admin__', '__staff_admin__'], true)
            || ! OperatorPermissions::allows($user, $permission)
        ) {
            return $this->deny();
        }

        return $next($request);
    }

    protected function deny(): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'این بخش برای نقش شما فعال نیست.',
            'errors' => null,
        ], 403);
    }
}
