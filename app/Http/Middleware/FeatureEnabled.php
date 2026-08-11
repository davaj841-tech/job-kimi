<?php

namespace App\Http\Middleware;

use App\Services\FeatureFlagService;
use Closure;
use Illuminate\Http\Request;

final class FeatureEnabled
{
    public function __construct(
        private readonly FeatureFlagService $features
    ) {}

    public function handle(Request $request, Closure $next, string $feature): mixed
    {
        if (! $this->features->isEnabled($feature)) {
            return response()->json([
                'success' => false,
                'message' => 'این قابلیت در حال حاضر غیرفعال است.',
                'errors' => ['feature' => [$feature]],
            ], 403);
        }

        return $next($request);
    }
}
