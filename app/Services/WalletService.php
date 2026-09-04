<?php

namespace App\Services;

use App\Exceptions\IdempotencyException;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WalletFrozenException;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletLedger;
use App\Notifications\GenericDatabaseNotification;
use App\Services\Wallet\WalletMutation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WalletService
{
    private static int $mutatingBalance = 0;

    public function __construct(
        protected AuditLogService $audit,
        protected FeatureFlagService $features,
    ) {}

    public static function isMutatingBalance(): bool
    {
        return self::$mutatingBalance > 0;
    }

    public function deposit(User $user, int $amount, Transaction $transaction): void
    {
        $credited = $this->applyPaymentCredit($user, $transaction);

        if (! $credited->duplicate) {
            DB::afterCommit(function () use ($user, $transaction) {
                $user->notify(new GenericDatabaseNotification(
                    'wallet_charged',
                    'شارژ کیف پول',
                    'مبلغ '.number_format((int) $transaction->amount).' ریال به کیف پول شما اضافه شد.',
                    '/wallet'
                ));
            });
        }
    }

    public function applyPaymentCredit(User $user, Transaction $transaction): WalletMutation
    {
        return $this->credit($user, (int) $transaction->amount, [
            'source_key' => 'payment:'.$transaction->id,
            'transaction' => $transaction,
            'type' => WalletLedger::TYPE_DEPOSIT,
            'tx_type' => 'deposit',
            'description' => $transaction->description ?: 'شارژ کیف پول',
            'gateway' => $transaction->gateway ?: 'zarinpal',
        ]);
    }

    public function withdraw(User $user, int $amount, string $description): bool
    {
        try {
            $this->debit($user, $amount, [
                'type' => WalletLedger::TYPE_PURCHASE,
                'tx_type' => 'purchase',
                'description' => $description,
                'gateway' => 'wallet',
            ]);
        } catch (InsufficientBalanceException) {
            return false;
        }

        return true;
    }

    public function getBalance(User $user): int
    {
        return (int) $user->fresh()->wallet_balance;
    }

    public function hasEnough(User $user, int $amount): bool
    {
        return $this->getBalance($user) >= $amount;
    }

    public function ledgerBalance(int $userId): int
    {
        $credits = (int) WalletLedger::query()
            ->where('user_id', $userId)
            ->where('direction', WalletLedger::DIRECTION_CREDIT)
            ->sum('amount');
        $debits = (int) WalletLedger::query()
            ->where('user_id', $userId)
            ->where('direction', WalletLedger::DIRECTION_DEBIT)
            ->sum('amount');

        return $credits - $debits;
    }

    /**
     * @return array{ok: bool, cached: int, ledger: int}
     */
    public function reconcile(User $user): array
    {
        $cached = $this->getBalance($user);
        $ledger = $this->ledgerBalance((int) $user->id);

        return [
            'ok' => $cached === $ledger,
            'cached' => $cached,
            'ledger' => $ledger,
        ];
    }

    public function adminDeposit(User $user, int $amount, string $description): Transaction
    {
        return $this->credit($user, $amount, [
            'type' => WalletLedger::TYPE_ADMIN_CREDIT,
            'tx_type' => 'deposit',
            'description' => $description,
            'gateway' => 'wallet',
            'bypass_wallet_freeze' => true,
        ])->transaction;
    }

    /** کسر دستی توسط ادمین */
    public function adminWithdraw(User $user, int $amount, string $reason): ?Transaction
    {
        try {
            return $this->debit($user, $amount, [
                'type' => WalletLedger::TYPE_ADMIN_DEBIT,
                'tx_type' => 'withdrawal',
                'description' => $reason,
                'gateway' => 'wallet',
                'bypass_wallet_freeze' => true,
            ])->transaction;
        } catch (InsufficientBalanceException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function credit(User $user, int $amount, array $meta = []): WalletMutation
    {
        return $this->post($user, $amount, WalletLedger::DIRECTION_CREDIT, $meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function debit(User $user, int $amount, array $meta = []): WalletMutation
    {
        return $this->post($user, $amount, WalletLedger::DIRECTION_DEBIT, $meta);
    }

    public function refund(Transaction $original): Transaction
    {
        $run = function () use ($original): Transaction {
            $locked = Transaction::query()
                ->whereKey($original->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== Transaction::STATUS_COMPLETED) {
                throw new IdempotencyException('Only completed transactions can be refunded.');
            }

            if ($locked->type === 'refund') {
                throw new IdempotencyException('Refund transactions cannot be refunded.');
            }

            $sourceKey = 'refund:'.$locked->id;
            $existing = WalletLedger::query()->where('source_key', $sourceKey)->first();
            if ($existing?->transaction_id) {
                $prior = Transaction::query()->find($existing->transaction_id);
                if ($prior instanceof Transaction) {
                    return $prior;
                }
            }

            $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            $amount = (int) $locked->amount;
            $direction = $this->refundDirection($locked);

            $refundTx = Transaction::query()->create([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => 'refund',
                'gateway' => $locked->gateway,
                'status' => Transaction::STATUS_COMPLETED,
                'description' => 'بازگشت وجه تراکنش #'.$locked->id,
                'reference_id' => 'REFUND-'.$locked->id.'-'.(string) Str::ulid(),
                'idempotency_key' => (string) Str::ulid(),
            ]);

            $this->post($user, $amount, $direction, [
                'source_key' => $sourceKey,
                'transaction' => $refundTx,
                'type' => WalletLedger::TYPE_REFUND,
                'tx_type' => 'refund',
                'description' => $refundTx->description,
                'gateway' => $locked->gateway,
                'bypass_wallet_freeze' => true,
            ]);

            $locked->update([
                'description' => trim(($locked->description ?? '').' | refunded:'.$refundTx->id),
            ]);

            return $refundTx->fresh() ?? $refundTx;
        };

        return $this->runInTransaction($run);
    }

    public function recordOpeningBalance(User $user, int $amount): void
    {
        if ($amount === 0) {
            return;
        }

        $this->runInTransaction(function () use ($user, $amount): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            $sourceKey = 'opening:'.$locked->id;
            if (WalletLedger::query()->where('source_key', $sourceKey)->exists()) {
                return;
            }

            $direction = $amount >= 0 ? WalletLedger::DIRECTION_CREDIT : WalletLedger::DIRECTION_DEBIT;
            $abs = abs($amount);
            $this->insertLedger(
                $locked,
                $abs,
                $direction,
                (int) $locked->wallet_balance,
                [
                    'source_key' => $sourceKey,
                    'type' => WalletLedger::TYPE_OPENING,
                    'description' => 'موجودی اولیه',
                    'transaction_id' => null,
                ]
            );
        });
    }

    public function verifyHashChain(int $userId): bool
    {
        $prev = str_repeat('0', 64);

        foreach (WalletLedger::query()->where('user_id', $userId)->orderBy('id')->cursor() as $row) {
            if ($row->prev_hash !== $prev) {
                return false;
            }
            if ($row->hash !== $this->computeHash($row)) {
                return false;
            }
            $prev = $row->hash;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function post(User $user, int $amount, string $direction, array $meta): WalletMutation
    {
        if ($amount < 1) {
            throw new InvalidArgumentException('Wallet amount must be a positive integer.');
        }

        $run = function () use ($user, $amount, $direction, $meta): WalletMutation {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertWalletMutable($locked, $meta);
            $this->ensureOpeningLedger($locked);

            $sourceKey = $this->resolveSourceKey($meta);
            $existing = WalletLedger::query()->where('source_key', $sourceKey)->first();
            if ($existing) {
                return $this->mutationFromExisting($existing, $meta);
            }

            $current = (int) $locked->wallet_balance;
            $allowNegative = $this->features->isEnabled(
                (string) config('wallet.allow_negative_feature', 'wallet_allow_negative'),
                false
            );

            if ($direction === WalletLedger::DIRECTION_DEBIT && $current < $amount && ! $allowNegative) {
                throw new InsufficientBalanceException($locked, $amount, $current);
            }

            $newBalance = $direction === WalletLedger::DIRECTION_CREDIT
                ? $current + $amount
                : $current - $amount;

            $transaction = $this->resolveTransaction($locked, $amount, $meta);

            try {
                $ledger = $this->insertLedger($locked, $amount, $direction, $newBalance, [
                    'source_key' => $sourceKey,
                    'type' => (string) ($meta['type'] ?? $this->defaultLedgerType($direction, $meta)),
                    'description' => (string) ($meta['description'] ?? ''),
                    'transaction_id' => $transaction->id,
                ]);
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }

                $existing = WalletLedger::query()->where('source_key', $sourceKey)->first();
                if ($existing) {
                    return $this->mutationFromExisting($existing, $meta);
                }

                throw $e;
            }

            $this->writeCachedBalance($locked, $newBalance);

            $this->audit->log(
                $direction === WalletLedger::DIRECTION_CREDIT ? 'wallet.credited' : 'wallet.debited',
                $ledger,
                ['balance' => $current],
                [
                    'balance' => $newBalance,
                    'amount' => $amount,
                    'reference' => $ledger->reference,
                    'source_key' => $sourceKey,
                    'type' => $ledger->type,
                ],
                $locked->id
            );

            return new WalletMutation($transaction->fresh() ?? $transaction, $ledger, false);
        };

        return $this->runInTransaction($run);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function insertLedger(User $user, int $amount, string $direction, int $balanceAfter, array $meta): WalletLedger
    {
        $previous = WalletLedger::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->first();
        $prevHash = ($previous instanceof WalletLedger ? $previous->hash : null) ?? str_repeat('0', 64);
        $reference = (string) ($meta['reference'] ?? 'WL-'.Str::ulid());

        $ledger = new WalletLedger;
        $ledger->forceFill([
            'user_id' => $user->id,
            'transaction_id' => $meta['transaction_id'] ?? null,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'type' => $meta['type'],
            'reference' => $reference,
            'source_key' => $meta['source_key'],
            'description' => $meta['description'] ?: null,
            'prev_hash' => $prevHash,
            'created_at' => now(),
        ]);
        $ledger->hash = $this->computeHash($ledger);
        $ledger->save();

        return $ledger;
    }

    private function computeHash(WalletLedger $ledger): string
    {
        $payload = implode('|', [
            (string) $ledger->prev_hash,
            (string) $ledger->user_id,
            (string) $ledger->direction,
            (string) ((int) $ledger->amount),
            (string) ((int) $ledger->balance_after),
            (string) $ledger->reference,
            (string) $ledger->source_key,
            (string) $ledger->type,
        ]);

        return hash_hmac(
            (string) config('wallet.hmac_algo', 'sha256'),
            $payload,
            (string) config('app.key')
        );
    }

    private function writeCachedBalance(User $user, int $balance): void
    {
        self::$mutatingBalance++;
        try {
            User::query()->whereKey($user->id)->update(['wallet_balance' => $balance]);
            $user->wallet_balance = $balance;
        } finally {
            self::$mutatingBalance--;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function assertWalletMutable(User $user, array $meta): void
    {
        if (! empty($meta['bypass_wallet_freeze'])) {
            return;
        }

        if ($user->isWalletFrozen()) {
            throw new WalletFrozenException($user);
        }
    }

    private function ensureOpeningLedger(User $user): void
    {
        if (WalletLedger::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        $cached = (int) $user->wallet_balance;
        if ($cached === 0) {
            return;
        }

        $direction = $cached >= 0 ? WalletLedger::DIRECTION_CREDIT : WalletLedger::DIRECTION_DEBIT;
        $this->insertLedger($user, abs($cached), $direction, $cached, [
            'source_key' => 'opening:'.$user->id,
            'type' => WalletLedger::TYPE_OPENING,
            'description' => 'موجودی اولیه',
            'transaction_id' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveSourceKey(array $meta): string
    {
        if (isset($meta['source_key']) && is_string($meta['source_key']) && $meta['source_key'] !== '') {
            return $meta['source_key'];
        }

        if (isset($meta['transaction']) && $meta['transaction'] instanceof Transaction) {
            return 'tx:'.$meta['transaction']->id;
        }

        if (isset($meta['reference_id']) && is_string($meta['reference_id']) && $meta['reference_id'] !== '') {
            return 'ref:'.$meta['reference_id'];
        }

        if (isset($meta['idempotency_key']) && is_string($meta['idempotency_key']) && $meta['idempotency_key'] !== '') {
            return 'idem:'.$meta['idempotency_key'];
        }

        return 'auto:'.(string) Str::ulid();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolveTransaction(User $user, int $amount, array $meta): Transaction
    {
        if (isset($meta['transaction']) && $meta['transaction'] instanceof Transaction) {
            return $meta['transaction'];
        }

        return Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => (string) ($meta['tx_type'] ?? 'deposit'),
            'gateway' => (string) ($meta['gateway'] ?? 'wallet'),
            'status' => Transaction::STATUS_COMPLETED,
            'description' => (string) ($meta['description'] ?? ''),
            'reference_id' => isset($meta['reference_id'])
                ? (string) $meta['reference_id']
                : 'TX-'.(string) Str::ulid(),
            'idempotency_key' => isset($meta['idempotency_key']) ? (string) $meta['idempotency_key'] : null,
            'payable_type' => $meta['payable_type'] ?? null,
            'payable_id' => $meta['payable_id'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function mutationFromExisting(WalletLedger $existing, array $meta): WalletMutation
    {
        $transaction = $existing->transaction;
        if (! $transaction && isset($meta['transaction']) && $meta['transaction'] instanceof Transaction) {
            $transaction = $meta['transaction'];
        }
        if (! $transaction && $existing->transaction_id) {
            $found = Transaction::query()->find($existing->transaction_id);
            $transaction = $found instanceof Transaction ? $found : null;
        }
        if (! $transaction instanceof Transaction) {
            throw new IdempotencyException('Duplicate wallet entry is missing its transaction.');
        }

        return new WalletMutation($transaction, $existing, true);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function defaultLedgerType(string $direction, array $meta): string
    {
        if (($meta['tx_type'] ?? null) === 'refund') {
            return WalletLedger::TYPE_REFUND;
        }

        return $direction === WalletLedger::DIRECTION_CREDIT
            ? WalletLedger::TYPE_DEPOSIT
            : WalletLedger::TYPE_PURCHASE;
    }

    private function refundDirection(Transaction $original): string
    {
        if ($original->type === 'deposit') {
            return WalletLedger::DIRECTION_DEBIT;
        }

        return WalletLedger::DIRECTION_CREDIT;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function runInTransaction(callable $callback): mixed
    {
        if (DB::transactionLevel() > 0) {
            return $callback();
        }

        return DB::transaction(function () use ($callback) {
            return $callback();
        });
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        if ($sqlState === '23000' || $driverCode === 1062 || $driverCode === 19) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique') || str_contains($message, 'duplicate');
    }
}
