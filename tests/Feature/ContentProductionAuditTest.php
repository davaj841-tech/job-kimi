<?php

namespace Tests\Feature;

use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Content\ContentStatus;
use App\Enums\Content\ContentType;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\User;
use App\Services\Content\ContentGeneratorService;
use App\Services\Content\ContentQualityService;
use App\Services\Content\ContentRenderer;
use App\Services\Content\WordPressPublishService;
use Carbon\Carbon;
use Database\Seeders\ContentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Production-hardening coverage for the Automated Content Generator.
 */
class ContentProductionAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'content.enabled' => true,
            'content.daily_generation_enabled' => true,
            'content.publish_mode' => 'draft',
            'content.sync_to_blog' => true,
            'content.max_articles_per_day' => 5,
            'content.minimum_content_length' => 200,
            'content.minimum_factual_score' => 3,
            'content.timezone' => 'Asia/Tehran',
            'content.allowed_source_reliability' => ['official', 'highly_trusted', 'trusted'],
        ]);
        (new ContentTemplateSeeder)->run();
    }

    protected function trustedSource(array $extra = []): JobSource
    {
        return JobSource::factory()->whitelisted()->create(array_merge([
            'reliability_level' => JobSourceReliability::Official,
        ], $extra));
    }

    protected function approvedJob(array $extra = [], ?JobSource $source = null): JobPost
    {
        $source ??= $this->trustedSource();

        return JobPost::factory()->approved()->create(array_merge([
            'job_source_id' => $source->id,
            'title' => 'آزمون استخدامی بانک نمونه',
            'company_name' => 'بانک نمونه',
            'description' => 'توضیحات رسمی آگهی استخدام بانک نمونه برای جذب نیروی متخصص در واحدهای مختلف سازمان.',
            'requirements' => 'حداکثر سن ۳۵ سال. مدارک مورد نیاز: کارت ملی، شناسنامه و مدرک تحصیلی.',
            'education' => 'کارشناسی',
            'field_of_study' => 'مدیریت',
            'province' => 'تهران',
            'provinces' => ['تهران'],
            'city' => 'تهران',
            'registration_starts_at' => now()->subDay(),
            'registration_deadline' => now()->addDays(10),
            'exam_date' => now()->addDays(40),
            'registration_link' => 'https://careers.example.gov.ir/apply',
            'source_url' => 'https://careers.example.gov.ir/job/1',
        ], $extra));
    }

    public function test_xss_payloads_are_escaped_in_generated_html(): void
    {
        $job = $this->approvedJob([
            'company_name' => '<script>alert(1)</script>بانک XSS',
            'title' => 'آگهی"><img src=x onerror=alert(1)> استخدام',
            'description' => 'متن <b>bold</b> و <script>bad()</script> ایمن',
        ]);

        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::NewJobExam);
        $this->assertSame('created', $result['outcome'], (string) $result['error']);
        $html = $result['content']->content;
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('onerror=', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('بانک XSS', strip_tags($html));
        $this->assertStringContainsString('&quot;', $html);
    }

    public function test_javascript_urls_are_rejected_from_links(): void
    {
        $job = $this->approvedJob([
            'registration_link' => 'javascript:alert(1)',
            'source_url' => 'https://careers.example.gov.ir/ok',
        ]);
        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::RegistrationGuide);
        $this->assertSame('created', $result['outcome'], (string) $result['error']);
        $this->assertStringNotContainsString('javascript:', $result['content']->content);
        $this->assertStringContainsString('https://careers.example.gov.ir/ok', $result['content']->content);
    }

    public function test_missing_fields_omit_empty_sections_without_fabrication(): void
    {
        $job = $this->approvedJob([
            'exam_date' => null,
            'city' => null,
            'education' => null,
            'field_of_study' => null,
            'registration_starts_at' => null,
        ]);
        $ctx = app(ContentRenderer::class)->contextFromJob($job);
        $this->assertSame('', $ctx['exam_date']);
        $this->assertSame('', $ctx['city']);
        $this->assertSame('', $ctx['education']);

        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::NewJobExam);
        $this->assertSame('created', $result['outcome'], (string) $result['error']);
        $this->assertStringNotContainsString('2027/', $result['content']->content);
        $this->assertDoesNotMatchRegularExpression('/\{[a-z0-9_]+\}/u', $result['content']->content);
    }

    public function test_thin_job_is_skipped(): void
    {
        $job = $this->approvedJob([
            'description' => 'کوتاه',
            'requirements' => null,
            'education' => null,
            'field_of_study' => null,
            'registration_deadline' => null,
            'registration_starts_at' => null,
            'exam_date' => null,
            'registration_link' => null,
            'source_url' => null,
            'province' => null,
            'provinces' => [],
            'city' => null,
        ]);
        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::OrganizationRecruitment);
        $this->assertSame('skipped', $result['outcome']);
        $this->assertSame('insufficient_factual_data', $result['error']);
    }

    public function test_daily_limit_uses_content_timezone(): void
    {
        config([
            'content.max_articles_per_day' => 1,
            'content.timezone' => 'Asia/Tehran',
            'app.timezone' => 'UTC',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-10 21:30:00', 'UTC')); // 01:00 next day Tehran
        $this->approvedJob();
        $stats = app(ContentGeneratorService::class)->generateDaily();
        $this->assertSame(1, $stats['created'] + $stats['updated']);

        // Still same Tehran day (Aug 11 01:00) — second run blocked by daily max
        $stats2 = app(ContentGeneratorService::class)->generateDaily();
        $this->assertContains('max_articles_per_day_reached', $stats2['errors']);

        Carbon::setTestNow();
    }

    public function test_scheduler_window_times(): void
    {
        config([
            'content.enabled' => true,
            'content.daily_generation_enabled' => true,
            'content.daily_generation_time' => '09:00',
            'content.timezone' => 'Asia/Tehran',
        ]);

        foreach (['08:59' => false, '09:00' => true, '09:01' => false, '23:59' => false, '00:00' => false] as $hm => $shouldMatch) {
            $match = $hm === config('content.daily_generation_time');
            $this->assertSame($shouldMatch, $match, "time {$hm}");
        }
    }

    public function test_public_article_requires_published_status(): void
    {
        $job = $this->approvedJob();
        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::NewJobExam);
        $this->assertSame('created', $result['outcome'], (string) $result['error']);
        $slug = $result['content']->slug;

        $this->getJson('/api/v1/articles/'.$slug)->assertNotFound();

        app(ContentGeneratorService::class)->publishContent($result['content']->fresh());
        $this->getJson('/api/v1/articles/'.$slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $slug)
            ->assertJsonPath('data.meta.title', $result['content']->title);

        $this->get('/sitemaps/articles.xml')->assertOk()->assertSee('/articles/'.$slug, false);
    }

    public function test_scheduled_article_not_public_before_time(): void
    {
        $job = $this->approvedJob();
        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::NewJobExam);
        $content = $result['content'];
        $content->update([
            'status' => ContentStatus::Scheduled,
            'scheduled_for' => now()->addDay(),
            'published_at' => null,
        ]);

        $this->getJson('/api/v1/articles/'.$content->slug)->assertNotFound();
    }

    public function test_publish_scheduled_command(): void
    {
        $job = $this->approvedJob();
        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::NewJobExam);
        $content = $result['content'];
        $content->update([
            'status' => ContentStatus::Scheduled,
            'scheduled_for' => now()->subMinute(),
        ]);

        $this->artisan('content:publish-scheduled')->assertSuccessful();
        $this->assertSame(ContentStatus::Published, $content->fresh()->status);
        $this->getJson('/api/v1/articles/'.$content->slug)->assertOk();
    }

    public function test_no_wordpress_runtime_dependency(): void
    {
        $this->assertFalse(class_exists(WordPressPublishService::class));
        $this->assertArrayNotHasKey('wordpress', config('content'));
        $this->assertFalse(Schema::hasColumn('generated_contents', 'wordpress_post_id'));
    }

    public function test_disabled_config_blocks_daily_generation(): void
    {
        config(['content.enabled' => false]);
        $this->approvedJob();
        $stats = app(ContentGeneratorService::class)->generateDaily();
        $this->assertContains('content_generation_disabled', $stats['errors']);
        $this->assertDatabaseCount('generated_contents', 0);
    }

    public function test_operator_cannot_bypass_source_checks_via_cli_path(): void
    {
        $source = JobSource::factory()->create([
            'is_enabled' => true,
            'is_approved' => false,
            'reliability_level' => JobSourceReliability::Official,
        ]);
        $job = $this->approvedJob([], $source);
        $this->artisan('content:generate', ['--job' => $job->id])->assertSuccessful();
        $this->assertDatabaseCount('generated_contents', 0);
    }

    public function test_invalid_job_option_fails_cleanly(): void
    {
        $this->artisan('content:generate', ['--job' => 'abc'])->assertFailed();
        $this->artisan('content:generate', ['--job' => 999999])->assertFailed();
        $job = $this->approvedJob();
        $this->artisan('content:generate', ['--job' => $job->id, '--type' => 'NOT_A_TYPE'])->assertFailed();
    }

    public function test_cleanup_dry_run_and_skips_published(): void
    {
        $pub = GeneratedContent::query()->create([
            'title' => 'منتشر',
            'slug' => 'pub-1',
            'content' => str_repeat('متن فارسی منتشرشده. ', 20),
            'content_type' => ContentType::NewJobExam,
            'status' => ContentStatus::Published,
            'generation_attempts' => 1,
        ]);
        $pub->forceFill(['created_at' => now()->subDays(120), 'updated_at' => now()->subDays(120)])->save();

        $fail = GeneratedContent::query()->create([
            'title' => 'ناموفق',
            'slug' => 'fail-1',
            'content' => str_repeat('متن ناموفق. ', 20),
            'content_type' => ContentType::NewJobExam,
            'status' => ContentStatus::Failed,
            'generation_attempts' => 1,
        ]);
        $fail->forceFill(['created_at' => now()->subDays(120), 'updated_at' => now()->subDays(120)])->save();

        $this->artisan('content:cleanup', ['--days' => 90, '--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('would_delete=1');

        $this->artisan('content:cleanup', ['--days' => 90])->assertSuccessful();
        $this->assertDatabaseHas('generated_contents', ['slug' => 'pub-1']);
        $this->assertDatabaseMissing('generated_contents', ['slug' => 'fail-1']);
    }

    public function test_non_admin_forbidden_from_content_admin_api(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'jobseeker', 'status' => 'active']));
        $this->getJson('/api/v1/admin/generated-contents')->assertForbidden();
        $this->postJson('/api/v1/admin/generated-contents/generate-now')->assertForbidden();
    }

    public function test_quality_rejects_dangerous_html(): void
    {
        $job = $this->approvedJob();
        $content = new GeneratedContent([
            'title' => 'عنوان کافی برای تست امنیت',
            'content' => str_repeat('متن فارسی معتبر. ', 30).'<script>alert(1)</script>',
            'content_type' => ContentType::NewJobExam,
            'job_post_id' => $job->id,
        ]);
        $result = app(ContentQualityService::class)->validate($content, $job);
        $this->assertFalse($result['valid']);
    }

    public function test_update_existing_preserves_laravel_identity(): void
    {
        $job = $this->approvedJob();
        $svc = app(ContentGeneratorService::class);
        $first = $svc->generateForJob($job, ContentType::NewJobExam);
        $this->assertSame('created', $first['outcome'], (string) $first['error']);
        $id = $first['content']->id;
        $slug = $first['content']->slug;
        $blogId = $first['content']->blog_post_id;

        $job->update(['company_name' => 'بانک به‌روز شده']);
        $second = $svc->generateForJob($job->fresh(['source']), ContentType::NewJobExam);
        $this->assertSame('updated', $second['outcome'], (string) $second['error']);
        $this->assertSame($id, $second['content']->id);
        $this->assertSame($slug, $second['content']->slug);
        $this->assertSame($blogId, $second['content']->blog_post_id);
        $this->assertDatabaseCount('generated_contents', 1);
    }

    public function test_admin_unpublish(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $job = $this->approvedJob();
        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::NewJobExam);
        app(ContentGeneratorService::class)->publishContent($result['content']->fresh());

        $this->postJson('/api/v1/admin/generated-contents/'.$result['content']->id.'/unpublish')
            ->assertOk();
        $this->assertSame(ContentStatus::Draft, $result['content']->fresh()->status);
        $this->getJson('/api/v1/articles/'.$result['content']->slug)->assertNotFound();
    }
}
