<?php

declare(strict_types=1);

namespace App\Actions\Subscription;

use App\Exceptions\DuplicateSubscriptionException;
use App\Listeners\DispatchAfterCommit;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Services\SubscriptionService;
use App\Traits\HandlesTransactions;

/**
 * Subscription state lives on User (subscription_plan_id / subscription_expires_at).
 */
final class SubscriptionAction
{
    use HandlesTransactions;

    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function activate(User $user, SubscriptionPlan $plan, ?Transaction $transaction = null): User
    {
        return $this->transaction(function () use ($user, $plan, $transaction) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($transaction === null && $locked->hasActiveSubscription()) {
                throw new DuplicateSubscriptionException($locked);
            }

            if ($transaction !== null) {
                Transaction::query()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $this->subscriptions->activate($locked, $plan, $transaction);

            $fresh = $locked->fresh() ?? $locked;

            if ($transaction === null) {
                DispatchAfterCommit::handle($fresh, static function (User $u): void {
                    $u->notify(new GenericDatabaseNotification(
                        'subscription_activated',
                        'اشتراک فعال شد',
                        'اشتراک شما با موفقیت فعال شد.',
                        '/subscription'
                    ));
                });
            }

            return $fresh;
        });
    }

    public function renew(User $user, SubscriptionPlan $plan): User
    {
        return $this->transaction(function () use ($user, $plan) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->subscriptions->activate($locked, $plan, null);

            return $locked->fresh() ?? $locked;
        });
    }

    public function cancel(User $user): void
    {
        $this->transaction(function () use ($user) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'subscription_plan_id' => null,
                'subscription_expires_at' => now(),
            ]);
        });
    }
}
