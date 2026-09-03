<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WalletLedger;
use App\Services\AuditLogService;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WalletReconcileCommand extends Command
{
    protected $signature = 'wallet:reconcile
                            {--user= : Reconcile a single user ID}
                            {--limit=500 : Max users to scan when checking all}';

    protected $description = 'Compare cached wallet_balance with ledger sums and report drift (read-only)';

    public function handle(WalletService $wallets, AuditLogService $audit): int
    {
        $userId = $this->option('user');

        if ($userId !== null && $userId !== '') {
            return $this->reconcileOne($wallets, (int) $userId);
        }

        $totalBalance = (int) User::query()->sum('wallet_balance');
        $ledgerTotal = (int) WalletLedger::query()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) as total")
            ->value('total');

        $this->info('System-wide totals:');
        $this->table(['Metric', 'Amount (IRR)'], [
            ['Cached balance sum', (string) $totalBalance],
            ['Ledger calculated sum', (string) $ledgerTotal],
            ['Difference', (string) ($totalBalance - $ledgerTotal)],
        ]);

        if ($totalBalance !== $ledgerTotal) {
            Log::critical('Wallet system-wide reconciliation mismatch', [
                'cached_total' => $totalBalance,
                'ledger_total' => $ledgerTotal,
            ]);
            $audit->log('wallet.reconcile_failed', null, null, [
                'scope' => 'system',
                'cached_total' => $totalBalance,
                'ledger_total' => $ledgerTotal,
            ]);
            $this->error('CRITICAL FINANCIAL INTEGRITY ERROR — system totals diverged.');
        } else {
            $this->info('System-wide reconciliation: PASS');
        }

        $limit = max(1, (int) $this->option('limit'));
        $mismatches = 0;

        User::query()->orderBy('id')->limit($limit)->each(function (User $user) use ($wallets, &$mismatches) {
            $result = $wallets->reconcile($user);
            if (! $result['ok']) {
                $mismatches++;
                $this->warn("User #{$user->id}: cached={$result['cached']} ledger={$result['ledger']}");
            }
        });

        if ($mismatches > 0) {
            Log::critical('Wallet per-user reconciliation mismatches', ['count' => $mismatches]);
            $this->error("{$mismatches} user(s) with balance/ledger drift (scanned up to {$limit}).");

            return self::FAILURE;
        }

        $this->info('Per-user reconciliation: PASS');

        return $totalBalance === $ledgerTotal ? self::SUCCESS : self::FAILURE;
    }

    protected function reconcileOne(WalletService $wallets, int $userId): int
    {
        $user = User::query()->find($userId);
        if (! $user) {
            $this->error("User #{$userId} not found.");

            return self::FAILURE;
        }

        $result = $wallets->reconcile($user);
        $this->table(['Field', 'Value'], [
            ['User ID', (string) $userId],
            ['Cached balance', (string) $result['cached']],
            ['Ledger balance', (string) $result['ledger']],
            ['OK', $result['ok'] ? 'yes' : 'no'],
        ]);

        if (! $result['ok']) {
            Log::critical('Wallet user reconciliation mismatch', [
                'user_id' => $userId,
                'cached' => $result['cached'],
                'ledger' => $result['ledger'],
            ]);
            $this->error('CRITICAL FINANCIAL INTEGRITY ERROR');

            return self::FAILURE;
        }

        $this->info('Reconciliation: PASS');

        return self::SUCCESS;
    }
}
