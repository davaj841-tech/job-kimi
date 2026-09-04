<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IdPayGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'idpay';
    }

    public function getDisplayName(): string
    {
        return 'آیدی‌پی';
    }

    public function requiredCredentialKeys(): array
    {
        return ['api_key'];
    }

    protected function apiKey(): string
    {
        return $this->credential('api_key');
    }

    protected function sandbox(): bool
    {
        $fromSetting = Setting::getFilled('idpay_sandbox', null);
        if ($fromSetting !== null && $fromSetting !== '') {
            return filter_var($fromSetting, FILTER_VALIDATE_BOOLEAN);
        }

        $fromCred = $this->credential('sandbox');
        if ($fromCred !== '') {
            return filter_var($fromCred, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('services.idpay.sandbox', false);
    }

    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array
    {
        $apiKey = $this->apiKey();
        if (blank($apiKey)) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'اطلاعات اتصال این درگاه کامل نشده است.'];
        }

        $orderId = (string) ($meta['order_id'] ?? uniqid('idp_', true));

        try {
            $headers = [
                'X-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
            ];
            if ($this->sandbox()) {
                $headers['X-SANDBOX'] = '1';
            }

            $response = Http::withHeaders($headers)->timeout(30)->post('https://api.idpay.ir/v1.1/payment', [
                'order_id' => $orderId,
                'amount' => $amount,
                'callback' => $callbackUrl,
                'desc' => $description,
            ]);

            $id = $response->json('id');
            $link = $response->json('link');

            if (! $response->successful() || blank($id) || blank($link)) {
                Log::warning('IDPay request failed', ['http' => $response->status()]);

                return [
                    'authority' => null,
                    'payment_url' => null,
                    'error' => $response->json('error_message') ?? 'خطا در ایجاد درخواست آیدی‌پی.',
                ];
            }

            return [
                'authority' => (string) $id,
                'payment_url' => (string) $link,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('IDPay request exception', ['error' => $e->getMessage()]);

            return ['authority' => null, 'payment_url' => null, 'error' => 'ارتباط با آیدی‌پی برقرار نشد.'];
        }
    }

    public function verify(string $authority, int $amount, array $meta = []): array
    {
        $apiKey = $this->apiKey();
        if (blank($apiKey)) {
            return ['success' => false, 'ref_id' => null, 'error' => 'اطلاعات اتصال این درگاه کامل نشده است.'];
        }

        $orderId = (string) ($meta['order_id'] ?? '');
        if ($orderId === '') {
            return ['success' => false, 'ref_id' => null, 'error' => 'order_id آیدی‌پی نامعتبر است.'];
        }

        try {
            $headers = [
                'X-API-KEY' => $apiKey,
                'Content-Type' => 'application/json',
            ];
            if ($this->sandbox()) {
                $headers['X-SANDBOX'] = '1';
            }

            $response = Http::withHeaders($headers)->timeout(30)->post('https://api.idpay.ir/v1.1/payment/verify', [
                'id' => $authority,
                'order_id' => $orderId,
            ]);

            if ($response->successful() && (int) $response->json('status') === 100) {
                $paid = $response->json('amount') ?? $response->json('payment.amount');
                if ($paid !== null && (int) $paid !== $amount) {
                    Log::warning('IDPay verify amount mismatch', ['expected' => $amount]);

                    return ['success' => false, 'ref_id' => null, 'error' => 'مبلغ پرداخت با تراکنش همخوانی ندارد'];
                }

                return [
                    'success' => true,
                    'ref_id' => (string) ($response->json('track_id') ?? $response->json('payment.track_id') ?? $authority),
                    'error' => null,
                ];
            }

            Log::warning('IDPay verify failed', ['http' => $response->status()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'پرداخت آیدی‌پی ناموفق بود'];
        } catch (\Throwable $e) {
            Log::error('IDPay verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'ارتباط با آیدی‌پی برقرار نشد.'];
        }
    }
}
