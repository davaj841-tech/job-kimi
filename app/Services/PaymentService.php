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
     * @return array{success: bool, ref_id: ?string, error: ?string}
     */
    public function verify(string $gateway, string $authority, int $amount, array $meta = []): array
    {
        return $this->gateways->driver($gateway)->verify($authority, $amount, $meta);
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
        return (string) (
            $request->query('Authority')
            ?? $request->input('Authority')
            ?? $request->query('trans_id')
            ?? $request->input('trans_id')
            ?? $request->query('id')
            ?? $request->input('id')
            ?? $request->query('authority')
            ?? $request->input('authority')
            ?? ''
        );
    }

    public function callbackSucceeded(Request $request, string $gateway): bool
    {
        return match ($gateway) {
            'zarinpal' => strtoupper((string) ($request->query('Status') ?? $request->input('Status') ?? '')) === 'OK',
            'nextpay' => true, // status confirmed in verify API
            'idpay' => in_array((int) ($request->query('status') ?? $request->input('status') ?? 0), [10, 100], true),
            default => true,
        };
    }

    public function depositToWallet(User $user, int $amount, Transaction $transaction): void
    {
        app(WalletService::class)->deposit($user, $amount, $transaction);
    }

    /**
     * @return array<string, mixed>
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
