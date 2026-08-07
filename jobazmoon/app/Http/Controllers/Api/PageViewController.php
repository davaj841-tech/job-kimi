<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageViewController extends BaseController
{
    public function __construct(protected AnalyticsService $analytics) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page_url' => ['required', 'string', 'max:500'],
            'route_name' => ['nullable', 'string', 'max:120'],
            'session_id' => ['nullable', 'string', 'max:64'],
            'referrer' => ['nullable', 'string', 'max:500'],
        ]);

        $sessionId = $data['session_id']
            ?? $request->cookie('ja_sid')
            ?? substr(sha1(($request->ip() ?? '').($request->userAgent() ?? '').date('YmdH')), 0, 32);

        $this->analytics->record([
            'user_id' => $request->user()?->id,
            'session_id' => $sessionId,
            'page_url' => $data['page_url'],
            'route_name' => $data['route_name'] ?? null,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'referrer' => $data['referrer'] ?? $request->headers->get('referer'),
        ]);

        return $this->successResponse(['session_id' => $sessionId], 'ok');
    }
}
