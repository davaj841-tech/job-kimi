<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Payment\ZarinPalGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;

class PaymentSandboxCheckCommand extends Command
{
    protected $signature = 'payment:sandbox-check
                            {--ping : Call sandbox request.json (no real payment completion)}';

    protected $description = 'Validate ZarinPal sandbox configuration and endpoint routing (never prints secrets)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->warn('Running in production — this command is intended for sandbox/staging only.');

            return self::FAILURE;
        }

        $sandbox = $this->isSandbox();
        $fake = (bool) config('payment.fake', false);
        $merchant = $this->merchantId();
        $masked = $this->maskMerchant($merchant);

        $this->table(['Check', 'Value'], [
            ['APP_ENV', app()->environment()],
            ['ZARINPAL_SANDBOX', $sandbox ? 'true' : 'false'],
            ['PAYMENT_FAKE', $fake ? 'true (tests only)' : 'false'],
            ['Merchant ID', $masked],
            ['API base (resolved)', $this->resolveApiBase()],
        ]);

        if (! $sandbox) {
            $this->error('ZARINPAL_SANDBOX is not true — enable sandbox before integration testing.');

            return self::FAILURE;
        }

        if ($fake) {
            $this->error('PAYMENT_FAKE=true — disable for real sandbox integration.');

            return self::FAILURE;
        }

        if (blank($merchant)) {
            $this->error('ZARINPAL_MERCHANT_ID (or admin zarinpal_merchant_id) is not configured.');
            $this->line('Set sandbox merchant in .env then run: php artisan config:clear');

            return self::FAILURE;
        }

        $this->info('Configuration: PASS');

        if (! $this->option('ping')) {
            $this->line('Use --ping to test sandbox API reachability (creates a request, does not complete payment).');

            return self::SUCCESS;
        }

        return $this->pingSandbox($merchant);
    }

    protected function isSandbox(): bool
    {
        $fromSetting = Setting::getFilled('zarinpal_sandbox', null);
        if ($fromSetting !== null && $fromSetting !== '') {
            return filter_var($fromSetting, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('services.zarinpal.sandbox', false);
    }

    protected function merchantId(): string
    {
        $fromSetting = Setting::getFilled('zarinpal_merchant_id', config('services.zarinpal.merchant_id'));

        return filled($fromSetting) ? (string) $fromSetting : '';
    }

    protected function maskMerchant(string $merchant): string
    {
        if ($merchant === '') {
            return '(not configured)';
        }

        if (strlen($merchant) <= 8) {
            return str_repeat('*', strlen($merchant));
        }

        return str_repeat('*', max(8, strlen($merchant) - 4)).substr($merchant, -4);
    }

    protected function resolveApiBase(): string
    {
        $gateway = app(ZarinPalGateway::class);
        $method = new ReflectionMethod($gateway, 'apiBase');
        $method->setAccessible(true);

        return (string) $method->invoke($gateway);
    }

    protected function pingSandbox(string $merchantId): int
    {
        $base = rtrim((string) config('services.zarinpal.sandbox_base_url', 'https://sandbox.zarinpal.com'), '/');
        $url = $base.'/pg/v4/payment/request.json';

        $this->info('Pinging: '.$url.' (sandbox only)');

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(15)
                ->post($url, [
                    'merchant_id' => $merchantId,
                    'amount' => 100000,
                    'callback_url' => url('/payment/wallet'),
                    'description' => 'JobAzmoon sandbox connectivity check',
                    'currency' => 'IRR',
                ]);

            $code = $response->json('data.code');
            $authority = $response->json('data.authority');

            $this->table(['Field', 'Value'], [
                ['HTTP', (string) $response->status()],
                ['data.code', (string) ($code ?? '-')],
                ['authority received', $authority ? 'yes (masked)' : 'no'],
            ]);

            if ($response->successful() && $authority) {
                $this->info('Sandbox API request.json: PASS');
                $this->warn('Payment was NOT completed — only authority request was tested.');

                return self::SUCCESS;
            }

            $this->error('Sandbox API request.json: FAIL — check merchant ID and sandbox mode.');

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Sandbox API unreachable: '.class_basename($e));

            return self::FAILURE;
        }
    }
}
