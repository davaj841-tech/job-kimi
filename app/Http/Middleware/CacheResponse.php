<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    public function handle(Request $request, Closure $next, int $seconds = 60): Response
    {
        if (! $request->isMethod('GET') || app()->environment('local', 'testing')) {
            return $next($request);
        }

        $key = 'response:'.md5($request->fullUrl());

        if ($cached = cache()->get($key)) {
            return response()->json($cached);
        }

        $response = $next($request);

        if ($response instanceof JsonResponse && $response->isSuccessful()) {
            cache()->put($key, $response->getData(true), $seconds);
        }

        return $response;
    }
}
