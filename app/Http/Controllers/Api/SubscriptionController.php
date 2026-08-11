<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use App\Services\IdempotencyService;
use App\Services\PaymentService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseController
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected PaymentService $paymentService,
        protected TransactionRepository $transactionRepository,
        protected IdempotencyService $idempotencyService,
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
            'idempotency_key' => $result['idempotency_key'] ?? null,
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

        $requestKey = $this->idempotencyService->extractKey($request);
        if (
            $requestKey !== null
            && $transaction->idempotency_key
            && ! hash_equals($transaction->idempotency_key, $requestKey)
        ) {
            return $this->errorResponse('کلید یکتایی پرداخت نامعتبر است.', 422);
        }

        if ($transaction->status === Transaction::STATUS_COMPLETED) {
            $user = User::query()->findOrFail($transaction->user_id);

            return $this->successResponse([
                'expires_at' => $this->formatExpiresAt($user),
                'already_processed' => true,
            ], 'اشتراک فعال شد');
        }

        $gateway = $transaction->gateway ?: 'zarinpal';

        if (! $this->paymentService->callbackSucceeded($request, $gateway)) {
            $transaction->update(['status' => Transaction::STATUS_FAILED]);

            return $this->errorResponse('پرداخت ناموفق بود', 400);
        }

        $verify = $this->paymentService->verify(
            $gateway,
            $authority,
            (int) $transaction->amount,
            ['order_id' => (string) $transaction->id]
        );

        if (! $verify['success']) {
            $transaction->update(['status' => Transaction::STATUS_FAILED]);

            return $this->errorResponse($verify['error'] ?? 'پرداخت ناموفق بود', 400);
        }

        $plan = SubscriptionPlan::query()->find($transaction->payable_id);

        if (! $plan) {
            $transaction->update(['status' => Transaction::STATUS_FAILED]);

            return $this->errorResponse('پلن اشتراک یافت نشد.', 404);
        }

        $outcome = $this->idempotencyService->completeOnce($transaction, function (Transaction $locked) use ($verify, $plan) {
            if ($verify['ref_id']) {
                $locked->description = trim(($locked->description ?? '').' | RefID: '.$verify['ref_id']);
            }

            $locked->status = Transaction::STATUS_COMPLETED;
            $locked->save();

            $user = User::query()->findOrFail($locked->user_id);
            $this->subscriptionService->activate($user, $plan, $locked);

            return true;
        });

        $user = User::query()->findOrFail($outcome['transaction']->user_id);

        return $this->successResponse([
            'expires_at' => $this->formatExpiresAt($user->fresh() ?? $user),
            'already_processed' => $outcome['already_processed'],
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
