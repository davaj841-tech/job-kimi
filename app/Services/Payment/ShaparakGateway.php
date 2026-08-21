<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;
use SoapClient;
use Throwable;

class ShaparakGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'shaparak';
    }

    public function getDisplayName(): string
    {
        return 'شاپرک';
    }

    protected function terminalId(): string
    {
        return (string) (config('services.shaparak.terminal_id') ?: config('services.mellat.terminal_id', ''));
    }

    protected function username(): string
    {
        return (string) (config('services.shaparak.username') ?: config('services.mellat.username', ''));
    }

    protected function password(): string
    {
        return (string) (config('services.shaparak.password') ?: config('services.mellat.password', ''));
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{authority: ?string, payment_url: ?string, error: ?string}
     */
    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array
    {
        if (! extension_loaded('soap')) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'افزونه SOAP برای درگاه شاپرک فعال نیست.'];
        }

        $terminalId = $this->terminalId();
        $username = $this->username();
        $password = $this->password();

        if (blank($terminalId) || blank($username) || blank($password)) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'اطلاعات درگاه شاپرک تنظیم نشده است.'];
        }

        $orderId = (int) ($meta['order_id'] ?? (time().random_int(100, 999)));

        try {
            $client = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl', [
                'encoding' => 'UTF-8',
                'exceptions' => true,
                'connection_timeout' => 30,
            ]);

            $result = $client->bpPayRequest([
                'terminalId' => $terminalId,
                'userName' => $username,
                'userPassword' => $password,
                'orderId' => $orderId,
                'amount' => $amount,
                'localDate' => date('Ymd'),
                'localTime' => date('His'),
                'additionalData' => $description,
                'callBackUrl' => $callbackUrl,
                'payerId' => 0,
            ]);

            $raw = is_object($result) ? ($result->return ?? '') : (string) $result;
            $parts = explode(',', (string) $raw);
            $code = $parts[0] ?? '';
            $refId = $parts[1] ?? '';

            if ($code !== '0' || blank($refId)) {
                Log::warning('Shaparak request failed', ['raw' => $raw]);

                return ['authority' => null, 'payment_url' => null, 'error' => 'خطا در ایجاد درخواست شاپرک.'];
            }

            return [
                'authority' => $refId,
                'payment_url' => 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat?RefId='.urlencode($refId),
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Shaparak request exception', ['error' => $e->getMessage()]);

            return ['authority' => null, 'payment_url' => null, 'error' => 'خطا در اتصال به درگاه شاپرک.'];
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{success: bool, ref_id: ?string, error: ?string}
     */
    public function verify(string $authority, int $amount, array $meta = []): array
    {
        if (! extension_loaded('soap')) {
            return ['success' => false, 'ref_id' => null, 'error' => 'افزونه SOAP برای درگاه شاپرک فعال نیست.'];
        }

        $orderId = (int) ($meta['order_id'] ?? 0);
        $saleOrderId = (int) ($meta['sale_order_id'] ?? $orderId);
        $saleReferenceId = (int) ($meta['sale_reference_id'] ?? 0);
        if ($saleOrderId <= 0 || $saleReferenceId <= 0) {
            return ['success' => false, 'ref_id' => null, 'error' => 'شناسه مرجع شاپرک ناقص است.'];
        }

        try {
            $client = new SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl', [
                'encoding' => 'UTF-8',
                'exceptions' => true,
                'connection_timeout' => 30,
            ]);

            $payload = [
                'terminalId' => $this->terminalId(),
                'userName' => $this->username(),
                'userPassword' => $this->password(),
                'orderId' => $orderId ?: $saleOrderId,
                'saleOrderId' => $saleOrderId,
                'saleReferenceId' => $saleReferenceId,
            ];

            $result = $client->bpVerifyRequest($payload);
            $code = is_object($result) ? (string) ($result->return ?? '') : (string) $result;

            if ($code !== '0') {
                return ['success' => false, 'ref_id' => null, 'error' => 'تأیید پرداخت شاپرک ناموفق بود.'];
            }

            try {
                $client->bpSettleRequest($payload);
            } catch (Throwable) {
            }

            return ['success' => true, 'ref_id' => $authority, 'error' => null];
        } catch (Throwable $e) {
            Log::error('Shaparak verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'خطا در تأیید پرداخت شاپرک.'];
        }
    }
}
