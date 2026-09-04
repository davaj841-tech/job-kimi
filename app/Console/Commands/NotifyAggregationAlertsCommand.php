<?php

namespace App\Console\Commands;

use App\Services\Aggregation\AggregationAlertNotifier;
use Illuminate\Console\Command;

class NotifyAggregationAlertsCommand extends Command
{
    protected $signature = 'aggregation:notify-alerts';

    protected $description = 'Push aggregation health alerts to admin in-panel notifications';

    public function handle(AggregationAlertNotifier $notifier): int
    {
        $result = $notifier->notifyAdmins();

        $this->info('Alerts: '.implode(', ', $result['alerts'] ?: ['none']));
        $this->line('Sent: '.$result['sent'].' | Skipped (cooldown): '.$result['skipped']);

        return self::SUCCESS;
    }
}
