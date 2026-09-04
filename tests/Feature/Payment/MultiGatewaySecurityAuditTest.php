<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\GatewayCallbackService;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

final class MultiGatewaySecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.fake' => true]);
        FakePaymentGateway::reset();
        app(FeatureFlagService::class)->enable('wallet');
        app(FeatureFlagService::class)->enable('subscription');
    }

    public function test_all_registered_gateways_implement_common_contract(): void
    {
        $manager = app(PaymentGatewayManager::class);
        $ref = new \ReflectionClass(PaymentGatewayManager::class);
        $prop = $ref->getProperty('drivers');
        $prop->setAccessible(true);
        /** @var array<string, class-string> $drivers */
        $drivers = $prop->getValue($manager);

        config(['payment.fake' => false]);

        foreach ($drivers as $code => $class) {
            $instance = app($class);

            $this->assertTrue(method_exists($instance, 'request'));
            $this->assertTrue(method_exists($instance, 'verify'));
            $this->assertTrue(method_exists($instance, 'getName'));
            $this->assertTrue(method_exists($instance, 'getDisplayName'));
            $this->assertTrue(method_exists($instance, 'isConfigured'));
            $this->assertTrue(method_exists($instance, 'testConnection'));
            $this->assertFalse(method_exists($instance, 'refund'), 'Bank gateway must not expose refund API');
            $this->assertFalse(method_exists($instance, 'isAvailable'), 'Use isConfigured, not isAvailable');
            $this->assertSame($code, $instance->getName());
        }

        config(['payment.fake' => true]);
    }

    public function test_no_active_gateway_does_not_fatal(): void
    {
        PaymentGateway::query()->create([
            'name' => 'zarinpal',
            'display_name' => 'زرین‌پال',
            'is_active' => false,
            'is_default' => true,
            'sort_order' => 1,
            'merchant_id' => 'm1',
        ]);

        $this->expectException(RuntimeException::class);
        app(PaymentGatewayManager::class)->assertPayable(null);
    }

    public function test_inactive_preferred_falls_back_before_purchase(): void
    {
        PaymentGateway::query()->create([
            'name' => 'nextpay',
            'display_name' => 'نکست‌پی',
            'is_active' => false,
            'is_default' => true,
            'sort_order' => 1,
            'api_key' => 'k1',
        ]);
        PaymentGateway::query()->create([
            'name' => 'zarinpal',
            'display_name' => 'زرین‌پال',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 2,
            'merchant_id' => 'm1',
        ]);

        $resolved = app(PaymentGatewayManager::class)->assertPayable('nextpay');
        $this->assertSame('zarinpal', $resolved);
    }

    public function test_wallet_charge_rejects_when_no_payable_gateway(): void
    {
        PaymentGateway::query()->create([
            'name' => 'zarinpal',
            'display_name' => 'زرین‌پال',
            'is_active' => false,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/charge', ['amount' => 50000, 'gateway' => 'zarinpal'])
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        $this->assertSame(0, Transaction::query()->count());
    }

    public function test_callback_failure_does_not_create_second_gateway_payment(): void
    {
        $user = User::factory()->create(['status' => 'active', 'wallet_balance' => 0]);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 500000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-FIXED-1',
            'idempotency_key' => 'ik-1',
            'description' => 'شارژ',
        ]);
        FakePaymentGateway::seed('AUTH-FIXED-1', 500000);
        FakePaymentGateway::$failNextVerify = true;

        $request = Request::create('/payment/wallet', 'GET', [
            'Authority' => 'AUTH-FIXED-1',
            'Status' => 'OK',
        ]);

        $result = app(GatewayCallbackService::class)->complete(
            $request,
            fn (Transaction $t) => $t->type === 'deposit',
            function (Transaction $row): void {
                app(PaymentService::class)->depositToWallet($row->user, (int) $row->amount, $row);
            }
        );

        $this->assertFalse($result->ok);
        $this->assertSame(1, Transaction::query()->count());
        $this->assertSame('zarinpal', $tx->fresh()->gateway);
        $this->assertNotSame(Transaction::STATUS_COMPLETED, $tx->fresh()->status);
        $this->assertSame(0, (int) $user->fresh()->wallet_balance);
    }

    public function test_amount_always_from_db_not_request(): void
    {
        $user = User::factory()->create(['status' => 'active', 'wallet_balance' => 0]);
        Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 500000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-AMT-1',
            'idempotency_key' => 'ik-amt',
            'description' => 'شارژ',
        ]);
        FakePaymentGateway::seed('AUTH-AMT-1', 500000);

        $request = Request::create('/payment/wallet', 'GET', [
            'Authority' => 'AUTH-AMT-1',
            'Status' => 'OK',
            'amount' => 1000,
        ]);

        $result = app(GatewayCallbackService::class)->complete(
            $request,
            fn (Transaction $t) => $t->type === 'deposit',
            function (Transaction $row) use ($user): void {
                app(PaymentService::class)->depositToWallet($user->fresh(), (int) $row->amount, $row);
            }
        );

        $this->assertTrue($result->ok);
        $this->assertSame(500000, (int) $user->fresh()->wallet_balance);
    }

    public function test_gateway_amount_mismatch_fails_payment(): void
    {
        $user = User::factory()->create(['status' => 'active', 'wallet_balance' => 0]);
        Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 500000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-MISMATCH',
            'idempotency_key' => 'ik-mm',
            'description' => 'شارژ',
        ]);
        // Gateway stored a different amount than DB — verify must fail.
        FakePaymentGateway::seed('AUTH-MISMATCH', 100000);

        $request = Request::create('/payment/wallet', 'GET', [
            'Authority' => 'AUTH-MISMATCH',
            'Status' => 'OK',
        ]);

        $result = app(GatewayCallbackService::class)->complete(
            $request,
            fn (Transaction $t) => $t->type === 'deposit',
            function (Transaction $row) use ($user): void {
                app(PaymentService::class)->depositToWallet($user->fresh(), (int) $row->amount, $row);
            }
        );

        $this->assertFalse($result->ok);
        $this->assertSame(0, (int) $user->fresh()->wallet_balance);
    }

    public function test_triple_callback_credits_wallet_once(): void
    {
        $user = User::factory()->create(['status' => 'active', 'wallet_balance' => 0]);
        Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 75000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-TRIPLE',
            'idempotency_key' => 'ik-triple',
            'description' => 'شارژ',
        ]);
        FakePaymentGateway::seed('AUTH-TRIPLE', 75000);

        $run = function () use ($user) {
            $request = Request::create('/payment/wallet', 'GET', [
                'Authority' => 'AUTH-TRIPLE',
                'Status' => 'OK',
            ]);

            return app(GatewayCallbackService::class)->complete(
                $request,
                fn (Transaction $t) => $t->type === 'deposit',
                function (Transaction $row) use ($user): void {
                    app(PaymentService::class)->depositToWallet($user->fresh(), (int) $row->amount, $row);
                }
            );
        };

        $this->assertTrue($run()->ok);
        $this->assertTrue($run()->ok);
        $this->assertTrue($run()->ok);
        $this->assertSame(75000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(1, Transaction::query()->where('status', Transaction::STATUS_COMPLETED)->count());
    }

    public function test_completed_transaction_cannot_revert_to_pending(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 10000,
            'type' => 'deposit',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_COMPLETED,
            'reference_id' => 'AUTH-DONE',
            'idempotency_key' => 'ik-done',
        ]);

        $request = Request::create('/payment/wallet', 'GET', [
            'Authority' => 'AUTH-DONE',
            'Status' => 'NOK',
        ]);

        $result = app(GatewayCallbackService::class)->complete(
            $request,
            fn (Transaction $t) => true,
            fn () => null
        );

        $this->assertTrue($result->ok);
        $this->assertTrue($result->alreadyProcessed);
        $this->assertSame(Transaction::STATUS_COMPLETED, $tx->fresh()->status);
    }

    public function test_admin_credentials_are_masked_in_api_response(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        PaymentGateway::query()->create([
            'name' => 'parsian',
            'display_name' => 'پارسیان',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 6,
            'merchant_id' => 'SECRET-PARSIAN-PIN-999',
            'settings' => ['password' => 'plain-should-not-leak'],
        ]);

        $response = $this->getJson('/api/v1/admin/payment-gateways')->assertOk();
        $body = $response->getContent();
        $this->assertIsString($body);
        $this->assertStringNotContainsString('SECRET-PARSIAN-PIN-999', $body);
        $this->assertStringNotContainsString('plain-should-not-leak', $body);

        $parsian = collect($response->json('data.gateways'))->firstWhere('name', 'parsian');
        $this->assertNotNull($parsian);
        $this->assertStringContainsString('*', (string) $parsian['merchant_id']);
    }

    public function test_admin_payment_gateways_forbidden_for_jobseeker_operator_and_admin(): void
    {
        $jobseeker = User::factory()->create(['role' => 'jobseeker', 'status' => 'active']);
        Sanctum::actingAs($jobseeker);
        $this->getJson('/api/v1/admin/payment-gateways')->assertForbidden();

        $operator = User::factory()->create(['role' => 'operator', 'status' => 'active']);
        Sanctum::actingAs($operator);
        $this->getJson('/api/v1/admin/payment-gateways')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/payment-gateways')->assertForbidden();
    }

    public function test_update_does_not_mirror_secrets_into_settings_table(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        PaymentGateway::query()->create([
            'name' => 'mellat',
            'display_name' => 'ملت',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 4,
        ]);

        $this->putJson('/api/v1/admin/payment-gateways/mellat', [
            'is_active' => true,
            'settings' => [
                'terminal_id' => 'term-1',
                'username' => 'user-1',
                'password' => 'super-secret-pass',
            ],
        ])->assertOk();

        $this->assertNull(Setting::query()->where('key', 'mellat_password')->value('value'));
        $this->assertNull(Setting::query()->where('key', 'mellat_username')->value('value'));
        $this->assertSame('true', Setting::get('mellat_active'));
    }

    public function test_settings_override_env_for_zarinpal_merchant(): void
    {
        config([
            'payment.fake' => false,
            'services.zarinpal.merchant_id' => 'env-merchant',
            'services.zarinpal.sandbox' => true,
        ]);
        Setting::set('zarinpal_merchant_id', 'admin-merchant', 'payments');

        $gateway = app(\App\Services\Payment\ZarinPalGateway::class);
        $ref = new \ReflectionMethod($gateway, 'merchantId');
        $ref->setAccessible(true);
        $this->assertSame('admin-merchant', $ref->invoke($gateway));
    }

    public function test_refund_uses_wallet_ledger_and_keeps_original_gateway(): void
    {
        $user = User::factory()->create(['status' => 'active', 'wallet_balance' => 100000]);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 50000,
            'type' => 'deposit',
            'gateway' => 'mellat',
            'status' => Transaction::STATUS_COMPLETED,
            'reference_id' => 'REF-MELLAT',
            'description' => 'شارژ',
        ]);

        $refund = app(PaymentService::class)->refund($tx);

        $this->assertSame('mellat', $refund->gateway);
        $this->assertSame('mellat', $tx->fresh()->gateway);
        $this->assertSame(50000, (int) $user->fresh()->wallet_balance);
    }
}
