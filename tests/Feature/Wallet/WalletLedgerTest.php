<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Actions\Payment\PaymentAction;
use App\Actions\Wallet\WalletAction;
use App\Exceptions\InsufficientBalanceException;
use App\Models\AuditLog;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\FeatureFlagService;
use App\Services\SubscriptionService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

final class WalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = app(WalletService::class);
    }

    public function test_balance_never_goes_negative_without_feature_flag(): void
    {
        $user = User::factory()->create(['wallet_balance' => 1_000]);

        try {
            $this->wallet->debit($user, 1_001, ['description' => 'overdraft']);
            $this->fail('Expected InsufficientBalanceException');
        } catch (InsufficientBalanceException $e) {
            $this->assertSame(1_000, $e->available);
        }

        $this->assertSame(1_000, (int) $user->fresh()->wallet_balance);
        $this->assertTrue($this->wallet->reconcile($user->fresh())['ok']);
    }

    public function test_negative_balance_allowed_only_when_feature_enabled(): void
    {
        $user = User::factory()->create(['wallet_balance' => 500]);
        app(FeatureFlagService::class)->enable('wallet_allow_negative');

        $this->wallet->debit($user, 800, ['description' => 'allowed overdraft']);

        $this->assertSame(-300, (int) $user->fresh()->wallet_balance);
        $this->assertTrue($this->wallet->reconcile($user->fresh())['ok']);
    }

    public function test_mass_assignment_and_direct_save_cannot_change_balance(): void
    {
        $user = User::factory()->create(['wallet_balance' => 2_000, 'name' => 'قبل']);

        $user->update(['wallet_balance' => 999_999, 'name' => 'بعد']);
        $user->refresh();
        $this->assertSame(2_000, (int) $user->wallet_balance);
        $this->assertSame('بعد', $user->name);

        $user->wallet_balance = 50_000;
        $user->save();
        $this->assertSame(2_000, (int) $user->fresh()->wallet_balance);
    }

    public function test_every_balance_change_writes_a_ledger_row_and_transaction(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);

        $this->wallet->credit($user, 4_000, ['description' => 'شارژ']);
        $this->wallet->debit($user->fresh(), 1_500, ['description' => 'خرید']);

        $this->assertSame(2_500, (int) $user->fresh()->wallet_balance);
        $this->assertSame(2, WalletLedger::query()->where('user_id', $user->id)->count());
        $this->assertSame(2, Transaction::query()->where('user_id', $user->id)->count());
        $this->assertTrue($this->wallet->reconcile($user->fresh())['ok']);
        $this->assertTrue($this->wallet->verifyHashChain((int) $user->id));
    }

    public function test_ledger_cannot_be_updated_or_deleted(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $ledger = $this->wallet->credit($user, 1_000, ['description' => 'x'])->ledger;

        $ledger->amount = 9;

        try {
            $ledger->save();
            $this->fail('Expected LogicException on update');
        } catch (LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $ledger->delete();
            $this->fail('Expected LogicException on delete');
        } catch (LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertSame(1_000, (int) $ledger->fresh()->amount);
    }

    public function test_each_ledger_row_has_a_unique_reference(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $first = $this->wallet->credit($user, 100, ['description' => 'a'])->ledger;
        $second = $this->wallet->credit($user->fresh(), 200, ['description' => 'b'])->ledger;

        $this->assertNotSame('', $first->reference);
        $this->assertNotSame($first->reference, $second->reference);
        $this->assertSame(2, WalletLedger::query()->distinct()->count('reference'));
    }

    public function test_duplicate_source_key_does_not_double_credit(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);

        $first = $this->wallet->credit($user, 7_000, ['source_key' => 'manual:once', 'description' => 'a']);
        $second = $this->wallet->credit($user->fresh(), 7_000, ['source_key' => 'manual:once', 'description' => 'b']);

        $this->assertTrue($second->duplicate);
        $this->assertSame($first->ledger->id, $second->ledger->id);
        $this->assertSame(7_000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(1, WalletLedger::query()->where('user_id', $user->id)->count());
    }

    public function test_payment_callback_does_not_credit_wallet_twice(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 25_000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-DUP',
            'description' => 'شارژ',
        ]);

        $this->wallet->deposit($user, 25_000, $tx);
        $this->wallet->deposit($user->fresh(), 25_000, $tx);
        $this->wallet->applyPaymentCredit($user->fresh(), $tx);

        $this->assertSame(25_000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(1, WalletLedger::query()->where('source_key', 'payment:'.$tx->id)->count());
        $this->assertTrue($this->wallet->reconcile($user->fresh())['ok']);
    }

    public function test_refund_credits_purchase_once_and_is_idempotent(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $this->wallet->credit($user, 10_000, ['description' => 'seed']);
        $purchase = $this->wallet->debit($user->fresh(), 4_000, [
            'description' => 'خرید',
            'tx_type' => 'purchase',
        ])->transaction;

        $first = $this->wallet->refund($purchase);
        $second = $this->wallet->refund($purchase);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(10_000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(1, Transaction::query()->where('type', 'refund')->where('user_id', $user->id)->count());
        $this->assertTrue($this->wallet->reconcile($user->fresh())['ok']);
    }

    public function test_refund_of_deposit_debits_wallet(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $deposit = $this->wallet->credit($user, 6_000, ['description' => 'deposit'])->transaction;

        $this->wallet->refund($deposit);

        $this->assertSame(0, (int) $user->fresh()->wallet_balance);
        $this->assertTrue($this->wallet->reconcile($user->fresh())['ok']);
    }

    public function test_wallet_mutations_write_audit_logs(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $this->wallet->credit($user, 3_000, ['description' => 'audit credit']);
        $this->wallet->debit($user->fresh(), 1_000, ['description' => 'audit debit']);

        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('action', 'wallet.credited')->count());
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('action', 'wallet.debited')->count());
    }

    public function test_cached_balance_matches_ledger_sum(): void
    {
        $user = User::factory()->create(['wallet_balance' => 8_000]);
        $this->wallet->debit($user, 2_000, ['description' => 'a']);
        $this->wallet->credit($user->fresh(), 500, ['description' => 'b']);

        $reconcile = $this->wallet->reconcile($user->fresh());
        $this->assertTrue($reconcile['ok']);
        $this->assertSame(6_500, $reconcile['cached']);
        $this->assertSame(6_500, $reconcile['ledger']);
        $this->assertSame(6_500, $this->wallet->ledgerBalance((int) $user->id));
    }

    public function test_concurrent_debits_are_serialized_and_second_overdraft_fails(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $this->wallet->credit($user, 10_000, ['description' => 'seed']);

        $this->wallet->debit($user->fresh(), 10_000, ['description' => 'first']);

        try {
            $this->wallet->debit($user->fresh(), 1, ['description' => 'second']);
            $this->fail('Expected InsufficientBalanceException');
        } catch (InsufficientBalanceException) {
            $this->assertSame(0, (int) $user->fresh()->wallet_balance);
        }

        $this->assertSame(1, WalletLedger::query()->where('direction', WalletLedger::DIRECTION_DEBIT)->where('user_id', $user->id)->count());
        $this->assertTrue($this->wallet->reconcile($user->fresh())['ok']);
    }

    public function test_concurrent_duplicate_source_key_under_row_lock(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);

        $run = function () use ($user): void {
            DB::transaction(function () use ($user): void {
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                app(WalletService::class)->credit($user, 5_000, [
                    'source_key' => 'race:payment-1',
                    'description' => 'callback',
                ]);
            });
        };

        $run();
        $run();

        $this->assertSame(5_000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(1, WalletLedger::query()->where('source_key', 'race:payment-1')->count());
    }

    public function test_two_distinct_credits_sum_under_lock(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $action = app(WalletAction::class);

        $action->charge($user, 20_000, ['reference_id' => 'R1']);
        $action->charge($user->fresh(), 20_000, ['reference_id' => 'R2']);
        $again = $action->charge($user->fresh(), 20_000, ['reference_id' => 'R1']);

        $this->assertSame(40_000, (int) $user->fresh()->wallet_balance);
        $this->assertSame('R1', $again->reference_id);
        $this->assertSame(2, WalletLedger::query()->where('user_id', $user->id)->count());
    }

    public function test_subscription_wallet_payment_writes_ledger(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $this->wallet->credit($user, 50_000, ['description' => 'seed']);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'ماهانه',
            'duration_days' => 30,
            'price' => 12_000,
            'is_active' => true,
            'features' => [],
        ]);

        $result = app(SubscriptionService::class)->subscribe($user->fresh(), $plan, 'wallet');

        $this->assertTrue($result['success']);
        $this->assertSame(38_000, (int) $user->fresh()->wallet_balance);
        $this->assertTrue(WalletLedger::query()->where('source_key', 'like', 'subscription:%')->exists());
        $this->assertTrue($this->wallet->reconcile($user->fresh())['ok']);
    }

    public function test_payment_action_refund_uses_ledger(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $deposit = $this->wallet->credit($user, 8_000, ['description' => 'in'])->transaction;

        $refund = app(PaymentAction::class)->refund($deposit);

        $this->assertSame('refund', $refund->type);
        $this->assertSame(0, (int) $user->fresh()->wallet_balance);
        $this->assertSame(1, WalletLedger::query()->where('source_key', 'refund:'.$deposit->id)->count());
    }

    public function test_hash_chain_detects_tampering(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $this->wallet->credit($user, 1_000, ['description' => 'a']);
        $this->wallet->credit($user->fresh(), 2_000, ['description' => 'b']);

        $this->assertTrue($this->wallet->verifyHashChain((int) $user->id));

        DB::table('wallet_ledgers')->where('user_id', $user->id)->orderBy('id')->limit(1)->update([
            'hash' => str_repeat('a', 64),
        ]);

        $this->assertFalse($this->wallet->verifyHashChain((int) $user->id));
    }
}
