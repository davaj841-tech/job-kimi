<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NextPayGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'nextpay';
    }

    public function getDisplayName(): string
    {
        return 'نکست‌پی';
    }

    protected function apiKey(): string
    {
        return (string) (Setting::getFilled('nextpay_api_key')
            ?: optional(PaymentGateway::query()->where('name', 'nextpay')->first())->api_key
            ?: config('services.nextpay.api_key')
            ?: '');
    }

    /** NextPay expects amount in Tomans */
    protected function toToman(int $rial): int
    {
        return max(1, (int) floor($rial / 10));
    }

    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array
    {
        $apiKey = $this->apiKey();
        if (blank($apiKey)) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'کلید API نکست‌پی تنظیم نشده است.'];
        }

        $orderId = (string) ($meta['order_id'] ?? uniqid('np_', true));
        $toman = $this->toToman($amount);

        try {
            $response = Http::asForm()->timeout(30)->post('https://nextpay.org/nx/gateway/token', [
                'api_key' => $apiKey,
                'order_id' => $orderId,
                'amount' => $toman,
                'callback_uri' => $callbackUrl,
            ]);

            $code = (int) ($response->json('code') ?? -1);
            $transId = $response->json('trans_id');

            if ($code !== 0 || blank($transId)) {
                Log::warning('NextPay request failed', ['body' => $response->body()]);

                return [
                    'authority' => null,
                    'payment_url' => null,
                    'error' => $response->json('message') ?? 'خطا در ایجاد درخواست نکست‌پی.',
                ];
            }

            return [
                'authority' => (string) $transId,
                'payment_url' => 'https://nextpay.org/nx/gateway/payment/'.$transId,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('NextPay request exception', ['error' => $e->getMessage()]);

            return ['authority' => null, 'payment_url' => null, 'error' => 'ارتباط با نکست‌پی برقرار نشد.'];
        }
    }

    public function verify(string $authority, int $amount, array $meta = []): array
    {
        $apiKey = $this->apiKey();
        if (blank($apiKey)) {
            return ['success' => false, 'ref_id' => null, 'error' => 'کلید API نکست‌پی تنظیم نشده است.'];
        }

        try {
            $response = Http::asForm()->timeout(30)->post('https://nextpay.org/nx/gateway/verify', [
                'api_key' => $apiKey,
                'trans_id' => $authority,
                'amount' => $this->toToman($amount),
            ]);

            $code = (int) ($response->json('code') ?? -1);

            if ($code === 0) {
                return [
                    'success' => true,
                    'ref_id' => (string) ($response->json('Shaparak_Ref_Id') ?? $authority),
                    'error' => null,
                ];
            }

            Log::warning('NextPay verify failed', ['body' => $response->body()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'پرداخت نکست‌پی ناموفق بود'];
        } catch (\Throwable $e) {
            Log::error('NextPay verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'ارتباط با نکست‌پی برقرار نشد.'];
        }
    }
}
