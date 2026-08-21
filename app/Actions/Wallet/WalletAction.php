<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Events\PaymentSuccessful;
use App\Listeners\DispatchAfterCommit;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\WalletService;
use App\Traits\HandlesTransactions;

final class WalletAction
{
    use HandlesTransactions;

    public function __construct(
        private readonly WalletService $wallet,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function charge(User $user, int $amount, array $meta = []): Transaction
    {
        return $this->transaction(function () use ($user, $amount, $meta) {
            $result = $this->wallet->credit($user, $amount, [
                'type' => WalletLedger::TYPE_DEPOSIT,
                'tx_type' => 'deposit',
                'gateway' => (string) ($meta['gateway'] ?? 'wallet'),
                'description' => (string) ($meta['description'] ?? 'شارژ کیف پول'),
                'reference_id' => isset($meta['reference_id']) ? (string) $meta['reference_id'] : null,
                'idempotency_key' => isset($meta['idempotency_key']) ? (string) $meta['idempotency_key'] : null,
                'source_key' => isset($meta['reference_id'])
                    ? 'wallet-charge:'.$meta['reference_id']
                    : (isset($meta['idempotency_key']) ? 'wallet-charge:'.$meta['idempotency_key'] : null),
            ]);

            if (! $result->duplicate) {
                DispatchAfterCommit::handle($result->transaction, static function (Transaction $tx): void {
                    event(new PaymentSuccessful($tx));
                });
            }

            return $result->transaction;
        });
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function deduct(User $user, int $amount, array $meta = []): Transaction
    {
        return $this->transaction(function () use ($user, $amount, $meta) {
            $result = $this->wallet->debit($user, $amount, [
                'type' => (string) ($meta['type'] ?? WalletLedger::TYPE_PURCHASE),
                'tx_type' => (string) ($meta['type'] ?? 'purchase'),
                'gateway' => (string) ($meta['gateway'] ?? 'wallet'),
                'description' => (string) ($meta['description'] ?? 'برداشت از کیف پول'),
                'reference_id' => isset($meta['reference_id']) ? (string) $meta['reference_id'] : null,
                'idempotency_key' => isset($meta['idempotency_key']) ? (string) $meta['idempotency_key'] : null,
                'payable_type' => $meta['payable_type'] ?? null,
                'payable_id' => $meta['payable_id'] ?? null,
                'source_key' => isset($meta['reference_id'])
                    ? 'wallet-debit:'.$meta['reference_id']
                    : (isset($meta['idempotency_key']) ? 'wallet-debit:'.$meta['idempotency_key'] : null),
            ]);

            return $result->transaction;
        });
    }
}
