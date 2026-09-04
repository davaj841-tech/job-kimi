<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletLedger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TransactionRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function getByUser(User $user, array $filters): LengthAwarePaginator
    {
        $query = Transaction::query()->where('user_id', $user->id);

        $this->applyTypeFilter($query, $filters);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Transaction>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyTypeFilter($query, array $filters): void
    {
        $type = $filters['type'] ?? $filters['category'] ?? null;
        if ($type === null || $type === '') {
            return;
        }

        match ($type) {
            'deposit' => $query
                ->where('type', 'deposit')
                ->whereDoesntHave('walletLedger', fn ($q) => $q->whereIn('type', [
                    WalletLedger::TYPE_ADMIN_CREDIT,
                    WalletLedger::TYPE_ADMIN_DEBIT,
                ])),
            'purchase' => $query->where('type', 'purchase'),
            'withdrawal' => $query->where('type', 'withdrawal'),
            'refund' => $query->where('type', 'refund'),
            'bonus' => $query->whereHas('walletLedger', fn ($q) => $q->where('type', WalletLedger::TYPE_ADMIN_CREDIT)),
            'adjustment' => $query->whereHas('walletLedger', fn ($q) => $q->whereIn('type', [
                WalletLedger::TYPE_ADMIN_CREDIT,
                WalletLedger::TYPE_ADMIN_DEBIT,
            ])),
            default => $query->where('type', $type),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = Transaction::query()->with('user:id,name,mobile');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['gateway'])) {
            $query->where('gateway', $filters['gateway']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getPending(): Collection
    {
        return Transaction::query()->where('status', 'pending')->latest()->get();
    }

    public function getByReference(string $ref): ?Transaction
    {
        return Transaction::query()->where('reference_id', $ref)->first();
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function recentForUser(User $user, int $limit = 20): Collection
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get();
    }
}
