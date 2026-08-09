<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function deposit(User $user, int $amount, Transaction $transaction): void
    {
        DB::transaction(function () use ($user, $amount, $transaction) {
            User::query()->whereKey($user->id)->increment('wallet_balance', $amount);

            if ($transaction->status !== 'success') {
                $transaction->update(['status' => 'success']);
            }
        });

        $user->notify(new \App\Notifications\GenericDatabaseNotification(
            'wallet_charged',
            'شارژ کیف پول',
            'مبلغ '.number_format($amount).' تومان به کیف پول شما اضافه شد.',
            '/wallet'
        ));
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
                'status' => 'success',
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
                'status' => 'success',
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
                'status' => 'success',
                'description' => $reason,
                'reference_id' => 'ADMIN-WD-'.uniqid(),
            ]);
        });
    }
}
