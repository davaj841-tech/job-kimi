<?php

namespace App\Actions\Wallet;

use App\Models\Transaction;
use App\Models\User;
use App\Services\WalletService;

/**
 * Wallet domain actions — delegates to WalletService.
 */
class ManageWallet
{
    public function __construct(
        protected WalletService $wallet
    ) {}

    public function balance(User $user): int
    {
        return $this->wallet->getBalance($user);
    }

    public function deposit(User $user, int $amount, Transaction $transaction): void
    {
        $this->wallet->deposit($user, $amount, $transaction);
    }

    public function withdraw(User $user, int $amount, string $description): bool
    {
        return $this->wallet->withdraw($user, $amount, $description);
    }

    public function hasEnough(User $user, int $amount): bool
    {
        return $this->wallet->hasEnough($user, $amount);
    }
}
