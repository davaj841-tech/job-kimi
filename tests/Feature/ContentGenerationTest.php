<?php

namespace Tests\Feature;

use App\Enums\Aggregation\JobSourceReliability;
use App\Enums\Content\ContentStatus;
use App\Enums\Content\ContentType;
use App\Jobs\GenerateDailyContentJob;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\User;
use App\Services\Content\ContentDuplicateDetector;
use App\Services\Content\ContentGeneratorService;
use App\Services\Content\ContentQualityService;
use App\Services\Content\ContentRenderer;
use App\Services\Content\InternalLinkService;
use Database\Seeders\ContentTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentGenerationTest extends TestCase
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
            'experience' => '۲ سال',
            'employment_type' => 'رسمی',
            'province' => 'تهران',
            'provinces' => ['تهران'],
            'city' => 'تهران',
            'job_category' => 'بانکی',
            'registration_starts_at' => now()->subDay(),
            'registration_deadline' => now()->addDays(10),
            'exam_date' => now()->addDays(40),
            'registration_link' => 'https://careers.example.gov.ir/apply',
            'source_url' => 'https://careers.example.gov.ir/job/1',
        ], $extra));
    }

    public function test_renderer_replaces_placeholders_and_omits_empty_fields(): void
    {
        $job = $this->approvedJob([
            'exam_date' => null,
            'city' => null,
        ]);
        $renderer = app(ContentRenderer::class);
        $ctx = $renderer->contextFromJob($job);
        $html = $renderer->render(
            "<p>سازمان: {organization}</p>\n<p>آزمون: {exam_date}</p>\n<p>شهر: {city}</p>",
            $ctx
        );

        $this->assertStringContainsString('بانک نمونه', $html);
        $this->assertStringNotContainsString('{exam_date}', $html);
        $this->assertStringNotContainsString('{city}', $html);
    }

    public function test_renderer_never_invents_missing_dates(): void
    {
        $job = $this->approvedJob([
            'registration_deadline' => null,
            'registration_starts_at' => null,
            'exam_date' => null,
        ]);
        $ctx = app(ContentRenderer::class)->contextFromJob($job);
        $this->assertSame('', $ctx['registration_deadline']);
        $this->assertSame('', $ctx['exam_date']);
        $this->assertSame('', $ctx['registration_starts_at']);
    }

    public function test_quality_rejects_placeholder_residue(): void
    {
        $job = $this->approvedJob();
        $content = new GeneratedContent([
            'title' => 'عنوان کافی برای تست کیفیت',
            'slug' => 'quality-test-1',
            'content' => str_repeat('متن فارسی معتبر برای آزمون کیفیت محتوا. ', 20).'{organization}',
            'content_type' => ContentType::NewJobExam,
            'status' => ContentStatus::Draft,
            'job_post_id' => $job->id,
        ]);
        $result = app(ContentQualityService::class)->validate($content, $job);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_quality_rejects_short_content(): void
    {
        $job = $this->approvedJob();
        $content = new GeneratedContent([
            'title' => 'عنوان کافی',
            'slug' => 'short-1',
            'content' => 'کوتاه',
            'content_type' => ContentType::NewJobExam,
            'job_post_id' => $job->id,
        ]);
        $result = app(ContentQualityService::class)->validate($content, $job);
        $this->assertFalse($result['valid']);
    }

    public function test_unverified_source_is_rejected(): void
    {
        $source = JobSource::factory()->create([
            'is_enabled' => true,
            'is_approved' => true,
            'reliability_level' => JobSourceReliability::Unverified,
        ]);
        $job = $this->approvedJob([], $source);
        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::OrganizationRecruitment);
        $this->assertSame('skipped', $result['outcome']);
        $this->assertSame('source_not_allowed', $result['error']);
        $this->assertDatabaseCount('generated_contents', 0);
    }

    public function test_unapproved_job_is_rejected(): void
    {
        $job = $this->approvedJob(['status' => 'pending']);
        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::OrganizationRecruitment);
        $this->assertSame('skipped', $result['outcome']);
        $this->assertSame('job_not_approved', $result['error']);
    }

    public function test_generates_persian_content_from_job(): void
    {
        $job = $this->approvedJob();
        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::NewJobExam);

        $this->assertSame('created', $result['outcome'], (string) $result['error']);
        $this->assertNotNull($result['content']);
        $this->assertStringContainsString('بانک نمونه', $result['content']->title);
        $this->assertStringNotContainsString('{', $result['content']->content);
        $this->assertSame(ContentStatus::Draft, $result['content']->status);
        $this->assertNotNull($result['content']->content_hash);
        $this->assertSame($job->id, $result['content']->job_post_id);
    }

    public function test_duplicate_command_does_not_create_second_article(): void
    {
        $job = $this->approvedJob();
        $svc = app(ContentGeneratorService::class);
        $first = $svc->generateForJob($job, ContentType::NewJobExam);
        $this->assertSame('created', $first['outcome'], (string) $first['error']);
        $second = $svc->generateForJob($job, ContentType::NewJobExam);
        $this->assertSame('skipped', $second['outcome']);
        $this->assertSame('duplicate_unchanged', $second['error']);
        $this->assertDatabaseCount('generated_contents', 1);
    }

    public function test_updates_existing_when_source_data_changes(): void
    {
        $job = $this->approvedJob();
        $svc = app(ContentGeneratorService::class);
        $first = $svc->generateForJob($job, ContentType::NewJobExam);
        $this->assertSame('created', $first['outcome'], (string) $first['error']);

        $job->update(['company_name' => 'بانک ملی نمونه']);
        $job->refresh()->load('source');
        $second = $svc->generateForJob($job->fresh(['source']), ContentType::NewJobExam);
        $this->assertSame('updated', $second['outcome'], (string) $second['error']);
        $this->assertDatabaseCount('generated_contents', 1);
        $this->assertStringContainsString('بانک ملی نمونه', $second['content']->title);
    }

    public function test_slug_and_hash_helpers(): void
    {
        $dup = app(ContentDuplicateDetector::class);
        $type = ContentType::NewJobExam;
        $hash = $dup->hash('عنوان', '<p>متن</p>', $type, 12);
        $this->assertSame(64, strlen($hash));

        GeneratedContent::query()->create([
            'title' => 'عنوان',
            'slug' => 'my-slug',
            'content' => str_repeat('متن کافی برای ذخیره. ', 30),
            'content_type' => $type,
            'status' => ContentStatus::Draft,
            'content_hash' => $hash,
            'generation_attempts' => 1,
        ]);

        $this->assertTrue($dup->slugExists('my-slug'));
        $this->assertTrue($dup->hashExists($hash));
    }

    public function test_internal_links_appended_without_spam(): void
    {
        $job = $this->approvedJob();
        $html = app(InternalLinkService::class)->appendLinks('<p>بدنه</p>', $job);
        $this->assertStringContainsString('/jobs/'.$job->id, $html);
        $this->assertLessThanOrEqual(4, substr_count($html, '<li>'));
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_laravel_only_generation_without_network(): void
    {
        Http::fake(function () {
            $this->fail('Content generation must not make HTTP requests');
        });

        $job = $this->approvedJob();
        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::RegistrationGuide);
        $this->assertSame('created', $result['outcome'], (string) $result['error']);
        $this->assertNotNull($result['content']->blog_post_id);
        $this->assertFalse(Schema::hasColumn('generated_contents', 'wordpress_post_id'));
        $this->assertDatabaseHas('generated_contents', ['id' => $result['content']->id, 'status' => 'draft']);
    }

    public function test_publish_mode_publish_sets_laravel_published_status(): void
    {
        config(['content.publish_mode' => 'publish']);
        Http::fake(function () {
            $this->fail('No external HTTP during Laravel publish');
        });

        $job = $this->approvedJob();
        $result = app(ContentGeneratorService::class)->generateForJob($job, ContentType::OrganizationRecruitment);
        $this->assertSame('created', $result['outcome'], (string) $result['error']);
        $this->assertTrue($result['published']);
        $this->assertSame(ContentStatus::Published, $result['content']->fresh()->status);
        $this->assertNotNull($result['content']->published_at);
    }

    public function test_daily_respects_max_articles(): void
    {
        config(['content.max_articles_per_day' => 1]);
        $this->approvedJob(['company_name' => 'سازمان الف']);
        $this->approvedJob([
            'company_name' => 'سازمان ب',
            'title' => 'آزمون دوم',
            'job_source_id' => $this->trustedSource(['domain' => 'b.example.gov.ir', 'slug' => 'src-b'])->id,
        ]);

        $stats = app(ContentGeneratorService::class)->generateDaily();
        $this->assertSame(1, $stats['created'] + $stats['updated']);

        $stats2 = app(ContentGeneratorService::class)->generateDaily();
        $this->assertSame(0, $stats2['created'] + $stats2['updated']);
        $this->assertContains('max_articles_per_day_reached', $stats2['errors']);
    }

    public function test_artisan_generate_daily_is_idempotent(): void
    {
        $this->approvedJob();
        $this->artisan('content:generate-daily')->assertSuccessful();
        $count = GeneratedContent::query()->whereNotIn('status', ['failed', 'skipped'])->count();
        $this->assertGreaterThanOrEqual(1, $count);
        $this->artisan('content:generate-daily')->assertSuccessful();
        $this->assertSame($count, GeneratedContent::query()->whereNotIn('status', ['failed', 'skipped'])->count());
    }

    public function test_artisan_generate_for_job(): void
    {
        $job = $this->approvedJob();
        $this->artisan('content:generate', ['--job' => $job->id])->assertSuccessful();
        $this->assertDatabaseHas('generated_contents', ['job_post_id' => $job->id]);
    }

    public function test_schedule_job_runs_generator(): void
    {
        $this->approvedJob();
        (new GenerateDailyContentJob)->handle(app(ContentGeneratorService::class));
        $this->assertGreaterThanOrEqual(1, GeneratedContent::query()->count());
    }

    public function test_admin_dashboard_requires_auth(): void
    {
        $this->getJson('/api/v1/admin/generated-contents/dashboard')->assertUnauthorized();
    }

    public function test_admin_can_list_and_generate(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $this->approvedJob();

        $this->getJson('/api/v1/admin/generated-contents/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/v1/admin/generated-contents/generate-now', ['seed_templates' => false])
            ->assertOk();

        config([
            'content.enabled' => false,
            'content.daily_generation_enabled' => false,
        ]);
        $forced = $this->postJson('/api/v1/admin/generated-contents/generate-now', ['seed_templates' => false])
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertNotContains('content_generation_disabled', $forced->json('data.errors') ?? []);

        $this->getJson('/api/v1/admin/generated-contents')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_source_allowed_levels(): void
    {
        $quality = app(ContentQualityService::class);
        $official = $this->trustedSource(['reliability_level' => JobSourceReliability::Official]);
        $this->assertTrue($quality->sourceAllowed($official));

        $unverified = JobSource::factory()->create([
            'is_enabled' => true,
            'is_approved' => true,
            'reliability_level' => JobSourceReliability::Unverified,
        ]);
        $this->assertFalse($quality->sourceAllowed($unverified));

        $disabled = JobSource::factory()->whitelisted()->create(['is_enabled' => false]);
        $this->assertFalse($quality->sourceAllowed($disabled));
    }
}
