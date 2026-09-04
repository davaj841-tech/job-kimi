<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;
use SoapClient;
use Throwable;

class ParsianGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'parsian';
    }

    public function getDisplayName(): string
    {
        return 'پارسیان';
    }

    public function requiredCredentialKeys(): array
    {
        return ['pin'];
    }

    protected function pin(): string
    {
        $pin = $this->credential('pin');
        if ($pin !== '') {
            return $pin;
        }

        return $this->credential('login_account');
    }

    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array
    {
        if (! extension_loaded('soap')) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'افزونه SOAP برای درگاه پارسیان فعال نیست.'];
        }

        $pin = $this->pin();
        if (blank($pin)) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'اطلاعات اتصال این درگاه کامل نشده است.'];
        }

        $orderId = (int) ($meta['order_id'] ?? (time() % 1000000000));

        try {
            $client = new SoapClient('https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx?wsdl', [
                'encoding' => 'UTF-8',
                'exceptions' => true,
                'connection_timeout' => 30,
            ]);

            $result = $client->SalePaymentRequest([
                'requestData' => [
                    'LoginAccount' => $pin,
                    'Amount' => $amount,
                    'OrderId' => $orderId,
                    'CallBackUrl' => $callbackUrl,
                    'AdditionalData' => mb_substr($description, 0, 100),
                ],
            ]);

            $status = (int) data_get($result, 'SalePaymentRequestResult.Status', -1);
            $token = (string) data_get($result, 'SalePaymentRequestResult.Token', '');

            if ($status !== 0 || $token === '' || $token === '0') {
                Log::warning('Parsian request failed', ['status' => $status]);

                return ['authority' => null, 'payment_url' => null, 'error' => 'خطا در ایجاد درخواست پارسیان.'];
            }

            return [
                'authority' => $token,
                'payment_url' => 'https://pec.shaparak.ir/NewIPG/?Token='.urlencode($token),
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Parsian request exception', ['error' => $e->getMessage()]);

            return ['authority' => null, 'payment_url' => null, 'error' => 'خطا در اتصال به درگاه پارسیان.'];
        }
    }

    public function verify(string $authority, int $amount, array $meta = []): array
    {
        if (! extension_loaded('soap')) {
            return ['success' => false, 'ref_id' => null, 'error' => 'افزونه SOAP برای درگاه پارسیان فعال نیست.'];
        }

        $pin = $this->pin();
        if (blank($pin) || $authority === '') {
            return ['success' => false, 'ref_id' => null, 'error' => 'اطلاعات اتصال این درگاه کامل نشده است.'];
        }

        try {
            $client = new SoapClient('https://pec.shaparak.ir/NewIPGServices/Confirm/ConfirmService.asmx?wsdl', [
                'encoding' => 'UTF-8',
                'exceptions' => true,
                'connection_timeout' => 30,
            ]);

            $result = $client->ConfirmPayment([
                'requestData' => [
                    'LoginAccount' => $pin,
                    'Token' => $authority,
                ],
            ]);

            $status = (int) data_get($result, 'ConfirmPaymentResult.Status', -1);
            $rrn = (string) data_get($result, 'ConfirmPaymentResult.RRN', $authority);

            if ($status !== 0) {
                return ['success' => false, 'ref_id' => null, 'error' => 'تأیید پرداخت پارسیان ناموفق بود.'];
            }

            return ['success' => true, 'ref_id' => $rrn !== '' ? $rrn : $authority, 'error' => null];
        } catch (Throwable $e) {
            Log::error('Parsian verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'خطا در تأیید پرداخت پارسیان.'];
        }
    }
}
