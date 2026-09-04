<?php

namespace Tests\Feature;

use App\Enums\Aggregation\JobSourceQualityStatus;
use App\Models\Feature;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Services\Aggregation\AggregationAlertNotifier;
use App\Services\Aggregation\AggregationHealthService;
use App\Services\Aggregation\SourceHealthService;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AggregationHealthAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('aggregation.alerts.stale_crawl_hours', 6);
        config()->set('aggregation.alerts.pending_jobs_threshold', 3);
        config()->set('aggregation.alerts.notify_cooldown_minutes', 60);
    }

    protected function actingAdmin(): User
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        Sanctum::actingAs($admin);

        return $admin;
    }

    protected function enableCrawler(): void
    {
        app(FeatureFlagService::class)->enable('job-crawler');
    }

    protected function source(array $attrs = []): JobSource
    {
        return JobSource::factory()->whitelisted()->create(array_merge([
            'quality_status' => JobSourceQualityStatus::Active,
            'domain' => 'alert-'.uniqid().'.example.gov.ir',
        ], $attrs));
    }

    public function test_stale_crawl_alert_when_last_run_older_than_six_hours(): void
    {
        $this->enableCrawler();
        $this->source(['last_crawled_at' => now()->subHours(7)]);

        $snapshot = app(AggregationHealthService::class)->snapshot();
        $codes = collect($snapshot['alerts'])->pluck('code')->all();

        $this->assertContains(AggregationHealthService::ALERT_STALE_CRAWL, $codes);
        $this->assertSame('warn', $snapshot['checks']['last_crawl']);
    }

    public function test_no_stale_crawl_alert_when_last_run_is_recent(): void
    {
        $this->enableCrawler();
        $this->source([
            'last_crawled_at' => now()->subHours(2),
            'last_crawl_outcome' => SourceHealthService::OUTCOME_SUCCESS,
        ]);

        $snapshot = app(AggregationHealthService::class)->snapshot();
        $codes = collect($snapshot['alerts'])->pluck('code')->all();

        $this->assertNotContains(AggregationHealthService::ALERT_STALE_CRAWL, $codes);
    }

    public function test_all_sources_failed_alert(): void
    {
        $this->enableCrawler();
        $this->source([
            'last_crawled_at' => now()->subHour(),
            'last_crawl_outcome' => SourceHealthService::OUTCOME_HTTP_FAILURE,
            'consecutive_failures' => 3,
        ]);
        $this->source([
            'last_crawled_at' => now()->subMinutes(30),
            'last_crawl_outcome' => SourceHealthService::OUTCOME_QUALITY_FAILURE,
            'consecutive_failures' => 1,
        ]);

        $snapshot = app(AggregationHealthService::class)->snapshot();
        $codes = collect($snapshot['alerts'])->pluck('code')->all();

        $this->assertContains(AggregationHealthService::ALERT_ALL_SOURCES_FAILED, $codes);
        $this->assertSame('critical', $snapshot['checks']['all_sources_failed']);
        $this->assertSame('critical', $snapshot['status']);
    }

    public function test_all_sources_failed_not_raised_when_one_succeeded(): void
    {
        $this->enableCrawler();
        $this->source([
            'last_crawled_at' => now()->subHour(),
            'last_crawl_outcome' => SourceHealthService::OUTCOME_HTTP_FAILURE,
        ]);
        $this->source([
            'last_crawled_at' => now()->subHour(),
            'last_crawl_outcome' => SourceHealthService::OUTCOME_SUCCESS,
        ]);

        $snapshot = app(AggregationHealthService::class)->snapshot();
        $codes = collect($snapshot['alerts'])->pluck('code')->all();

        $this->assertNotContains(AggregationHealthService::ALERT_ALL_SOURCES_FAILED, $codes);
    }

    public function test_feature_flag_disabled_alert(): void
    {
        Feature::query()->create([
            'name' => 'job-crawler',
            'enabled' => false,
            'description' => 'خزشگر',
        ]);
        app(FeatureFlagService::class)->forgetCache();

        $snapshot = app(AggregationHealthService::class)->snapshot();
        $codes = collect($snapshot['alerts'])->pluck('code')->all();

        $this->assertContains(AggregationHealthService::ALERT_FEATURE_DISABLED, $codes);
        $this->assertSame('critical', $snapshot['checks']['feature_flag']);
    }

    public function test_pending_jobs_backlog_alert_when_over_threshold(): void
    {
        $this->enableCrawler();
        $source = $this->source([
            'last_crawled_at' => now()->subHour(),
            'last_crawl_outcome' => SourceHealthService::OUTCOME_SUCCESS,
        ]);
        JobPost::factory()->count(3)->create([
            'job_source_id' => $source->id,
            'status' => 'pending',
        ]);

        $snapshot = app(AggregationHealthService::class)->snapshot();
        $codes = collect($snapshot['alerts'])->pluck('code')->all();

        $this->assertContains(AggregationHealthService::ALERT_PENDING_BACKLOG, $codes);
        $this->assertSame(3, $snapshot['jobs']['pending']);
        $this->assertSame('warn', $snapshot['checks']['pending_backlog']);
    }

    public function test_health_endpoint_exposes_alerts(): void
    {
        $this->actingAdmin();
        Feature::query()->create([
            'name' => 'job-crawler',
            'enabled' => false,
            'description' => 'خزشگر',
        ]);
        app(FeatureFlagService::class)->forgetCache();

        $this->getJson('/api/v1/admin/aggregation/health')
            ->assertOk()
            ->assertJsonFragment(['code' => AggregationHealthService::ALERT_FEATURE_DISABLED])
            ->assertJsonStructure([
                'data' => [
                    'alerts' => [
                        ['code', 'severity', 'title', 'message', 'link'],
                    ],
                ],
            ]);
    }

    public function test_dashboard_includes_alerts_payload(): void
    {
        $this->actingAdmin();
        $this->enableCrawler();
        $this->source(['last_crawled_at' => now()->subHours(8)]);

        $this->getJson('/api/v1/admin/dashboard-stats')
            ->assertOk()
            ->assertJsonFragment(['code' => AggregationHealthService::ALERT_STALE_CRAWL]);
    }

    public function test_notifier_sends_database_notification_for_each_alert(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $this->enableCrawler();
        $source = $this->source([
            'last_crawled_at' => now()->subHours(8),
            'last_crawl_outcome' => SourceHealthService::OUTCOME_HTTP_FAILURE,
        ]);
        JobPost::factory()->count(3)->create([
            'job_source_id' => $source->id,
            'status' => 'pending',
        ]);

        $result = app(AggregationAlertNotifier::class)->notifyAdmins();

        $this->assertGreaterThanOrEqual(3, $result['sent']);
        $this->assertContains(AggregationHealthService::ALERT_STALE_CRAWL, $result['alerts']);
        $this->assertContains(AggregationHealthService::ALERT_ALL_SOURCES_FAILED, $result['alerts']);
        $this->assertContains(AggregationHealthService::ALERT_PENDING_BACKLOG, $result['alerts']);

        Notification::assertSentTo(
            $admin,
            GenericDatabaseNotification::class,
            fn (GenericDatabaseNotification $n) => $n->type === 'aggregation_alert'
                && $n->extra['code'] === AggregationHealthService::ALERT_STALE_CRAWL
        );
        Notification::assertSentTo(
            $admin,
            GenericDatabaseNotification::class,
            fn (GenericDatabaseNotification $n) => $n->extra['code'] === AggregationHealthService::ALERT_ALL_SOURCES_FAILED
        );
        Notification::assertSentTo(
            $admin,
            GenericDatabaseNotification::class,
            fn (GenericDatabaseNotification $n) => $n->extra['code'] === AggregationHealthService::ALERT_PENDING_BACKLOG
        );
    }

    public function test_notifier_skips_duplicate_alerts_within_cooldown(): void
    {
        Notification::fake();
        User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->enableCrawler();
        $this->source(['last_crawled_at' => now()->subHours(9)]);

        $notifier = app(AggregationAlertNotifier::class);
        $first = $notifier->notifyAdmins();
        $second = $notifier->notifyAdmins();

        $this->assertGreaterThan(0, $first['sent']);
        $this->assertSame(0, $second['sent']);
        $this->assertGreaterThan(0, $second['skipped']);
    }

    public function test_notify_command_dispatches_feature_flag_alert(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Feature::query()->create([
            'name' => 'job-crawler',
            'enabled' => false,
            'description' => 'خزشگر',
        ]);
        app(FeatureFlagService::class)->forgetCache();

        $exit = Artisan::call('aggregation:notify-alerts');

        $this->assertSame(0, $exit);
        Notification::assertSentTo(
            $admin,
            GenericDatabaseNotification::class,
            fn (GenericDatabaseNotification $n) => $n->extra['code'] === AggregationHealthService::ALERT_FEATURE_DISABLED
        );
    }
}
