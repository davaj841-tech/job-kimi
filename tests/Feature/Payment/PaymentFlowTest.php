<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\AuditLog;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\GatewayCallbackService;
use App\Services\Payment\ZarinPalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(FeatureFlagService::class)->enable('wallet');
        app(FeatureFlagService::class)->enable('subscription');
    }

    public function test_full_wallet_flow_create_redirect_callback_verify_success(): void
    {
        $user = User::factory()->create([
            'wallet_balance' => 0,
            'role' => 'jobseeker',
            'status' => 'active',
        ]);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/wallet/charge', [
            'amount' => 50000,
            'gateway' => 'zarinpal',
        ]);
        $create->assertOk();
        $this->assertStringContainsString('pay.fake.test', (string) $create->json('data.payment_url'));

        $tx = Transaction::query()->findOrFail($create->json('data.transaction_id'));
        $this->assertSame(Transaction::STATUS_PENDING, $tx->status);
        $this->assertNotEmpty($tx->reference_id);
        $this->assertSame(50000, (int) $tx->amount);
        $this->assertSame(50000, FakePaymentGateway::storedAmount($tx->reference_id));

        $this->assertTrue(
            AuditLog::query()->where('action', 'payment.initiated')->where('entity_id', $tx->id)->exists()
        );

        $callback = $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
            'amount' => 1,
        ]);
        $callback->assertOk();
        $callback->assertJsonPath('data.new_balance', 50000);
        $callback->assertJsonPath('data.already_processed', false);

        $this->assertSame(50000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(Transaction::STATUS_COMPLETED, $tx->fresh()->status);
        $this->assertTrue(
            AuditLog::query()->where('action', 'payment.verified')->where('entity_id', $tx->id)->exists()
        );

        $duplicate = $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
            'amount' => 999999,
        ]);
        $duplicate->assertOk();
        $duplicate->assertJsonPath('data.already_processed', true);
        $this->assertSame(50000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(1, Transaction::query()->where('user_id', $user->id)->where('status', Transaction::STATUS_COMPLETED)->count());
    }

    public function test_cancel_sets_cancelled_and_does_not_credit(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0, 'status' => 'active']);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/wallet/charge', ['amount' => 20000, 'gateway' => 'zarinpal'])->assertOk();
        $tx = Transaction::query()->findOrFail($create->json('data.transaction_id'));

        $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'NOK',
            'ik' => $tx->idempotency_key,
        ])->assertStatus(400);

        $this->assertSame(Transaction::STATUS_CANCELLED, $tx->fresh()->status);
        $this->assertSame(0, (int) $user->fresh()->wallet_balance);

        $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
        ])->assertStatus(400);

        $this->assertSame(0, (int) $user->fresh()->wallet_balance);
    }

    public function test_failed_gateway_verify_marks_failed(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0, 'status' => 'active']);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/wallet/charge', ['amount' => 15000, 'gateway' => 'zarinpal'])->assertOk();
        $tx = Transaction::query()->findOrFail($create->json('data.transaction_id'));

        FakePaymentGateway::$failNextVerify = true;

        $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
        ])->assertStatus(400);

        $this->assertSame(Transaction::STATUS_FAILED, $tx->fresh()->status);
        $this->assertSame(0, (int) $user->fresh()->wallet_balance);
    }

    public function test_subscription_flow_is_idempotent_and_uses_plan_price(): void
    {
        $plan = SubscriptionPlan::query()->create([
            'name' => 'ماهانه',
            'duration_days' => 30,
            'price' => 90000,
            'is_active' => true,
            'features' => [],
        ]);
        $user = User::factory()->create([
            'status' => 'active',
            'subscription_plan_id' => null,
            'subscription_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/subscription/subscribe', [
            'plan_id' => $plan->id,
            'payment_method' => 'zarinpal',
            'amount' => 1,
        ]);
        $create->assertOk();

        $tx = Transaction::query()->where('user_id', $user->id)->where('payable_id', $plan->id)->firstOrFail();
        $this->assertSame(90000, (int) $tx->amount);
        $this->assertSame(Transaction::STATUS_PENDING, $tx->status);

        $payload = [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
            'amount' => 1,
        ];
        $this->postJson('/api/v1/subscription/verify', $payload)->assertOk();
        $this->postJson('/api/v1/subscription/verify', $payload)->assertOk();

        $fresh = $user->fresh();
        $this->assertSame($plan->id, $fresh->subscription_plan_id);
        $this->assertNotNull($fresh->subscription_expires_at);
        $this->assertSame(1, Transaction::query()->where('idempotency_key', $tx->idempotency_key)->where('status', Transaction::STATUS_COMPLETED)->count());
    }

    public function test_expire_pending_command_marks_stale_transactions(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 10000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'FAKE-OLD',
            'description' => 'stale',
        ]);
        Transaction::query()->whereKey($tx->id)->update([
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        $count = app(GatewayCallbackService::class)->expireStalePending();
        $this->assertSame(1, $count);
        $this->assertSame(Transaction::STATUS_EXPIRED, $tx->fresh()->status);
    }

    public function test_gateway_logs_do_not_include_merchant_secret(): void
    {
        config([
            'payment.fake' => false,
            'services.zarinpal.merchant_id' => 'super-secret-merchant',
            'services.zarinpal.sandbox' => true,
        ]);
        Http::fake([
            'sandbox.zarinpal.com/*' => Http::response([
                'errors' => ['message' => 'denied'],
            ], 400),
        ]);

        $result = app(ZarinPalGateway::class)->request(10000, 'test', 'https://example.test/cb');

        $this->assertNotNull($result['error']);
        $this->assertStringNotContainsString('super-secret-merchant', (string) $result['error']);
    }
}
