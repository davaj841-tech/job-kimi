<?php

declare(strict_types=1);

namespace Tests\Feature\Crawler;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobEndpointType;
use App\Enums\Aggregation\JobSourceQualityStatus;
use App\Enums\Aggregation\JobSourceReliability;
use App\Jobs\Aggregation\CrawlJobSourceJob;
use App\Models\CrawlerRun;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use App\Services\Aggregation\CrawlOrchestrator;
use App\Services\Aggregation\DuplicateDetector;
use App\Services\Aggregation\JobNormalizer;
use App\Services\Aggregation\JobPublisher;
use App\Services\Aggregation\JobValidator;
use App\Services\Aggregation\SafeHttpFetcher;
use App\Services\Aggregation\SourceHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CrawlerSystemTest extends TestCase
{
    use RefreshDatabase;

    private function createSource(array $overrides = []): JobSource
    {
        return JobSource::create(array_merge([
            'name' => 'Test Source',
            'slug' => 'test-source-'.uniqid(),
            'official_url' => 'https://example.gov.ir/',
            'domain' => 'example.gov.ir',
            'source_type' => 'government',
            'reliability_level' => JobSourceReliability::Official,
            'priority' => 1,
            'is_enabled' => true,
            'is_approved' => true,
            'quality_status' => JobSourceQualityStatus::Active,
            'crawler_type' => JobCrawlerType::Json,
            'crawl_frequency' => 'daily',
        ], $overrides));
    }

    private function createEndpoint(JobSource $source, array $overrides = []): JobSourceEndpoint
    {
        return JobSourceEndpoint::create(array_merge([
            'job_source_id' => $source->id,
            'url' => 'https://example.gov.ir/api/jobs',
            'endpoint_type' => JobEndpointType::Json,
            'http_method' => 'GET',
            'is_enabled' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    // ─── Requirement 1: Independent source adapters ───

    public function test_each_crawler_type_resolves_independently(): void
    {
        $resolver = app(\App\Services\Aggregation\CrawlerResolver::class);

        foreach ([JobCrawlerType::Rss, JobCrawlerType::Json, JobCrawlerType::Html, JobCrawlerType::Api] as $type) {
            $source = new JobSource(['crawler_type' => $type, 'is_enabled' => true, 'is_approved' => true]);
            $crawler = $resolver->resolve($source);
            $this->assertTrue($crawler->supports($source));
        }
    }

    // ─── Requirement 2: Queue-based execution ───

    public function test_crawl_dispatches_to_queue(): void
    {
        Queue::fake();
        $source = $this->createSource();

        CrawlJobSourceJob::dispatch($source->id);

        Queue::assertPushed(CrawlJobSourceJob::class, function ($job) use ($source) {
            return $job->jobSourceId === $source->id && $job->queue === 'crawlers';
        });
    }

    public function test_job_is_unique_per_source(): void
    {
        $source = $this->createSource();
        $job = new CrawlJobSourceJob($source->id);

        $this->assertEquals((string) $source->id, $job->uniqueId());
    }

    // ─── Requirement 3: Retry with backoff ───

    public function test_job_has_backoff_strategy(): void
    {
        $source = $this->createSource();
        $job = new CrawlJobSourceJob($source->id);

        $backoff = $job->backoff();
        $this->assertIsArray($backoff);
        $this->assertGreaterThan(0, $backoff[0]);
        $this->assertGreaterThan($backoff[0], $backoff[1]);
    }

    // ─── Requirement 4: Failure isolation ───

    public function test_single_item_failure_does_not_stop_crawl(): void
    {
        $source = $this->createSource();
        $this->createEndpoint($source);

        $fixture = file_get_contents(base_path('tests/fixtures/crawler/json_feed.json'));
        Http::fake(['https://example.gov.ir/*' => Http::response($fixture, 200)]);

        $orchestrator = app(CrawlOrchestrator::class);
        $result = $orchestrator->crawlSource($source);

        $this->assertGreaterThanOrEqual(1, $result['summary']['found']);
        $this->assertNotEquals(CrawlerRunStatus::Failed->value, $result['summary']['status']);
    }

    public function test_disabled_source_skipped_by_job(): void
    {
        $source = $this->createSource(['is_enabled' => false]);
        $this->createEndpoint($source);

        Http::fake();
        $job = new CrawlJobSourceJob($source->id);
        $job->handle(app(CrawlOrchestrator::class));

        Http::assertNothingSent();
    }

    // ─── Requirement 5: Duplicate detection ───

    public function test_duplicate_by_external_id(): void
    {
        $source = $this->createSource();
        JobPost::create([
            'title' => 'Existing Job',
            'company_name' => 'Gov',
            'status' => 'pending',
            'job_source_id' => $source->id,
            'external_id' => 'ext-123',
        ]);

        $detector = app(DuplicateDetector::class);
        $result = $detector->findDuplicate([
            'job_source_id' => $source->id,
            'external_id' => 'ext-123',
            'title' => 'Different Title',
            'company_name' => 'Gov',
        ]);

        $this->assertTrue($result['is_duplicate']);
        $this->assertEquals('source_external_id', $result['reason']);
    }

    public function test_duplicate_by_source_url(): void
    {
        JobPost::create([
            'title' => 'Job',
            'company_name' => 'Gov',
            'status' => 'pending',
            'source_url' => 'https://example.gov.ir/jobs/456',
        ]);

        $detector = app(DuplicateDetector::class);
        $result = $detector->findDuplicate([
            'title' => 'Another title',
            'company_name' => 'Gov',
            'source_url' => 'https://example.gov.ir/jobs/456',
            '_endpoint_url' => 'https://example.gov.ir/api/list',
        ]);

        $this->assertTrue($result['is_duplicate']);
        $this->assertEquals('source_url', $result['reason']);
    }

    public function test_no_false_duplicate_for_new_job(): void
    {
        $detector = app(DuplicateDetector::class);
        $result = $detector->findDuplicate([
            'title' => 'Brand new job',
            'company_name' => 'New Org',
            'external_id' => 'unique-999',
            'job_source_id' => 999,
        ]);

        $this->assertFalse($result['is_duplicate']);
    }

    // ─── Requirement 6: Canonical URL stored ───

    public function test_source_url_stored_on_published_job(): void
    {
        $source = $this->createSource();
        $publisher = app(JobPublisher::class);

        $post = $publisher->publish([
            'title' => 'Test Job',
            'company_name' => 'Org',
            'source_url' => 'https://example.gov.ir/jobs/789',
            'registration_link' => 'https://example.gov.ir/apply/789',
            'external_id' => 'pub-789',
        ], $source);

        $this->assertEquals('https://example.gov.ir/jobs/789', $post->source_url);
        $this->assertEquals('https://example.gov.ir/apply/789', $post->registration_link);
    }

    // ─── Requirement 7: Source clearly tracked ───

    public function test_job_source_id_attached(): void
    {
        $source = $this->createSource();
        $publisher = app(JobPublisher::class);

        $post = $publisher->publish([
            'title' => 'Sourced Job',
            'company_name' => 'Org',
            'external_id' => 'src-test',
        ], $source);

        $this->assertEquals($source->id, $post->job_source_id);
    }

    // ─── Requirement 8: Last crawl timestamp ───

    public function test_last_crawled_at_updated_after_crawl(): void
    {
        $source = $this->createSource();
        $this->createEndpoint($source);

        Http::fake(['*' => Http::response('[]', 200)]);

        $orchestrator = app(CrawlOrchestrator::class);
        $orchestrator->crawlSource($source);

        $source->refresh();
        $this->assertNotNull($source->last_crawled_at);
    }

    // ─── Requirement 9: Expired jobs managed ───

    public function test_expired_crawled_jobs_handled_by_expire_command(): void
    {
        $source = $this->createSource();
        $post = JobPost::create([
            'title' => 'Old crawled job',
            'company_name' => 'Gov',
            'status' => 'approved',
            'job_source_id' => $source->id,
            'registration_deadline' => now()->subDays(2),
        ]);

        $this->artisan('jobs:expire')->assertSuccessful();

        $post->refresh();
        $this->assertEquals('expired', $post->status);
    }

    // ─── Requirement 10: Rate limiting between endpoints ───

    public function test_endpoint_delay_config_exists(): void
    {
        $delay = config('aggregation.http.endpoint_delay_ms');
        $this->assertIsInt($delay);
        $this->assertGreaterThan(0, $delay);
    }

    // ─── Requirement 11: Timeout ───

    public function test_job_has_timeout(): void
    {
        $source = $this->createSource();
        $job = new CrawlJobSourceJob($source->id);

        $this->assertGreaterThan(0, $job->timeout);
    }

    public function test_http_fetcher_has_timeout(): void
    {
        $timeout = config('aggregation.http.timeout_seconds');
        $this->assertGreaterThan(0, $timeout);
    }

    // ─── Requirement 12: Logging ───

    public function test_crawl_errors_logged_to_database(): void
    {
        $source = $this->createSource();
        $this->createEndpoint($source);

        Http::fake(['*' => Http::response('Server Error', 500)]);

        $orchestrator = app(CrawlOrchestrator::class);
        $orchestrator->crawlSource($source);

        $this->assertDatabaseHas('crawler_errors', [
            'job_source_id' => $source->id,
        ]);
    }

    // ─── Requirement 13: Parser crash isolation ───

    public function test_normalizer_handles_empty_input(): void
    {
        $normalizer = app(JobNormalizer::class);
        $result = $normalizer->normalize([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
    }

    public function test_validator_rejects_incomplete_data(): void
    {
        $validator = app(JobValidator::class);
        $result = $validator->validate(['title' => null, 'company_name' => null]);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    // ─── Health & Backoff ───

    public function test_health_service_applies_backoff_after_failures(): void
    {
        $source = $this->createSource(['consecutive_failures' => 3]);
        $health = app(SourceHealthService::class);

        $run = CrawlerRun::create([
            'job_source_id' => $source->id,
            'status' => CrawlerRunStatus::Failed,
        ]);

        $result = $health->recordCrawl($source, $run, [
            'found' => 0,
            'created' => 0,
            'updated' => 0,
            'duplicates' => 0,
            'rejected' => 0,
            'validation_errors' => 0,
            'http_status' => 500,
        ]);

        $source->refresh();
        $this->assertNotNull($source->health_backoff_until);
        $this->assertEquals('http_failure', $result['outcome']);
    }

    // ─── SSRF Protection ───

    public function test_ssrf_blocks_private_ips(): void
    {
        $fetcher = app(SafeHttpFetcher::class);

        $this->assertTrue($fetcher->isBlockedIp('127.0.0.1'));
        $this->assertTrue($fetcher->isBlockedIp('10.0.0.1'));
        $this->assertTrue($fetcher->isBlockedIp('169.254.169.254'));
        $this->assertTrue($fetcher->isBlockedHost('localhost'));
    }

    public function test_ssrf_blocks_metadata_hosts(): void
    {
        $fetcher = app(SafeHttpFetcher::class);

        $this->assertTrue($fetcher->isBlockedHost('metadata.google.internal'));
        $this->assertTrue($fetcher->isBlockedHost('something.localhost'));
    }

    // ─── Fixture-based parser tests ───

    public function test_normalizer_with_json_fixture(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/fixtures/crawler/json_feed.json')),
            true
        );

        $normalizer = app(JobNormalizer::class);
        $normalized = $normalizer->normalize($fixture[0]);

        $this->assertNotEmpty($normalized['title']);
        $this->assertNotEmpty($normalized['company_name']);
        $this->assertNotNull($normalized['content_hash']);
    }

    public function test_validator_passes_valid_normalized_data(): void
    {
        $fixture = json_decode(
            file_get_contents(base_path('tests/fixtures/crawler/json_feed.json')),
            true
        );

        $normalizer = app(JobNormalizer::class);
        $validator = app(JobValidator::class);
        $normalized = $normalizer->normalize($fixture[0]);

        $result = $validator->validate($normalized);
        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
    }
}
