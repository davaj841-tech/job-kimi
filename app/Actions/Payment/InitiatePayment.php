<?php

namespace App\Actions\Payment;

use App\Services\IdempotencyService;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * Thin action wrapper around PaymentService for initiating gateway charges.
 */
class InitiatePayment
{
    public function __construct(
        protected PaymentService $payments,
        protected IdempotencyService $idempotency,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     * @return array{authority: ?string, payment_url: ?string, error: ?string}
     */
    public function handle(string $gateway, int $amount, string $description, string $callback, array $meta = []): array
    {
        return $this->payments->initiate($gateway, $amount, $description, $callback, $meta);
    }

    public function newIdempotencyKey(): string
    {
        return $this->idempotency->generateKey();
    }

    public function callbackWithIdempotency(string $callback, string $key): string
    {
        return $this->idempotency->appendKeyToCallback($callback, $key);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{success: bool, ref_id: ?string, error: ?string}
     */
    public function verify(string $gateway, string $authority, int $amount, array $meta = []): array
    {
        return $this->payments->verify($gateway, $authority, $amount, $meta);
    }

    public function authorityFrom(Request $request): string
    {
        return $this->payments->extractAuthority($request);
    }
}
