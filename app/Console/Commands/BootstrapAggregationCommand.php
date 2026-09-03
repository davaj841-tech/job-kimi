<?php

namespace App\Console\Commands;

use App\Models\JobSource;
use App\Services\Aggregation\AggregationScheduleService;
use App\Services\FeatureFlagService;
use Database\Seeders\PilotJobSourceSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * One-shot setup for job aggregation: seed official sources + default schedule.
 */
class BootstrapAggregationCommand extends Command
{
    protected $signature = 'jobs:bootstrap-aggregation
                            {--seed-sources : Seed official sources from config}
                            {--enable-schedule : Enable schedule with default daily times if empty}';

    protected $description = 'Seed aggregation sources and default schedule for automatic job collection';

    public function handle(AggregationScheduleService $schedule, FeatureFlagService $features): int
    {
        $seedSources = (bool) $this->option('seed-sources');
        $enableSchedule = (bool) $this->option('enable-schedule');

        if (! $seedSources && ! $enableSchedule) {
            $seedSources = JobSource::query()->count() === 0;
            $enableSchedule = ! $schedule->isEnabled() || $schedule->enabledGlobalTimes() === [];
        }

        // Always ensure the gate flag is on — DatabaseSeeder historically left it off.
        $features->enable('job-crawler');
        $this->info('Feature flag job-crawler: enabled');

        if ($seedSources) {
            $before = JobSource::query()->count();
            $this->call('db:seed', ['--class' => PilotJobSourceSeeder::class, '--force' => true]);
            $after = JobSource::query()->count();
            $this->info('Job sources: '.$before.' → '.$after.' (dispatchable: '.JobSource::dispatchable()->count().')');
        }

        if ($enableSchedule) {
            $config = $schedule->get();
            $times = $config['times'] ?? [];
            if (collect($times)->where('enabled', true)->isEmpty()) {
                foreach (['06:30', '12:30', '18:30'] as $time) {
                    $times[] = [
                        'id' => (string) Str::uuid(),
                        'time' => $time,
                        'enabled' => true,
                        'label' => 'خزش خودکار',
                    ];
                }
            }

            $updated = $schedule->update([
                'enabled' => true,
                'timezone' => $config['timezone'] ?: 'Asia/Tehran',
                'times' => $times,
            ]);

            $this->info('Schedule enabled. Times: '.implode(', ', $schedule->enabledGlobalTimes()));
            $this->line('Timezone: '.$updated['timezone']);
        }

        $this->newLine();
        $this->line('Next: php artisan jobs:aggregate-dispatch --force --sync');
        $this->line('Cron: * * * * * php artisan schedule:run');
        $this->line('Queue (if database): processed automatically via scheduler when QUEUE_CONNECTION=database');

        return self::SUCCESS;
    }
}
