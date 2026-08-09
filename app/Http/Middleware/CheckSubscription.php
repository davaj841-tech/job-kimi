<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $this->subscriptionService->expireIfNeeded($user);
            $user->refresh();

            $isActive = $this->subscriptionService->isActive($user);

            $request->attributes->set('subscription', [
                'is_active' => $isActive,
                'plan_id' => $user->subscription_plan_id,
                'expires_at' => $user->subscription_expires_at?->toIso8601String(),
                'days_left' => $this->subscriptionService->getDaysLeft($user),
                'is_free_user' => ! $isActive,
            ]);
        }

        return $next($request);
    }
}
