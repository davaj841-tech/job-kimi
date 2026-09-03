<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Payment\PaymentAction;
use App\Exceptions\IdempotencyException;
use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionAdminController extends BaseController
{
    public function __construct(
        protected TransactionRepository $transactionRepository,
        protected PaymentAction $paymentAction,
        protected AuditLogService $audit,
    ) {}

    public function stats(): JsonResponse
    {
        $today = (int) Transaction::query()->where('status', 'success')->whereDate('created_at', today())->sum('amount');
        $week = (int) Transaction::query()->where('status', 'success')->where('created_at', '>=', now()->startOfWeek())->sum('amount');
        $month = (int) Transaction::query()->where('status', 'success')->where('created_at', '>=', now()->startOfMonth())->sum('amount');

        $successCount = Transaction::query()->where('status', 'success')->count();
        $failedCount = Transaction::query()->where('status', 'failed')->count();
        $pendingCount = Transaction::query()->where('status', 'pending')->count();

        return $this->successResponse([
            'revenue_today' => $today,
            'revenue_week' => $week,
            'revenue_month' => $month,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'pending_count' => $pendingCount,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'status', 'gateway', 'user_id', 'date_from', 'date_to', 'per_page']);
        $transactions = $this->transactionRepository->getAll($filters);

        return $this->successResponse([
            'data' => TransactionResource::collection($transactions->items()),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $tx = Transaction::query()->with('user:id,name,mobile,email')->findOrFail($id);

        return $this->successResponse(new TransactionResource($tx));
    }

    public function refund(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $original = Transaction::query()->findOrFail($id);
        $admin = $request->user();

        $alreadyRefunded = \App\Models\WalletLedger::query()
            ->where('source_key', 'refund:'.$original->id)
            ->exists();

        if (! $alreadyRefunded && ! $original->isRefundable()) {
            return $this->errorResponse('این تراکنش قابل بازگشت وجه نیست.', 422, [
                'code' => 'not_refundable',
            ]);
        }

        try {
            $refundTx = DB::transaction(function () use ($original) {
                return $this->paymentAction->refund($original);
            });
        } catch (IdempotencyException $e) {
            return $this->errorResponse($e->getMessage(), 422, [
                'code' => 'refund_not_allowed',
            ]);
        }

        if (! $alreadyRefunded) {
            $this->audit->log('wallet.refund', $refundTx, [
                'original_transaction_id' => $original->id,
                'original_amount' => (int) $original->amount,
            ], [
                'reason' => $data['reason'],
                'amount' => (int) $refundTx->amount,
                'original_transaction_id' => $original->id,
                'refund_transaction_id' => $refundTx->id,
                'target_user_id' => $original->user_id,
                'admin_id' => $admin?->id,
            ], $admin?->id);
        }

        return $this->successResponse([
            'refund_transaction' => new TransactionResource($refundTx->loadMissing('user')),
            'original_transaction' => new TransactionResource($original->fresh()->loadMissing('user')),
            'already_refunded' => $alreadyRefunded,
        ], $alreadyRefunded ? 'این تراکنش قبلاً بازگشت داده شده بود.' : 'بازگشت وجه با موفقیت انجام شد.');
    }
}
