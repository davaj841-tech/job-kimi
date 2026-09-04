<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SadadGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'sadad';
    }

    public function getDisplayName(): string
    {
        return 'سداد (بانک ملی)';
    }

    public function requiredCredentialKeys(): array
    {
        return ['merchant_id', 'terminal_id', 'terminal_key'];
    }

    protected function merchantId(): string
    {
        return $this->credential('merchant_id');
    }

    protected function terminalId(): string
    {
        return $this->credential('terminal_id');
    }

    protected function terminalKey(): string
    {
        return $this->credential('terminal_key');
    }

    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array
    {
        $merchantId = $this->merchantId();
        $terminalId = $this->terminalId();
        $terminalKey = $this->terminalKey();

        if (blank($merchantId) || blank($terminalId) || blank($terminalKey)) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'اطلاعات اتصال این درگاه کامل نشده است.'];
        }

        $orderId = (string) ($meta['order_id'] ?? (string) time());
        $signData = $this->encrypt("{$terminalId};{$orderId};{$amount}", $terminalKey);
        if ($signData === null) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'کلید ترمینال سداد نامعتبر است.'];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(30)
                ->post('https://sadad.shaparak.ir/api/v0/Request/PaymentRequest', [
                    'MerchantId' => $merchantId,
                    'TerminalId' => $terminalId,
                    'Amount' => $amount,
                    'OrderId' => $orderId,
                    'LocalDateTime' => now()->format('Y/m/d H:i:s'),
                    'ReturnUrl' => $callbackUrl,
                    'SignData' => $signData,
                    'AdditionalData' => mb_substr($description, 0, 100),
                ]);

            $resCode = (string) ($response->json('ResCode') ?? '');
            $token = (string) ($response->json('Token') ?? '');

            if ($resCode !== '0' || $token === '') {
                Log::warning('Sadad request failed', ['http' => $response->status(), 'code' => $resCode]);

                return [
                    'authority' => null,
                    'payment_url' => null,
                    'error' => (string) ($response->json('Description') ?? 'خطا در ایجاد درخواست سداد.'),
                ];
            }

            return [
                'authority' => $token,
                'payment_url' => 'https://sadad.shaparak.ir/VPG/Purchase?Token='.urlencode($token),
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Sadad request exception', ['error' => $e->getMessage()]);

            return ['authority' => null, 'payment_url' => null, 'error' => 'خطا در اتصال به درگاه سداد.'];
        }
    }

    public function verify(string $authority, int $amount, array $meta = []): array
    {
        $terminalKey = $this->terminalKey();
        if (blank($terminalKey) || $authority === '') {
            return ['success' => false, 'ref_id' => null, 'error' => 'اطلاعات اتصال این درگاه کامل نشده است.'];
        }

        $signData = $this->encrypt($authority, $terminalKey);
        if ($signData === null) {
            return ['success' => false, 'ref_id' => null, 'error' => 'کلید ترمینال سداد نامعتبر است.'];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(30)
                ->post('https://sadad.shaparak.ir/api/v0/Advice/Verify', [
                    'Token' => $authority,
                    'SignData' => $signData,
                ]);

            $resCode = (string) ($response->json('ResCode') ?? '');
            if ($resCode !== '0') {
                return ['success' => false, 'ref_id' => null, 'error' => 'تأیید پرداخت سداد ناموفق بود.'];
            }

            $paid = $response->json('Amount') ?? data_get($response->json(), 'VerifyInfo.Amount');
            if ($paid !== null && (int) $paid !== $amount) {
                return ['success' => false, 'ref_id' => null, 'error' => 'مبلغ پرداخت با تراکنش همخوانی ندارد'];
            }

            return [
                'success' => true,
                'ref_id' => (string) ($response->json('RetrivalRefNo') ?? $response->json('SystemTraceNo') ?? $authority),
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Sadad verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'خطا در تأیید پرداخت سداد.'];
        }
    }

    protected function encrypt(string $data, string $key): ?string
    {
        $decoded = base64_decode($key, true);
        if ($decoded === false || $decoded === '') {
            $decoded = $key;
        }

        $encrypted = openssl_encrypt($data, 'AES-128-CBC', $decoded, OPENSSL_RAW_DATA, $decoded);
        if ($encrypted === false) {
            return null;
        }

        return base64_encode($encrypted);
    }
}
