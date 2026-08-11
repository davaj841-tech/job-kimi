<?php

namespace Tests\Feature;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobEndpointType;
use App\Enums\Aggregation\JobSourceQualityStatus;
use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Aggregation\JobSourceType;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use App\Models\User;
use App\Services\Aggregation\CrawlOrchestrator;
use App\Services\Aggregation\JobSourceManager;
use App\Services\Aggregation\SourceHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobAggregatorPhase9HealthTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    protected function makeSource(array $attrs = []): JobSource
    {
        $source = JobSource::factory()->whitelisted()->create(array_merge([
            'domain' => 'jobs.example.gov.ir',
            'official_url' => 'https://jobs.example.gov.ir/',
            'source_type' => JobSourceType::Government,
            'reliability_level' => JobSourceReliability::Official,
            'crawler_type' => JobCrawlerType::Rss,
            'quality_status' => JobSourceQualityStatus::Active,
            'crawl_frequency' => 'hourly',
        ], $attrs));

        JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://jobs.example.gov.ir/feed.xml',
            'endpoint_type' => JobEndpointType::Rss,
            'parser_type' => 'employment_keyword_rss',
            'is_enabled' => true,
        ]);

        return $source->fresh(['endpoints']);
    }

    protected function emptyRss(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
<title>News</title>
<item><title>گزارش عملیات پولی</title><link>https://jobs.example.gov.ir/1</link></item>
</channel></rss>
XML;
    }

    protected function employmentRss(): string
    {
        return file_get_contents(base_path('tests/Fixtures/Aggregation/cbi_news_rss.xml'));
    }

    public function test_successful_crawl_updates_health_to_active(): void
    {
        $source = $this->makeSource(['quality_status' => JobSourceQualityStatus::Limited]);
        Http::fake([
            'jobs.example.gov.ir/*' => Http::response($this->employmentRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);
        $source->refresh();

        $this->assertSame(CrawlerRunStatus::Completed, $result['run']->status);
        $this->assertSame(SourceHealthService::OUTCOME_SUCCESS, $result['health']['outcome']);
        $this->assertSame(JobSourceQualityStatus::Active, $source->quality_status);
        $this->assertSame(0, $source->consecutive_failures);
        $this->assertSame(1, $source->total_successful_crawls);
        $this->assertNull($source->health_backoff_until);
        $this->assertGreaterThanOrEqual(1, $source->lifetime_jobs_created);
    }

    public function test_empty_successful_crawl_is_not_http_failure(): void
    {
        $source = $this->makeSource(['quality_status' => JobSourceQualityStatus::Active]);
        Http::fake([
            'jobs.example.gov.ir/*' => Http::response($this->emptyRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);
        $source->refresh();

        $this->assertSame(CrawlerRunStatus::Completed, $result['run']->status);
        $this->assertSame(SourceHealthService::OUTCOME_EMPTY_SUCCESS, $result['health']['outcome']);
        $this->assertSame(0, $source->consecutive_failures);
        $this->assertSame(1, $source->consecutive_empty_crawls);
        $this->assertSame(1, $source->total_empty_successful_crawls);
        $this->assertSame(JobSourceQualityStatus::Limited, $source->quality_status);
        $this->assertNotNull($source->last_success_at);
    }

    public function test_403_increments_failures_and_trips_unavailable(): void
    {
        config(['aggregation.health.consecutive_failure_threshold' => 3]);
        $source = $this->makeSource();

        Http::fake(['jobs.example.gov.ir/*' => Http::response('forbidden', 403)]);

        for ($i = 0; $i < 3; $i++) {
            app(CrawlOrchestrator::class)->crawlSource($source->fresh());
        }

        $source->refresh();
        $this->assertSame(3, $source->consecutive_failures);
        $this->assertSame(JobSourceQualityStatus::TemporarilyUnavailable, $source->quality_status);
        $this->assertNotNull($source->health_backoff_until);
        $this->assertFalse($source->allowsAutomaticCrawl());
        $this->assertTrue($source->is_approved); // never auto-unapprove
    }

    public function test_timeout_counts_as_http_failure(): void
    {
        $source = $this->makeSource();
        Http::fake([
            'jobs.example.gov.ir/*' => function () {
                throw new ConnectionException('cURL error 28: Connection timed out');
            },
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);
        $source->refresh();

        $this->assertSame(CrawlerRunStatus::Failed, $result['run']->status);
        $this->assertSame(SourceHealthService::OUTCOME_HTTP_FAILURE, $result['health']['outcome']);
        $this->assertSame(1, $source->consecutive_failures);
        $this->assertSame(1, $source->total_failed_crawls);
    }

    public function test_5xx_failure_is_http_failure(): void
    {
        $source = $this->makeSource();
        Http::fake(['jobs.example.gov.ir/*' => Http::response('oops', 503)]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);
        $source->refresh();

        $this->assertSame(SourceHealthService::OUTCOME_HTTP_FAILURE, $result['health']['outcome']);
        $this->assertSame(503, $source->last_http_status);
    }

    public function test_recovery_after_successful_manual_test(): void
    {
        $source = $this->makeSource([
            'quality_status' => JobSourceQualityStatus::TemporarilyUnavailable,
            'consecutive_failures' => 5,
            'health_backoff_until' => now()->addHour(),
        ]);

        Http::fake([
            'jobs.example.gov.ir/*' => Http::response($this->employmentRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source, true);
        $source->refresh();

        $this->assertSame(SourceHealthService::OUTCOME_SUCCESS, $result['health']['outcome']);
        $this->assertSame(JobSourceQualityStatus::Active, $source->quality_status);
        $this->assertSame(0, $source->consecutive_failures);
        $this->assertNull($source->health_backoff_until);
    }

    public function test_empty_success_recovers_unavailable_to_limited(): void
    {
        $source = $this->makeSource([
            'quality_status' => JobSourceQualityStatus::TemporarilyUnavailable,
            'consecutive_failures' => 4,
        ]);
        Http::fake([
            'jobs.example.gov.ir/*' => Http::response($this->emptyRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        app(CrawlOrchestrator::class)->crawlSource($source, true);
        $source->refresh();

        $this->assertSame(JobSourceQualityStatus::Limited, $source->quality_status);
        $this->assertSame(0, $source->consecutive_failures);
    }

    public function test_manual_only_never_auto_changes(): void
    {
        $source = $this->makeSource(['quality_status' => JobSourceQualityStatus::ManualOnly]);
        Http::fake([
            'jobs.example.gov.ir/*' => Http::response($this->employmentRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        app(CrawlOrchestrator::class)->crawlSource($source);
        $source->refresh();

        $this->assertSame(JobSourceQualityStatus::ManualOnly, $source->quality_status);
    }

    public function test_approval_never_auto_set_true(): void
    {
        $source = $this->makeSource([
            'is_approved' => false,
            'is_enabled' => true,
            'quality_status' => JobSourceQualityStatus::TemporarilyUnavailable,
        ]);

        $this->expectException(\RuntimeException::class);
        app(CrawlOrchestrator::class)->crawlSource($source);
    }

    public function test_backoff_excludes_source_from_dispatchable(): void
    {
        $source = $this->makeSource([
            'quality_status' => JobSourceQualityStatus::Active,
            'health_backoff_until' => now()->addMinutes(30),
        ]);

        $this->assertFalse($source->allowsAutomaticCrawl());
        $this->assertFalse(
            app(JobSourceManager::class)->dispatchableSources()->contains(fn ($s) => $s->id === $source->id)
        );
    }

    public function test_admin_can_disable_enable_and_reset_health(): void
    {
        $this->actingAdmin();
        $source = $this->makeSource([
            'consecutive_failures' => 4,
            'health_backoff_until' => now()->addHour(),
        ]);

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/disable")
            ->assertOk()
            ->assertJsonPath('data.is_enabled', false);

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/enable")
            ->assertOk()
            ->assertJsonPath('data.is_enabled', true);

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/reset-health")
            ->assertOk()
            ->assertJsonPath('data.consecutive_failures', 0)
            ->assertJsonPath('data.health_backoff_until', null);
    }

    public function test_manual_retest_returns_health_payload(): void
    {
        $this->actingAdmin();
        $source = $this->makeSource();
        Http::fake([
            'jobs.example.gov.ir/*' => Http::response($this->employmentRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/test-crawl")
            ->assertOk()
            ->assertJsonPath('data.summary.found', 1)
            ->assertJsonPath('data.health.outcome', SourceHealthService::OUTCOME_SUCCESS)
            ->assertJsonPath('data.quality_status', JobSourceQualityStatus::Active->value);
    }

    public function test_dashboard_stats_include_health_and_alerts(): void
    {
        $this->actingAdmin();
        $this->makeSource([
            'consecutive_failures' => 5,
            'quality_status' => JobSourceQualityStatus::TemporarilyUnavailable,
        ]);

        $this->getJson('/api/v1/admin/aggregation/quality-stats')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'source_health' => ['healthy', 'limited', 'temporarily_unavailable', 'manual_only', 'unhealthy_sources'],
                    'crawl_quality' => ['total_crawls', 'successful_crawls', 'failed_crawls', 'empty_successful_crawls'],
                    'alerts',
                ],
            ])
            ->assertJsonPath('data.source_health.temporarily_unavailable', 1);
    }

    public function test_high_rejection_marks_limited(): void
    {
        $source = JobSource::factory()->whitelisted()->create([
            'domain' => 'jobs.example.gov.ir',
            'official_url' => 'https://jobs.example.gov.ir/',
            'crawler_type' => JobCrawlerType::Json,
            'quality_status' => JobSourceQualityStatus::Active,
        ]);
        JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://jobs.example.gov.ir/api/jobs',
            'endpoint_type' => JobEndpointType::Json,
            'parser_type' => null,
            'is_enabled' => true,
        ]);

        Http::fake([
            'jobs.example.gov.ir/*' => Http::response([
                ['id' => '1', 'title' => 'a', 'apply_url' => 'bad'],
                ['id' => '2', 'title' => 'b', 'apply_url' => 'also-bad'],
                ['id' => '3', 'title' => 'c', 'apply_url' => 'still-bad'],
            ], 200),
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);
        $source->refresh();

        $this->assertSame(SourceHealthService::OUTCOME_QUALITY_FAILURE, $result['health']['outcome']);
        $this->assertSame(JobSourceQualityStatus::Limited, $source->quality_status);
        $this->assertSame(0, $source->consecutive_failures);
    }

    public function test_non_admin_cannot_reset_health(): void
    {
        $user = User::factory()->create(['role' => 'jobseeker', 'status' => 'active']);
        Sanctum::actingAs($user);
        $source = $this->makeSource();

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/reset-health")->assertForbidden();
    }
}
