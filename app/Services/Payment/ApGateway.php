<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'ap';
    }

    public function getDisplayName(): string
    {
        return 'آسان‌پرداخت (AP)';
    }

    public function requiredCredentialKeys(): array
    {
        return ['username', 'password', 'merchant_config_id'];
    }

    protected function username(): string
    {
        return $this->credential('username');
    }

    protected function password(): string
    {
        return $this->credential('password');
    }

    protected function merchantConfigId(): string
    {
        return $this->credential('merchant_config_id');
    }

    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array
    {
        $username = $this->username();
        $password = $this->password();
        $merchantConfigId = $this->merchantConfigId();

        if (blank($username) || blank($password) || blank($merchantConfigId)) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'اطلاعات اتصال این درگاه کامل نشده است.'];
        }

        $localInvoiceId = (string) ($meta['order_id'] ?? uniqid('ap_', true));

        try {
            $tokenResponse = Http::withBasicAuth($username, $password)
                ->acceptJson()
                ->timeout(30)
                ->get('https://ipgrest.asanpardakht.com/v1/Token');

            $token = (string) ($tokenResponse->json('token') ?? $tokenResponse->body());
            if (! $tokenResponse->successful() || blank($token) || str_contains($token, '{')) {
                Log::warning('AP token failed', ['http' => $tokenResponse->status()]);

                return ['authority' => null, 'payment_url' => null, 'error' => 'خطا در دریافت توکن آسان‌پرداخت.'];
            }

            $response = Http::withToken(trim($token, "\" \t\n\r"))
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post('https://ipgrest.asanpardakht.com/v1/Payment/GetToken', [
                    'merchantConfigurationId' => (int) $merchantConfigId,
                    'serviceTypeId' => 1,
                    'localInvoiceId' => $localInvoiceId,
                    'amountInRials' => $amount,
                    'localDate' => now()->format('Ymd His'),
                    'callbackURL' => $callbackUrl,
                    'paymentId' => 0,
                    'additionalData' => mb_substr($description, 0, 100),
                ]);

            $refId = (string) ($response->json() ?? $response->body());
            $refId = trim($refId, "\" \t\n\r");

            if (! $response->successful() || blank($refId) || str_starts_with($refId, '{')) {
                Log::warning('AP request failed', ['http' => $response->status()]);

                return ['authority' => null, 'payment_url' => null, 'error' => 'خطا در ایجاد درخواست آسان‌پرداخت.'];
            }

            return [
                'authority' => $refId,
                'payment_url' => 'https://asan.shaparak.ir/?RefId='.urlencode($refId),
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('AP request exception', ['error' => $e->getMessage()]);

            return ['authority' => null, 'payment_url' => null, 'error' => 'خطا در اتصال به درگاه آسان‌پرداخت.'];
        }
    }

    public function verify(string $authority, int $amount, array $meta = []): array
    {
        $username = $this->username();
        $password = $this->password();
        $merchantConfigId = $this->merchantConfigId();
        $payGateTranId = (string) ($meta['sale_reference_id'] ?? $authority);

        if (blank($username) || blank($password) || blank($merchantConfigId) || $payGateTranId === '') {
            return ['success' => false, 'ref_id' => null, 'error' => 'اطلاعات اتصال این درگاه کامل نشده است.'];
        }

        try {
            $tokenResponse = Http::withBasicAuth($username, $password)
                ->acceptJson()
                ->timeout(30)
                ->get('https://ipgrest.asanpardakht.com/v1/Token');

            $token = trim((string) ($tokenResponse->json('token') ?? $tokenResponse->body()), "\" \t\n\r");
            if (! $tokenResponse->successful() || blank($token)) {
                return ['success' => false, 'ref_id' => null, 'error' => 'خطا در دریافت توکن آسان‌پرداخت.'];
            }

            $verify = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post('https://ipgrest.asanpardakht.com/v1/Payment/Verify', [
                    'merchantConfigurationId' => (int) $merchantConfigId,
                    'payGateTranId' => $payGateTranId,
                ]);

            if (! $verify->successful()) {
                return ['success' => false, 'ref_id' => null, 'error' => 'تأیید پرداخت آسان‌پرداخت ناموفق بود.'];
            }

            try {
                Http::withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout(30)
                    ->post('https://ipgrest.asanpardakht.com/v1/Payment/Settlement', [
                        'merchantConfigurationId' => (int) $merchantConfigId,
                        'payGateTranId' => $payGateTranId,
                    ]);
            } catch (Throwable) {
            }

            return ['success' => true, 'ref_id' => $payGateTranId, 'error' => null];
        } catch (Throwable $e) {
            Log::error('AP verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'خطا در تأیید پرداخت آسان‌پرداخت.'];
        }
    }
}
