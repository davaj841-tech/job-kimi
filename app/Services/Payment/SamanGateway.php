<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SamanGateway extends AbstractPaymentGateway
{
    public function getName(): string
    {
        return 'saman';
    }

    public function getDisplayName(): string
    {
        return 'سامان (SEP)';
    }

    public function requiredCredentialKeys(): array
    {
        return ['terminal_id'];
    }

    protected function terminalId(): string
    {
        return $this->credential('terminal_id');
    }

    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array
    {
        $terminalId = $this->terminalId();
        if (blank($terminalId)) {
            return ['authority' => null, 'payment_url' => null, 'error' => 'اطلاعات اتصال این درگاه کامل نشده است.'];
        }

        $resNum = (string) ($meta['order_id'] ?? uniqid('smn_', true));

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(30)
                ->post('https://sep.shaparak.ir/onlinepg/onlinepg', [
                    'action' => 'token',
                    'TerminalId' => $terminalId,
                    'Amount' => $amount,
                    'ResNum' => $resNum,
                    'RedirectUrl' => $callbackUrl,
                    'CellNumber' => $meta['mobile'] ?? null,
                ]);

            $status = (int) ($response->json('status') ?? -1);
            $token = (string) ($response->json('token') ?? '');

            if ($status !== 1 || $token === '') {
                Log::warning('Saman request failed', ['http' => $response->status(), 'status' => $status]);

                return [
                    'authority' => null,
                    'payment_url' => null,
                    'error' => (string) ($response->json('errorDesc') ?? 'خطا در ایجاد درخواست سامان.'),
                ];
            }

            return [
                'authority' => $token,
                'payment_url' => 'https://sep.shaparak.ir/OnlinePG/OnlinePG?Token='.urlencode($token),
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Saman request exception', ['error' => $e->getMessage()]);

            return ['authority' => null, 'payment_url' => null, 'error' => 'خطا در اتصال به درگاه سامان.'];
        }
    }

    public function verify(string $authority, int $amount, array $meta = []): array
    {
        $terminalId = $this->terminalId();
        $refNum = (string) ($meta['sale_reference_id'] ?? $authority);
        if (blank($terminalId) || $refNum === '') {
            return ['success' => false, 'ref_id' => null, 'error' => 'اطلاعات اتصال این درگاه کامل نشده است.'];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(30)
                ->post('https://sep.shaparak.ir/verifyTxnRandomSessionkey/ipg/VerifyTransaction', [
                    'RefNum' => $refNum,
                    'TerminalNumber' => $terminalId,
                ]);

            $success = (bool) ($response->json('Success') ?? false);
            $resultCode = (int) ($response->json('ResultCode') ?? -1);
            $txnAmount = $response->json('TransactionDetail.OrgAmount')
                ?? $response->json('TransactionDetail.Amount')
                ?? null;

            if (! $success || $resultCode !== 0) {
                Log::warning('Saman verify failed', ['http' => $response->status(), 'code' => $resultCode]);

                return ['success' => false, 'ref_id' => null, 'error' => 'تأیید پرداخت سامان ناموفق بود.'];
            }

            if ($txnAmount !== null && (int) $txnAmount !== $amount) {
                return ['success' => false, 'ref_id' => null, 'error' => 'مبلغ پرداخت با تراکنش همخوانی ندارد'];
            }

            return [
                'success' => true,
                'ref_id' => (string) ($response->json('TransactionDetail.RefNum') ?? $refNum),
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Saman verify exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'ref_id' => null, 'error' => 'خطا در تأیید پرداخت سامان.'];
        }
    }
}
