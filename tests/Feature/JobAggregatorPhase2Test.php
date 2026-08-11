<?php

namespace Tests\Feature;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Aggregation\JobSourceType;
use App\Models\CrawlerError;
use App\Models\CrawlerRun;
use App\Models\JobDuplicate;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use App\Services\Aggregation\JobSourceManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JobAggregatorPhase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_job_aggregator_tables_exist(): void
    {
        foreach ([
            'job_sources',
            'job_source_endpoints',
            'crawler_runs',
            'crawler_errors',
            'job_duplicates',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('job_posts', [
            'job_source_id',
            'external_id',
            'content_hash',
        ]));
    }

    public function test_can_create_job_source_with_defaults(): void
    {
        $source = JobSource::factory()->create([
            'name' => 'سازمان سنجش',
            'official_url' => 'https://sanjesh.org/careers',
            'domain' => null,
        ]);

        $this->assertNotEmpty($source->slug);
        $this->assertSame('sanjesh.org', $source->domain);
        $this->assertFalse($source->is_enabled);
        $this->assertFalse($source->is_approved);
        $this->assertSame(JobSourceReliability::Unverified, $source->reliability_level);
    }

    public function test_reliability_auto_publish_policy(): void
    {
        $official = JobSource::factory()->whitelisted()->official()->create();
        $trusted = JobSource::factory()->create([
            'is_enabled' => true,
            'is_approved' => true,
            'reliability_level' => JobSourceReliability::Trusted,
        ]);
        $unverified = JobSource::factory()->create([
            'is_enabled' => true,
            'is_approved' => true,
            'reliability_level' => JobSourceReliability::Unverified,
        ]);

        $this->assertTrue($official->allowsAutoPublish());
        $this->assertFalse($trusted->allowsAutoPublish());
        $this->assertFalse($unverified->allowsAutoPublish());
        $this->assertTrue(JobSourceReliability::HighlyTrusted->allowsAutoPublish());
    }

    public function test_source_approval_and_whitelist_scope(): void
    {
        JobSource::factory()->create(['is_enabled' => true, 'is_approved' => false]);
        JobSource::factory()->create(['is_enabled' => false, 'is_approved' => true]);
        $ok = JobSource::factory()->whitelisted()->create(['domain' => 'bankmellat.ir']);

        $whitelist = JobSource::query()->whitelisted()->get();

        $this->assertCount(1, $whitelist);
        $this->assertTrue($whitelist->first()->is($ok));
    }

    public function test_source_endpoint_relationship(): void
    {
        $source = JobSource::factory()->whitelisted()->create();
        $endpoint = JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://bankmellat.ir/rss/jobs',
        ]);

        $this->assertTrue($source->endpoints->contains($endpoint));
        $this->assertTrue($endpoint->source->is($source));
    }

    public function test_crawler_run_creation_and_status_helpers(): void
    {
        $source = JobSource::factory()->whitelisted()->create();
        $run = CrawlerRun::factory()->create([
            'job_source_id' => $source->id,
            'status' => CrawlerRunStatus::Pending,
        ]);

        $run->markRunning();
        $this->assertSame(CrawlerRunStatus::Running, $run->fresh()->status);
        $this->assertNotNull($run->fresh()->started_at);

        $run->update([
            'jobs_found' => 3,
            'jobs_created' => 2,
            'duplicates' => 1,
        ]);
        $run->markFinished(CrawlerRunStatus::Completed);

        $fresh = $run->fresh();
        $this->assertSame(CrawlerRunStatus::Completed, $fresh->status);
        $this->assertNotNull($fresh->finished_at);
        $this->assertNotNull($fresh->execution_ms);
        $this->assertTrue($source->crawlerRuns->contains($fresh));
    }

    public function test_crawler_error_belongs_to_source_and_run(): void
    {
        $source = JobSource::factory()->create();
        $run = CrawlerRun::factory()->create(['job_source_id' => $source->id]);

        $error = CrawlerError::query()->create([
            'job_source_id' => $source->id,
            'crawler_run_id' => $run->id,
            'error_type' => 'http_timeout',
            'message' => 'Timed out',
            'url' => 'https://example.gov.ir/jobs',
            'context' => ['timeout' => 20],
        ]);

        $this->assertTrue($error->source->is($source));
        $this->assertTrue($error->run->is($run));
        $this->assertNotNull($error->occurred_at);
        $this->assertTrue($run->errors->contains($error));
    }

    public function test_job_duplicate_unique_pair_constraint(): void
    {
        $a = JobPost::factory()->create();
        $b = JobPost::factory()->create();

        JobDuplicate::query()->create([
            'original_job_post_id' => $a->id,
            'duplicate_job_post_id' => $b->id,
            'similarity_score' => 95.5,
            'detection_reason' => 'registration_link',
        ]);

        $this->expectException(QueryException::class);

        JobDuplicate::query()->create([
            'original_job_post_id' => $a->id,
            'duplicate_job_post_id' => $b->id,
            'similarity_score' => 90,
            'detection_reason' => 'title_hash',
        ]);
    }

    public function test_job_post_can_link_to_source(): void
    {
        $source = JobSource::factory()->whitelisted()->create();
        $post = JobPost::factory()->create([
            'job_source_id' => $source->id,
            'external_id' => 'EXT-1',
            'content_hash' => hash('sha256', 'sample'),
            'source_url' => 'https://'.$source->domain.'/job/1',
        ]);

        $this->assertTrue($post->source->is($source));
        $this->assertTrue($source->jobPosts->contains($post));
    }

    public function test_source_manager_domain_allowlist(): void
    {
        JobSource::factory()->whitelisted()->create([
            'domain' => 'example.gov.ir',
            'official_url' => 'https://example.gov.ir/',
        ]);
        JobSource::factory()->create([
            'domain' => 'evil.test',
            'is_enabled' => true,
            'is_approved' => false,
        ]);

        $manager = app(JobSourceManager::class);

        $this->assertTrue($manager->isDomainAllowed('example.gov.ir'));
        $this->assertTrue($manager->isDomainAllowed('https://jobs.example.gov.ir/list'));
        $this->assertFalse($manager->isDomainAllowed('evil.test'));
        $this->assertContains('example.gov.ir', $manager->allowedDomains());
        $this->assertCount(1, $manager->dispatchableSources());
    }

    public function test_aggregate_dispatch_command_is_dry_run_only(): void
    {
        JobSource::factory()->whitelisted()->create([
            'name' => 'بانک ملی',
            'domain' => 'bmi.ir',
            'source_type' => JobSourceType::Bank,
        ]);

        $exit = Artisan::call('jobs:aggregate-dispatch', [
            '--dry-run' => true,
            '--force' => true,
        ]);

        $this->assertSame(0, $exit);
        $output = Artisan::output();
        $this->assertStringContainsString('Due sources', $output);
        $this->assertStringContainsString('bmi.ir', $output);
        $this->assertStringContainsString('Dry-run', $output);
    }
}
