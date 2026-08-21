<?php

namespace App\Services\Payment;

use App\Models\Transaction;

final class GatewayCallbackResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly int $status,
        public readonly string $message,
        public readonly ?Transaction $transaction = null,
        public readonly bool $alreadyProcessed = false,
    ) {}
}
