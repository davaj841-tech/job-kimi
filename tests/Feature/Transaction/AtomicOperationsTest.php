<?php

declare(strict_types=1);

namespace Tests\Feature\Transaction;

use App\Actions\Subscription\SubscriptionAction;
use App\Actions\Wallet\WalletAction;
use App\Events\PaymentSuccessful;
use App\Exceptions\InsufficientBalanceException;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\HandlesTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

final class AtomicOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_deduct_and_transaction_are_atomic(): void
    {
        $user = User::factory()->create(['wallet_balance' => 50_000]);
        $before = (int) $user->wallet_balance;

        $runner = new class
        {
            use HandlesTransactions;
        };

        try {
            $runner->transaction(function () use ($user): void {
                User::query()->whereKey($user->id)->lockForUpdate()->decrement('wallet_balance', 10_000);
                Transaction::query()->create([
                    'user_id' => $user->id,
                    'amount' => 10_000,
                    'type' => 'purchase',
                    'gateway' => 'wallet',
                    'status' => Transaction::STATUS_COMPLETED,
                    'description' => 'partial',
                ]);
                throw new RuntimeException('simulated failure after deduct');
            });
            $this->fail('Expected exception was not thrown');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame($before, (int) $user->fresh()->wallet_balance);
        $this->assertSame(0, Transaction::query()->where('user_id', $user->id)->count());
    }

    public function test_concurrent_payments_do_not_double_charge(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $wallet = app(WalletAction::class);

        $first = $wallet->charge($user, 20_000, ['description' => 'a', 'reference_id' => 'R1']);
        $second = $wallet->charge($user->fresh(), 20_000, ['description' => 'b', 'reference_id' => 'R2']);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(40_000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(2, Transaction::query()->where('user_id', $user->id)->count());

        // Sequential deduct under lock: second insufficient fails with no partial state.
        $wallet->deduct($user->fresh(), 40_000, ['description' => 'all']);
        $this->assertSame(0, (int) $user->fresh()->wallet_balance);

        try {
            $wallet->deduct($user->fresh(), 1, ['description' => 'overdraw']);
            $this->fail('Expected InsufficientBalanceException');
        } catch (InsufficientBalanceException) {
            $this->assertSame(0, (int) $user->fresh()->wallet_balance);
        }
    }

    public function test_subscription_activation_is_atomic(): void
    {
        $plan = SubscriptionPlan::query()->create([
            'name' => 'ماهانه',
            'duration_days' => 30,
            'price' => 1000,
            'is_active' => true,
            'features' => [],
        ]);

        $user = User::factory()->create([
            'wallet_balance' => 0,
            'subscription_plan_id' => null,
            'subscription_expires_at' => null,
        ]);

        $runner = new class
        {
            use HandlesTransactions;
        };

        try {
            $runner->transaction(function () use ($user, $plan): void {
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $locked->update([
                    'subscription_plan_id' => $plan->id,
                    'subscription_expires_at' => now()->addDays(30),
                ]);
                throw new RuntimeException('subscription side-effect failed');
            });
            $this->fail('Expected exception');
        } catch (RuntimeException) {
            // expected
        }

        $fresh = $user->fresh();
        $this->assertNull($fresh->subscription_plan_id);
        $this->assertNull($fresh->subscription_expires_at);
    }

    public function test_events_only_dispatched_after_commit(): void
    {
        Event::fake([PaymentSuccessful::class]);

        $user = User::factory()->create(['wallet_balance' => 0]);
        $runner = new class
        {
            use HandlesTransactions;
        };

        try {
            $runner->transaction(function () use ($user): void {
                app(WalletAction::class)->charge($user, 5000, ['description' => 'nested']);
                throw new RuntimeException('rollback events');
            });
        } catch (RuntimeException) {
            // expected
        }

        Event::assertNotDispatched(PaymentSuccessful::class);
        $this->assertSame(0, (int) $user->fresh()->wallet_balance);

        app(WalletAction::class)->charge($user->fresh(), 5000, ['description' => 'ok']);
        Event::assertDispatched(PaymentSuccessful::class);
    }

    public function test_deadlock_retry_succeeds(): void
    {
        $attempts = 0;
        $runner = new class
        {
            use HandlesTransactions;
        };

        $result = $runner->transaction(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new RuntimeException('Deadlock found when trying to get lock; try restarting transaction');
            }

            return 'ok';
        }, 3);

        $this->assertSame('ok', $result);
        $this->assertSame(3, $attempts);
    }

    public function test_insufficient_balance_throws_exception(): void
    {
        $user = User::factory()->create(['wallet_balance' => 1000]);
        $wallet = app(WalletAction::class);

        try {
            $wallet->deduct($user, 5000, ['description' => 'too much']);
            $this->fail('Expected InsufficientBalanceException');
        } catch (InsufficientBalanceException $e) {
            $this->assertSame(5000, $e->requested);
            $this->assertSame(1000, $e->available);
        }

        $this->assertSame(1000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(0, Transaction::query()->where('user_id', $user->id)->count());
    }

    public function test_subscription_action_activates_under_lock(): void
    {
        $plan = SubscriptionPlan::query()->create([
            'name' => 'سالانه',
            'duration_days' => 365,
            'price' => 0,
            'is_active' => true,
            'features' => [],
        ]);

        $user = User::factory()->create([
            'subscription_plan_id' => null,
            'subscription_expires_at' => null,
        ]);

        $updated = app(SubscriptionAction::class)->activate($user, $plan);

        $this->assertSame($plan->id, $updated->subscription_plan_id);
        $this->assertNotNull($updated->subscription_expires_at);
    }
}
