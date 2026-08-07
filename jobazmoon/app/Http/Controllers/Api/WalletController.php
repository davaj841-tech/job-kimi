<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends BaseController
{
    public function __construct(
        protected WalletService $walletService,
        protected PaymentService $paymentService,
        protected TransactionRepository $transactionRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $transactions = $this->transactionRepository->recentForUser($user, 20);

        return $this->successResponse([
            'balance' => $this->walletService->getBalance($user),
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

    public function charge(Request $request): JsonResponse
    {
        $minCharge = (int) Setting::get('min_wallet_charge', 10000);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:'.$minCharge],
            'gateway' => ['nullable', 'string', 'in:zarinpal,nextpay,idpay'],
        ]);

        $amount = (int) $data['amount'];
        $gateway = $this->paymentService->resolveGatewayName($data['gateway'] ?? null);
        $user = $request->user();

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'deposit',
            'gateway' => $gateway,
            'status' => 'pending',
            'description' => 'شارژ کیف پول',
        ]);

        $callback = url('/payment/wallet');
        $result = $this->paymentService->initiate(
            $gateway,
            $amount,
            'شارژ کیف پول JobAzmoon',
            $callback,
            ['order_id' => (string) $transaction->id]
        );

        if ($result['error'] || ! $result['authority']) {
            $transaction->update(['status' => 'failed']);

            return $this->errorResponse($result['error'] ?? 'خطا در اتصال به درگاه پرداخت.', 400);
        }

        $transaction->update(['reference_id' => $result['authority']]);

        return $this->successResponse([
            'payment_url' => $result['payment_url'],
            'transaction_id' => $transaction->id,
            'gateway' => $gateway,
        ], 'در حال انتقال به درگاه پرداخت');
    }

    public function verify(Request $request): JsonResponse
    {
        $authority = $this->paymentService->extractAuthority($request);

        if ($authority === '') {
            return $this->errorResponse('شناسه پرداخت نامعتبر است.', 422);
        }

        $transaction = $this->transactionRepository->getByReference($authority);

        if (! $transaction || $transaction->type !== 'deposit') {
            return $this->errorResponse('تراکنش یافت نشد.', 404);
        }

        if ($transaction->status === 'success') {
            $balance = $this->walletService->getBalance($transaction->user);

            return $this->successResponse([
                'new_balance' => $balance,
            ], 'کیف پول با موفقیت شارژ شد');
        }

        $gateway = $transaction->gateway ?: 'zarinpal';

        if (! $this->paymentService->callbackSucceeded($request, $gateway)) {
            $transaction->update(['status' => 'failed']);

            return $this->errorResponse('پرداخت ناموفق بود', 400);
        }

        $verify = $this->paymentService->verify(
            $gateway,
            $authority,
            (int) $transaction->amount,
            ['order_id' => (string) $transaction->id]
        );

        if (! $verify['success']) {
            $transaction->update(['status' => 'failed']);

            return $this->errorResponse($verify['error'] ?? 'پرداخت ناموفق بود', 400);
        }

        $this->paymentService->depositToWallet($transaction->user, (int) $transaction->amount, $transaction);

        if ($verify['ref_id']) {
            $transaction->update([
                'description' => trim(($transaction->description ?? '').' | RefID: '.$verify['ref_id']),
            ]);
        }

        event(new \App\Events\PaymentSuccessful($transaction->fresh()));

        $newBalance = $this->walletService->getBalance($transaction->user);

        return $this->successResponse([
            'new_balance' => $newBalance,
        ], 'کیف پول با موفقیت شارژ شد');
    }
}
