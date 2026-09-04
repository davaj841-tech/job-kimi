<?php

namespace Tests\Unit\Services;

use App\Models\Setting;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\ZarinPalGateway;
use App\Services\Sms\KavenegarSmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductionIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_fails_closed_without_key_when_fallback_disabled(): void
    {
        config([
            'sms.allow_log_fallback' => false,
            'services.sms.allow_log_fallback' => false,
            'services.kavenegar.api_key' => null,
            'sms.kavenegar.api_key' => null,
        ]);

        $this->assertFalse((new KavenegarSmsGateway)->send('09120000000', 'code'));
    }

    public function test_sms_uses_env_key_when_settings_empty(): void
    {
        config([
            'sms.allow_log_fallback' => false,
            'services.sms.allow_log_fallback' => false,
            'services.kavenegar.api_key' => 'env-kavenegar-key',
            'sms.kavenegar.api_key' => 'env-kavenegar-key',
        ]);

        Http::fake([
            'api.kavenegar.com/*' => Http::response(['return' => ['status' => 200]], 200),
        ]);

        $this->assertTrue((new KavenegarSmsGateway)->send('09120000000', 'code'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'env-kavenegar-key'));
    }

    public function test_zarinpal_reads_merchant_from_config_when_settings_empty(): void
    {
        config(['services.zarinpal.merchant_id' => 'env-merchant', 'services.zarinpal.sandbox' => false]);

        Http::fake([
            'payment.zarinpal.com/*' => Http::response([
                'data' => [
                    'code' => 100,
                    'authority' => str_repeat('A', 36),
                ],
            ], 200),
        ]);

        $result = app(ZarinPalGateway::class)->request(10000, 'test', 'https://example.test/cb');

        $this->assertNull($result['error'] ?? null);
        $this->assertNotEmpty($result['authority']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'payment.zarinpal.com')
            && ($request['merchant_id'] ?? null) === 'env-merchant'
            && ($request['currency'] ?? null) === 'IRR'
            && ($request['amount'] ?? null) === 10000);
    }

    public function test_zarinpal_prefers_admin_setting_merchant_over_env(): void
    {
        config([
            'services.zarinpal.merchant_id' => 'env-merchant',
            'services.zarinpal.sandbox' => true,
        ]);
        Setting::set('zarinpal_merchant_id', 'admin-merchant', 'payments');
        Setting::set('zarinpal_sandbox', 'true', 'payments');

        Http::fake([
            'sandbox.zarinpal.com/*' => Http::response([
                'data' => [
                    'code' => 100,
                    'authority' => str_repeat('B', 36),
                ],
            ], 200),
        ]);

        $result = app(ZarinPalGateway::class)->request(20000, 'test', 'https://example.test/cb');

        $this->assertNull($result['error'] ?? null);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sandbox.zarinpal.com')
            && ($request['merchant_id'] ?? null) === 'admin-merchant');
    }

    public function test_zarinpal_verify_rejects_amount_mismatch(): void
    {
        config(['services.zarinpal.merchant_id' => 'm-1', 'services.zarinpal.sandbox' => true]);

        Http::fake([
            'sandbox.zarinpal.com/*' => Http::response([
                'data' => [
                    'code' => 100,
                    'ref_id' => 123,
                    'amount' => 999,
                ],
            ], 200),
        ]);

        $result = app(ZarinPalGateway::class)->verify(str_repeat('A', 36), 10000);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('همخوانی', (string) $result['error']);
    }

    public function test_zarinpal_verify_accepts_code_101_already_verified(): void
    {
        config(['services.zarinpal.merchant_id' => 'm-1', 'services.zarinpal.sandbox' => true]);

        Http::fake([
            'sandbox.zarinpal.com/*' => Http::response([
                'data' => [
                    'code' => 101,
                    'ref_id' => 456,
                    'amount' => 10000,
                ],
            ], 200),
        ]);

        $result = app(ZarinPalGateway::class)->verify(str_repeat('A', 36), 10000);

        $this->assertTrue($result['success']);
        $this->assertSame('456', $result['ref_id']);
    }

    public function test_payment_fake_is_ignored_in_production_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config([
            'payment.fake' => true,
            'services.zarinpal.merchant_id' => 'prod-merchant',
            'services.zarinpal.sandbox' => false,
        ]);

        $driver = app(PaymentGatewayManager::class)->driver('zarinpal');
        $this->assertInstanceOf(ZarinPalGateway::class, $driver);
    }

    public function test_setting_get_filled_falls_back_when_db_value_empty(): void
    {
        Setting::set('sms_api_key', '', 'sms');

        $this->assertSame('from-env', Setting::getFilled('sms_api_key', 'from-env'));
    }
}
