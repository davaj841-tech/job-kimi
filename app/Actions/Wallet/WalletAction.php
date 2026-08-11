<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Events\PaymentSuccessful;
use App\Exceptions\InsufficientBalanceException;
use App\Listeners\DispatchAfterCommit;
use App\Models\Transaction;
use App\Models\User;
use App\Traits\HandlesTransactions;

final class WalletAction
{
    use HandlesTransactions;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function charge(User $user, int $amount, array $meta = []): Transaction
    {
        return $this->transaction(function () use ($user, $amount, $meta) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->updateBalance($locked, $amount, 'credit');

            $transaction = Transaction::query()->create([
                'user_id' => $locked->id,
                'amount' => $amount,
                'type' => 'deposit',
                'gateway' => (string) ($meta['gateway'] ?? 'wallet'),
                'status' => Transaction::STATUS_COMPLETED,
                'description' => (string) ($meta['description'] ?? 'شارژ کیف پول'),
                'reference_id' => isset($meta['reference_id']) ? (string) $meta['reference_id'] : null,
                'idempotency_key' => isset($meta['idempotency_key']) ? (string) $meta['idempotency_key'] : null,
            ]);

            DispatchAfterCommit::handle($transaction, static function (Transaction $tx): void {
                event(new PaymentSuccessful($tx));
            });

            return $transaction;
        });
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function deduct(User $user, int $amount, array $meta = []): Transaction
    {
        return $this->transaction(function () use ($user, $amount, $meta) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $available = (int) $locked->wallet_balance;
            if ($available < $amount) {
                throw new InsufficientBalanceException($locked, $amount, $available);
            }

            $this->updateBalance($locked, $amount, 'debit');

            return Transaction::query()->create([
                'user_id' => $locked->id,
                'amount' => $amount,
                'type' => (string) ($meta['type'] ?? 'purchase'),
                'gateway' => (string) ($meta['gateway'] ?? 'wallet'),
                'status' => Transaction::STATUS_COMPLETED,
                'description' => (string) ($meta['description'] ?? 'برداشت از کیف پول'),
                'reference_id' => isset($meta['reference_id']) ? (string) $meta['reference_id'] : null,
                'idempotency_key' => isset($meta['idempotency_key']) ? (string) $meta['idempotency_key'] : null,
                'payable_type' => $meta['payable_type'] ?? null,
                'payable_id' => $meta['payable_id'] ?? null,
            ]);
        });
    }

    private function updateBalance(User $user, int $amount, string $type): void
    {
        if ($type === 'credit') {
            $user->increment('wallet_balance', $amount);

            return;
        }

        $user->decrement('wallet_balance', $amount);
    }
}
