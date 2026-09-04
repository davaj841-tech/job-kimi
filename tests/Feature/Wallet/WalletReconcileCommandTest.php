<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Models\User;
use App\Models\WalletLedger;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WalletReconcileCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_passes_for_consistent_wallet(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        app(WalletService::class)->adminDeposit($user, 50000, 'test deposit');

        $this->artisan('wallet:reconcile', ['--user' => $user->id])
            ->assertSuccessful();
    }

    public function test_reconcile_fails_when_cached_balance_diverges(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        app(WalletService::class)->adminDeposit($user, 10000, 'seed');

        // Simulate corruption outside WalletService (should never happen in production flow)
        User::query()->whereKey($user->id)->update(['wallet_balance' => 99999]);

        $this->artisan('wallet:reconcile', ['--user' => $user->id])
            ->assertFailed();

        $this->assertGreaterThan(0, WalletLedger::query()->where('user_id', $user->id)->count());
    }
}
