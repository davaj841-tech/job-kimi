<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $zarinpalMerchant = Setting::get('zarinpal_merchant_id', '');

        PaymentGateway::query()->updateOrCreate(
            ['name' => 'zarinpal'],
            [
                'display_name' => 'زرین‌پال',
                'merchant_id' => $zarinpalMerchant ?: null,
                'api_key' => null,
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ]
        );

        PaymentGateway::query()->updateOrCreate(
            ['name' => 'nextpay'],
            [
                'display_name' => 'نکست‌پی',
                'merchant_id' => null,
                'api_key' => Setting::get('nextpay_api_key', '') ?: null,
                'is_active' => filter_var(Setting::get('nextpay_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => false,
                'sort_order' => 2,
            ]
        );

        PaymentGateway::query()->updateOrCreate(
            ['name' => 'idpay'],
            [
                'display_name' => 'آیدی‌پی',
                'merchant_id' => null,
                'api_key' => Setting::get('idpay_api_key', '') ?: null,
                'is_active' => filter_var(Setting::get('idpay_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => false,
                'sort_order' => 3,
            ]
        );
    }
}
