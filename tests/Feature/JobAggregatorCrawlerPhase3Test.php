<?php

namespace Tests\Feature;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobEndpointType;
use App\Enums\Aggregation\JobSourceReliability;
use App\Jobs\Aggregation\CrawlJobSourceJob;
use App\Models\CrawlerRun;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use App\Services\Aggregation\CrawlOrchestrator;
use App\Services\Aggregation\SafeHttpFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class JobAggregatorCrawlerPhase3Test extends TestCase
{
    use RefreshDatabase;

    protected function makeWhitelistedSource(array $attrs = []): JobSource
    {
        return JobSource::factory()->whitelisted()->create(array_merge([
            'name' => 'سازمان نمونه',
            'domain' => 'jobs.example.gov.ir',
            'official_url' => 'https://jobs.example.gov.ir/',
            'crawler_type' => JobCrawlerType::Json,
            'reliability_level' => JobSourceReliability::Official,
        ], $attrs));
    }

    public function test_refuses_non_whitelisted_source(): void
    {
        $source = JobSource::factory()->create([
            'is_enabled' => true,
            'is_approved' => false,
            'domain' => 'jobs.example.gov.ir',
        ]);

        $this->expectException(\RuntimeException::class);
        app(CrawlOrchestrator::class)->crawlSource($source);
    }

    public function test_safe_fetcher_blocks_non_allowlisted_host(): void
    {
        $this->makeWhitelistedSource();

        $this->expectException(\RuntimeException::class);
        app(SafeHttpFetcher::class)->assertUrlAllowed('https://evil.test/jobs');
    }

    public function test_json_crawler_creates_pending_jobs(): void
    {
        Http::fake([
            'jobs.example.gov.ir/*' => Http::response([
                'jobs' => [
                    [
                        'id' => 'J-100',
                        'title' => 'کارشناس اداری',
                        'company_name' => 'سازمان نمونه',
                        'description' => 'شرح شغل تست',
                        'province' => 'تهران',
                        'url' => 'https://jobs.example.gov.ir/apply/100',
                        'deadline' => '2026-12-01',
                    ],
                    [
                        'id' => 'J-101',
                        'title' => 'کارشناس مالی',
                        'description' => 'شرح دوم',
                        'apply_url' => 'https://jobs.example.gov.ir/apply/101',
                    ],
                ],
            ], 200),
        ]);

        $source = $this->makeWhitelistedSource(['crawler_type' => JobCrawlerType::Json]);
        JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://jobs.example.gov.ir/api/jobs',
            'endpoint_type' => JobEndpointType::Json,
            'is_enabled' => true,
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);

        $this->assertSame(CrawlerRunStatus::Completed, $result['run']->status);
        $this->assertSame(2, $result['summary']['created']);
        $this->assertDatabaseCount('job_posts', 2);
        $this->assertDatabaseHas('job_posts', [
            'external_id' => 'J-100',
            'status' => 'pending',
            'job_source_id' => $source->id,
            'title' => 'کارشناس اداری',
        ]);
        $this->assertTrue(JobPost::query()->where('status', 'approved')->doesntExist());
    }

    public function test_rss_crawler_parses_feed(): void
    {
        $rss = <<<'XML'
<?xml version="1.0"?>
<rss version="2.0"><channel>
<title>Jobs</title>
<item>
  <title>استخدام بانک</title>
  <link>https://jobs.example.gov.ir/bank/1</link>
  <guid>bank-1</guid>
  <description>شرح بانک</description>
</item>
</channel></rss>
XML;

        Http::fake([
            'jobs.example.gov.ir/*' => Http::response($rss, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $source = $this->makeWhitelistedSource(['crawler_type' => JobCrawlerType::Rss]);
        JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://jobs.example.gov.ir/feed.xml',
            'endpoint_type' => JobEndpointType::Rss,
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);

        $this->assertSame(1, $result['summary']['created']);
        $this->assertDatabaseHas('job_posts', [
            'title' => 'استخدام بانک',
            'status' => 'pending',
            'registration_link' => 'https://jobs.example.gov.ir/bank/1',
        ]);
    }

    public function test_html_json_ld_crawler(): void
    {
        $html = <<<'HTML'
<html><head>
<script type="application/ld+json">
{"@type":"JobPosting","title":"کارشناس IT","description":"نیاز به تجربه","url":"https://jobs.example.gov.ir/it/1","hiringOrganization":{"name":"وزارت نمونه"},"validThrough":"2026-11-01"}
</script>
</head><body></body></html>
HTML;

        Http::fake([
            'jobs.example.gov.ir/*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);

        $source = $this->makeWhitelistedSource(['crawler_type' => JobCrawlerType::Html]);
        JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://jobs.example.gov.ir/careers',
            'endpoint_type' => JobEndpointType::Html,
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);
        $this->assertSame(1, $result['summary']['created']);
        $this->assertDatabaseHas('job_posts', [
            'title' => 'کارشناس IT',
            'company_name' => 'وزارت نمونه',
            'status' => 'pending',
        ]);
    }

    public function test_duplicate_updates_instead_of_second_create(): void
    {
        // Isolate duplicate/upsert behavior from detail-page enrichment HTTP calls.
        config(['aggregation.detail_fetch.enabled' => false]);

        Http::fake([
            'jobs.example.gov.ir/*' => Http::sequence()
                ->push([
                    ['id' => 'DUP-1', 'title' => 'شغل تکراری', 'description' => 'version-one', 'url' => 'https://jobs.example.gov.ir/d/1'],
                ], 200)
                ->push([
                    ['id' => 'DUP-1', 'title' => 'شغل تکراری', 'description' => 'version-two-updated', 'url' => 'https://jobs.example.gov.ir/d/1'],
                ], 200),
        ]);

        $source = $this->makeWhitelistedSource(['crawler_type' => JobCrawlerType::Api]);
        JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://jobs.example.gov.ir/api/v1/jobs',
            'endpoint_type' => JobEndpointType::Api,
        ]);

        app(CrawlOrchestrator::class)->crawlSource($source);
        $second = app(CrawlOrchestrator::class)->crawlSource($source->fresh());

        $this->assertSame(0, $second['summary']['created']);
        $this->assertSame(1, $second['summary']['updated']);
        $this->assertDatabaseCount('job_posts', 1);
        $description = (string) JobPost::query()->where('external_id', 'DUP-1')->value('description');
        $this->assertStringContainsString('version-two-updated', strip_tags($description));
        $this->assertSame('pending', JobPost::query()->where('external_id', 'DUP-1')->value('status'));
    }

    public function test_dispatch_command_queues_jobs(): void
    {
        Queue::fake();
        $source = $this->makeWhitelistedSource();

        $this->artisan('jobs:aggregate-dispatch', ['--force' => true])
            ->assertSuccessful();

        Queue::assertPushed(CrawlJobSourceJob::class, function (CrawlJobSourceJob $job) use ($source) {
            return $job->jobSourceId === $source->id;
        });
    }

    public function test_dispatch_dry_run_does_not_queue(): void
    {
        Queue::fake();
        $this->makeWhitelistedSource();

        $this->artisan('jobs:aggregate-dispatch', ['--dry-run' => true, '--force' => true])
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_http_error_recorded_on_run(): void
    {
        Http::fake([
            'jobs.example.gov.ir/*' => Http::response('nope', 500),
        ]);

        $source = $this->makeWhitelistedSource();
        JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://jobs.example.gov.ir/api/jobs',
            'endpoint_type' => JobEndpointType::Json,
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);

        $this->assertSame(CrawlerRunStatus::Failed, $result['run']->status);
        $this->assertDatabaseHas('crawler_errors', [
            'job_source_id' => $source->id,
            'error_type' => 'crawl_failed',
        ]);
        $this->assertTrue(CrawlerRun::query()->where('job_source_id', $source->id)->exists());
    }

    public function test_blocks_url_outside_source_domain_even_if_globally_listed(): void
    {
        $this->makeWhitelistedSource(['domain' => 'jobs.example.gov.ir']);
        JobSource::factory()->whitelisted()->create([
            'domain' => 'other.gov.ir',
            'official_url' => 'https://other.gov.ir/',
        ]);

        $source = JobSource::query()->where('domain', 'jobs.example.gov.ir')->first();

        $this->expectException(\RuntimeException::class);
        app(SafeHttpFetcher::class)->get('https://other.gov.ir/feed', $source);
    }
}
