<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PasargadGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'pasargad';
    }

    public function getDisplayName(): string
    {
        return 'پاسارگاد';
    }

    public function requiredCredentialKeys(): array
    {
        return ['merchant_code', 'terminal_code', 'private_key'];
    }

    protected function merchantCode(): string
    {
        return $this->credential('merchant_code');
    }

    protected function terminalCode(): string
    {
        return $this->credential('terminal_code');
    }

    protected function privateKey(): string
    {
        return $this->credential('private_key');
    }

    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array
    {
        $merchant = $this->merchantCode();
        $terminal = $this->terminalCode();
        $privateKey = $this->privateKey();

        if (blank($merchant) || blank($terminal) || blank($privateKey)) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'اطلاعات اتصال این درگاه کامل نشده است.'];
        }

        $invoiceNumber = (string) ($meta['order_id'] ?? (string) time());
        $invoiceDate = now()->format('Y/m/d H:i:s');
        $timestamp = now()->format('Y/m/d H:i:s');
        $action = '1003';

        $signData = "#{$merchant}#{$terminal}#{$invoiceNumber}#{$invoiceDate}#{$amount}#{$callbackUrl}#{$action}#{$timestamp}#";
        $sign = $this->sign($signData, $privateKey);
        if ($sign === null) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'امضای دیجیتال پاسارگاد نامعتبر است.'];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(30)
                ->post('https://pep.shaparak.ir/Api/v1/Payment/GetToken', [
                    'InvoiceNumber' => $invoiceNumber,
                    'InvoiceDate' => $invoiceDate,
                    'TerminalCode' => $terminal,
                    'MerchantCode' => $merchant,
                    'Amount' => $amount,
                    'RedirectAddress' => $callbackUrl,
                    'Timestamp' => $timestamp,
                    'Action' => $action,
                    'Sign' => $sign,
                ]);

            $token = (string) ($response->json('Token') ?? '');
            $isSuccess = (bool) ($response->json('IsSuccess') ?? false);

            if (! $isSuccess || $token === '') {
                Log::warning('Pasargad request failed', ['http' => $response->status()]);

                return [
                    'authority' => null,
                    'payment_url' => null,
                    'error' => (string) ($response->json('Message') ?? 'خطا در ایجاد درخواست پاسارگاد.'),
                ];
            }

            return [
                'authority' => $token,
                'payment_url' => 'https://pep.shaparak.ir/payment.aspx?n='.urlencode($token),
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Pasargad request exception', ['error' => $e->getMessage()]);

            return ['authority' => null, 'payment_url' => null, 'error' => 'خطا در اتصال به درگاه پاسارگاد.'];
        }
    }

    public function verify(string $authority, int $amount, array $meta = []): array
    {
        $merchant = $this->merchantCode();
        $terminal = $this->terminalCode();
        $invoiceNumber = (string) ($meta['order_id'] ?? '');
        $invoiceDate = (string) ($meta['invoice_date'] ?? now()->format('Y/m/d H:i:s'));

        if (blank($merchant) || blank($terminal) || $invoiceNumber === '') {
            return ['success' => false, 'ref_id' => null, 'error' => 'اطلاعات اتصال این درگاه کامل نشده است.'];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(30)
                ->post('https://pep.shaparak.ir/Api/v1/Payment/CheckTransactionResult', [
                    'InvoiceNumber' => $invoiceNumber,
                    'InvoiceDate' => $invoiceDate,
                    'TerminalCode' => $terminal,
                    'MerchantCode' => $merchant,
                ]);

            $isSuccess = (bool) ($response->json('IsSuccess') ?? false);
            $paid = $response->json('Amount');

            if (! $isSuccess) {
                return ['success' => false, 'ref_id' => null, 'error' => 'تأیید پرداخت پاسارگاد ناموفق بود.'];
            }

            if ($paid !== null && (int) $paid !== $amount) {
                return ['success' => false, 'ref_id' => null, 'error' => 'مبلغ پرداخت با تراکنش همخوانی ندارد'];
            }

            return [
                'success' => true,
                'ref_id' => (string) ($response->json('ReferenceNumber') ?? $response->json('TraceNumber') ?? $authority),
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Pasargad verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'خطا در تأیید پرداخت پاسارگاد.'];
        }
    }

    protected function sign(string $data, string $privateKeyPem): ?string
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false && ! str_contains($privateKeyPem, 'BEGIN')) {
            $wrapped = "-----BEGIN PRIVATE KEY-----\n".chunk_split(trim($privateKeyPem), 64, "\n").'-----END PRIVATE KEY-----';
            $key = openssl_pkey_get_private($wrapped);
        }
        if ($key === false) {
            return null;
        }

        $signature = '';
        $ok = openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA1);

        return $ok ? base64_encode($signature) : null;
    }
}
