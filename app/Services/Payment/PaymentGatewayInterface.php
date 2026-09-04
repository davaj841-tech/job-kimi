<?php

namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    /**
     * @param  array<string, mixed>  $meta
     * @return array{authority: ?string, payment_url: ?string, error: ?string}
     */
    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array;

    /**
     * @param  array<string, mixed>  $meta
     * @return array{success: bool, ref_id: ?string, error: ?string}
     */
    public function verify(string $authority, int $amount, array $meta = []): array;

    public function getName(): string;

    public function getDisplayName(): string;

    public function isConfigured(): bool;

    /**
     * Local credentials / connectivity check — must not create a real charge.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array;
}
