<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventInstallAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (is_file(storage_path('installed'))) {
            return redirect('/');
        }

        return $next($request);
    }
}
