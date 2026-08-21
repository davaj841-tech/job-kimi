<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\TransactionResource;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletLedger;
use App\Services\AuditLogService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletAdminController extends BaseController
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    public function stats(): JsonResponse
    {
        $totalBalance = (int) User::query()->sum('wallet_balance');
        $ledgerTotal = (int) WalletLedger::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as total")
            ->value('total');
        $chargesToday = Transaction::query()
            ->where('type', 'deposit')
            ->where('status', 'success')
            ->whereDate('created_at', today())
            ->count();
        $chargeAmountToday = (int) Transaction::query()
            ->where('type', 'deposit')
            ->where('status', 'success')
            ->whereDate('created_at', today())
            ->sum('amount');

        return $this->successResponse([
            'total_balance' => $totalBalance,
            'ledger_total' => $ledgerTotal,
            'reconciled' => $totalBalance === $ledgerTotal,
            'charges_today' => $chargesToday,
            'charge_amount_today' => $chargeAmountToday,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->withMax('transactions as last_transaction_at', 'created_at')
            ->orderByDesc('wallet_balance');

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('mobile', 'like', "%{$s}%");
            });
        }

        $items = $query->paginate((int) $request->query('per_page', 20));

        $userIds = collect($items->items())->pluck('id');

        $deposits = Transaction::query()
            ->selectRaw('user_id, SUM(amount) as total')
            ->whereIn('user_id', $userIds)
            ->where('type', 'deposit')
            ->where('status', 'success')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $withdrawals = Transaction::query()
            ->selectRaw('user_id, SUM(amount) as total')
            ->whereIn('user_id', $userIds)
            ->whereIn('type', ['withdrawal', 'purchase'])
            ->where('gateway', 'wallet')
            ->where('status', 'success')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $data = $items->getCollection()->map(function (User $user) use ($deposits, $withdrawals): array {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->mobile,
                'balance' => (int) $user->wallet_balance,
                'total_charged' => (int) ($deposits[$user->id] ?? 0),
                'total_withdrawn' => (int) ($withdrawals[$user->id] ?? 0),
                'last_transaction_at' => $user->last_transaction_at,
            ];
        });

        return $this->successResponse([
            'data' => $data,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    public function history(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $query = Transaction::query()->where('user_id', $user->id)->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        $items = $query->paginate((int) $request->query('per_page', 30));

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->mobile,
                'balance' => (int) $user->wallet_balance,
            ],
            'data' => TransactionResource::collection($items->items()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function charge(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $maxCharge = max(1000, (int) Setting::get('max_wallet_charge', 50_000_000));
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000', 'max:'.$maxCharge],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $tx = $this->walletService->adminDeposit(
            $user,
            (int) $data['amount'],
            $data['description'] ?? 'شارژ دستی توسط ادمین'
        );

        app(AuditLogService::class)->log('wallet.admin_charged', $user, null, [
            'amount' => (int) $data['amount'],
            'transaction_id' => $tx->id,
            'description' => $data['description'] ?? 'شارژ دستی توسط ادمین',
        ]);

        return $this->successResponse([
            'balance' => $this->walletService->getBalance($user),
            'transaction' => new TransactionResource($tx),
        ], 'موجودی شارژ شد.');
    }

    public function deduct(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $maxCharge = max(1000, (int) Setting::get('max_wallet_charge', 50_000_000));
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1000', 'max:'.$maxCharge],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $tx = $this->walletService->adminWithdraw(
            $user,
            (int) $data['amount'],
            $data['reason']
        );

        if (! $tx) {
            return $this->errorResponse('موجودی کافی نیست.', 422);
        }

        app(AuditLogService::class)->log('wallet.admin_deducted', $user, null, [
            'amount' => (int) $data['amount'],
            'transaction_id' => $tx->id,
            'reason' => $data['reason'],
        ]);

        return $this->successResponse([
            'balance' => $this->walletService->getBalance($user),
            'transaction' => new TransactionResource($tx),
        ], 'مبلغ از کیف پول کسر شد.');
    }
}
