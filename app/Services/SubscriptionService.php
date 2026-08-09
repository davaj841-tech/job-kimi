<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

        $gateway = $this->paymentService->resolveGatewayName($gateway ?: ($method !== 'wallet' ? $method : null));

        return $this->subscribeWithGateway($user, $plan, $amount, $original, $discount, $coupon, $gateway);
    }

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

        return DB::transaction(function () use ($user, $plan, $amount, $original, $discount, $coupon) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($amount > 0) {
                if (! $locked || (int) $locked->wallet_balance < $amount) {
                    return ['success' => false, 'message' => 'موجودی کیف پول کافی نیست.', 'error' => 'insufficient_balance'];
                }
                $locked->decrement('wallet_balance', $amount);
            }

            $tx = Transaction::query()->create([
                'user_id' => $locked->id,
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

            if ($coupon) {
                $this->couponService->redeem($coupon);
            }

            $this->activate($locked, $plan);
            $this->invoiceService->ensureInvoice($tx);
            event(new \App\Events\PaymentSuccessful($tx));

            return [
                'success' => true,
                'message' => 'اشتراک فعال شد',
                'expires_at' => $locked->fresh()->subscription_expires_at?->toIso8601String(),
            ];
        });
    }

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

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'original_amount' => $original,
            'discount_amount' => $discount,
            'coupon_id' => $coupon?->id,
            'type' => 'purchase',
            'gateway' => $gateway,
            'status' => 'pending',
            'description' => 'خرید اشتراک '.$plan->name,
            'payable_type' => SubscriptionPlan::class,
            'payable_id' => $plan->id,
        ]);

        $callback = url('/payment/subscription');
        $result = $this->paymentService->initiate(
            $gateway,
            $amount,
            'خرید اشتراک '.$plan->name.' — JobAzmoon',
            $callback,
            ['order_id' => (string) $transaction->id]
        );

        if ($result['error'] || ! $result['authority']) {
            $transaction->update(['status' => 'failed']);

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
        ];
    }

    public function activate(User $user, SubscriptionPlan $plan, ?Transaction $transaction = null): void
    {
        $startsFrom = $user->subscription_expires_at && $user->subscription_expires_at->isFuture()
            ? $user->subscription_expires_at->copy()
            : now();

        $user->update([
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => $startsFrom->copy()->addDays($plan->duration_days),
        ]);

        if ($transaction) {
            if ($transaction->coupon_id) {
                $coupon = Coupon::query()->find($transaction->coupon_id);
                if ($coupon) {
                    $this->couponService->redeem($coupon);
                }
            }
            $this->invoiceService->ensureInvoice($transaction);
            event(new \App\Events\PaymentSuccessful($transaction->fresh()));
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
            $user->update(['subscription_plan_id' => null]);
            event(new \App\Events\SubscriptionExpired($user->fresh()));
        }
    }

    public function freePlanExamLimit(): int
    {
        return (int) Setting::get('free_plan_exam_limit', 5);
    }
}
