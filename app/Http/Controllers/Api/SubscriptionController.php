<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\GatewayCallbackService;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends BaseController
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected PaymentService $paymentService,
        protected GatewayCallbackService $gatewayCallback,
    ) {}

    /**
     * فهرست پلن‌های اشتراک
     *
     * @group پرداخت‌ها
     *
     * @unauthenticated
     *
     * @response 200 {"success":true,"data":[]}
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        return $this->successResponse(SubscriptionPlanResource::collection($plans));
    }

    /**
     * خرید اشتراک
     *
     * خرید پلن اشتراک از کیف پول یا درگاه آنلاین.
     *
     * @group پرداخت‌ها
     *
     * @authenticated
     *
     * @bodyParam plan_id integer required شناسه پلن. Example: 1
     * @bodyParam payment_method string required روش پرداخت. Example: zarinpal
     * @bodyParam coupon_code string کد تخفیف. Example: WELCOME10
     * @bodyParam gateway string درگاه. Example: zarinpal
     *
     * @response 200 {"success":true,"data":{"payment_url":"https://..."}}
     * @response 400 {"success":false,"message":"..."}
     */
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'payment_method' => ['required', 'in:wallet,zarinpal,nextpay,idpay'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'gateway' => ['nullable', 'string', Rule::in(app(PaymentGatewayManager::class)->registeredCodes())],
        ]);

        $plan = SubscriptionPlan::query()->findOrFail($data['plan_id']);
        $method = $data['payment_method'] === 'wallet' ? 'wallet' : 'online';
        $gateway = $data['payment_method'] === 'wallet'
            ? null
            : ($data['gateway'] ?? $data['payment_method']);

        $result = $this->subscriptionService->subscribe(
            $request->user(),
            $plan,
            $method === 'wallet' ? 'wallet' : ($gateway ?: 'zarinpal'),
            $data['coupon_code'] ?? null,
            $gateway
        );

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 400, ['code' => $result['error'] ?? null]);
        }

        return $this->successResponse([
            'payment_url' => $result['payment_url'] ?? null,
            'expires_at' => $result['expires_at'] ?? null,
            'idempotency_key' => $result['idempotency_key'] ?? null,
        ], $result['message']);
    }

    public function upgrade(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'payment_method' => ['required', 'in:wallet,zarinpal,nextpay,idpay'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'gateway' => ['nullable', 'string', Rule::in(app(PaymentGatewayManager::class)->registeredCodes())],
        ]);

        $plan = SubscriptionPlan::query()->findOrFail($data['plan_id']);
        $gateway = $data['payment_method'] === 'wallet'
            ? null
            : ($data['gateway'] ?? $data['payment_method']);

        $result = $this->subscriptionService->upgrade(
            $request->user(),
            $plan,
            $data['payment_method'] === 'wallet' ? 'wallet' : ($gateway ?: 'zarinpal'),
            $data['coupon_code'] ?? null,
            $gateway
        );

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 400, ['code' => $result['error'] ?? null]);
        }

        return $this->successResponse([
            'payment_url' => $result['payment_url'] ?? null,
            'expires_at' => $result['expires_at'] ?? null,
            'idempotency_key' => $result['idempotency_key'] ?? null,
        ], $result['message']);
    }

    public function verifySubscription(Request $request): JsonResponse
    {
        $result = $this->gatewayCallback->complete(
            $request,
            fn (Transaction $tx) => $tx->type === 'purchase' && $tx->payable_type === SubscriptionPlan::class,
            function (Transaction $locked) {
                $plan = SubscriptionPlan::query()->find($locked->payable_id);
                if (! $plan) {
                    throw new \RuntimeException('پلن اشتراک یافت نشد.');
                }
                $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
                $this->subscriptionService->activate($user, $plan, $locked);
            }
        );

        if (! $result->ok) {
            return $this->errorResponse($result->message, $result->status);
        }

        $user = User::query()->findOrFail($result->transaction?->user_id);

        return $this->successResponse([
            'expires_at' => $this->formatExpiresAt($user->fresh() ?? $user),
            'already_processed' => $result->alreadyProcessed,
        ], 'اشتراک فعال شد');
    }

    private function formatExpiresAt(User $user): ?string
    {
        $expiresAt = $user->subscription_expires_at;

        if ($expiresAt instanceof \DateTimeInterface) {
            return $expiresAt->format(\DateTimeInterface::ATOM);
        }

        return null;
    }
}
