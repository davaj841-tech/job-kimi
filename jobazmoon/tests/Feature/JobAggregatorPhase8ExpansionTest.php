<?php

namespace Tests\Feature;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Enums\Aggregation\JobSourceQualityStatus;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Services\Aggregation\CrawlOrchestrator;
use App\Services\Aggregation\JobSourceManager;
use App\Services\Aggregation\Parsers\OfficialAnnouncementHtmlParser;
use Database\Seeders\PilotJobSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobAggregatorPhase8ExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_seeder_expands_sources_with_quality_status(): void
    {
        $this->seed(PilotJobSourceSeeder::class);

        $sources = JobSource::query()->orderBy('priority')->get();
        $this->assertGreaterThanOrEqual(10, $sources->count());

        foreach ($sources as $source) {
            $this->assertNotNull($source->quality_status);
            $this->assertTrue($source->is_approved);
            $this->assertGreaterThanOrEqual(1, $source->endpoints()->count());
        }

        $this->assertDatabaseHas('job_sources', [
            'slug' => 'iust-university',
            'quality_status' => JobSourceQualityStatus::Limited->value,
            'is_enabled' => true,
        ]);

        $this->assertDatabaseHas('job_sources', [
            'slug' => 'bank-melli',
            'quality_status' => JobSourceQualityStatus::TemporarilyUnavailable->value,
            'is_approved' => true,
        ]);

        $this->assertDatabaseHas('job_sources', [
            'slug' => 'cbi-central-bank',
        ]);

        $cbi = JobSource::query()->where('slug', 'cbi-central-bank')->with('endpoints')->firstOrFail();
        $this->assertTrue(
            $cbi->endpoints->contains(fn ($e) => str_contains($e->url, 'www.cbi.ir'))
        );
    }

    public function test_dispatchable_excludes_temporarily_unavailable_and_manual_only(): void
    {
        $this->seed(PilotJobSourceSeeder::class);

        $manager = app(JobSourceManager::class);
        $dispatchable = $manager->dispatchableSources();

        $this->assertTrue($dispatchable->contains(fn ($s) => $s->slug === 'cbi-central-bank'));
        $this->assertTrue($dispatchable->contains(fn ($s) => $s->slug === 'iust-university'));
        $this->assertFalse($dispatchable->contains(fn ($s) => $s->slug === 'sanjesh-org'));
        $this->assertFalse($dispatchable->contains(fn ($s) => $s->slug === 'bank-melli'));
        $this->assertFalse($dispatchable->contains(fn ($s) => $s->slug === 'nioc-national-oil'));
        $this->assertFalse($dispatchable->contains(fn ($s) => $s->slug === 'sharif-university'));

        // Whitelisted for admin test-crawl, but quality blocks scheduler dispatch.
        $this->assertTrue(JobSource::query()->where('slug', 'sanjesh-org')->whitelisted()->exists());
    }

    public function test_iust_fixture_parser_extracts_employment_links_only(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/Aggregation/iust_announcements.html'));
        $items = app(OfficialAnnouncementHtmlParser::class)->parse($html, [
            'source_name' => 'دانشگاه علم و صنعت ایران',
            'endpoint_url' => 'https://www.iust.ac.ir/',
        ]);

        $this->assertGreaterThanOrEqual(2, count($items));
        $this->assertTrue(collect($items)->every(fn ($i) => filled($i['registration_link'])));
        $this->assertTrue(collect($items)->contains(
            fn ($i) => str_contains($i['title'], 'فراخوان جذب')
        ));
        $this->assertFalse(collect($items)->contains(
            fn ($i) => str_contains($i['title'], 'اخبار عمومی')
        ));
    }

    public function test_mocked_iust_pipeline_creates_pending_jobs(): void
    {
        $this->seed(PilotJobSourceSeeder::class);
        $source = JobSource::query()->where('slug', 'iust-university')->firstOrFail();

        Http::fake([
            'www.iust.ac.ir/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/Aggregation/iust_announcements.html')),
                200,
                ['Content-Type' => 'text/html; charset=utf-8']
            ),
            'iust.ac.ir/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/Aggregation/iust_announcements.html')),
                200,
                ['Content-Type' => 'text/html; charset=utf-8']
            ),
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);

        $this->assertSame(CrawlerRunStatus::Completed, $result['run']->status);
        $this->assertGreaterThanOrEqual(1, $result['summary']['found']);
        $this->assertGreaterThanOrEqual(1, $result['summary']['created']);
        $this->assertDatabaseHas('job_posts', [
            'job_source_id' => $source->id,
            'status' => 'pending',
            'company_name' => $source->name,
        ]);
    }

    public function test_mocked_cbi_www_endpoint_still_works(): void
    {
        $this->seed(PilotJobSourceSeeder::class);
        $source = JobSource::query()->where('slug', 'cbi-central-bank')->firstOrFail();

        Http::fake([
            'www.cbi.ir/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/Aggregation/cbi_news_rss.xml')),
                200,
                ['Content-Type' => 'application/rss+xml']
            ),
            'cbi.ir/*' => Http::response(
                file_get_contents(base_path('tests/Fixtures/Aggregation/cbi_news_rss.xml')),
                200,
                ['Content-Type' => 'application/rss+xml']
            ),
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);

        $this->assertSame(CrawlerRunStatus::Completed, $result['run']->status);
        $this->assertSame(1, $result['summary']['created']);
        $this->assertSame(0, JobPost::query()->where('job_source_id', $source->id)->where('status', 'approved')->count());
    }

    public function test_unavailable_source_is_not_auto_dispatchable_even_if_enabled(): void
    {
        $this->seed(PilotJobSourceSeeder::class);
        $source = JobSource::query()->where('slug', 'sanjesh-org')->firstOrFail();
        $source->update([
            'is_enabled' => true,
            'quality_status' => JobSourceQualityStatus::TemporarilyUnavailable,
        ]);

        $this->assertFalse($source->fresh()->allowsAutomaticCrawl());
        $this->assertFalse(
            app(JobSourceManager::class)->dispatchableSources()->contains(fn ($s) => $s->id === $source->id)
        );
    }
}
