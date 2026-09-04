<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Jobs\Seo\AnalyzeSeoJob;
use App\Jobs\Seo\AutoOptimizeSeoJob;
use App\Jobs\Seo\RunSeoAuditJob;
use App\Models\CmsPage;
use App\Models\Exam;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\Seo\SeoAnalysis;
use App\Models\Seo\SeoAudit;
use App\Models\Seo\SeoMeta;
use App\Models\Seo\SeoRedirect;
use App\Models\User;
use App\Services\Seo\CannibalizationService;
use App\Services\Seo\CanonicalService;
use App\Services\Seo\DuplicateContentService;
use App\Services\Seo\MetaGenerator;
use App\Services\Seo\RedirectService;
use App\Services\Seo\SchemaGenerator;
use App\Services\Seo\SeoAnalyzer;
use App\Services\Seo\SitemapService;
use App\Services\SEOService;
use App\Support\LegalPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SeoSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    // --- SeoMeta ---

    public function test_seo_meta_polymorphic_relation(): void
    {
        $exam = Exam::factory()->create();
        $meta = $exam->seoMeta()->create([
            'title' => 'آزمون تست',
            'description' => 'توضیح آزمون تست',
        ]);

        $this->assertInstanceOf(SeoMeta::class, $meta);
        $this->assertSame('آزمون تست', $exam->fresh()->seoMeta->title);
    }

    public function test_seo_meta_unique_per_model(): void
    {
        $exam = Exam::factory()->create();
        $exam->seoMeta()->create(['title' => 'A']);
        $exam->seoMeta()->updateOrCreate(
            ['seoable_type' => $exam->getMorphClass(), 'seoable_id' => $exam->getKey()],
            ['title' => 'B']
        );

        $this->assertSame(1, SeoMeta::where('seoable_id', $exam->id)->count());
        $this->assertSame('B', $exam->fresh()->seoMeta->title);
    }

    // --- MetaGenerator ---

    public function test_meta_generator_uses_custom_meta(): void
    {
        $exam = Exam::factory()->create(['title' => 'آزمون اصلی']);
        $exam->seoMeta()->create(['title' => 'عنوان سفارشی', 'description' => 'توضیح سفارشی']);

        $generator = app(MetaGenerator::class);
        $meta = $generator->generate($exam->fresh());

        $this->assertSame('عنوان سفارشی', $meta['meta_title']);
        $this->assertSame('توضیح سفارشی', $meta['meta_description']);
    }

    public function test_meta_generator_falls_back_to_model_fields(): void
    {
        $exam = Exam::factory()->create(['title' => 'آزمون فالبک', 'description' => 'توضیح فالبک']);

        $generator = app(MetaGenerator::class);
        $meta = $generator->generate($exam);

        $this->assertSame('آزمون فالبک', $meta['meta_title']);
        $this->assertStringContainsString('توضیح فالبک', $meta['meta_description']);
    }

    // --- SeoAnalyzer ---

    public function test_analyzer_creates_analysis_with_score(): void
    {
        $exam = Exam::factory()->create(['title' => 'آزمون با عنوان مناسب برای سئو', 'description' => str_repeat('محتوای آزمون تستی ', 50)]);
        $exam->seoKeyword()->create(['focus_keyword' => 'آزمون']);

        $analyzer = app(SeoAnalyzer::class);
        $analysis = $analyzer->analyze($exam);

        $this->assertInstanceOf(SeoAnalysis::class, $analysis);
        $this->assertGreaterThan(0, $analysis->score);
        $this->assertIsArray($analysis->checks);
        $this->assertArrayHasKey('title', $analysis->checks);
    }

    public function test_analyzer_detects_keyword_stuffing(): void
    {
        $exam = Exam::factory()->create([
            'title' => 'آزمون',
            'description' => str_repeat('آزمون ', 200),
        ]);
        $exam->seoKeyword()->create(['focus_keyword' => 'آزمون']);

        $analyzer = app(SeoAnalyzer::class);
        $analysis = $analyzer->analyze($exam);

        $keywordCheck = $analysis->checks['keyword_in_content'] ?? [];
        $this->assertFalse($keywordCheck['pass'] ?? true);
    }

    // --- SeoScore ---

    public function test_score_status_mapping(): void
    {
        Queue::fake();
        $exam = Exam::factory()->create();
        $exam->seoAnalysis()->updateOrCreate(
            ['analyzable_type' => $exam->getMorphClass(), 'analyzable_id' => $exam->getKey()],
            ['score' => 95, 'status' => 'excellent', 'analyzed_at' => now()]
        );

        $this->assertSame('excellent', $exam->fresh()->seoAnalysis->status);
    }

    // --- Canonical ---

    public function test_canonical_generates_url(): void
    {
        $exam = Exam::factory()->create(['slug' => 'test-exam']);

        $service = app(CanonicalService::class);
        $canonical = $service->getCanonical($exam);

        $this->assertStringContainsString('/exams/test-exam', $canonical);
    }

    public function test_canonical_uses_custom_when_set(): void
    {
        $exam = Exam::factory()->create();
        $exam->seoMeta()->create(['canonical' => 'https://example.com/custom']);

        $service = app(CanonicalService::class);
        $this->assertSame('https://example.com/custom', $service->getCanonical($exam->fresh()));
    }

    // --- Sitemap ---

    public function test_sitemap_index_returns_xml(): void
    {
        $response = $this->get('/sitemap.xml');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('sitemapindex');
    }

    public function test_sitemap_jobs_includes_approved_posts(): void
    {
        JobPost::factory()->create(['status' => 'approved']);
        JobPost::factory()->create(['status' => 'pending']);

        $service = app(SitemapService::class);
        $xml = $service->generateJobs();

        $this->assertStringContainsString('/jobs/', $xml);
        $this->assertSame(2, substr_count($xml, '<url>')); // /jobs listing + 1 approved
    }

    public function test_sitemap_exams_excludes_draft(): void
    {
        Exam::factory()->create(['status' => 'published', 'slug' => 'pub-exam']);
        Exam::factory()->create(['status' => 'draft', 'slug' => 'draft-exam']);

        $service = app(SitemapService::class);
        $xml = $service->generateExams();

        $this->assertStringContainsString('pub-exam', $xml);
        $this->assertStringNotContainsString('draft-exam', $xml);
    }

    // --- Schema ---

    public function test_schema_generates_job_posting(): void
    {
        $job = JobPost::factory()->create(['title' => 'برنامه‌نویس', 'company_name' => 'شرکت تست']);

        $generator = app(SchemaGenerator::class);
        $schemas = $generator->generate($job);

        $jobSchema = collect($schemas)->firstWhere('@type', 'JobPosting');
        $this->assertNotNull($jobSchema);
        $this->assertSame('برنامه‌نویس', $jobSchema['title']);
    }

    public function test_schema_generates_faq_when_present(): void
    {
        $exam = Exam::factory()->create();
        $exam->seoFaqs()->create(['question' => 'سوال اول', 'answer' => 'پاسخ اول']);

        $generator = app(SchemaGenerator::class);
        $schemas = $generator->generate($exam->fresh());

        $faqSchema = collect($schemas)->firstWhere('@type', 'FAQPage');
        $this->assertNotNull($faqSchema);
        $this->assertCount(1, $faqSchema['mainEntity']);
    }

    // --- Breadcrumb ---

    public function test_breadcrumb_schema_generates_list(): void
    {
        $generator = app(SchemaGenerator::class);
        $schema = $generator->breadcrumbSchema([
            ['name' => 'خانه', 'url' => url('/')],
            ['name' => 'آزمون‌ها', 'url' => url('/exams')],
        ]);

        $this->assertSame('BreadcrumbList', $schema['@type']);
        $this->assertCount(2, $schema['itemListElement']);
        $this->assertSame(1, $schema['itemListElement'][0]['position']);
    }

    // --- Internal Linking (existing service) ---

    public function test_seo_links_morphic_relation(): void
    {
        $job = JobPost::factory()->create();
        $link = $job->seoLinks()->create([
            'target_url' => url('/exams/test'),
            'target_type' => 'internal',
            'anchor_text' => 'آزمون مرتبط',
        ]);

        $this->assertSame('internal', $link->target_type);
        $this->assertCount(1, $job->fresh()->seoLinks);
    }

    // --- Redirect ---

    public function test_redirect_301_works(): void
    {
        SeoRedirect::create([
            'source_path' => '/old-page',
            'target_url' => '/new-page',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $response = $this->get('/old-page');
        $response->assertRedirect('/new-page');
        $response->assertStatus(301);
    }

    public function test_redirect_410_returns_gone(): void
    {
        SeoRedirect::create([
            'source_path' => '/deleted-page',
            'target_url' => '/',
            'status_code' => 410,
            'is_active' => true,
        ]);

        $response = $this->get('/deleted-page');
        $response->assertStatus(410);
    }

    public function test_inactive_redirect_is_ignored(): void
    {
        SeoRedirect::create([
            'source_path' => '/inactive',
            'target_url' => '/other',
            'status_code' => 301,
            'is_active' => false,
        ]);

        $response = $this->get('/inactive');
        $response->assertStatus(404); // no route matches, returns 404
    }

    // --- Cannibalization ---

    public function test_cannibalization_detects_duplicates(): void
    {
        $exam1 = Exam::factory()->create();
        $exam2 = Exam::factory()->create();
        $exam1->seoKeyword()->create(['focus_keyword' => 'آزمون استخدامی']);
        $exam2->seoKeyword()->create(['focus_keyword' => 'آزمون استخدامی']);

        $service = app(CannibalizationService::class);
        $results = $service->findCannibalization();

        $this->assertNotEmpty($results);
        $this->assertSame('آزمون استخدامی', $results[0]['keyword']);
        $this->assertSame(2, $results[0]['count']);
    }

    // --- Exam SEO ---

    public function test_exam_has_seo_trait(): void
    {
        $exam = Exam::factory()->create(['title' => 'آزمون SEO']);
        $this->assertSame('آزمون SEO', $exam->getSeoTitle());
    }

    // --- Job SEO ---

    public function test_job_has_seo_trait(): void
    {
        $job = JobPost::factory()->create(['title' => 'شغل تست']);
        $this->assertSame('شغل تست', $job->getSeoTitle());
    }

    // --- Article SEO ---

    public function test_article_schema_generated(): void
    {
        $article = GeneratedContent::create([
            'title' => 'مقاله SEO',
            'slug' => 'article-seo',
            'content' => 'محتوای مقاله',
            'content_type' => 'NEW_JOB_EXAM',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $schemas = app(SchemaGenerator::class)->generate($article);

        $articleSchema = collect($schemas)->firstWhere('@type', 'Article');
        $this->assertNotNull($articleSchema);
        $this->assertSame('مقاله SEO', $articleSchema['headline']);
    }

    public function test_seo_meta_overrides_legacy_meta(): void
    {
        $exam = Exam::factory()->create(['title' => 'عنوان مدل', 'description' => 'توضیح مدل']);
        $exam->seoMeta()->create(['title' => 'عنوان SEO', 'description' => 'توضیح SEO']);

        $meta = app(MetaGenerator::class)->generate($exam->fresh());

        $this->assertSame('عنوان SEO', $meta['meta_title']);
        $this->assertSame('توضیح SEO', $meta['meta_description']);
    }

    public function test_job_canonical_uses_real_public_route(): void
    {
        $job = JobPost::factory()->create(['status' => 'approved']);

        $canonical = app(CanonicalService::class)->getCanonical($job);

        $this->assertSame(url('/jobs/'.$job->getKey()), $canonical);
    }

    public function test_noindex_content_excluded_from_sitemap(): void
    {
        $exam = Exam::factory()->create(['status' => 'published', 'slug' => 'indexed-exam']);
        $hidden = Exam::factory()->create(['status' => 'published', 'slug' => 'hidden-exam']);
        $hidden->seoMeta()->create(['robots' => 'noindex, follow']);

        app(SitemapService::class)->clearCache();
        $xml = app(SitemapService::class)->generateExams();

        $this->assertStringContainsString('indexed-exam', $xml);
        $this->assertStringNotContainsString('hidden-exam', $xml);
    }

    public function test_analyze_seo_job_dispatched_on_content_update(): void
    {
        Queue::fake();

        $exam = Exam::factory()->create(['title' => 'آزمون اولیه']);
        Queue::assertPushed(AutoOptimizeSeoJob::class);

        Queue::fake();
        $exam->update(['title' => 'آزمون به‌روز']);

        Queue::assertPushed(AnalyzeSeoJob::class);
    }

    public function test_duplicate_analysis_executed_in_audit_job(): void
    {
        $job = new RunSeoAuditJob('duplicate');
        $job->handle(
            app(SeoAnalyzer::class),
            app(CannibalizationService::class),
            app(DuplicateContentService::class),
        );

        $audit = SeoAudit::query()->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertArrayHasKey('duplicate_content', $audit->results ?? []);
    }

    public function test_redirect_loop_prevented(): void
    {
        $service = app(RedirectService::class);

        $service->create('/page-a', '/page-b');

        $this->expectException(\InvalidArgumentException::class);
        $service->create('/page-b', '/page-a');
    }

    public function test_article_api_returns_seo_payload(): void
    {
        $article = GeneratedContent::create([
            'title' => 'مقاله API',
            'slug' => 'article-api-seo',
            'content' => str_repeat('محتوای مقاله API. ', 30),
            'excerpt' => 'خلاصه مقاله',
            'content_type' => 'NEW_JOB_EXAM',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $article->seoMeta()->create(['title' => 'SEO مقاله', 'description' => 'توضیح SEO']);

        $this->getJson('/api/v1/articles/article-api-seo')
            ->assertOk()
            ->assertJsonPath('data.seo.meta.meta_title', 'SEO مقاله')
            ->assertJsonPath('data.seo.meta.meta_description', 'توضیح SEO');
    }

    public function test_about_page_api_returns_seo_payload(): void
    {
        LegalPages::ensure();
        $page = CmsPage::query()->where('slug', 'about')->first();
        $this->assertNotNull($page);
        $page->seoMeta()->create(['title' => 'درباره SEO', 'description' => 'توضیح درباره']);

        $this->getJson('/api/v1/pages/about')
            ->assertOk()
            ->assertJsonPath('data.seo.meta.meta_title', 'درباره SEO');
    }

    public function test_legacy_seo_service_uses_schema_generator(): void
    {
        $job = JobPost::factory()->create(['title' => 'شغل Schema', 'company_name' => 'شرکت']);

        $legacy = app(SEOService::class)->generateJobPostSchema($job);
        $primary = app(SchemaGenerator::class)->primarySchema($job);

        $this->assertSame($primary['@type'] ?? null, $legacy['@type'] ?? null);
        $this->assertSame('شغل Schema', $legacy['title']);
    }
}
