<?php

namespace App\Console\Commands;

use Illuminate\Queue\Console\MonitorCommand as FrameworkMonitorCommand;

/**
 * Safer queue:monitor: queues argument is optional with safe defaults.
 * Auto-discovered after the framework command so this signature wins.
 */
class QueueMonitorCommand extends FrameworkMonitorCommand
{
    protected $signature = 'queue:monitor
                       {queues? : Comma-separated queue names (default: configured queues)}
                       {--max=1000 : The maximum number of jobs that can be on the queue before an event is dispatched}
                       {--json : Output the queue size as JSON}';

    public function handle()
    {
        $queues = $this->argument('queues');
        if ($queues === null || $queues === '') {
            $queues = $this->defaultQueues();
            if (! $this->option('json')) {
                $this->comment("No queues specified; monitoring: {$queues}");
            }
            $this->input->setArgument('queues', $queues);
        }

        parent::handle();
    }

    protected function defaultQueues(): string
    {
        $connection = (string) config('queue.default', 'sync');
        $primary = (string) config("queue.connections.{$connection}.queue", 'default');
        if ($primary === '') {
            $primary = 'default';
        }

        $names = array_values(array_unique(array_filter([
            $primary,
            'default',
            'crawlers',
        ])));

        return implode(',', $names);
    }
}
