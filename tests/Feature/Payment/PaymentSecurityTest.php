<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\Payment\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(FeatureFlagService::class)->enable('wallet');
        app(FeatureFlagService::class)->enable('subscription');
    }

    public function test_callback_amount_tampering_does_not_affect_credit(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0, 'status' => 'active']);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/wallet/charge', [
            'amount' => 50000,
            'gateway' => 'zarinpal',
        ]);
        $create->assertOk();

        $tx = Transaction::query()->findOrFail($create->json('data.transaction_id'));
        FakePaymentGateway::seed((string) $tx->reference_id, 50000);

        $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
            'amount' => 1,
        ])->assertOk();

        $this->assertSame(50000, (int) $user->fresh()->wallet_balance);
    }

    public function test_invalid_idempotency_key_rejected(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 50000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-IK-TEST',
            'idempotency_key' => 'real-key-uuid',
        ]);
        FakePaymentGateway::seed('AUTH-IK-TEST', 50000);

        $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => 'wrong-key',
        ])->assertStatus(422);
    }

    public function test_unknown_authority_returns_not_found(): void
    {
        $this->postJson('/api/v1/wallet/verify', [
            'Authority' => 'UNKNOWN-AUTHORITY',
            'Status' => 'OK',
        ])->assertStatus(404);
    }

    public function test_cancelled_callback_does_not_credit_wallet(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0, 'status' => 'active']);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 50000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-CANCEL',
            'idempotency_key' => 'ik-cancel',
        ]);

        $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'NOK',
            'ik' => $tx->idempotency_key,
        ])->assertStatus(400);

        $this->assertSame(0, (int) $user->fresh()->wallet_balance);
        $this->assertSame(Transaction::STATUS_CANCELLED, $tx->fresh()->status);
    }

    public function test_subscription_amount_comes_from_plan_not_client(): void
    {
        $plan = SubscriptionPlan::query()->create([
            'name' => 'طلایی',
            'price' => 99000,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/subscription/subscribe', [
            'plan_id' => $plan->id,
            'payment_method' => 'zarinpal',
            'gateway' => 'zarinpal',
            'amount' => 1,
        ]);

        $response->assertOk();

        $tx = Transaction::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($tx);
        $this->assertSame(99000, (int) $tx->amount);
    }

    public function test_wallet_charge_rejects_amount_below_minimum(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/charge', [
            'amount' => 100,
            'gateway' => 'zarinpal',
        ])->assertStatus(422);
    }
}
