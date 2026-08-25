<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Jobs\Seo\AnalyzeSeoJob;
use App\Models\BlogPost;
use App\Models\CmsPage;
use App\Models\Exam;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\PdfProduct;
use App\Models\User;
use App\Services\Seo\CanonicalService;
use App\Services\Seo\MetaGenerator;
use App\Services\Seo\SchemaGenerator;
use App\Services\Seo\SeoManager;
use App\Services\Seo\SitemapService;
use App\Services\SEOService;
use App\Support\LegalPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Final production audit checks (read-only validation).
 */
final class FinalSeoProductionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_blocks_admin_api_filament_and_lists_sitemap(): void
    {
        $response = $this->get('/robots.txt');
        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Disallow: /api', $body);
        $this->assertStringContainsString('Disallow: /filament', $body);
        $this->assertStringContainsString('Sitemap:', $body);
        $this->assertStringContainsString('/sitemap.xml', $body);
    }

    public function test_all_sitemap_endpoints_return_valid_xml_without_admin_urls(): void
    {
        JobPost::factory()->create(['status' => 'approved']);
        Exam::factory()->create(['status' => 'published', 'slug' => 'audit-exam']);
        $author = User::factory()->create();
        BlogPost::query()->create([
            'title' => 'Audit Blog',
            'slug' => 'audit-blog',
            'status' => 'published',
            'created_by' => $author->id,
        ]);
        GeneratedContent::create([
            'title' => 'Audit Article',
            'slug' => 'audit-article',
            'content' => str_repeat('متن ', 50),
            'content_type' => 'NEW_JOB_EXAM',
            'status' => 'published',
            'published_at' => now(),
        ]);
        PdfProduct::query()->create([
            'title' => 'Audit PDF',
            'file_path' => 'pdfs/audit.pdf',
            'is_active' => true,
        ]);
        LegalPages::ensure();

        app(SitemapService::class)->clearCache();

        $paths = [
            '/sitemap.xml' => 'sitemapindex',
            '/sitemaps/pages.xml' => 'urlset',
            '/sitemaps/jobs.xml' => 'urlset',
            '/sitemaps/exams.xml' => 'urlset',
            '/sitemaps/articles.xml' => 'urlset',
            '/sitemaps/blog.xml' => 'urlset',
            '/sitemaps/files.xml' => 'urlset',
        ];

        foreach ($paths as $path => $rootTag) {
            $response = $this->get($path);
            $response->assertOk();
            $xml = $response->getContent();
            $this->assertStringContainsString('<?xml', $xml);
            $this->assertStringContainsString("<{$rootTag}", $xml);
            $this->assertStringNotContainsString('/admin', $xml);
            $this->assertStringNotContainsString('/filament', $xml);
            $this->assertStringNotContainsString('/api/', $xml);
        }
    }

    public function test_sitemap_urls_are_unique_and_match_public_routes(): void
    {
        $job = JobPost::factory()->create(['status' => 'approved']);
        $exam = Exam::factory()->create(['status' => 'published', 'slug' => 'route-exam']);
        $author = User::factory()->create();
        $blog = BlogPost::query()->create([
            'title' => 'Route Blog',
            'slug' => 'route-blog',
            'status' => 'published',
            'created_by' => $author->id,
        ]);
        $article = GeneratedContent::create([
            'title' => 'Route Article',
            'slug' => 'route-article',
            'content' => str_repeat('متن ', 50),
            'content_type' => 'NEW_JOB_EXAM',
            'status' => 'published',
            'published_at' => now(),
        ]);

        app(SitemapService::class)->clearCache();

        $jobsXml = app(SitemapService::class)->generateJobs();
        $this->assertStringContainsString(url('/jobs/'.$job->getKey()), $jobsXml);
        $this->assertSame(1, substr_count($jobsXml, url('/jobs/'.$job->getKey())));

        $examsXml = app(SitemapService::class)->generateExams();
        $this->assertStringContainsString(url('/exams/route-exam'), $examsXml);

        $blogXml = app(SitemapService::class)->generateBlog();
        $this->assertStringContainsString(url('/blog/route-blog'), $blogXml);

        $articlesXml = app(SitemapService::class)->generateArticles();
        $this->assertStringContainsString(url('/articles/route-article'), $articlesXml);
    }

    public function test_noindex_excluded_from_sitemap(): void
    {
        Exam::factory()->create(['status' => 'published', 'slug' => 'visible-exam']);
        $hidden = Exam::factory()->create(['status' => 'published', 'slug' => 'hidden-exam']);
        $hidden->seoMeta()->create(['robots' => 'noindex, follow']);

        app(SitemapService::class)->clearCache();
        $xml = app(SitemapService::class)->generateExams();

        $this->assertStringContainsString('visible-exam', $xml);
        $this->assertStringNotContainsString('hidden-exam', $xml);
    }

    public function test_sitemap_cache_works(): void
    {
        Cache::flush();
        $service = app(SitemapService::class);
        $service->clearCache();

        $first = $service->generateJobs();
        Cache::put('sitemap:jobs', '<urlset>CACHED</urlset>', 3600);
        $second = $service->generateJobs();

        $this->assertSame('<urlset>CACHED</urlset>', $second);
        $this->assertNotSame($first, $second);
    }

    public function test_canonical_urls_match_public_routes_for_all_content_types(): void
    {
        $job = JobPost::factory()->create(['status' => 'approved']);
        $exam = Exam::factory()->create(['status' => 'published', 'slug' => 'canon-exam']);
        $author = User::factory()->create();
        $blog = BlogPost::query()->create([
            'title' => 'Canon Blog',
            'slug' => 'canon-blog',
            'status' => 'published',
            'created_by' => $author->id,
        ]);
        $article = GeneratedContent::create([
            'title' => 'Canon',
            'slug' => 'canon-article',
            'content' => 'content',
            'content_type' => 'NEW_JOB_EXAM',
            'status' => 'published',
            'published_at' => now(),
        ]);
        LegalPages::ensure();
        $about = CmsPage::query()->where('slug', 'about')->firstOrFail();
        $pdf = PdfProduct::query()->create([
            'title' => 'Canon PDF',
            'file_path' => 'pdfs/canon.pdf',
            'is_active' => true,
        ]);

        $canonical = app(CanonicalService::class);

        $this->assertSame(url('/jobs/'.$job->getKey()), $canonical->getCanonical($job));
        $this->assertSame(url('/exams/canon-exam'), $canonical->getCanonical($exam));
        $this->assertSame(url('/blog/canon-blog'), $canonical->getCanonical($blog));
        $this->assertSame(url('/articles/canon-article'), $canonical->getCanonical($article));
        $this->assertSame(url('/about'), $canonical->getCanonical($about));
        $this->assertSame(url('/pdf-products/'.$pdf->getKey()), $canonical->getCanonical($pdf));
    }

    public function test_seo_meta_priority_over_model_and_config(): void
    {
        $exam = Exam::factory()->create([
            'title' => 'Model Title',
            'description' => 'Model Description',
        ]);
        $exam->seoMeta()->create([
            'title' => 'SEO Meta Title',
            'description' => 'SEO Meta Description',
        ]);

        $meta = app(MetaGenerator::class)->generate($exam->fresh());

        $this->assertSame('SEO Meta Title', $meta['meta_title']);
        $this->assertSame('SEO Meta Description', $meta['meta_description']);
    }

    public function test_public_api_seo_payload_structure_for_content_pages(): void
    {
        $job = JobPost::factory()->create(['status' => 'approved']);
        $job->seoMeta()->create(['title' => 'Job SEO', 'description' => 'Job Desc']);

        $exam = Exam::factory()->create(['status' => 'published', 'slug' => 'api-exam']);
        $exam->seoMeta()->create(['title' => 'Exam SEO']);

        $author = User::factory()->create();
        $blog = BlogPost::query()->create([
            'title' => 'API Blog',
            'slug' => 'api-blog',
            'status' => 'published',
            'created_by' => $author->id,
        ]);
        $blog->seoMeta()->create(['title' => 'Blog SEO']);

        $article = GeneratedContent::create([
            'title' => 'API Article',
            'slug' => 'api-article',
            'content' => str_repeat('متن ', 50),
            'content_type' => 'NEW_JOB_EXAM',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $article->seoMeta()->create(['title' => 'Article SEO']);

        LegalPages::ensure();
        CmsPage::query()->where('slug', 'about')->first()?->seoMeta()->create(['title' => 'About SEO']);

        $this->getJson('/api/v1/job-posts/'.$job->getKey())
            ->assertOk()
            ->assertJsonPath('data.seo.meta.meta_title', 'Job SEO')
            ->assertJsonStructure(['data' => ['seo' => ['meta', 'schema', 'schemas', 'score']]]);

        $this->getJson('/api/v1/exams/api-exam')
            ->assertOk()
            ->assertJsonPath('data.seo.meta.meta_title', 'Exam SEO');

        $this->getJson('/api/v1/blog-posts/api-blog')
            ->assertOk()
            ->assertJsonPath('data.seo.meta.meta_title', 'Blog SEO');

        $this->getJson('/api/v1/articles/api-article')
            ->assertOk()
            ->assertJsonPath('data.seo.meta.meta_title', 'Article SEO');

        $this->getJson('/api/v1/pages/about')
            ->assertOk()
            ->assertJsonPath('data.seo.meta.meta_title', 'About SEO');

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonStructure(['data' => ['seo' => ['meta', 'schemas']]]);
    }

    public function test_schema_json_is_valid_and_not_duplicated_from_legacy_bridge(): void
    {
        $job = JobPost::factory()->create(['status' => 'approved', 'company_name' => 'Real Co']);
        $payload = app(SeoManager::class)->buildPublicPayload($job);
        $legacy = app(SEOService::class)->generateJobPostSchema($job);

        $this->assertSame($legacy['@type'], 'JobPosting');
        $this->assertSame('Real Co', $legacy['hiringOrganization']['name']);

        foreach ($payload['schemas'] as $schema) {
            $encoded = json_encode($schema);
            $this->assertNotFalse($encoded);
            $this->assertSame(JSON_ERROR_NONE, json_last_error());
            $this->assertSame('https://schema.org', $schema['@context'] ?? null);
        }

        $types = collect($payload['schemas'])->pluck('@type')->filter()->values()->all();
        $this->assertContains('WebSite', $types);
        $this->assertContains('JobPosting', $types);
    }

    public function test_observer_dispatches_on_create_and_content_update_not_seo_meta_only(): void
    {
        Queue::fake();

        $exam = Exam::factory()->create(['title' => 'Observer Test']);
        Queue::assertPushed(AnalyzeSeoJob::class, 1);

        Queue::fake();
        $exam->seoMeta()->create(['title' => 'Only SEO meta change']);
        $exam->touch();
        Queue::assertNotPushed(AnalyzeSeoJob::class);

        Queue::fake();
        $exam->update(['title' => 'Updated title']);
        Queue::assertPushed(AnalyzeSeoJob::class, 1);
    }

    public function test_schema_generator_produces_expected_types_with_real_data_only(): void
    {
        $exam = Exam::factory()->create(['status' => 'published', 'title' => 'Real Exam']);
        $exam->seoFaqs()->create(['question' => 'Q?', 'answer' => 'A.', 'sort_order' => 1]);

        $schemas = app(SchemaGenerator::class)->generate($exam->fresh());
        $types = collect($schemas)->pluck('@type')->all();

        $this->assertContains('WebSite', $types);
        $this->assertContains('Quiz', $types);
        $this->assertContains('FAQPage', $types);

        $quiz = collect($schemas)->firstWhere('@type', 'Quiz');
        $this->assertSame('Real Exam', $quiz['name']);
    }
}
