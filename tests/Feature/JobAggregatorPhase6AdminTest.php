<?php

namespace Tests\Feature;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobEndpointType;
use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Aggregation\JobSourceType;
use App\Models\CrawlerError;
use App\Models\CrawlerRun;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobAggregatorPhase6AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    protected function actingAdmin(): User
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_non_admin_cannot_access_job_sources(): void
    {
        $user = User::factory()->create(['role' => 'jobseeker', 'status' => 'active']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/job-sources')->assertForbidden();
        $this->getJson('/api/v1/admin/crawler-runs')->assertForbidden();
        $this->getJson('/api/v1/admin/aggregation/quality-stats')->assertForbidden();
    }

    public function test_source_crud_approve_and_enable(): void
    {
        $this->actingAdmin();

        $create = $this->postJson('/api/v1/admin/job-sources', [
            'name' => 'سازمان آزمایشی',
            'official_url' => 'https://jobs.example.gov.ir/',
            'domain' => 'jobs.example.gov.ir',
            'source_type' => JobSourceType::Government->value,
            'reliability_level' => JobSourceReliability::Official->value,
            'crawler_type' => JobCrawlerType::Json->value,
            'priority' => 10,
            'is_enabled' => false,
            'is_approved' => false,
        ])->assertCreated()
            ->assertJsonPath('data.is_whitelisted', false);

        $id = $create->json('data.id');

        $this->postJson("/api/v1/admin/job-sources/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.is_approved', true);

        $this->postJson("/api/v1/admin/job-sources/{$id}/enable")
            ->assertOk()
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.is_whitelisted', true);

        $this->postJson("/api/v1/admin/job-sources/{$id}/disable")
            ->assertOk()
            ->assertJsonPath('data.is_enabled', false);

        $this->putJson("/api/v1/admin/job-sources/{$id}", [
            'name' => 'سازمان آزمایشی به‌روز',
            'official_url' => 'https://jobs.example.gov.ir/about',
            'source_type' => JobSourceType::Government->value,
            'reliability_level' => JobSourceReliability::Official->value,
            'crawler_type' => JobCrawlerType::Json->value,
            'priority' => 5,
            'is_enabled' => true,
            'is_approved' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'سازمان آزمایشی به‌روز');

        $this->getJson('/api/v1/admin/job-sources')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_endpoint_domain_validation_rejects_foreign_host(): void
    {
        $this->actingAdmin();
        $source = JobSource::factory()->whitelisted()->create([
            'domain' => 'jobs.example.gov.ir',
            'official_url' => 'https://jobs.example.gov.ir/',
        ]);

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/endpoints", [
            'url' => 'https://evil.test/feed',
            'endpoint_type' => JobEndpointType::Rss->value,
            'http_method' => 'GET',
        ])->assertStatus(422);

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/endpoints", [
            'url' => 'https://jobs.example.gov.ir/api/jobs',
            'endpoint_type' => JobEndpointType::Json->value,
            'parser_type' => null,
            'is_enabled' => true,
        ])->assertCreated()
            ->assertJsonPath('data.url', 'https://jobs.example.gov.ir/api/jobs');
    }

    public function test_manual_test_crawl_requires_whitelist_and_records_run(): void
    {
        $this->actingAdmin();
        $source = JobSource::factory()->create([
            'domain' => 'jobs.example.gov.ir',
            'official_url' => 'https://jobs.example.gov.ir/',
            'is_enabled' => true,
            'is_approved' => false,
            'crawler_type' => JobCrawlerType::Json,
            'reliability_level' => JobSourceReliability::Official,
        ]);
        JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://jobs.example.gov.ir/api/jobs',
            'endpoint_type' => JobEndpointType::Json,
        ]);

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/test-crawl")
            ->assertStatus(422);

        $source->update(['is_approved' => true]);

        Http::fake([
            'jobs.example.gov.ir/*' => Http::response([
                [
                    'id' => 'P6-1',
                    'title' => 'کارشناس رسمی',
                    'company_name' => 'سازمان نمونه',
                    'apply_url' => 'https://jobs.example.gov.ir/apply/1',
                    'source_url' => 'https://jobs.example.gov.ir/list/1',
                ],
            ], 200),
        ]);

        $this->postJson("/api/v1/admin/job-sources/{$source->id}/test-crawl")
            ->assertOk()
            ->assertJsonPath('data.summary.created', 1)
            ->assertJsonPath('data.note', fn ($n) => str_contains($n, 'pending'));

        $this->assertDatabaseHas('job_posts', [
            'external_id' => 'P6-1',
            'status' => 'pending',
            'job_source_id' => $source->id,
        ]);
        $this->assertDatabaseHas('crawler_runs', [
            'job_source_id' => $source->id,
        ]);
    }

    public function test_crawl_monitoring_lists_runs_and_sanitized_errors(): void
    {
        $this->actingAdmin();
        $source = JobSource::factory()->whitelisted()->create(['name' => 'منبع پایش']);
        $run = CrawlerRun::factory()->create([
            'job_source_id' => $source->id,
            'status' => CrawlerRunStatus::Failed,
            'jobs_found' => 0,
            'errors_count' => 1,
        ]);
        CrawlerError::query()->create([
            'job_source_id' => $source->id,
            'crawler_run_id' => $run->id,
            'error_type' => 'crawl_failed',
            'message' => 'HTTP 403',
            'context' => ['password' => 'secret', 'errors' => ['x']],
            'occurred_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/crawler-runs')
            ->assertOk()
            ->assertJsonPath('data.data.0.source_name', 'منبع پایش');

        $this->getJson("/api/v1/admin/crawler-runs/{$run->id}")
            ->assertOk()
            ->assertJsonPath('data.errors.0.error_type', 'crawl_failed')
            ->assertJsonMissing(['password' => 'secret']);

        $this->getJson('/api/v1/admin/crawler-runs/errors')
            ->assertOk()
            ->assertJsonPath('data.data.0.error_type', 'crawl_failed');
    }

    public function test_pending_aggregated_job_review_publish_and_reject(): void
    {
        $admin = $this->actingAdmin();
        $source = JobSource::factory()->whitelisted()->create(['name' => 'منبع بررسی']);
        $pending = JobPost::factory()->create([
            'title' => 'آگهی تجمیع',
            'company_name' => 'سازمان الف',
            'status' => 'pending',
            'job_source_id' => $source->id,
            'source_url' => 'https://jobs.example.gov.ir/a',
            'registration_link' => 'https://jobs.example.gov.ir/apply/a',
            'external_id' => 'AGG-1',
        ]);
        JobPost::factory()->create([
            'title' => 'دستی',
            'status' => 'pending',
            'job_source_id' => null,
        ]);

        $this->getJson('/api/v1/admin/aggregation/pending-jobs')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.is_aggregated', true)
            ->assertJsonPath('data.data.0.job_source.name', 'منبع بررسی');

        $this->putJson("/api/v1/admin/aggregation/jobs/{$pending->id}", [
            'title' => 'آگهی تجمیع ویرایش‌شده',
            'description' => 'شرح اصلاحی',
            'province' => 'تهران',
            'provinces' => ['تهران'],
        ])->assertOk()
            ->assertJsonPath('data.title', 'آگهی تجمیع ویرایش‌شده')
            ->assertJsonPath('data.job_source_id', $source->id);

        $this->postJson("/api/v1/admin/aggregation/jobs/{$pending->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('job_posts', [
            'id' => $pending->id,
            'status' => 'approved',
            'approved_by' => $admin->id,
            'job_source_id' => $source->id,
        ]);

        $another = JobPost::factory()->create([
            'status' => 'pending',
            'job_source_id' => $source->id,
            'title' => 'برای رد',
        ]);
        $this->postJson("/api/v1/admin/aggregation/jobs/{$another->id}/reject", ['reason' => 'ناقص'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_quality_stats_and_dashboard_include_aggregation_counts(): void
    {
        $this->actingAdmin();
        $source = JobSource::factory()->whitelisted()->create([
            'reliability_level' => JobSourceReliability::Official,
        ]);
        JobPost::factory()->create(['job_source_id' => $source->id, 'status' => 'pending']);
        JobPost::factory()->create(['job_source_id' => $source->id, 'status' => 'approved']);
        CrawlerRun::factory()->create([
            'job_source_id' => $source->id,
            'status' => CrawlerRunStatus::Failed,
            'duplicates' => 3,
            'finished_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/aggregation/quality-stats')
            ->assertOk()
            ->assertJsonPath('data.total_aggregated_jobs', 2)
            ->assertJsonPath('data.pending_jobs', 1)
            ->assertJsonPath('data.published_jobs', 1)
            ->assertJsonPath('data.duplicate_updates', 3);

        $this->getJson('/api/v1/admin/dashboard-stats')
            ->assertOk()
            ->assertJsonPath('data.counts.aggregated_jobs_pending', 1)
            ->assertJsonPath('data.counts.whitelisted_job_sources', 1);
    }

    public function test_official_url_outside_domain_rejected_on_create(): void
    {
        $this->actingAdmin();

        $this->postJson('/api/v1/admin/job-sources', [
            'name' => 'بد',
            'official_url' => 'https://other.gov.ir/',
            'domain' => 'jobs.example.gov.ir',
            'source_type' => JobSourceType::Government->value,
            'reliability_level' => JobSourceReliability::Official->value,
            'crawler_type' => JobCrawlerType::Html->value,
        ])->assertStatus(422);
    }
}
