<?php

namespace Tests\Unit\Services;

use App\Models\Setting;
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
            'services.sms.allow_log_fallback' => false,
            'services.kavenegar.api_key' => null,
        ]);

        $this->assertFalse((new KavenegarSmsGateway)->send('09120000000', 'code'));
    }

    public function test_sms_uses_env_key_when_settings_empty(): void
    {
        config([
            'services.sms.allow_log_fallback' => false,
            'services.kavenegar.api_key' => 'env-kavenegar-key',
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
            'www.zarinpal.com/*' => Http::response([
                'data' => [
                    'code' => 100,
                    'authority' => str_repeat('A', 36),
                ],
            ], 200),
        ]);

        $result = app(ZarinPalGateway::class)->request(10000, 'test', 'https://example.test/cb');

        $this->assertNull($result['error'] ?? null);
        $this->assertNotEmpty($result['authority']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'www.zarinpal.com'));
    }

    public function test_setting_get_filled_falls_back_when_db_value_empty(): void
    {
        Setting::set('sms_api_key', '', 'sms');

        $this->assertSame('from-env', Setting::getFilled('sms_api_key', 'from-env'));
    }
}
