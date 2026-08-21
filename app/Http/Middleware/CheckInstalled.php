<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        if (is_file(storage_path('installed'))) {
            return $next($request);
        }

        if ($request->is('install') || $request->is('install/*')) {
            return $next($request);
        }

        if ($request->is('up') || $request->is('health')) {
            return $next($request);
        }

        // API health check endpoint for monitoring.
        // Exempt from /install redirect so uptime checks work.
        if ($request->is('api/v1/health')) {
            return $next($request);
        }

        return redirect('/install');
    }
}
