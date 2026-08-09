<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZarinPalGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'zarinpal';
    }

    public function getDisplayName(): string
    {
        return 'زرین‌پال';
    }

    protected function baseUrl(): string
    {
        $sandbox = filter_var(Setting::get('zarinpal_sandbox', 'true'), FILTER_VALIDATE_BOOLEAN);

        return $sandbox
            ? 'https://sandbox.zarinpal.com'
            : 'https://www.zarinpal.com';
    }

    protected function merchantId(): string
    {
        return (string) (Setting::get('zarinpal_merchant_id')
            ?: optional(\App\Models\PaymentGateway::query()->where('name', 'zarinpal')->first())->merchant_id
            ?: '');
    }

    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array
    {
        $merchantId = $this->merchantId();

        if (blank($merchantId)) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'merchant_id زرین‌پال تنظیم نشده است.'];
        }

        try {
            $response = Http::timeout(30)->post($this->baseUrl().'/pg/v4/payment/request.json', [
                'merchant_id' => $merchantId,
                'amount' => $amount,
                'callback_url' => $callbackUrl,
                'description' => $description,
                'currency' => 'IRR',
            ]);

            $data = $response->json('data') ?? [];
            $authority = $data['authority'] ?? null;
            $code = $data['code'] ?? null;

            if (! $response->successful() || ! $authority || (int) $code !== 100) {
                $error = $response->json('errors.message')
                    ?? data_get($response->json(), 'errors.0.message')
                    ?? 'خطا در ایجاد درخواست پرداخت.';

                Log::warning('ZarinPal request failed', ['body' => $response->body()]);

                return ['authority' => null, 'payment_url' => null, 'error' => is_string($error) ? $error : 'خطا در ایجاد درخواست پرداخت.'];
            }

            return [
                'authority' => $authority,
                'payment_url' => $this->baseUrl().'/pg/StartPay/'.$authority,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('ZarinPal request exception', ['error' => $e->getMessage()]);

            return ['authority' => null, 'payment_url' => null, 'error' => 'ارتباط با درگاه پرداخت برقرار نشد.'];
        }
    }

    public function verify(string $authority, int $amount, array $meta = []): array
    {
        $merchantId = $this->merchantId();

        if (blank($merchantId)) {
            return ['success' => false, 'ref_id' => null, 'error' => 'merchant_id زرین‌پال تنظیم نشده است.'];
        }

        try {
            $response = Http::timeout(30)->post($this->baseUrl().'/pg/v4/payment/verify.json', [
                'merchant_id' => $merchantId,
                'amount' => $amount,
                'authority' => $authority,
            ]);

            $data = $response->json('data') ?? [];
            $code = (int) ($data['code'] ?? 0);

            if (in_array($code, [100, 101], true)) {
                return [
                    'success' => true,
                    'ref_id' => isset($data['ref_id']) ? (string) $data['ref_id'] : null,
                    'error' => null,
                ];
            }

            return ['success' => false, 'ref_id' => null, 'error' => 'پرداخت ناموفق بود'];
        } catch (\Throwable $e) {
            Log::error('ZarinPal verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'ارتباط با درگاه پرداخت برقرار نشد.'];
        }
    }
}
