<?php

namespace App\Http\Middleware;

use App\Services\AnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    public function __construct(protected AnalyticsService $analytics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET')) {
            return $response;
        }

        $path = '/'.ltrim($request->path(), '/');
        if ($this->shouldSkip($path)) {
            return $response;
        }

        try {
            $this->analytics->record([
                'user_id' => $request->user()?->id,
                'session_id' => $request->session()->getId() ?: substr(sha1($request->ip().($request->userAgent() ?? '')), 0, 32),
                'page_url' => $path,
                'route_name' => optional($request->route())->getName(),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'referrer' => $request->headers->get('referer'),
            ]);
        } catch (\Throwable) {
            // never break the response for analytics
        }

        return $response;
    }

    protected function shouldSkip(string $path): bool
    {
        $skip = ['api', 'admin', 'filament', 'horizon', 'sanctum', 'up', 'health', 'install', 'api/documentation', 'docs'];
        foreach ($skip as $prefix) {
            if ($path === '/'.$prefix || Str::startsWith($path, '/'.$prefix.'/')) {
                return true;
            }
        }

        return Str::contains($path, ['.js', '.css', '.map', '.ico', '.png', '.jpg', '.woff', 'storage/']);
    }
}
