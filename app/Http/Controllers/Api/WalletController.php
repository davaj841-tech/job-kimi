<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payment\InitiatePayment;
use App\Actions\Wallet\ManageWallet;
use App\Events\PaymentSuccessful;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\TransactionRepository;
use App\Services\AuditLogService;
use App\Services\IdempotencyService;
use App\Services\Payment\GatewayCallbackService;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends BaseController
{
    public function __construct(
        protected WalletService $walletService,
        protected PaymentService $paymentService,
        protected TransactionRepository $transactionRepository,
        protected ManageWallet $manageWallet,
        protected InitiatePayment $initiatePayment,
        protected IdempotencyService $idempotencyService,
        protected GatewayCallbackService $gatewayCallback,
        protected AuditLogService $audit,
    ) {}

    /**
     * موجودی و تراکنش‌های کیف پول
     *
     * @group پرداخت‌ها
     *
     * @authenticated
     *
     * @response 200 {"success":true,"data":{"balance":50000,"transactions":[]}}
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $transactions = $this->transactionRepository->recentForUser($user, 20);

        return $this->successResponse([
            'balance' => $this->manageWallet->balance($user),
            'transactions' => $transactions->map(fn (Transaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => (int) $tx->amount,
                'status' => $tx->status,
                'description' => $tx->description,
                'invoice_number' => $tx->invoice_number,
                'created_at' => $tx->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * شارژ کیف پول
     *
     * شروع شارژ کیف پول از طریق درگاه پرداخت (مثلاً زرین‌پال).
     *
     * @group پرداخت‌ها
     *
     * @authenticated
     *
     * @bodyParam amount integer required مبلغ به ریال. Example: 50000
     * @bodyParam gateway string درگاه پرداخت. Example: zarinpal
     *
     * @response 200 {"success":true,"message":"در حال انتقال به درگاه پرداخت","data":{"payment_url":"https://zarinpal.com/...","transaction_id":1,"idempotency_key":"...","gateway":"zarinpal"}}
     * @response 400 {"success":false,"message":"خطا در اتصال به درگاه پرداخت."}
     */
    public function charge(Request $request): JsonResponse
    {
        $minCharge = (int) Setting::get('min_wallet_charge', config('payment.min_wallet_charge', 10000));
        $maxCharge = max($minCharge, (int) Setting::get('max_wallet_charge', config('payment.max_wallet_charge', 50_000_000)));

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:'.$minCharge, 'max:'.$maxCharge],
            'gateway' => ['nullable', 'string', 'in:zarinpal,nextpay,idpay,mellat,shaparak'],
        ], [
            'amount.min' => 'مبلغ شارژ کمتر از حد مجاز است.',
            'amount.max' => 'مبلغ شارژ بیشتر از حد مجاز است.',
        ]);

        $amount = (int) $data['amount'];
        $gateway = $this->paymentService->resolveGatewayName($data['gateway'] ?? null);
        $user = $request->user();
        $idempotencyKey = $this->idempotencyService->generateKey();

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'deposit',
            'gateway' => $gateway,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => $idempotencyKey,
            'description' => 'شارژ کیف پول',
        ]);

        $callback = $this->idempotencyService->appendKeyToCallback(url('/payment/wallet'), $idempotencyKey);

        $result = $this->initiatePayment->handle(
            $gateway,
            $amount,
            'شارژ کیف پول JobAzmoon',
            $callback,
            ['order_id' => (string) $transaction->id, 'idempotency_key' => $idempotencyKey]
        );

        // Retry once with the same idempotency key if the gateway call fails.
        if ($result['error'] || ! $result['authority']) {
            $result = $this->initiatePayment->handle(
                $gateway,
                $amount,
                'شارژ کیف پول JobAzmoon',
                $callback,
                ['order_id' => (string) $transaction->id, 'idempotency_key' => $idempotencyKey]
            );
        }

        if ($result['error'] || ! $result['authority']) {
            $transaction->update(['status' => Transaction::STATUS_FAILED]);

            return $this->errorResponse($result['error'] ?? 'خطا در اتصال به درگاه پرداخت.', 400);
        }

        $transaction->update(['reference_id' => $result['authority']]);

        $this->audit->log('payment.initiated', $transaction, null, [
            'amount' => (int) $transaction->amount,
            'type' => $transaction->type,
            'gateway' => $transaction->gateway,
        ], $user->id);

        return $this->successResponse([
            'payment_url' => $result['payment_url'],
            'transaction_id' => $transaction->id,
            'idempotency_key' => $idempotencyKey,
            'gateway' => $gateway,
        ], 'در حال انتقال به درگاه پرداخت');
    }

    /**
     * تأیید پرداخت شارژ کیف پول
     *
     * کال‌بک درگاه (عمومی — بدون Bearer).
     *
     * @group پرداخت‌ها
     *
     * @unauthenticated
     *
     * @queryParam Authority string شناسه پرداخت زرین‌پال. Example: A000000000000000000000000000000000000
     * @queryParam Status string وضعیت. Example: OK
     * @queryParam idempotency_key string کلید یکتایی. Example: abc-123
     *
     * @response 200 {"success":true,"message":"کیف پول با موفقیت شارژ شد","data":{"new_balance":50000}}
     * @response 404 {"success":false,"message":"تراکنش یافت نشد."}
     */
    public function verify(Request $request): JsonResponse
    {
        $result = $this->gatewayCallback->complete(
            $request,
            fn (Transaction $tx) => $tx->type === 'deposit',
            function (Transaction $locked) {
                $user = User::query()->findOrFail($locked->user_id);
                $this->walletService->deposit($user, (int) $locked->amount, $locked);
            }
        );

        if (! $result->ok) {
            return $this->errorResponse($result->message, $result->status);
        }

        $tx = $result->transaction;
        if (! $result->alreadyProcessed && $tx) {
            event(new PaymentSuccessful($tx));
        }

        $balance = $tx
            ? $this->walletService->getBalance($tx->user ?? User::query()->findOrFail($tx->user_id))
            : 0;

        return $this->successResponse([
            'new_balance' => $balance,
            'already_processed' => $result->alreadyProcessed,
        ], $result->alreadyProcessed ? 'کیف پول با موفقیت شارژ شد' : 'کیف پول با موفقیت شارژ شد');
    }
}
