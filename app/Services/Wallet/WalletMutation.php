<?php

namespace App\Services\Wallet;

use App\Models\Transaction;
use App\Models\WalletLedger;

final class WalletMutation
{
    public function __construct(
        public readonly Transaction $transaction,
        public readonly WalletLedger $ledger,
        public readonly bool $duplicate = false,
    ) {}
}
