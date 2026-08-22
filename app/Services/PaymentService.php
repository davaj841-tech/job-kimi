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
        foreach (['Authority', 'authority', 'trans_id', 'track_id'] as $key) {
            $value = $request->query($key) ?? $request->request->get($key);
            if (is_string($value) && $value !== '') {
                return $value;
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
        $idpay = (int) ($request->query('status') ?? $request->input('status') ?? 0);
        $resCode = (string) ($request->input('ResCode') ?? $request->query('ResCode') ?? '');

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

    public function resolveGatewayName(?string $name): string
    {
        $name = $name ?: $this->gateways->defaultName();
        if (! $this->gateways->isOnlineGateway($name)) {
            return $this->gateways->defaultName();
        }

        return $name;
    }
}
