<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\PdfProduct;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\Payment\ZarinPalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Sandbox endpoint routing + full integration flows (HTTP mocked to sandbox.zarinpal.com).
 * Does not call production ZarinPal or complete real sandbox payments.
 */
final class SandboxIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payment.fake' => false,
            'services.zarinpal.sandbox' => true,
            'services.zarinpal.merchant_id' => 'sandbox-merchant-test',
            'services.zarinpal.sandbox_base_url' => 'https://sandbox.zarinpal.com',
        ]);
        app(FeatureFlagService::class)->enable('wallet');
        app(FeatureFlagService::class)->enable('subscription');
        app(FeatureFlagService::class)->enable('pdf-store');
    }

    public function test_sandbox_mode_uses_sandbox_api_base(): void
    {
        $gateway = app(ZarinPalGateway::class);
        $method = new ReflectionMethod($gateway, 'apiBase');
        $method->setAccessible(true);

        $this->assertSame('https://sandbox.zarinpal.com', $method->invoke($gateway));
    }

    public function test_request_hits_sandbox_request_json_not_production(): void
    {
        Http::fake([
            'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'SANDBOX-AUTH-001'],
            ], 200),
            'payment.zarinpal.com/*' => Http::response(['errors' => ['message' => 'production blocked']], 500),
        ]);

        $result = app(ZarinPalGateway::class)->request(
            100000,
            'Sandbox wallet test',
            'https://example.test/payment/wallet?ik=test',
        );

        $this->assertSame('SANDBOX-AUTH-001', $result['authority']);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'sandbox.zarinpal.com/pg/v4/payment/request.json'));
        Http::assertNotSent(fn ($req) => str_contains($req->url(), 'payment.zarinpal.com'));
    }

    public function test_verify_hits_sandbox_verify_json(): void
    {
        Http::fake([
            'sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => ['code' => 100, 'ref_id' => 123456, 'amount' => 100000],
            ], 200),
        ]);

        $result = app(ZarinPalGateway::class)->verify('SANDBOX-AUTH-001', 100000);

        $this->assertTrue($result['success']);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'sandbox.zarinpal.com/pg/v4/payment/verify.json'));
    }

    public function test_wallet_sandbox_flow_with_mocked_zarinpal(): void
    {
        $this->fakeZarinPalSuccess(100000);

        $user = User::factory()->create(['wallet_balance' => 0, 'status' => 'active']);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/wallet/charge', [
            'amount' => 100000,
            'gateway' => 'zarinpal',
        ]);
        $create->assertOk();

        $tx = Transaction::query()->findOrFail($create->json('data.transaction_id'));
        $this->assertSame(Transaction::STATUS_PENDING, $tx->status);
        $this->assertStringContainsString('sandbox.zarinpal.com', (string) $create->json('data.payment_url'));

        $verify = $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
            'amount' => 1,
        ]);
        $verify->assertOk();
        $this->assertSame(100000, (int) $user->fresh()->wallet_balance);

        $refresh = $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
        ]);
        $refresh->assertOk();
        $refresh->assertJsonPath('data.already_processed', true);
        $this->assertSame(100000, (int) $user->fresh()->wallet_balance);
    }

    public function test_cancel_does_not_credit_wallet(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0, 'status' => 'active']);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 100000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'SANDBOX-CANCEL',
            'idempotency_key' => 'ik-cancel-sandbox',
        ]);

        $this->postJson('/api/v1/wallet/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'NOK',
            'ik' => $tx->idempotency_key,
        ])->assertStatus(400);

        $this->assertSame(0, (int) $user->fresh()->wallet_balance);
        $this->assertSame(Transaction::STATUS_CANCELLED, $tx->fresh()->status);
    }

    public function test_subscription_sandbox_activation_once(): void
    {
        $this->fakeZarinPalSuccess(99000);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Sandbox Plan',
            'price' => 99000,
            'duration_days' => 30,
            'is_active' => true,
        ]);
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $sub = $this->postJson('/api/v1/subscription/subscribe', [
            'plan_id' => $plan->id,
            'payment_method' => 'zarinpal',
        ]);
        $sub->assertOk();

        $tx = Transaction::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($tx);

        $payload = [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
        ];

        $this->postJson('/api/v1/subscription/verify', $payload)->assertOk();
        $this->assertNotNull($user->fresh()->subscription_plan_id);

        $dup = $this->postJson('/api/v1/subscription/verify', $payload);
        $dup->assertOk();
        $dup->assertJsonPath('data.already_processed', true);
    }

    public function test_pdf_sandbox_entitlement_once(): void
    {
        $this->fakeZarinPalSuccess(25000);

        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $pdf = PdfProduct::query()->create([
            'title' => 'Sandbox PDF',
            'file_path' => 'pdfs/sandbox.pdf',
            'price' => 25000,
            'is_active' => true,
        ]);

        $buy = $this->postJson('/api/v1/pdf-products/'.$pdf->id.'/purchase', [
            'payment_method' => 'zarinpal',
        ]);
        $buy->assertOk();

        $tx = Transaction::query()->where('user_id', $user->id)->latest('id')->first();
        $payload = [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
        ];

        $this->postJson('/api/v1/pdf-products/'.$pdf->id.'/verify', $payload)->assertOk();
        $this->postJson('/api/v1/pdf-products/'.$pdf->id.'/verify', $payload)
            ->assertOk()
            ->assertJsonPath('data.already_processed', true);
    }

    public function test_uncertain_gateway_failure_does_not_auto_retry(): void
    {
        Http::fake([
            'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::sequence()
                ->push(['errors' => ['message' => 'timeout']], 500)
                ->push(['data' => ['code' => 100, 'authority' => 'SHOULD-NOT-BE-USED']], 200),
        ]);

        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/wallet/charge', [
            'amount' => 100000,
            'gateway' => 'zarinpal',
        ]);

        $response->assertStatus(400);
        $this->assertSame(1, Transaction::query()->where('user_id', $user->id)->count());
        $tx = Transaction::query()->where('user_id', $user->id)->first();
        $this->assertNull($tx?->reference_id);
        // Uncertain network/timeout → remain pending for TTL/reconciliation (no second request).
        $this->assertSame(Transaction::STATUS_PENDING, $tx?->status);
        Http::assertSentCount(1);
    }

    public function test_invalid_amounts_rejected(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/charge', ['amount' => 0, 'gateway' => 'zarinpal'])->assertStatus(422);
        $this->postJson('/api/v1/wallet/charge', ['amount' => -100, 'gateway' => 'zarinpal'])->assertStatus(422);
    }

    protected function fakeZarinPalSuccess(int $amount): void
    {
        Http::fake([
            'sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'SANDBOX-'.uniqid()],
            ], 200),
            'sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => ['code' => 100, 'ref_id' => 999888, 'amount' => $amount],
            ], 200),
        ]);
    }
}
