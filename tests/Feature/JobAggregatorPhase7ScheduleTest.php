<?php

namespace Tests\Feature;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Jobs\Aggregation\CrawlJobSourceJob;
use App\Models\CrawlerRun;
use App\Models\JobSource;
use App\Models\User;
use App\Services\Aggregation\AggregationScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobAggregatorPhase7ScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected AggregationScheduleService $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schedule = app(AggregationScheduleService::class);
    }

    protected function actingAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    protected function enableSchedule(array $times, string $timezone = 'Asia/Tehran'): array
    {
        return $this->schedule->update([
            'enabled' => true,
            'timezone' => $timezone,
            'max_concurrent' => 5,
            'dispatch_delay_seconds' => 0,
            'retry_tries' => 2,
            'times' => $times,
        ]);
    }

    public function test_schedule_disabled_does_not_dispatch(): void
    {
        Queue::fake();
        JobSource::factory()->whitelisted()->create(['domain' => 'a.example.gov.ir']);

        $this->schedule->update([
            'enabled' => false,
            'timezone' => 'Asia/Tehran',
            'times' => [['time' => '02:00', 'enabled' => true]],
        ]);

        $this->travelTo(Carbon::parse('2026-08-08 02:00:00', 'Asia/Tehran'));

        Artisan::call('jobs:aggregate-dispatch');

        Queue::assertNothingPushed();
        $this->assertStringContainsString('disabled', Artisan::output());
    }

    public function test_one_daily_execution_dispatches_on_matching_slot(): void
    {
        Queue::fake();
        JobSource::factory()->whitelisted()->create([
            'domain' => 'once.example.gov.ir',
            'crawl_frequency' => 'hourly',
        ]);

        $this->enableSchedule([
            ['time' => '14:00', 'enabled' => true, 'label' => 'ظهر'],
        ]);

        $this->travelTo(Carbon::parse('2026-08-08 14:00:10', 'Asia/Tehran'));
        Artisan::call('jobs:aggregate-dispatch');

        Queue::assertPushedOn(config('aggregation.queue', 'crawlers'), CrawlJobSourceJob::class);
    }

    public function test_multiple_daily_execution_times(): void
    {
        Queue::fake();
        JobSource::factory()->whitelisted()->create([
            'domain' => 'multi.example.gov.ir',
            'crawl_frequency' => 'hourly',
        ]);

        $this->enableSchedule([
            ['time' => '02:00', 'enabled' => true],
            ['time' => '08:00', 'enabled' => true],
            ['time' => '14:00', 'enabled' => true],
            ['time' => '20:00', 'enabled' => true],
        ]);

        foreach (['02:00', '08:00', '14:00', '20:00'] as $slot) {
            Queue::fake();
            $this->travelTo(Carbon::parse('2026-08-08 '.$slot.':00', 'Asia/Tehran'));
            Artisan::call('jobs:aggregate-dispatch');
            Queue::assertPushed(CrawlJobSourceJob::class);
        }

        Queue::fake();
        $this->travelTo(Carbon::parse('2026-08-08 03:00:00', 'Asia/Tehran'));
        Artisan::call('jobs:aggregate-dispatch');
        Queue::assertNothingPushed();
    }

    public function test_admin_can_add_remove_and_toggle_execution_times(): void
    {
        $this->actingAdmin();

        $this->getJson('/api/v1/admin/aggregation-schedule')
            ->assertOk()
            ->assertJsonPath('data.schedule.enabled', false);

        $this->putJson('/api/v1/admin/aggregation-schedule', [
            'enabled' => true,
            'timezone' => 'Asia/Tehran',
            'times' => [
                ['time' => '02:00', 'enabled' => true, 'label' => 'شب'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.schedule.enabled', true)
            ->assertJsonCount(1, 'data.schedule.times');

        $add = $this->postJson('/api/v1/admin/aggregation-schedule/times', [
            'time' => '08:00',
            'enabled' => true,
            'label' => 'صبح',
        ])->assertCreated();

        $id = collect($add->json('data.schedule.times'))->firstWhere('time', '08:00')['id'];

        $this->putJson("/api/v1/admin/aggregation-schedule/times/{$id}", [
            'enabled' => false,
            'label' => 'صبح غیرفعال',
        ])->assertOk()
            ->assertJsonPath('data.schedule.times.1.enabled', false);

        $this->deleteJson("/api/v1/admin/aggregation-schedule/times/{$id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.schedule.times');
    }

    public function test_disabled_schedule_entries_are_ignored(): void
    {
        Queue::fake();
        JobSource::factory()->whitelisted()->create([
            'domain' => 'offslot.example.gov.ir',
            'crawl_frequency' => 'hourly',
        ]);

        $this->enableSchedule([
            ['time' => '10:00', 'enabled' => false],
            ['time' => '11:00', 'enabled' => true],
        ]);

        $this->travelTo(Carbon::parse('2026-08-08 10:00:00', 'Asia/Tehran'));
        Artisan::call('jobs:aggregate-dispatch');
        Queue::assertNothingPushed();

        $this->travelTo(Carbon::parse('2026-08-08 11:00:00', 'Asia/Tehran'));
        Artisan::call('jobs:aggregate-dispatch');
        Queue::assertPushed(CrawlJobSourceJob::class);
    }

    public function test_timezone_handling_uses_schedule_timezone_not_app_timezone(): void
    {
        Queue::fake();
        config(['app.timezone' => 'UTC']);

        JobSource::factory()->whitelisted()->create([
            'domain' => 'tz.example.gov.ir',
            'crawl_frequency' => 'hourly',
        ]);

        $this->enableSchedule([
            ['time' => '15:30', 'enabled' => true],
        ], 'Asia/Tehran');

        // 12:00 UTC == 15:30 Asia/Tehran (IRST, +03:30)
        $this->travelTo(Carbon::parse('2026-08-08 12:00:00', 'UTC'));
        $this->assertSame('15:30', $this->schedule->nowSlot());
        $this->assertTrue($this->schedule->isDueNow());

        Artisan::call('jobs:aggregate-dispatch');
        Queue::assertPushed(CrawlJobSourceJob::class);
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_source_custom_schedule_overrides_global(): void
    {
        Queue::fake();

        $globalOnly = JobSource::factory()->whitelisted()->create([
            'domain' => 'global.example.gov.ir',
            'schedule_mode' => 'global',
            'crawl_frequency' => 'hourly',
        ]);
        $custom = JobSource::factory()->whitelisted()->create([
            'domain' => 'custom.example.gov.ir',
            'schedule_mode' => 'custom',
            'custom_schedule_times' => [
                ['time' => '09:00', 'enabled' => true],
            ],
            'crawl_frequency' => 'hourly',
        ]);

        // Global slots exclude 09:00; custom source alone owns 09:00.
        $this->enableSchedule([
            ['time' => '02:00', 'enabled' => true],
            ['time' => '14:00', 'enabled' => true],
        ]);

        $this->travelTo(Carbon::parse('2026-08-08 02:00:00', 'Asia/Tehran'));
        Artisan::call('jobs:aggregate-dispatch');
        Queue::assertPushed(CrawlJobSourceJob::class, fn (CrawlJobSourceJob $job) => $job->jobSourceId === $globalOnly->id);
        Queue::assertNotPushed(CrawlJobSourceJob::class, fn (CrawlJobSourceJob $job) => $job->jobSourceId === $custom->id);

        Queue::fake();
        $this->travelTo(Carbon::parse('2026-08-08 09:00:00', 'Asia/Tehran'));
        Artisan::call('jobs:aggregate-dispatch');
        Queue::assertPushed(CrawlJobSourceJob::class, fn (CrawlJobSourceJob $job) => $job->jobSourceId === $custom->id);
        Queue::assertNotPushed(CrawlJobSourceJob::class, fn (CrawlJobSourceJob $job) => $job->jobSourceId === $globalOnly->id);
    }

    public function test_only_enabled_and_approved_sources_are_dispatched(): void
    {
        Queue::fake();

        $ok = JobSource::factory()->whitelisted()->create([
            'domain' => 'ok.example.gov.ir',
            'crawl_frequency' => 'hourly',
        ]);
        JobSource::factory()->create([
            'domain' => 'disabled.example.gov.ir',
            'is_enabled' => false,
            'is_approved' => true,
            'crawl_frequency' => 'hourly',
        ]);
        JobSource::factory()->create([
            'domain' => 'unapproved.example.gov.ir',
            'is_enabled' => true,
            'is_approved' => false,
            'crawl_frequency' => 'hourly',
        ]);

        $this->enableSchedule([['time' => '07:00', 'enabled' => true]]);
        $this->travelTo(Carbon::parse('2026-08-08 07:00:00', 'Asia/Tehran'));
        Artisan::call('jobs:aggregate-dispatch');

        Queue::assertPushed(CrawlJobSourceJob::class, 1);
        Queue::assertPushed(CrawlJobSourceJob::class, fn (CrawlJobSourceJob $job) => $job->jobSourceId === $ok->id);
    }

    public function test_busy_source_is_skipped_while_others_dispatch(): void
    {
        Queue::fake();

        $busy = JobSource::factory()->whitelisted()->create([
            'domain' => 'busy.example.gov.ir',
            'crawl_frequency' => 'hourly',
        ]);
        $free = JobSource::factory()->whitelisted()->create([
            'domain' => 'free.example.gov.ir',
            'crawl_frequency' => 'hourly',
        ]);

        CrawlerRun::query()->create([
            'job_source_id' => $busy->id,
            'status' => CrawlerRunStatus::Running,
            'started_at' => now(),
            'jobs_found' => 0,
            'jobs_created' => 0,
            'jobs_updated' => 0,
            'duplicates' => 0,
            'errors_count' => 0,
        ]);

        $this->enableSchedule([['time' => '06:00', 'enabled' => true]]);
        $this->travelTo(Carbon::parse('2026-08-08 06:00:00', 'Asia/Tehran'));
        Artisan::call('jobs:aggregate-dispatch');

        Queue::assertPushed(CrawlJobSourceJob::class, 1);
        Queue::assertPushed(CrawlJobSourceJob::class, fn (CrawlJobSourceJob $job) => $job->jobSourceId === $free->id);
        Queue::assertNotPushed(CrawlJobSourceJob::class, fn (CrawlJobSourceJob $job) => $job->jobSourceId === $busy->id);
        $this->assertStringContainsString('Skip busy', Artisan::output());
    }

    public function test_scheduler_does_not_crawl_synchronously_by_default(): void
    {
        Queue::fake();
        Http::fake();

        JobSource::factory()->whitelisted()->create([
            'domain' => 'async.example.gov.ir',
            'crawl_frequency' => 'hourly',
        ]);

        $this->enableSchedule([['time' => '01:00', 'enabled' => true]]);
        $this->travelTo(Carbon::parse('2026-08-08 01:00:00', 'Asia/Tehran'));
        Artisan::call('jobs:aggregate-dispatch');

        Queue::assertPushedOn(config('aggregation.queue', 'crawlers'), CrawlJobSourceJob::class);
        Http::assertNothingSent();
    }

    public function test_crawl_frequency_minimum_interval_is_respected(): void
    {
        Queue::fake();

        JobSource::factory()->whitelisted()->create([
            'domain' => 'freq.example.gov.ir',
            'crawl_frequency' => 'every_6_hours',
            'last_crawled_at' => Carbon::parse('2026-08-08 10:00:00', 'Asia/Tehran'),
        ]);

        $this->enableSchedule([
            ['time' => '12:00', 'enabled' => true],
            ['time' => '16:00', 'enabled' => true],
        ]);

        $this->travelTo(Carbon::parse('2026-08-08 12:00:00', 'Asia/Tehran'));
        Artisan::call('jobs:aggregate-dispatch');
        Queue::assertNothingPushed();

        $this->travelTo(Carbon::parse('2026-08-08 16:00:00', 'Asia/Tehran'));
        Artisan::call('jobs:aggregate-dispatch');
        Queue::assertPushed(CrawlJobSourceJob::class);
    }

    public function test_validation_rejects_invalid_duplicate_and_bad_timezone(): void
    {
        $this->actingAdmin();

        $this->putJson('/api/v1/admin/aggregation-schedule', [
            'enabled' => true,
            'timezone' => 'Not/AZone',
            'times' => [['time' => '08:00', 'enabled' => true]],
        ])->assertStatus(422);

        $this->putJson('/api/v1/admin/aggregation-schedule', [
            'enabled' => true,
            'timezone' => 'Asia/Tehran',
            'times' => [
                ['time' => '25:99', 'enabled' => true],
            ],
        ])->assertStatus(422);

        $this->putJson('/api/v1/admin/aggregation-schedule', [
            'enabled' => true,
            'timezone' => 'Asia/Tehran',
            'times' => [
                ['time' => '08:00', 'enabled' => true],
                ['time' => '08:00', 'enabled' => true],
            ],
        ])->assertStatus(422);

        $this->putJson('/api/v1/admin/aggregation-schedule', [
            'enabled' => true,
            'timezone' => 'Asia/Tehran',
            'times' => [],
        ])->assertStatus(422);
    }

    public function test_laravel_scheduler_registers_aggregate_dispatch_every_minute(): void
    {
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $found = collect($events)->contains(
            fn ($e) => str_contains($e->command ?? $e->description ?? '', 'jobs:aggregate-dispatch')
                || str_contains(json_encode($e), 'jobs:aggregate-dispatch')
        );

        $this->assertTrue(
            $found || collect($events)->contains(fn ($e) => property_exists($e, 'command') && str_contains((string) $e->command, 'aggregate-dispatch')),
            'Expected jobs:aggregate-dispatch on the Laravel schedule'
        );
    }

    public function test_manual_dispatch_now_endpoint_queues_without_sync_crawl(): void
    {
        Queue::fake();
        Http::fake();
        $this->actingAdmin();

        JobSource::factory()->whitelisted()->create([
            'domain' => 'manual.example.gov.ir',
            'crawl_frequency' => 'hourly',
        ]);

        $this->postJson('/api/v1/admin/aggregation-schedule/dispatch-now', ['dry_run' => false])
            ->assertOk()
            ->assertJsonPath('data.queue', config('aggregation.queue', 'crawlers'));

        Queue::assertPushedOn(config('aggregation.queue', 'crawlers'), CrawlJobSourceJob::class);
        Http::assertNothingSent();
    }

    public function test_job_unique_id_is_source_id(): void
    {
        $job = new CrawlJobSourceJob(42);
        $this->assertSame('42', $job->uniqueId());
        $this->assertSame(config('aggregation.queue', 'crawlers'), $job->queue);
    }
}
