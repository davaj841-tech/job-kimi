<?php

namespace Tests\Feature;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Models\CrawlerError;
use App\Models\CrawlerRun;
use App\Models\Feature;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\User;
use App\Services\Aggregation\AggregationHealthService;
use App\Services\Aggregation\AggregationScheduleService;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AggregationHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_health_endpoint_reports_core_metrics(): void
    {
        $this->actingAdmin();
        app(FeatureFlagService::class)->enable('job-crawler');

        app(AggregationScheduleService::class)->update([
            'enabled' => true,
            'timezone' => 'Asia/Tehran',
            'times' => [
                ['time' => '06:30', 'enabled' => true, 'label' => 'صبح'],
            ],
        ]);

        $source = JobSource::factory()->whitelisted()->create([
            'domain' => 'health.example.gov.ir',
            'quality_status' => 'active',
            'last_crawled_at' => now()->subHours(2),
        ]);

        JobPost::factory()->create([
            'job_source_id' => $source->id,
            'status' => 'pending',
            'title' => 'آگهی pending',
        ]);
        JobPost::factory()->create([
            'job_source_id' => $source->id,
            'status' => 'approved',
            'title' => 'آگهی approved',
        ]);

        CrawlerRun::factory()->create([
            'job_source_id' => $source->id,
            'status' => CrawlerRunStatus::Completed,
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour()->addMinutes(2),
            'jobs_created' => 1,
        ]);

        CrawlerError::query()->create([
            'job_source_id' => $source->id,
            'error_type' => 'http',
            'message' => 'Upstream timeout while fetching listing',
            'url' => 'https://health.example.gov.ir/jobs',
            'occurred_at' => now()->subMinutes(10),
        ]);

        $this->getJson('/api/v1/admin/aggregation/health')
            ->assertOk()
            ->assertJsonPath('data.feature_flag.enabled', true)
            ->assertJsonPath('data.scheduler.enabled', true)
            ->assertJsonPath('data.sources.dispatchable', 1)
            ->assertJsonPath('data.sources.enabled', 1)
            ->assertJsonPath('data.jobs.pending', 1)
            ->assertJsonPath('data.jobs.approved', 1)
            ->assertJsonPath('data.last_error.message', 'Upstream timeout while fetching listing')
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'checked_at',
                    'feature_flag' => ['name', 'enabled'],
                    'scheduler' => ['enabled', 'timezone', 'times', 'queue'],
                    'sources' => ['total', 'enabled', 'dispatchable'],
                    'jobs' => ['pending', 'approved'],
                    'last_crawled_at',
                    'last_run',
                    'last_error',
                    'checks',
                    'alerts',
                    'issues',
                ],
            ]);
    }

    public function test_dashboard_stats_includes_aggregation_health(): void
    {
        $this->actingAdmin();
        app(FeatureFlagService::class)->enable('job-crawler');

        JobSource::factory()->whitelisted()->create([
            'domain' => 'dash.example.gov.ir',
            'quality_status' => 'active',
            'last_crawled_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/dashboard-stats')
            ->assertOk()
            ->assertJsonPath('data.aggregation_health.feature_flag.name', 'job-crawler')
            ->assertJsonPath('data.aggregation_health.feature_flag.enabled', true)
            ->assertJsonPath('data.aggregation_health.sources.dispatchable', 1);
    }

    public function test_disabled_feature_flag_marks_health_critical(): void
    {
        Feature::query()->create([
            'name' => 'job-crawler',
            'enabled' => false,
            'description' => 'خزشگر',
        ]);
        app(FeatureFlagService::class)->forgetCache();

        $snapshot = app(AggregationHealthService::class)->snapshot();

        $this->assertFalse($snapshot['feature_flag']['enabled']);
        $this->assertSame('critical', $snapshot['status']);
        $this->assertSame('critical', $snapshot['checks']['feature_flag']);
        $this->assertNotEmpty($snapshot['issues']);
    }

    public function test_health_endpoint_requires_admin_auth(): void
    {
        $this->getJson('/api/v1/admin/aggregation/health')->assertUnauthorized();
    }
}
