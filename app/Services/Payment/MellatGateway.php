<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;
use Throwable;

class MellatGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'mellat';
    }

    public function getDisplayName(): string
    {
        return 'بانک ملت';
    }

    protected function terminalId(): string
    {
        return (string) Setting::getFilled('mellat_terminal_id', '');
    }

    protected function username(): string
    {
        return (string) Setting::getFilled('mellat_username', '');
    }

    protected function password(): string
    {
        return (string) Setting::getFilled('mellat_password', '');
    }

    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array
    {
        if (! extension_loaded('soap')) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'افزونه SOAP برای درگاه بانک ملت فعال نیست.'];
        }

        $terminalId = $this->terminalId();
        $username = $this->username();
        $password = $this->password();

        if (blank($terminalId) || blank($username) || blank($password)) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'اطلاعات درگاه بانک ملت تنظیم نشده است.'];
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
                Log::warning('Mellat request failed', ['raw' => $raw]);

                return ['authority' => null, 'payment_url' => null, 'error' => 'خطا در ایجاد درخواست بانک ملت.'];
            }

            return [
                'authority' => $refId,
                'payment_url' => 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat?RefId='.urlencode($refId),
                'error' => null,
            ];
        } catch (SoapFault|Throwable $e) {
            Log::error('Mellat request exception', ['error' => $e->getMessage()]);

            return ['authority' => null, 'payment_url' => null, 'error' => 'خطا در اتصال به درگاه بانک ملت.'];
        }
    }

    public function verify(string $authority, int $amount, array $meta = []): array
    {
        if (! extension_loaded('soap')) {
            return ['success' => false, 'ref_id' => null, 'error' => 'افزونه SOAP برای درگاه بانک ملت فعال نیست.'];
        }

        $orderId = (int) ($meta['order_id'] ?? 0);
        $saleOrderId = (int) ($meta['sale_order_id'] ?? $orderId);
        $saleReferenceId = (int) ($meta['sale_reference_id'] ?? 0);

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
                return ['success' => false, 'ref_id' => null, 'error' => 'تأیید پرداخت بانک ملت ناموفق بود.'];
            }

            try {
                $client->bpSettleRequest($payload);
            } catch (Throwable) {
                // verify succeeded
            }

            return ['success' => true, 'ref_id' => $authority, 'error' => null];
        } catch (SoapFault|Throwable $e) {
            Log::error('Mellat verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'خطا در تأیید پرداخت بانک ملت.'];
        }
    }
}
