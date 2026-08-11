<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function deposit(User $user, int $amount, Transaction $transaction): void
    {
        $credited = false;

        DB::transaction(function () use ($user, $amount, $transaction, &$credited) {
            /** @var Transaction $lockedTx */
            $lockedTx = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTx->status === Transaction::STATUS_COMPLETED) {
                return;
            }

            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            User::query()->whereKey($user->id)->increment('wallet_balance', $amount);

            $lockedTx->update(['status' => Transaction::STATUS_COMPLETED]);
            $credited = true;
        });

        if (! $credited) {
            return;
        }

        DB::afterCommit(function () use ($user, $amount) {
            $user->notify(new GenericDatabaseNotification(
                'wallet_charged',
                'شارژ کیف پول',
                'مبلغ '.number_format($amount).' تومان به کیف پول شما اضافه شد.',
                '/wallet'
            ));
        });
    }

    public function withdraw(User $user, int $amount, string $description): bool
    {
        return DB::transaction(function () use ($user, $amount, $description) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if (! $locked || (int) $locked->wallet_balance < $amount) {
                return false;
            }

            $locked->decrement('wallet_balance', $amount);

            Transaction::query()->create([
                'user_id' => $locked->id,
                'amount' => $amount,
                'type' => 'purchase',
                'gateway' => 'wallet',
                'status' => Transaction::STATUS_COMPLETED,
                'description' => $description,
            ]);

            return true;
        });
    }

    public function getBalance(User $user): int
    {
        return (int) $user->fresh()->wallet_balance;
    }

    public function hasEnough(User $user, int $amount): bool
    {
        return $this->getBalance($user) >= $amount;
    }

    /** شارژ دستی توسط ادمین */
    public function adminDeposit(User $user, int $amount, string $description): Transaction
    {
        return DB::transaction(function () use ($user, $amount, $description) {
            $transaction = Transaction::query()->create([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => 'deposit',
                'gateway' => 'wallet',
                'status' => Transaction::STATUS_COMPLETED,
                'description' => $description,
                'reference_id' => 'ADMIN-DEP-'.uniqid(),
            ]);

            User::query()->whereKey($user->id)->increment('wallet_balance', $amount);

            return $transaction;
        });
    }

    /** کسر دستی توسط ادمین */
    public function adminWithdraw(User $user, int $amount, string $reason): ?Transaction
    {
        return DB::transaction(function () use ($user, $amount, $reason) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if (! $locked || (int) $locked->wallet_balance < $amount) {
                return null;
            }

            $locked->decrement('wallet_balance', $amount);

            return Transaction::query()->create([
                'user_id' => $locked->id,
                'amount' => $amount,
                'type' => 'withdrawal',
                'gateway' => 'wallet',
                'status' => Transaction::STATUS_COMPLETED,
                'description' => $reason,
                'reference_id' => 'ADMIN-WD-'.uniqid(),
            ]);
        });
    }
}
