<?php

namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    /**
     * @return array{authority: ?string, payment_url: ?string, error: ?string}
     */
    public function request(int $amount, string $description, string $callbackUrl, array $meta = []): array;

    /**
     * @return array{success: bool, ref_id: ?string, error: ?string}
     */
    public function verify(string $authority, int $amount, array $meta = []): array;

    public function getName(): string;

    public function getDisplayName(): string;
}
