<?php

namespace App\Services;

use App\Events\PaymentSuccessful;
use App\Events\SubscriptionExpired;
use App\Exceptions\InsufficientBalanceException;
use App\Listeners\DispatchAfterCommit;
use App\Models\Coupon;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubscriptionService
{
    public function __construct(
        protected WalletService $walletService,
        protected PaymentService $paymentService,
        protected CouponService $couponService,
        protected InvoiceService $invoiceService
    ) {}

    /**
     * @return array{success: bool, message: string, payment_url?: string, expires_at?: string, error?: string}
     */
    public function subscribe(User $user, SubscriptionPlan $plan, string $method, ?string $couponCode = null, ?string $gateway = null): array
    {
        if (! $plan->is_active) {
            return ['success' => false, 'message' => 'این پلن فعال نیست.', 'error' => 'plan_inactive'];
        }

        if ((int) $plan->price === 0 && (int) $plan->duration_days === 0) {
            return ['success' => false, 'message' => 'پلن رایگان قابل خرید نیست.', 'error' => 'free_plan_not_purchasable'];
        }

        $original = (int) $plan->price;
        $amount = $original;
        $coupon = null;
        $discount = 0;

        if ($couponCode) {
            $check = $this->couponService->validate($couponCode, $original, 'subscription');
            if (! $check['valid']) {
                return ['success' => false, 'message' => $check['message'], 'error' => 'invalid_coupon'];
            }
            $discount = (int) $check['discount_amount'];
            $amount = (int) $check['final_amount'];
            $coupon = $check['coupon'];
        }

        if ($method === 'wallet') {
            return $this->subscribeWithWallet($user, $plan, $amount, $original, $discount, $coupon);
        }

        try {
            $gateway = $this->paymentService->resolveGatewayName($gateway ?: ($method !== 'wallet' ? $method : null));
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'error' => 'gateway_unavailable'];
        }

        return $this->subscribeWithGateway($user, $plan, $amount, $original, $discount, $coupon, $gateway);
    }

    /**
     * @return array<string, mixed>
     */
    protected function subscribeWithWallet(
        User $user,
        SubscriptionPlan $plan,
        int $amount,
        int $original,
        int $discount,
        ?Coupon $coupon
    ): array {
        if ($amount > 0 && ! $this->walletService->hasEnough($user, $amount)) {
            return ['success' => false, 'message' => 'موجودی کیف پول کافی نیست.', 'error' => 'insufficient_balance'];
        }

        if ($user->isWalletFrozen()) {
            return ['success' => false, 'message' => 'کیف پول شما مسدود است.', 'error' => 'wallet_frozen'];
        }

        try {
            return DB::transaction(function () use ($user, $plan, $amount, $original, $discount, $coupon) {
                $tx = Transaction::query()->create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'original_amount' => $original,
                    'discount_amount' => $discount,
                    'coupon_id' => $coupon?->id,
                    'type' => 'purchase',
                    'gateway' => 'wallet',
                    'status' => 'success',
                    'description' => 'خرید اشتراک '.$plan->name,
                    'payable_type' => SubscriptionPlan::class,
                    'payable_id' => $plan->id,
                ]);

                if ($amount > 0) {
                    $this->walletService->debit($user, $amount, [
                        'source_key' => 'subscription:'.$tx->id,
                        'transaction' => $tx,
                        'type' => WalletLedger::TYPE_PURCHASE,
                        'tx_type' => 'purchase',
                        'description' => $tx->description,
                        'gateway' => 'wallet',
                    ]);
                }

                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                if ($coupon) {
                    $this->couponService->redeem($coupon);
                }

                $this->activate($locked, $plan);
                $this->invoiceService->ensureInvoice($tx);
                DispatchAfterCommit::handle($tx, static function (Transaction $transaction): void {
                    event(new PaymentSuccessful($transaction));
                });

                return [
                    'success' => true,
                    'message' => 'اشتراک فعال شد',
                    'expires_at' => $locked->fresh()->subscription_expires_at?->toIso8601String(),
                ];
            });
        } catch (InsufficientBalanceException) {
            return ['success' => false, 'message' => 'موجودی کیف پول کافی نیست.', 'error' => 'insufficient_balance'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function subscribeWithGateway(
        User $user,
        SubscriptionPlan $plan,
        int $amount,
        int $original,
        int $discount,
        ?Coupon $coupon,
        string $gateway
    ): array {
        if ($amount <= 0) {
            return $this->subscribeWithWallet($user, $plan, 0, $original, $discount, $coupon);
        }

        $idempotency = app(IdempotencyService::class);
        $idempotencyKey = $idempotency->generateKey();

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'original_amount' => $original,
            'discount_amount' => $discount,
            'coupon_id' => $coupon?->id,
            'type' => 'purchase',
            'gateway' => $gateway,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => $idempotencyKey,
            'description' => 'خرید اشتراک '.$plan->name,
            'payable_type' => SubscriptionPlan::class,
            'payable_id' => $plan->id,
        ]);

        $callback = $idempotency->appendKeyToCallback(url('/payment/subscription'), $idempotencyKey);
        $meta = ['order_id' => (string) $transaction->id, 'idempotency_key' => $idempotencyKey];

        $result = $this->paymentService->initiate(
            $gateway,
            $amount,
            'خرید اشتراک '.$plan->name.' — JobAzmoon',
            $callback,
            $meta
        );

        // No automatic retry (avoids duplicate bank authorities after uncertain timeouts).
        if ($result['error'] || ! $result['authority']) {
            $this->paymentService->markInitiateFailure($transaction, $result['error'] ?? null);

            return [
                'success' => false,
                'message' => $result['error'] ?? 'خطا در اتصال به درگاه.',
                'error' => 'gateway_error',
            ];
        }

        $transaction->update(['reference_id' => $result['authority']]);

        return [
            'success' => true,
            'message' => 'در حال انتقال به درگاه پرداخت',
            'payment_url' => $result['payment_url'],
            'idempotency_key' => $idempotencyKey,
        ];
    }

    public function activate(User $user, SubscriptionPlan $plan, ?Transaction $transaction = null): void
    {
        $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

        if ((int) $plan->duration_days < 1) {
            throw new \InvalidArgumentException('Plan must have at least 1 duration day to activate.');
        }

        $startsFrom = $locked->subscription_expires_at && $locked->subscription_expires_at->isFuture()
            ? $locked->subscription_expires_at->copy()
            : now();

        $expiresAt = $startsFrom->copy()->addDays($plan->duration_days);

        $locked->update([
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => $expiresAt,
        ]);

        if ($transaction) {
            if ($transaction->coupon_id) {
                $coupon = Coupon::query()->find($transaction->coupon_id);
                if ($coupon) {
                    $this->couponService->redeem($coupon);
                }
            }
            $this->invoiceService->ensureInvoice($transaction);
            DispatchAfterCommit::handle($transaction->fresh() ?? $transaction, static function (Transaction $tx): void {
                event(new PaymentSuccessful($tx));
            });
        }
    }

    public function isActive(User $user): bool
    {
        return $user->subscription_expires_at !== null
            && $user->subscription_expires_at->isFuture();
    }

    public function getDaysLeft(User $user): int
    {
        if (! $this->isActive($user)) {
            return 0;
        }

        return (int) now()->diffInDays($user->subscription_expires_at, false);
    }

    public function expireIfNeeded(User $user): void
    {
        if ($user->subscription_plan_id && ! $this->isActive($user)) {
            $user->update([
                'subscription_plan_id' => null,
                'subscription_expires_at' => $user->subscription_expires_at,
            ]);
            event(new SubscriptionExpired($user->fresh()));
        }
    }

    /**
     * Upgrade: cancel remaining days, activate new plan from now.
     * Downgrade is not supported — user keeps current plan until expiry then purchases new.
     */
    /**
     * @return array<string, mixed>
     */
    public function upgrade(User $user, SubscriptionPlan $newPlan, string $method, ?string $couponCode = null, ?string $gateway = null): array
    {
        if (! $newPlan->is_active || (int) $newPlan->duration_days < 1) {
            return ['success' => false, 'message' => 'پلن انتخابی معتبر نیست.', 'error' => 'invalid_plan'];
        }

        $currentPlan = $user->subscriptionPlan;
        if ($currentPlan instanceof SubscriptionPlan && (int) $newPlan->price <= (int) $currentPlan->price && $this->isActive($user)) {
            return ['success' => false, 'message' => 'فقط ارتقا به پلن بالاتر ممکن است. پلن فعلی تا انقضا ادامه دارد.', 'error' => 'downgrade_not_allowed'];
        }

        return $this->subscribe($user, $newPlan, $method, $couponCode, $gateway);
    }

    public function freePlanExamLimit(): int
    {
        return (int) Setting::get('free_plan_exam_limit', 5);
    }
}
