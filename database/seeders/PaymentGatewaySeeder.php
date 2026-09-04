<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'name' => 'zarinpal',
                'display_name' => 'زرین‌پال',
                'merchant_id' => Setting::get('zarinpal_merchant_id', '') ?: null,
                'api_key' => null,
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'nextpay',
                'display_name' => 'نکست‌پی',
                'merchant_id' => null,
                'api_key' => Setting::get('nextpay_api_key', '') ?: null,
                'is_active' => filter_var(Setting::get('nextpay_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'idpay',
                'display_name' => 'آیدی‌پی',
                'merchant_id' => null,
                'api_key' => Setting::get('idpay_api_key', '') ?: null,
                'is_active' => filter_var(Setting::get('idpay_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'mellat',
                'display_name' => 'بانک ملت (به‌پرداخت)',
                'merchant_id' => Setting::get('mellat_terminal_id', '') ?: null,
                'api_key' => Setting::get('mellat_username', '') ?: null,
                'is_active' => filter_var(Setting::get('mellat_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => false,
                'sort_order' => 4,
                'settings' => array_filter([
                    'password' => Setting::get('mellat_password', '') ?: null,
                ]),
            ],
            [
                'name' => 'shaparak',
                'display_name' => 'شاپرک (به‌پرداخت)',
                'merchant_id' => Setting::get('shaparak_terminal_id', Setting::get('shaparak_merchant_id', '')) ?: null,
                'api_key' => Setting::get('shaparak_username', '') ?: null,
                'is_active' => filter_var(Setting::get('shaparak_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => false,
                'sort_order' => 5,
                'settings' => array_filter([
                    'password' => Setting::get('shaparak_password', '') ?: null,
                ]),
            ],
            [
                'name' => 'parsian',
                'display_name' => 'پارسیان',
                'merchant_id' => Setting::get('parsian_pin', '') ?: null,
                'api_key' => null,
                'is_active' => filter_var(Setting::get('parsian_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => false,
                'sort_order' => 6,
            ],
            [
                'name' => 'saman',
                'display_name' => 'سامان (SEP)',
                'merchant_id' => Setting::get('saman_terminal_id', '') ?: null,
                'api_key' => null,
                'is_active' => filter_var(Setting::get('saman_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => false,
                'sort_order' => 7,
            ],
            [
                'name' => 'pasargad',
                'display_name' => 'پاسارگاد',
                'merchant_id' => Setting::get('pasargad_merchant_code', '') ?: null,
                'api_key' => null,
                'is_active' => filter_var(Setting::get('pasargad_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => false,
                'sort_order' => 8,
            ],
            [
                'name' => 'ap',
                'display_name' => 'آسان‌پرداخت (AP)',
                'merchant_id' => Setting::get('ap_merchant_config_id', '') ?: null,
                'api_key' => Setting::get('ap_username', '') ?: null,
                'is_active' => filter_var(Setting::get('ap_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => false,
                'sort_order' => 9,
            ],
            [
                'name' => 'sadad',
                'display_name' => 'سداد (بانک ملی)',
                'merchant_id' => Setting::get('sadad_merchant_id', '') ?: null,
                'api_key' => null,
                'is_active' => filter_var(Setting::get('sadad_active', 'false'), FILTER_VALIDATE_BOOLEAN),
                'is_default' => false,
                'sort_order' => 10,
            ],
        ];

        foreach ($catalog as $item) {
            $settings = $item['settings'] ?? null;
            unset($item['settings']);
            if (is_array($settings) && $settings !== []) {
                $item['settings'] = $settings;
            }

            PaymentGateway::query()->updateOrCreate(
                ['name' => $item['name']],
                $item
            );
        }

        $default = (string) Setting::get('payment_gateway', 'zarinpal');
        if ($default !== '') {
            PaymentGateway::query()->where('name', '!=', $default)->update(['is_default' => false]);
            PaymentGateway::query()->where('name', $default)->update(['is_default' => true, 'is_active' => true]);
        }
    }
}
