<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\ZarinPalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MultiGatewayManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_codes_include_bank_gateways(): void
    {
        $codes = app(PaymentGatewayManager::class)->registeredCodes();

        foreach (['zarinpal', 'parsian', 'saman', 'pasargad', 'sadad', 'ap', 'mellat'] as $code) {
            $this->assertContains($code, $codes);
        }
    }

    public function test_default_falls_back_when_default_row_inactive(): void
    {
        PaymentGateway::query()->create([
            'name' => 'zarinpal',
            'display_name' => 'زرین‌پال',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 1,
            'merchant_id' => 'test-merchant',
        ]);
        PaymentGateway::query()->create([
            'name' => 'nextpay',
            'display_name' => 'نکست‌پی',
            'is_active' => false,
            'is_default' => true,
            'sort_order' => 2,
        ]);

        $this->assertSame('zarinpal', app(PaymentGatewayManager::class)->defaultName());
    }

    public function test_zarinpal_resolves_merchant_from_gateway_row(): void
    {
        PaymentGateway::query()->create([
            'name' => 'zarinpal',
            'display_name' => 'زرین‌پال',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
            'merchant_id' => 'row-merchant-xyz',
        ]);

        config(['services.zarinpal.merchant_id' => null]);
        Setting::query()->where('key', 'zarinpal_merchant_id')->delete();

        $gateway = app(ZarinPalGateway::class);
        $this->assertTrue($gateway->isConfigured());
    }

    public function test_admin_can_list_and_set_default_gateway(): void
    {
        config(['payment.fake' => true]);
        FakePaymentGateway::reset();

        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        PaymentGateway::query()->create([
            'name' => 'zarinpal',
            'display_name' => 'زرین‌پال',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
            'merchant_id' => 'm1',
        ]);
        PaymentGateway::query()->create([
            'name' => 'saman',
            'display_name' => 'سامان',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 2,
            'merchant_id' => 'term-1',
        ]);

        $list = $this->getJson('/api/v1/admin/payment-gateways');
        $list->assertOk();
        $this->assertNotEmpty($list->json('data.gateways'));

        $this->postJson('/api/v1/admin/payment-gateways/default', ['name' => 'saman'])
            ->assertOk();

        $this->assertTrue(
            (bool) PaymentGateway::query()->where('name', 'saman')->value('is_default')
        );
        $this->assertSame('saman', Setting::get('payment_gateway'));
    }

    public function test_cannot_default_inactive_gateway(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        PaymentGateway::query()->create([
            'name' => 'zarinpal',
            'display_name' => 'زرین‌پال',
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
            'merchant_id' => 'm1',
        ]);
        PaymentGateway::query()->create([
            'name' => 'parsian',
            'display_name' => 'پارسیان',
            'is_active' => false,
            'is_default' => false,
            'sort_order' => 2,
            'merchant_id' => 'pin-1',
        ]);

        $this->postJson('/api/v1/admin/payment-gateways/default', ['name' => 'parsian'])
            ->assertStatus(422);
    }
}
