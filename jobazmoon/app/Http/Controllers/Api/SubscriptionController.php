<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Repositories\TransactionRepository;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseController
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected PaymentService $paymentService,
        protected TransactionRepository $transactionRepository
    ) {}

    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        return $this->successResponse(SubscriptionPlanResource::collection($plans));
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'payment_method' => ['required', 'in:wallet,zarinpal,nextpay,idpay'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'gateway' => ['nullable', 'string', 'in:zarinpal,nextpay,idpay'],
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
        ], $result['message']);
    }

    public function verifySubscription(Request $request): JsonResponse
    {
        $authority = $this->paymentService->extractAuthority($request);

        if ($authority === '') {
            return $this->errorResponse('شناسه پرداخت نامعتبر است.', 422);
        }

        $transaction = $this->transactionRepository->getByReference($authority);

        if (! $transaction || $transaction->type !== 'purchase' || $transaction->payable_type !== SubscriptionPlan::class) {
            return $this->errorResponse('تراکنش یافت نشد.', 404);
        }

        if ($transaction->status === 'success') {
            return $this->successResponse([
                'expires_at' => $transaction->user->subscription_expires_at?->toIso8601String(),
            ], 'اشتراک فعال شد');
        }

        $gateway = $transaction->gateway ?: 'zarinpal';

        if (! $this->paymentService->callbackSucceeded($request, $gateway)) {
            $transaction->update(['status' => 'failed']);

            return $this->errorResponse('پرداخت ناموفق بود', 400);
        }

        $verify = $this->paymentService->verify(
            $gateway,
            $authority,
            (int) $transaction->amount,
            ['order_id' => (string) $transaction->id]
        );

        if (! $verify['success']) {
            $transaction->update(['status' => 'failed']);

            return $this->errorResponse($verify['error'] ?? 'پرداخت ناموفق بود', 400);
        }

        $plan = SubscriptionPlan::query()->find($transaction->payable_id);

        if (! $plan) {
            $transaction->update(['status' => 'failed']);

            return $this->errorResponse('پلن اشتراک یافت نشد.', 404);
        }

        if ($verify['ref_id']) {
            $transaction->description = trim(($transaction->description ?? '').' | RefID: '.$verify['ref_id']);
        }

        $transaction->status = 'success';
        $transaction->save();

        $this->subscriptionService->activate($transaction->user, $plan, $transaction);

        return $this->successResponse([
            'expires_at' => $transaction->user->fresh()->subscription_expires_at?->toIso8601String(),
        ], 'اشتراک فعال شد');
    }
}
