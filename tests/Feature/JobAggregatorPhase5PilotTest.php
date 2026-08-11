<?php

namespace Tests\Feature;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobEndpointType;
use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Aggregation\JobSourceType;
use App\Models\CrawlerRun;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use App\Services\Aggregation\CrawlOrchestrator;
use App\Services\Aggregation\JobSourceManager;
use App\Services\Aggregation\Parsers\EmploymentKeywordRssParser;
use App\Services\Aggregation\Parsers\OfficialAnnouncementHtmlParser;
use Database\Seeders\PilotJobSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobAggregatorPhase5PilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_seeder_creates_whitelisted_official_sources(): void
    {
        $this->seed(PilotJobSourceSeeder::class);

        $sources = JobSource::query()->whereIn('slug', [
            'cbi-central-bank', 'sanjesh-org', 'hrtc-jihad-exam', 'aro-gov', 'bank-mellat',
        ])->with('endpoints')->get();

        $this->assertCount(5, $sources);
        foreach ($sources as $source) {
            $this->assertTrue($source->is_approved);
            $this->assertContains($source->reliability_level, [
                JobSourceReliability::Official,
                JobSourceReliability::HighlyTrusted,
            ]);
            $this->assertGreaterThanOrEqual(1, $source->endpoints->count());
            $this->assertNotEquals(JobSourceType::Company, $source->source_type);
        }

        $domains = app(JobSourceManager::class)->allowedDomains();
        foreach (['cbi.ir', 'sanjesh.org', 'hrtc.ir', 'aro.gov.ir', 'bankmellat.ir'] as $domain) {
            $this->assertContains($domain, $domains);
        }
    }

    public function test_employment_keyword_rss_filters_non_jobs(): void
    {
        $xml = file_get_contents(base_path('tests/Fixtures/Aggregation/cbi_news_rss.xml'));
        $items = app(EmploymentKeywordRssParser::class)->parse($xml, [
            'source_name' => 'بانک مرکزی',
            'endpoint_url' => 'https://cbi.ir/NewsRss.aspx?ln=fa',
        ]);

        $this->assertCount(1, $items);
        $this->assertStringContainsString('جذب', $items[0]['title']);
    }

    public function test_official_announcement_html_extracts_only_employment_links(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/Aggregation/sanjesh_announcements.html'));
        $items = app(OfficialAnnouncementHtmlParser::class)->parse($html, [
            'source_name' => 'سازمان سنجش',
            'endpoint_url' => 'https://www.sanjesh.org/',
        ]);

        $this->assertCount(2, $items);
        $this->assertTrue(collect($items)->every(fn ($i) => filled($i['registration_link'])));
    }

    public function test_mocked_cbi_pilot_pipeline_creates_pending_job(): void
    {
        $this->seed(PilotJobSourceSeeder::class);
        $source = JobSource::query()->where('slug', 'cbi-central-bank')->firstOrFail();

        Http::fake([
            'cbi.ir/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/Aggregation/cbi_news_rss.xml')),
                200,
                ['Content-Type' => 'application/rss+xml']
            ),
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);

        $this->assertSame(CrawlerRunStatus::Completed, $result['run']->status);
        $this->assertSame(1, $result['summary']['found']);
        $this->assertSame(1, $result['summary']['created']);
        $this->assertSame(0, $result['summary']['rejected']);
        $this->assertDatabaseHas('job_posts', [
            'job_source_id' => $source->id,
            'status' => 'pending',
            'company_name' => $source->name,
        ]);
        $this->assertTrue(JobPost::query()->where('status', 'approved')->doesntExist());
    }

    public function test_mocked_sanjesh_pilot_pipeline(): void
    {
        $this->seed(PilotJobSourceSeeder::class);
        $source = JobSource::query()->where('slug', 'sanjesh-org')->firstOrFail();

        Http::fake([
            'sanjesh.org/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/Aggregation/sanjesh_announcements.html')),
                200,
                ['Content-Type' => 'text/html']
            ),
            'www.sanjesh.org/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/Aggregation/sanjesh_announcements.html')),
                200,
                ['Content-Type' => 'text/html']
            ),
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);
        $this->assertSame(2, $result['summary']['created']);
        $this->assertSame('pending', JobPost::query()->where('job_source_id', $source->id)->value('status'));
    }

    public function test_failed_source_does_not_stop_sibling_pilot_crawl(): void
    {
        $this->seed(PilotJobSourceSeeder::class);

        Http::fake([
            'cbi.ir/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/Aggregation/cbi_news_rss.xml')),
                200
            ),
            'www.cbi.ir/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/Aggregation/cbi_news_rss.xml')),
                200
            ),
            'sanjesh.org/*' => Http::response('forbidden', 403),
            'www.sanjesh.org/*' => Http::response('forbidden', 403),
            'hrtc.ir/*' => Http::response('timeout-sim', 504),
            'www.hrtc.ir/*' => Http::response('timeout-sim', 504),
            'aro.gov.ir/*' => Http::response('<html></html>', 200),
            'www.aro.gov.ir/*' => Http::response('<html></html>', 200),
            'bankmellat.ir/*' => Http::response('<html><a href="/x">استخدام بانک ملت</a></html>', 200),
            'www.bankmellat.ir/*' => Http::response('<html><a href="/x">استخدام بانک ملت</a></html>', 200),
        ]);

        $results = [];
        foreach (JobSource::query()->whereIn('slug', [
            'cbi-central-bank', 'sanjesh-org', 'hrtc-jihad-exam', 'aro-gov', 'bank-mellat',
        ])->orderBy('priority')->get() as $source) {
            $results[$source->slug] = app(CrawlOrchestrator::class)->crawlSource($source);
        }

        $this->assertSame(CrawlerRunStatus::Completed, $results['cbi-central-bank']['run']->status);
        $this->assertSame(CrawlerRunStatus::Failed, $results['sanjesh-org']['run']->status);
        $this->assertSame(CrawlerRunStatus::Failed, $results['hrtc-jihad-exam']['run']->status);
        $this->assertSame(CrawlerRunStatus::Completed, $results['bank-mellat']['run']->status);

        $this->assertDatabaseHas('crawler_errors', [
            'job_source_id' => JobSource::query()->where('slug', 'sanjesh-org')->value('id'),
            'error_type' => 'crawl_failed',
        ]);

        $this->assertGreaterThanOrEqual(1, JobPost::query()->where('status', 'pending')->count());
        $this->assertSame(5, CrawlerRun::query()->count());
    }

    public function test_pilot_command_with_seed_and_mocks(): void
    {
        Http::fake([
            'cbi.ir/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/Aggregation/cbi_news_rss.xml')),
                200
            ),
            '*' => Http::response('nope', 503),
        ]);

        $this->artisan('jobs:pilot-crawl', [
            '--seed' => true,
            '--slug' => 'cbi-central-bank',
            '--report' => storage_path('framework/testing/pilot-report.json'),
        ])->assertSuccessful();

        $this->assertFileExists(storage_path('framework/testing/pilot-report.json'));
        $this->assertDatabaseHas('job_sources', ['slug' => 'cbi-central-bank', 'is_approved' => true]);
    }

    public function test_malformed_and_invalid_url_items_are_rejected(): void
    {
        $source = JobSource::factory()->whitelisted()->create([
            'slug' => 'pilot-json-quality',
            'name' => 'سازمان نمونه رسمی',
            'domain' => 'jobs.example.gov.ir',
            'official_url' => 'https://jobs.example.gov.ir/',
            'source_type' => JobSourceType::Government,
            'reliability_level' => JobSourceReliability::Official,
            'crawler_type' => JobCrawlerType::Json,
        ]);
        JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://jobs.example.gov.ir/api/jobs',
            'endpoint_type' => JobEndpointType::Json,
            'parser_type' => null,
        ]);

        Http::fake([
            'jobs.example.gov.ir/*' => Http::response([
                ['id' => 'bad', 'title' => 'x', 'apply_url' => 'not-a-url'],
                [
                    'id' => 'good',
                    'title' => 'کارشناس رسمی',
                    'company_name' => 'سازمان نمونه رسمی',
                    'apply_url' => 'https://jobs.example.gov.ir/apply/good',
                    'source_url' => 'https://jobs.example.gov.ir/list/good',
                ],
            ], 200),
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);

        $this->assertSame(1, $result['summary']['created']);
        $this->assertGreaterThanOrEqual(1, $result['summary']['rejected']);
        $this->assertDatabaseHas('crawler_errors', [
            'job_source_id' => $source->id,
            'error_type' => 'validation',
        ]);
        $this->assertDatabaseCount('job_posts', 1);
    }
}
