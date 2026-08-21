<?php

namespace App\Services\Payment;

use App\Exceptions\IdempotencyException;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use App\Services\AuditLogService;
use App\Services\IdempotencyService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single callback path: inspect row → gateway verify(DB amount) → credit once under lock.
 * HTTP verify is outside the row lock so callbacks do not hold InnoDB locks during network I/O.
 */
class GatewayCallbackService
{
    public function __construct(
        protected PaymentService $payments,
        protected TransactionRepository $transactions,
        protected IdempotencyService $idempotency,
        protected AuditLogService $audit,
    ) {}

    /**
     * @param  callable(Transaction): bool  $matches
     * @param  callable(Transaction, array{success: bool, ref_id: ?string, error: ?string}): void  $credit
     */
    public function complete(Request $request, callable $matches, callable $credit): GatewayCallbackResult
    {
        $authority = $this->payments->extractAuthority($request);
        if ($authority === '') {
            return new GatewayCallbackResult(false, 422, 'شناسه پرداخت نامعتبر است.');
        }

        $transaction = $this->transactions->getByReference($authority);
        if (! $transaction || ! $matches($transaction)) {
            return new GatewayCallbackResult(false, 404, 'تراکنش یافت نشد.');
        }

        $requestKey = $this->idempotency->extractKey($request);
        if (
            $requestKey !== null
            && is_string($transaction->idempotency_key)
            && $transaction->idempotency_key !== ''
            && ! hash_equals($transaction->idempotency_key, $requestKey)
        ) {
            return new GatewayCallbackResult(false, 422, 'کلید یکتایی پرداخت نامعتبر است.', $transaction);
        }

        $pre = $this->inspectBeforeVerify($request, $transaction);
        if ($pre !== null) {
            return $pre;
        }

        $transaction = $transaction->fresh() ?? $transaction;
        $gateway = $transaction->gateway ?: 'zarinpal';
        $amount = (int) $transaction->amount;

        $verify = $this->payments->verify($gateway, (string) $transaction->reference_id, $amount, [
            'order_id' => (string) $transaction->id,
            'sale_order_id' => (string) $transaction->id,
            'sale_reference_id' => $this->saleReferenceId($request),
        ]);

        if (! $verify['success']) {
            $this->markFailed($transaction);
            $this->auditSafe('payment.verify_failed', $transaction->fresh() ?? $transaction, [
                'error' => $verify['error'] ?? null,
            ]);

            return new GatewayCallbackResult(
                false,
                400,
                $verify['error'] ?? 'پرداخت ناموفق بود',
                $transaction->fresh()
            );
        }

        try {
            $result = $this->idempotency->completeOnce($transaction, function (Transaction $row) use ($credit, $verify) {
                if ($verify['ref_id']) {
                    $row->update([
                        'description' => trim(($row->description ?? '').' | RefID: '.$verify['ref_id']),
                    ]);
                }
                $credit($row->fresh() ?? $row, $verify);

                return true;
            });
        } catch (IdempotencyException $e) {
            Log::warning('Payment callback not eligible', [
                'transaction_id' => $transaction->id,
                'reason' => $e->getMessage(),
            ]);

            return new GatewayCallbackResult(false, 400, 'تراکنش قابل تایید نیست.', $transaction->fresh());
        }

        $fresh = $result['transaction']->fresh() ?? $result['transaction'];
        if (! $result['already_processed']) {
            $this->auditSafe('payment.verified', $fresh);
        }

        return new GatewayCallbackResult(
            true,
            200,
            'پرداخت با موفقیت انجام شد.',
            $fresh,
            $result['already_processed']
        );
    }

    public function expireStalePending(): int
    {
        $minutes = max(5, (int) config('payment.pending_ttl_minutes', 45));
        $cutoff = now()->subMinutes($minutes);

        $ids = Transaction::query()
            ->where('status', Transaction::STATUS_PENDING)
            ->where('gateway', '!=', 'wallet')
            ->where('created_at', '<', $cutoff)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        Transaction::query()->whereIn('id', $ids)->update(['status' => Transaction::STATUS_EXPIRED]);

        $this->audit->log('payment.expired_batch', null, null, [
            'count' => $ids->count(),
            'ids' => $ids->take(50)->all(),
        ]);

        return $ids->count();
    }

    protected function inspectBeforeVerify(Request $request, Transaction $transaction): ?GatewayCallbackResult
    {
        return DB::transaction(function () use ($request, $transaction) {
            /** @var Transaction $locked */
            $locked = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === Transaction::STATUS_COMPLETED) {
                return new GatewayCallbackResult(true, 200, 'پرداخت قبلاً ثبت شده است.', $locked, true);
            }

            if ($locked->status === Transaction::STATUS_CANCELLED) {
                return new GatewayCallbackResult(false, 400, 'پرداخت لغو شده است.', $locked);
            }

            if ($locked->status === Transaction::STATUS_EXPIRED) {
                return new GatewayCallbackResult(false, 400, 'مهلت پرداخت به پایان رسیده است.', $locked);
            }

            $gateway = $locked->gateway ?: 'zarinpal';
            $outcome = $this->payments->callbackOutcome($request, $gateway);

            if ($outcome === 'cancel') {
                $locked->update(['status' => Transaction::STATUS_CANCELLED]);
                $this->auditSafe('payment.cancelled', $locked);

                return new GatewayCallbackResult(false, 400, 'پرداخت توسط کاربر لغو شد.', $locked->fresh());
            }

            if ($outcome !== 'ok') {
                $locked->update(['status' => Transaction::STATUS_FAILED]);
                $this->auditSafe('payment.failed', $locked);

                return new GatewayCallbackResult(false, 400, 'پرداخت ناموفق بود', $locked->fresh());
            }

            return null;
        });
    }

    protected function markFailed(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $locked = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->first();
            if ($locked && $locked->status === Transaction::STATUS_PENDING) {
                $locked->update(['status' => Transaction::STATUS_FAILED]);
            }
        });
    }

    protected function saleReferenceId(Request $request): string
    {
        foreach (['SaleReferenceId', 'sale_reference_id', 'RefNum', 'ref_num'] as $key) {
            $value = $request->input($key) ?? $request->query($key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_numeric($value) && (int) $value !== 0) {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function auditSafe(string $action, Transaction $tx, array $extra = []): void
    {
        $this->audit->log($action, $tx, null, array_merge([
            'amount' => (int) $tx->amount,
            'type' => $tx->type,
            'gateway' => $tx->gateway,
            'status' => $tx->status,
        ], $extra), $tx->user_id);
    }
}
