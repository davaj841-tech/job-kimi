<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\Request;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayManager $gateways
    ) {}

    /**
     * ایجاد درخواست پرداخت در درگاه (alias برای initiate).
     *
     * @param  array<string, mixed>  $meta
     * @return array{authority: ?string, payment_url: ?string, error: ?string}
     */
    public function create(string $gateway, int $amount, string $description, string $callback, array $meta = []): array
    {
        return $this->initiate($gateway, $amount, $description, $callback, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{authority: ?string, payment_url: ?string, error: ?string}
     */
    public function initiate(string $gateway, int $amount, string $description, string $callback, array $meta = []): array
    {
        $user = auth()->user();
        if ($user) {
            $meta['mobile'] = $meta['mobile'] ?? $user->mobile;
            $meta['email'] = $meta['email'] ?? $user->email;
        }

        return $this->gateways->driver($gateway)->request($amount, $description, $callback, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{success: bool, ref_id: ?string, error: ?string}
     */
    public function verify(string $gateway, string $authority, int $amount, array $meta = []): array
    {
        return $this->gateways->driver($gateway)->verify($authority, $amount, $meta);
    }

    /**
     * بازگشت وجه تراکنش تکمیل‌شده از طریق کیف پول.
     */
    public function refund(Transaction $transaction): Transaction
    {
        return app(WalletService::class)->refund($transaction);
    }

    /** Backward-compatible aliases */
    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function initiateZarinPal(int $amount, string $description, string $callback, array $meta = []): array
    {
        return $this->initiate('zarinpal', $amount, $description, $callback, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function verifyZarinPal(string $authority, int $amount, array $meta = []): array
    {
        return $this->verify('zarinpal', $authority, $amount, $meta);
    }

    public function extractAuthority(Request $request): string
    {
        foreach ([
            'Authority', 'authority', 'trans_id', 'track_id', 'Token', 'token',
            'RefNum', 'ref_num', 'RefId', 'refId', 'invoiceId', 'iN',
        ] as $key) {
            $value = $request->query($key) ?? $request->request->get($key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_numeric($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        $queryId = $request->query('id');
        $routeId = $request->route('id');
        if (is_string($queryId) && $queryId !== '' && (string) $routeId !== $queryId) {
            return $queryId;
        }
        if (is_string($queryId) && $queryId !== '' && $routeId === null) {
            return $queryId;
        }

        return '';
    }

    public function callbackSucceeded(Request $request, string $gateway): bool
    {
        return $this->callbackOutcome($request, $gateway) === 'ok';
    }

    /**
     * @return 'ok'|'cancel'|'fail'
     */
    public function callbackOutcome(Request $request, string $gateway): string
    {
        $status = strtoupper((string) ($request->query('Status') ?? $request->input('Status') ?? ''));
        $state = strtoupper((string) ($request->query('State') ?? $request->input('State') ?? ''));
        $idpay = (int) ($request->query('status') ?? $request->input('status') ?? 0);
        $resCode = (string) ($request->input('ResCode') ?? $request->query('ResCode') ?? '');
        $parsianStatus = (string) ($request->input('status') ?? $request->query('status') ?? '');
        $apResult = (string) ($request->input('PayGateTranID') ?? $request->query('PayGateTranID') ?? $request->input('payingGateTranID') ?? '');

        return match ($gateway) {
            'zarinpal', 'fake' => match ($status) {
                'OK' => 'ok',
                'NOK' => 'cancel',
                default => 'fail',
            },
            'nextpay' => $status === 'NOK' ? 'cancel' : 'ok',
            'idpay' => match ($idpay) {
                10, 100 => 'ok',
                6, 7, 8 => 'cancel',
                default => 'fail',
            },
            'mellat', 'shaparak' => match ($resCode) {
                '0', '00' => 'ok',
                '17' => 'cancel',
                default => 'fail',
            },
            'parsian' => match ($parsianStatus) {
                '0' => 'ok',
                '-138', '138' => 'cancel',
                default => ((int) $parsianStatus === 0 && $parsianStatus !== '') ? 'ok' : 'fail',
            },
            'saman' => match ($state) {
                'OK' => 'ok',
                'CANCELED', 'CANCELLED' => 'cancel',
                default => $status === 'OK' ? 'ok' : 'fail',
            },
            'pasargad' => filled($request->input('tref') ?? $request->query('tref'))
                || filter_var($request->input('IsSuccess') ?? $request->query('IsSuccess'), FILTER_VALIDATE_BOOLEAN)
                ? 'ok'
                : 'fail',
            'sadad' => match ($resCode) {
                '0', '00' => 'ok',
                default => 'fail',
            },
            'ap' => $apResult !== '' || strtoupper((string) ($request->input('Result') ?? $request->query('Result') ?? '')) === 'OK'
                ? 'ok'
                : 'fail',
            default => 'fail',
        };
    }

    public function depositToWallet(User $user, int $amount, Transaction $transaction): void
    {
        app(WalletService::class)->deposit($user, $amount, $transaction);
    }

    /**
     * @return list<array{name: string, display_name: string|null, is_default: bool}>
     */
    public function activeGateways(): array
    {
        return $this->gateways->activeList();
    }

    /**
     * Resolve payable online gateway before creating a pending transaction.
     * May fall back to another active gateway if preferred is inactive/unconfigured.
     *
     * @throws \RuntimeException when no payable gateway exists
     */
    public function resolveGatewayName(?string $name): string
    {
        return $this->gateways->assertPayable($name);
    }

    /**
     * Network/timeout style failures are uncertain (bank may have created a payment).
     * Do not auto-retry; leave pending for TTL/reconciliation instead of FAILED.
     */
    public function isUncertainGatewayError(?string $error): bool
    {
        $error = mb_strtolower((string) $error);
        if ($error === '') {
            return false;
        }

        foreach (['timeout', 'timed out', 'ارتباط', 'اتصال', 'برقرار نشد', 'connection', 'curl'] as $needle) {
            if (str_contains($error, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply safe failure policy after a single initiate attempt (no automatic retry).
     */
    public function markInitiateFailure(\App\Models\Transaction $transaction, ?string $error): void
    {
        $status = $this->isUncertainGatewayError($error)
            ? \App\Models\Transaction::STATUS_PENDING
            : \App\Models\Transaction::STATUS_FAILED;

        $transaction->update(['status' => $status]);
    }
}
