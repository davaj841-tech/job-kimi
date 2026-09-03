<?php

/**
 * One-shot diagnostic — run: php scripts/zarinpal-422-diagnose.php
 * Never prints full merchant_id or secrets.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PaymentGateway;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;

function mask(string $v): string
{
    if ($v === '') {
        return '(empty)';
    }
    if (strlen($v) <= 8) {
        return str_repeat('*', strlen($v));
    }

    return str_repeat('*', max(8, strlen($v) - 4)).substr($v, -4);
}

function merchantFormat(string $id): string
{
    if ($id === '' || str_contains(strtoupper($id), 'YOUR_') || str_contains(strtoupper($id), 'PLACEHOLDER')) {
        return 'INVALID (placeholder or empty)';
    }
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
        return 'VALID (UUID)';
    }
    if (preg_match('/^[0-9a-f]{32}$/i', $id)) {
        return 'VALID (32-char hex)';
    }

    return 'INVALID (not UUID/hex merchant format)';
}

$envMerchant = (string) env('ZARINPAL_MERCHANT_ID', '');
$configMerchant = (string) config('services.zarinpal.merchant_id', '');
$settingMerchant = Setting::get('zarinpal_merchant_id');
$dbGatewayMerchant = PaymentGateway::query()->where('name', 'zarinpal')->value('merchant_id');
$effective = (string) Setting::getFilled('zarinpal_merchant_id', config('services.zarinpal.merchant_id'));
if (! filled($effective)) {
    $effective = filled($dbGatewayMerchant) ? (string) $dbGatewayMerchant : '';
}

echo "=== ZarinPal 422 Diagnostic ===\n\n";
echo "Merchant configured: ".(filled($effective) ? 'YES' : 'NO')."\n";
echo 'Merchant format: '.merchantFormat($effective)."\n";
echo 'Merchant masked: '.mask($effective)."\n\n";

echo "Config sources (masked effective values):\n";
echo '  .env ZARINPAL_MERCHANT_ID: '.mask($envMerchant)."\n";
echo '  config(services.zarinpal.merchant_id): '.mask($configMerchant)."\n";
echo '  Setting zarinpal_merchant_id: '.mask((string) ($settingMerchant ?? ''))."\n";
echo '  payment_gateways.merchant_id: '.mask((string) ($dbGatewayMerchant ?? ''))."\n";
echo '  RESOLVED (Setting->env->DB): '.mask($effective)."\n\n";

$sandbox = filter_var(
    Setting::getFilled('zarinpal_sandbox', config('services.zarinpal.sandbox')),
    FILTER_VALIDATE_BOOLEAN
);
$base = rtrim((string) config('services.zarinpal.sandbox_base_url', 'https://sandbox.zarinpal.com'), '/');
$url = $base.'/pg/v4/payment/request.json';
$callback = url('/payment/wallet');
$amount = 100000;

$payload = [
    'merchant_id' => $effective,
    'amount' => $amount,
    'callback_url' => $callback,
    'description' => 'JobAzmoon sandbox connectivity check',
    'currency' => (string) config('services.zarinpal.currency', 'IRR'),
];

echo "Sandbox: ".($sandbox ? 'ON' : 'OFF')."\n";
echo "Fake payment: ".(config('payment.fake') ? 'ON' : 'OFF')."\n";
echo "Endpoint: {$url}\n\n";

echo "Request audit:\n";
echo "  Method: POST\n";
echo "  Content-Type: application/json\n";
echo "  Accept: application/json\n";
echo "  Timeout: 15s\n";
echo "  amount: {$amount} (integer, IRR)\n";
echo "  currency: {$payload['currency']}\n";
echo '  callback_url: '.$callback."\n";
echo '  merchant_id: '.mask($effective)." (masked)\n";
echo "  metadata: (none in ping)\n\n";

try {
    $response = Http::acceptJson()->asJson()->timeout(15)->post($url, $payload);
    $body = $response->json();

    echo "Response:\n";
    echo '  HTTP: '.$response->status()."\n";

    $dataCode = data_get($body, 'data.code');
    $errors = data_get($body, 'errors');
    $errMsg = data_get($body, 'errors.message') ?? data_get($body, 'errors.0.message');
    $errCode = data_get($body, 'errors.code') ?? data_get($body, 'errors.0.code');

    echo '  data.code: '.($dataCode ?? '-')."\n";
    echo '  errors.code: '.($errCode ?? '-')."\n";
    echo '  errors.message: '.($errMsg ?? '-')."\n";

    if (is_array($errors) && $errMsg === null) {
        echo '  errors (sanitized): '.json_encode($errors, JSON_UNESCAPED_UNICODE)."\n";
    }

    $authority = data_get($body, 'data.authority');
    echo '  authority: '.($authority ? 'received' : 'no')."\n";
} catch (Throwable $e) {
    echo '  Exception: '.class_basename($e).': '.$e->getMessage()."\n";
}

echo "\nProduction isolation check:\n";
echo '  request URL contains sandbox.zarinpal.com: '.(str_contains($url, 'sandbox.zarinpal.com') ? 'YES' : 'NO')."\n";
