<?php

namespace App\Services\Payment;

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

    protected function sandbox(): bool
    {
        return (bool) config('services.zarinpal.sandbox', false);
    }

    protected function apiBase(): string
    {
        return $this->sandbox()
            ? 'https://sandbox.zarinpal.com'
            : 'https://payment.zarinpal.com';
    }

    protected function startPayBase(): string
    {
        return $this->apiBase();
    }

    protected function merchantId(): string
    {
        return (string) config('services.zarinpal.merchant_id', '');
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function metadata(array $meta): array
    {
        $out = [];
        $mobile = preg_replace('/\D+/', '', (string) ($meta['mobile'] ?? '')) ?: null;
        if ($mobile && strlen($mobile) === 10) {
            $mobile = '0'.$mobile;
        }
        if ($mobile && preg_match('/^09\d{9}$/', $mobile)) {
            $out['mobile'] = $mobile;
        }
        $email = trim((string) ($meta['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $out['email'] = $email;
        }
        if (! empty($meta['order_id'])) {
            $out['order_id'] = (string) $meta['order_id'];
        }

        return $out;
    }

    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array
    {
        $merchantId = $this->merchantId();

        if (blank($merchantId)) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'merchant_id زرین‌پال تنظیم نشده است.'];
        }

        $payload = [
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'callback_url' => $callbackUrl,
            'description' => mb_substr($description, 0, 500),
            'currency' => 'IRR',
        ];
        $metadata = $this->metadata($meta);
        if ($metadata !== []) {
            $payload['metadata'] = $metadata;
        }

        try {
            $response = Http::acceptJson()->asJson()->timeout(30)
                ->post($this->apiBase().'/pg/v4/payment/request.json', $payload);

            $data = $response->json('data') ?? [];
            $authority = $data['authority'] ?? null;
            $code = $data['code'] ?? null;

            if (! $response->successful() || ! $authority || (int) $code !== 100) {
                $error = $response->json('errors.message')
                    ?? data_get($response->json(), 'errors.0.message')
                    ?? 'خطا در ایجاد درخواست پرداخت.';

                Log::warning('ZarinPal request failed', [
                    'http' => $response->status(),
                    'code' => $code,
                ]);

                return ['authority' => null, 'payment_url' => null, 'error' => is_string($error) ? $error : 'خطا در ایجاد درخواست پرداخت.'];
            }

            return [
                'authority' => $authority,
                'payment_url' => $this->startPayBase().'/pg/StartPay/'.$authority,
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
            $response = Http::acceptJson()->asJson()->timeout(30)->post($this->apiBase().'/pg/v4/payment/verify.json', [
                'merchant_id' => $merchantId,
                'amount' => $amount,
                'authority' => $authority,
            ]);

            $data = $response->json('data') ?? [];
            $code = (int) ($data['code'] ?? 0);
            $paid = $data['amount'] ?? null;
            if ($paid !== null && (int) $paid !== $amount) {
                Log::warning('ZarinPal verify amount mismatch', [
                    'expected' => $amount,
                ]);

                return ['success' => false, 'ref_id' => null, 'error' => 'مبلغ پرداخت با تراکنش همخوانی ندارد'];
            }

            if (in_array($code, [100, 101], true)) {
                return [
                    'success' => true,
                    'ref_id' => isset($data['ref_id']) ? (string) $data['ref_id'] : null,
                    'error' => null,
                ];
            }

            $message = $response->json('errors.message')
                ?? data_get($response->json(), 'errors.0.message')
                ?? 'پرداخت ناموفق بود';

            return ['success' => false, 'ref_id' => null, 'error' => is_string($message) ? $message : 'پرداخت ناموفق بود'];
        } catch (\Throwable $e) {
            Log::error('ZarinPal verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'ارتباط با درگاه پرداخت برقرار نشد.'];
        }
    }
}
