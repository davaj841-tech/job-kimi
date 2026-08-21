<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Events\PaymentSuccessful;
use App\Exceptions\IdempotencyException;
use App\Listeners\DispatchAfterCommit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\IdempotencyService;
use App\Services\PaymentService;
use App\Services\WalletService;
use App\Traits\HandlesTransactions;

final class PaymentAction
{
    use HandlesTransactions;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly IdempotencyService $idempotency,
        private readonly WalletService $wallet,
    ) {}

    /**
     * @return array{payment_url: ?string, authority: ?string, idempotency_key: string, transaction_id: int, error: ?string}
     */
    public function initiate(User $user, int $amount, string $type = 'deposit', string $gateway = 'zarinpal', string $description = ''): array
    {
        $key = $this->idempotency->generateKey();

        /** @var Transaction $transaction */
        $transaction = $this->transaction(function () use ($user, $amount, $type, $gateway, $description, $key) {
            return Transaction::query()->create([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => $type === 'subscription' ? 'purchase' : 'deposit',
                'gateway' => $gateway,
                'status' => Transaction::STATUS_PENDING,
                'idempotency_key' => $key,
                'description' => $description !== '' ? $description : 'پرداخت آنلاین',
            ]);
        });

        $callback = $this->idempotency->appendKeyToCallback(
            url($type === 'subscription' ? '/payment/subscription' : '/payment/wallet'),
            $key
        );

        $result = $this->payments->initiate(
            $gateway,
            $amount,
            $description !== '' ? $description : 'پرداخت JobAzmoon',
            $callback,
            ['order_id' => (string) $transaction->id, 'idempotency_key' => $key]
        );

        if ($result['error'] || ! $result['authority']) {
            $transaction->update(['status' => Transaction::STATUS_FAILED]);

            return [
                'payment_url' => null,
                'authority' => null,
                'idempotency_key' => $key,
                'transaction_id' => $transaction->id,
                'error' => $result['error'] ?? 'خطا در اتصال به درگاه پرداخت.',
            ];
        }

        $transaction->update(['reference_id' => $result['authority']]);

        return [
            'payment_url' => $result['payment_url'],
            'authority' => $result['authority'],
            'idempotency_key' => $key,
            'transaction_id' => $transaction->id,
            'error' => null,
        ];
    }

    public function verify(string $authority, string $idempotencyKey): Transaction
    {
        return $this->transaction(function () use ($authority, $idempotencyKey) {
            $transaction = Transaction::query()
                ->where('reference_id', $authority)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                throw new IdempotencyException('Transaction not found for authority/idempotency key.');
            }

            if ($transaction->status === Transaction::STATUS_COMPLETED) {
                return $transaction;
            }

            $gateway = $transaction->gateway ?: 'zarinpal';
            $verify = $this->payments->verify(
                $gateway,
                $authority,
                (int) $transaction->amount,
                ['order_id' => (string) $transaction->id]
            );

            if (! $verify['success']) {
                $transaction->update(['status' => Transaction::STATUS_FAILED]);

                throw new IdempotencyException($verify['error'] ?? 'Payment verification failed.');
            }

            $user = User::query()->whereKey($transaction->user_id)->lockForUpdate()->firstOrFail();

            if ($transaction->type === 'deposit') {
                $this->wallet->deposit($user, (int) $transaction->amount, $transaction);
            }

            $transaction->update([
                'status' => Transaction::STATUS_COMPLETED,
                'description' => $verify['ref_id']
                    ? trim(($transaction->description ?? '').' | RefID: '.$verify['ref_id'])
                    : $transaction->description,
            ]);

            $fresh = $transaction->fresh() ?? $transaction;

            DispatchAfterCommit::handle($fresh, static function (Transaction $tx): void {
                event(new PaymentSuccessful($tx));
            });

            return $fresh;
        });
    }

    public function refund(Transaction $transaction): Transaction
    {
        return $this->transaction(function () use ($transaction) {
            $refund = $this->wallet->refund($transaction);

            if ($refund->wasRecentlyCreated) {
                DispatchAfterCommit::handle($refund, static function (Transaction $tx): void {
                    event(new PaymentSuccessful($tx));
                });
            }

            return $refund;
        });
    }
}
