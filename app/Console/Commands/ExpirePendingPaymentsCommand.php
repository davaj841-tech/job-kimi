<?php

namespace App\Console\Commands;

use App\Services\Payment\GatewayCallbackService;
use Illuminate\Console\Command;

class ExpirePendingPaymentsCommand extends Command
{
    protected $signature = 'payments:expire-pending';

    protected $description = 'Mark abandoned gateway payments as expired after the configured TTL';

    public function handle(GatewayCallbackService $callbacks): int
    {
        $count = $callbacks->expireStalePending();
        $this->info("Expired {$count} pending payment(s).");

        return self::SUCCESS;
    }
}
