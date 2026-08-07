<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionAdminController extends BaseController
{
    public function stats(): JsonResponse
    {
        $activeSubscribers = User::query()
            ->whereNotNull('subscription_plan_id')
            ->where('subscription_expires_at', '>', now())
            ->count();

        $monthlyRevenue = (int) Transaction::query()
            ->where('type', 'purchase')
            ->where('status', 'success')
            ->where('payable_type', SubscriptionPlan::class)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        $renewalsToday = Transaction::query()
            ->where('type', 'purchase')
            ->where('status', 'success')
            ->where('payable_type', SubscriptionPlan::class)
            ->whereDate('created_at', today())
            ->count();

        $expiringSoon = User::query()
            ->whereNotNull('subscription_plan_id')
            ->whereBetween('subscription_expires_at', [now(), now()->addDays(3)])
            ->count();

        return $this->successResponse([
            'active_subscriptions' => $activeSubscribers,
            'monthly_revenue' => $monthlyRevenue,
            'renewals_today' => $renewalsToday,
            'expiring_soon' => $expiringSoon,
        ]);
    }

    public function plans(Request $request): JsonResponse
    {
        $query = SubscriptionPlan::query()->orderBy('price');

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $plans = $query->get();

        return $this->successResponse(SubscriptionPlanResource::collection($plans));
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $this->validatePlan($request);
        $plan = SubscriptionPlan::query()->create($data);

        return $this->successResponse(new SubscriptionPlanResource($plan), 'پلن ایجاد شد.', 201);
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $plan = SubscriptionPlan::query()->findOrFail($id);
        $data = $this->validatePlan($request, false);
        $plan->update($data);

        return $this->successResponse(new SubscriptionPlanResource($plan->fresh()), 'پلن به‌روزرسانی شد.');
    }

    public function destroyPlan(int $id): JsonResponse
    {
        $plan = SubscriptionPlan::query()->findOrFail($id);
        $plan->delete();

        return $this->successResponse(null, 'پلن حذف شد.');
    }

    public function subscribers(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('subscriptionPlan:id,name,duration_days,price')
            ->whereNotNull('subscription_plan_id')
            ->latest('subscription_expires_at');

        if ($request->filled('status')) {
            if ($request->query('status') === 'active') {
                $query->where('subscription_expires_at', '>', now());
            } elseif ($request->query('status') === 'expired') {
                $query->where(function ($q) {
                    $q->whereNull('subscription_expires_at')
                        ->orWhere('subscription_expires_at', '<=', now());
                });
            }
        }

        if ($request->filled('plan_id')) {
            $query->where('subscription_plan_id', $request->query('plan_id'));
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('mobile', 'like', "%{$s}%");
            });
        }

        $items = $query->paginate((int) $request->query('per_page', 20));

        $data = collect($items->items())->map(function (User $user) {
            $active = $user->hasActiveSubscription();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->mobile,
                'plan' => $user->subscriptionPlan ? [
                    'id' => $user->subscriptionPlan->id,
                    'name' => $user->subscriptionPlan->name,
                ] : null,
                'started_at' => $user->subscription_expires_at && $user->subscriptionPlan
                    ? $user->subscription_expires_at->copy()->subDays((int) $user->subscriptionPlan->duration_days)->toIso8601String()
                    : null,
                'expires_at' => $user->subscription_expires_at?->toIso8601String(),
                'status' => $active ? 'active' : 'expired',
            ];
        });

        return $this->successResponse([
            'data' => $data,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    public function renew(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $data = $request->validate([
            'plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'days' => ['nullable', 'integer', 'min:1', 'max:730'],
        ]);

        $plan = SubscriptionPlan::query()->find(
            $data['plan_id'] ?? $user->subscription_plan_id
        );

        if (! $plan) {
            return $this->errorResponse('پلن اشتراک یافت نشد.', 422);
        }

        $days = (int) ($data['days'] ?? $plan->duration_days);
        $base = $user->subscription_expires_at && $user->subscription_expires_at->isFuture()
            ? $user->subscription_expires_at
            : now();

        $user->update([
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => $base->copy()->addDays($days),
        ]);

        return $this->successResponse([
            'id' => $user->id,
            'expires_at' => $user->fresh()->subscription_expires_at?->toIso8601String(),
            'plan_id' => $plan->id,
        ], 'اشتراک تمدید شد.');
    }

    public function cancel(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $user->update([
            'subscription_expires_at' => now()->subMinute(),
        ]);

        return $this->successResponse(null, 'اشتراک لغو شد.');
    }

    protected function validatePlan(Request $request, bool $requireAll = true): array
    {
        $rules = [
            'name' => [$requireAll ? 'required' : 'sometimes', 'string', 'max:100'],
            'duration_days' => [$requireAll ? 'required' : 'sometimes', 'integer', 'min:1', 'max:730'],
            'price' => [$requireAll ? 'required' : 'sometimes', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'badge_color' => ['nullable', 'string', 'max:30'],
        ];

        $data = $request->validate($rules);
        $data['is_active'] = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : true;

        // Persist optional badge color inside features metadata without schema change
        if (array_key_exists('badge_color', $data)) {
            $features = $data['features'] ?? [];
            $features = array_values(array_filter($features, fn ($f) => ! str_starts_with((string) $f, '__badge:')));
            if ($data['badge_color']) {
                $features[] = '__badge:'.$data['badge_color'];
            }
            $data['features'] = $features;
            unset($data['badge_color']);
        }

        return $data;
    }
}
