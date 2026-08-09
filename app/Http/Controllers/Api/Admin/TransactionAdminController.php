<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Repositories\TransactionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionAdminController extends BaseController
{
    public function __construct(
        protected TransactionRepository $transactionRepository
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
}
