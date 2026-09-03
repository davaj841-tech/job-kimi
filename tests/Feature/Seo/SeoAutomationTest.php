<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Models\Exam;
use App\Models\JobPost;
use App\Models\Setting;
use App\Services\Seo\SearchEnginePingService;
use App\Services\Seo\SeoAnalyzer;
use App\Services\Seo\SeoAutoOptimizer;
use App\Services\Seo\SeoHeadRenderer;
use App\Services\Seo\SeoManager;
use App\Services\Seo\SeoRouteResolver;
use App\Services\Seo\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SeoAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spa_renders_server_side_meta_for_job_detail(): void
    {
        $job = JobPost::factory()->create([
            'status' => 'approved',
            'title' => 'استخدام بانک ملی',
            'description' => str_repeat('توضیحات آگهی استخدام. ', 20),
        ]);

        $response = $this->get("/jobs/{$job->id}");

        $response->assertOk();
        $response->assertSee('<title>', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('og:title', false);
    }

    public function test_spa_renders_list_page_meta_for_jobs(): void
    {
        $response = $this->get('/jobs');

        $response->assertOk();
        $response->assertSee('آگهی‌های استخدامی', false);
        $response->assertSee('meta name="description"', false);
    }

    public function test_home_payload_uses_admin_meta_title_setting(): void
    {
        Setting::set('meta_title', 'عنوان سفارشی سایت');
        Setting::set('meta_description', 'توضیحات سفارشی سایت');

        $payload = app(SeoManager::class)->buildHomePayload();

        $this->assertSame('عنوان سفارشی سایت', $payload['meta']['meta_title']);
        $this->assertSame('توضیحات سفارشی سایت', $payload['meta']['meta_description']);
    }

    public function test_auto_optimizer_fills_missing_seo_meta(): void
    {
        Queue::fake();

        $exam = Exam::factory()->create([
            'title' => 'آزمون بانک مرکزی',
            'description' => str_repeat('محتوای آزمون بانک مرکزی برای استخدام. ', 15),
            'status' => 'published',
        ]);

        $changed = app(SeoAutoOptimizer::class)->optimize($exam);

        $this->assertTrue($changed);
        $exam->refresh();
        $this->assertNotNull($exam->seoMeta?->title);
        $this->assertNotNull($exam->seoMeta?->description);
        $this->assertNotNull($exam->seoKeyword?->focus_keyword);
    }

    public function test_analyzer_creates_suggestions_for_failed_checks(): void
    {
        $exam = Exam::factory()->create([
            'title' => 'ک',
            'description' => 'کوتاه',
            'status' => 'published',
        ]);

        $analysis = app(SeoAnalyzer::class)->analyze($exam);

        $this->assertGreaterThan(0, $analysis->suggestions()->count());
    }

    public function test_route_resolver_resolves_exam_slug(): void
    {
        $exam = Exam::factory()->create([
            'slug' => 'bank-exam',
            'title' => 'آزمون بانک',
            'status' => 'published',
        ]);

        $payload = app(SeoRouteResolver::class)->resolve('exams/bank-exam');

        $this->assertNotNull($payload);
        $this->assertStringContainsString('آزمون بانک', (string) $payload['meta']['meta_title']);
    }

    public function test_head_renderer_outputs_canonical_and_json_ld(): void
    {
        $html = app(SeoHeadRenderer::class)->render([
            'meta' => [
                'meta_title' => 'تست SEO',
                'meta_description' => 'توضیحات تست SEO برای موتورهای جستجو',
                'canonical_url' => 'https://example.test/jobs/1',
                'robots' => 'index, follow',
                'og_type' => 'article',
            ],
            'schemas' => [
                ['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => 'تست'],
            ],
        ]);

        $this->assertStringContainsString('<title>تست SEO</title>', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('application/ld+json', $html);
    }

    public function test_search_engine_ping_calls_google_and_bing(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response('OK', 200),
            'www.bing.com/*' => Http::response('OK', 200),
        ]);

        $results = app(SearchEnginePingService::class)->pingSitemap('https://example.test/sitemap.xml');

        $this->assertTrue($results['google'] ?? false);
        $this->assertTrue($results['bing'] ?? false);
    }

    public function test_sitemap_includes_listing_pages(): void
    {
        $xml = app(SitemapService::class)->generateJobs();

        $this->assertStringContainsString(url('/jobs'), $xml);
    }
}
