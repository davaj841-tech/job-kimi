<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Models\AuditLog;
use App\Models\PdfProduct;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\FeatureFlagService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class WalletFinalizationTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = app(WalletService::class);
        app(FeatureFlagService::class)->enable('wallet');
        app(FeatureFlagService::class)->enable('pdf-store');
    }

    public function test_admin_can_refund_transaction(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $user = User::factory()->create(['wallet_balance' => 0, 'status' => 'active']);
        $this->wallet->adminDeposit($user, 20000, 'seed');

        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 5000,
            'type' => 'purchase',
            'gateway' => 'wallet',
            'status' => Transaction::STATUS_COMPLETED,
            'description' => 'خرید تست',
        ]);
        $this->wallet->debit($user, 5000, [
            'source_key' => 'purchase:'.$tx->id,
            'transaction' => $tx,
            'type' => WalletLedger::TYPE_PURCHASE,
            'tx_type' => 'purchase',
        ]);

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/v1/admin/transactions/'.$tx->id.'/refund', [
            'reason' => 'تست بازگشت وجه',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet.refund']);
        $this->assertTrue(
            WalletLedger::query()->where('source_key', 'refund:'.$tx->id)->exists()
        );
    }

    public function test_refund_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $user = User::factory()->create(['wallet_balance' => 10000, 'status' => 'active']);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 3000,
            'type' => 'purchase',
            'gateway' => 'wallet',
            'status' => Transaction::STATUS_COMPLETED,
            'description' => 'خرید',
        ]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/transactions/'.$tx->id.'/refund', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_refund_cannot_be_done_twice(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $user = User::factory()->create(['wallet_balance' => 0]);
        $this->wallet->adminDeposit($user, 10000, 'seed');
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 4000,
            'type' => 'purchase',
            'gateway' => 'wallet',
            'status' => Transaction::STATUS_COMPLETED,
            'description' => 'خرید',
        ]);
        $this->wallet->debit($user, 4000, [
            'source_key' => 'purchase:'.$tx->id,
            'transaction' => $tx,
            'type' => WalletLedger::TYPE_PURCHASE,
            'tx_type' => 'purchase',
        ]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/transactions/'.$tx->id.'/refund', [
            'reason' => 'اول',
        ])->assertOk();

        $balanceAfterFirst = (int) $user->fresh()->wallet_balance;
        $this->postJson('/api/v1/admin/transactions/'.$tx->id.'/refund', [
            'reason' => 'دوم',
        ])->assertOk()
            ->assertJsonPath('data.already_refunded', true);

        $this->assertSame($balanceAfterFirst, (int) $user->fresh()->wallet_balance);
        $this->assertSame(1, WalletLedger::query()->where('source_key', 'refund:'.$tx->id)->count());
    }

    public function test_refund_creates_reversal(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $this->wallet->adminDeposit($user, 8000, 'seed');
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 3000,
            'type' => 'purchase',
            'gateway' => 'wallet',
            'status' => Transaction::STATUS_COMPLETED,
            'description' => 'خرید',
        ]);
        $this->wallet->debit($user, 3000, [
            'source_key' => 'purchase:'.$tx->id,
            'transaction' => $tx,
            'type' => WalletLedger::TYPE_PURCHASE,
            'tx_type' => 'purchase',
        ]);

        $this->wallet->refund($tx);
        $ledger = WalletLedger::query()->where('source_key', 'refund:'.$tx->id)->first();
        $this->assertNotNull($ledger);
        $this->assertSame(WalletLedger::TYPE_REFUND, $ledger->type);
        $this->assertSame(WalletLedger::DIRECTION_CREDIT, $ledger->direction);
    }

    public function test_refund_does_not_modify_original_transaction_amount(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $this->wallet->adminDeposit($user, 5000, 'seed');
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 2000,
            'type' => 'purchase',
            'gateway' => 'wallet',
            'status' => Transaction::STATUS_COMPLETED,
            'description' => 'خرید',
        ]);
        $this->wallet->debit($user, 2000, [
            'source_key' => 'purchase:'.$tx->id,
            'transaction' => $tx,
            'type' => WalletLedger::TYPE_PURCHASE,
            'tx_type' => 'purchase',
        ]);

        $originalAmount = (int) $tx->amount;
        $this->wallet->refund($tx);
        $this->assertSame($originalAmount, (int) $tx->fresh()->amount);
        $this->assertSame('purchase', $tx->fresh()->type);
    }

    public function test_non_admin_cannot_refund(): void
    {
        $user = User::factory()->create(['role' => 'jobseeker', 'status' => 'active']);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 1000,
            'type' => 'purchase',
            'gateway' => 'wallet',
            'status' => Transaction::STATUS_COMPLETED,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/admin/transactions/'.$tx->id.'/refund', [
            'reason' => 'test',
        ])->assertForbidden();
    }

    public function test_admin_can_freeze_wallet(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $user = User::factory()->create(['wallet_balance' => 5000, 'status' => 'active']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/wallets/'.$user->id.'/freeze', [
            'reason' => 'تست مسدودسازی',
        ])->assertOk();

        $this->assertTrue($user->fresh()->isWalletFrozen());
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet.freeze']);
    }

    public function test_admin_can_unfreeze_wallet(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $user = User::factory()->create([
            'wallet_balance' => 5000,
            'status' => 'active',
            'wallet_frozen_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/wallets/'.$user->id.'/unfreeze', [
            'reason' => 'رفع مسدودیت',
        ])->assertOk();

        $this->assertFalse($user->fresh()->isWalletFrozen());
        $this->assertDatabaseHas('audit_logs', ['action' => 'wallet.unfreeze']);
    }

    public function test_freeze_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/wallets/'.$user->id.'/freeze', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_unfreeze_requires_reason(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $user = User::factory()->create([
            'status' => 'active',
            'wallet_frozen_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/wallets/'.$user->id.'/unfreeze', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_non_admin_cannot_freeze_wallet(): void
    {
        $user = User::factory()->create(['role' => 'jobseeker', 'status' => 'active']);
        $target = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/admin/wallets/'.$target->id.'/freeze', [
            'reason' => 'test',
        ])->assertForbidden();
    }

    public function test_non_admin_cannot_unfreeze_wallet(): void
    {
        $user = User::factory()->create(['role' => 'jobseeker', 'status' => 'active']);
        $target = User::factory()->create([
            'status' => 'active',
            'wallet_frozen_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/admin/wallets/'.$target->id.'/unfreeze', [
            'reason' => 'test',
        ])->assertForbidden();
    }

    public function test_frozen_wallet_cannot_purchase_pdf(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'wallet_balance' => 50000,
            'wallet_frozen_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $pdf = PdfProduct::query()->create([
            'title' => 'فایل',
            'file_path' => 'pdfs/x.pdf',
            'price' => 10000,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/pdf-products/'.$pdf->id.'/purchase', [
            'payment_method' => 'wallet',
        ])->assertStatus(400);

        $this->assertSame(50000, (int) $user->fresh()->wallet_balance);
    }

    public function test_frozen_wallet_cannot_charge(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'wallet_balance' => 0,
            'wallet_frozen_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/charge', [
            'amount' => 10000,
            'gateway' => 'zarinpal',
        ])->assertStatus(422);
    }

    public function test_frozen_wallet_can_view_balance_and_transactions(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'wallet_balance' => 12000,
            'wallet_frozen_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/wallet');
        $response->assertOk();
        $response->assertJsonPath('data.wallet_frozen', true);
        $response->assertJsonPath('data.balance', 12000);
    }

    public function test_user_can_filter_wallet_transactions(): void
    {
        $user = User::factory()->create(['status' => 'active', 'wallet_balance' => 0]);
        $this->wallet->credit($user, 5000, [
            'type' => WalletLedger::TYPE_DEPOSIT,
            'tx_type' => 'deposit',
            'description' => 'شارژ',
        ]);
        Sanctum::actingAs($user);

        $all = $this->getJson('/api/v1/wallet');
        $all->assertOk();

        $deposits = $this->getJson('/api/v1/wallet?type=deposit');
        $deposits->assertOk();
        foreach ($deposits->json('data.transactions') as $row) {
            $this->assertSame('deposit', $row['type']);
        }
    }

    public function test_user_cannot_view_other_user_transactions(): void
    {
        $userA = User::factory()->create(['status' => 'active', 'wallet_balance' => 0]);
        $userB = User::factory()->create(['status' => 'active', 'wallet_balance' => 0]);
        $this->wallet->adminDeposit($userB, 9000, 'other user');

        Sanctum::actingAs($userA);
        $response = $this->getJson('/api/v1/wallet');
        $response->assertOk();

        foreach ($response->json('data.transactions') as $row) {
            $tx = Transaction::query()->find($row['id']);
            $this->assertSame($userA->id, $tx?->user_id);
        }
    }

    public function test_transaction_filter_preserves_pagination(): void
    {
        $user = User::factory()->create(['status' => 'active', 'wallet_balance' => 0]);
        for ($i = 0; $i < 10; $i++) {
            $this->wallet->credit($user, 1000 + $i, [
                'type' => WalletLedger::TYPE_DEPOSIT,
                'tx_type' => 'deposit',
                'description' => 'credit '.$i,
            ]);
        }
        Sanctum::actingAs($user);

        $page1 = $this->getJson('/api/v1/wallet?type=deposit&per_page=5&page=1');
        $page1->assertOk();
        $page1->assertJsonPath('data.meta.per_page', 5);
        $page1->assertJsonPath('data.meta.total', 10);
        $this->assertCount(5, $page1->json('data.transactions'));

        $page2 = $this->getJson('/api/v1/wallet?type=deposit&per_page=5&page=2');
        $page2->assertOk();
        $this->assertCount(5, $page2->json('data.transactions'));
    }
}
