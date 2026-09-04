<?php

namespace Tests\Feature;

use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobEndpointType;
use App\Enums\Aggregation\JobSourceReliability;
use App\Http\Requests\Api\JobPostStoreRequest;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use App\Services\Aggregation\CrawlOrchestrator;
use App\Services\Aggregation\DuplicateDetector;
use App\Services\Aggregation\JobNormalizer;
use App\Services\Aggregation\JobPublisher;
use App\Services\Aggregation\JobValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobAggregatorPhase4Test extends TestCase
{
    use RefreshDatabase;

    protected function makeSource(array $attrs = []): JobSource
    {
        return JobSource::factory()->whitelisted()->create(array_merge([
            'name' => 'سازمان سنجش',
            'domain' => 'jobs.example.gov.ir',
            'official_url' => 'https://jobs.example.gov.ir/',
            'crawler_type' => JobCrawlerType::Json,
            'reliability_level' => JobSourceReliability::Official,
        ], $attrs));
    }

    public function test_pipeline_normalizes_persian_quality_fields_and_keeps_pending(): void
    {
        Http::fake([
            'jobs.example.gov.ir/*' => Http::response([
                'jobs' => [[
                    'id' => 'P4-1',
                    'title' => 'استخدام كارشناس اداري',
                    'organization' => 'بانك ملي',
                    'description' => 'شرح آگهی',
                    'requirements' => 'حداقل لیسانس',
                    'education' => 'کارشناسی',
                    'field_of_study' => 'حسابداری',
                    'experience' => '۲ سال',
                    'employment_type' => 'تمام وقت',
                    'province' => 'استان تهران',
                    'city' => 'شهر تهران',
                    'registration_start' => '۱۴۰۴/۰۱/۰۱',
                    'deadline' => '۱۴۰۴/۰۲/۰۱',
                    'exam_date' => '۱۴۰۴/۰۳/۰۱',
                    'publication_date' => '2026-01-10',
                    'apply_url' => 'https://jobs.example.gov.ir/apply/p4-1',
                    'source_url' => 'https://jobs.example.gov.ir/listing/p4-1',
                ]],
            ], 200),
        ]);

        $source = $this->makeSource();
        JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://jobs.example.gov.ir/api/jobs',
            'endpoint_type' => JobEndpointType::Json,
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);

        $this->assertSame(1, $result['summary']['created']);
        $post = JobPost::query()->where('external_id', 'P4-1')->first();
        $this->assertNotNull($post);
        $this->assertSame('pending', $post->status);
        $this->assertSame('استخدام کارشناس اداری', $post->title);
        $this->assertSame('بانک ملی', $post->company_name);
        $this->assertSame('تهران', $post->province);
        $this->assertSame('تهران', $post->city);
        $this->assertSame('کارشناسی', $post->education);
        $this->assertSame('حسابداری', $post->field_of_study);
        $this->assertSame('2 سال', $post->experience);
        $this->assertSame('full_time', $post->employment_type);
        $this->assertSame('حداقل لیسانس', $post->requirements);
        $this->assertSame('https://jobs.example.gov.ir/apply/p4-1', $post->registration_link);
        $this->assertSame('https://jobs.example.gov.ir/listing/p4-1', $post->source_url);
        $this->assertSame($source->id, $post->job_source_id);
        $this->assertNotNull($post->registration_starts_at);
        $this->assertNotNull($post->registration_deadline);
        $this->assertNotNull($post->exam_date);
        $this->assertNotNull($post->published_at);
        $this->assertNotNull($post->content_hash);
    }

    public function test_malformed_records_are_rejected(): void
    {
        Http::fake([
            'jobs.example.gov.ir/*' => Http::response([
                'jobs' => [
                    ['id' => 'BAD-1', 'title' => 'ab', 'company_name' => 'X'],
                    [
                        'id' => 'BAD-2',
                        'title' => 'عنوان معتبر',
                        'company_name' => 'سازمان',
                        'apply_url' => 'ftp://jobs.example.gov.ir/x',
                    ],
                    [
                        'id' => 'OK-1',
                        'title' => 'عنوان معتبر کامل',
                        'company_name' => 'سازمان',
                        'apply_url' => 'https://jobs.example.gov.ir/ok',
                        'source_url' => 'https://jobs.example.gov.ir/api/jobs',
                    ],
                ],
            ], 200),
        ]);

        $source = $this->makeSource();
        JobSourceEndpoint::factory()->create([
            'job_source_id' => $source->id,
            'url' => 'https://jobs.example.gov.ir/api/jobs',
            'endpoint_type' => JobEndpointType::Json,
        ]);

        $result = app(CrawlOrchestrator::class)->crawlSource($source);

        $this->assertSame(1, $result['summary']['created']);
        $this->assertGreaterThanOrEqual(2, $result['summary']['errors']);
        $this->assertDatabaseCount('job_posts', 1);
        $this->assertDatabaseHas('job_posts', ['external_id' => 'OK-1', 'status' => 'pending']);
        $this->assertDatabaseMissing('job_posts', ['external_id' => 'BAD-1']);
        $this->assertDatabaseMissing('job_posts', ['external_id' => 'BAD-2']);
    }

    public function test_duplicate_detection_by_external_id_and_urls(): void
    {
        $source = $this->makeSource();
        $normalizer = app(JobNormalizer::class);
        $detector = app(DuplicateDetector::class);
        $publisher = app(JobPublisher::class);

        $base = $normalizer->normalize([
            'title' => 'کارشناس منابع انسانی',
            'company_name' => 'سازمان سنجش',
            'external_id' => 'DUP-EXT',
            'apply_url' => 'https://jobs.example.gov.ir/apply/dup',
            'source_url' => 'https://jobs.example.gov.ir/list/dup',
            'deadline' => '2026-12-01',
            'job_source_id' => $source->id,
        ]);
        $base['job_source_id'] = $source->id;
        $this->assertTrue(app(JobValidator::class)->validate($base)['valid']);
        $post = $publisher->publish($base, $source);

        $byExt = $detector->findDuplicate(array_merge($base, ['title' => 'عنوان دیگر']));
        $this->assertTrue($byExt['is_duplicate']);
        $this->assertSame('source_external_id', $byExt['reason']);
        $this->assertTrue($post->is($byExt['original']));

        $byApply = $detector->findDuplicate($normalizer->normalize([
            'title' => 'عنوان دیگر ۲',
            'company_name' => 'دیگر',
            'apply_url' => 'https://jobs.example.gov.ir/apply/dup',
            'external_id' => 'OTHER',
            'job_source_id' => $source->id,
        ]));
        $this->assertTrue($byApply['is_duplicate']);
        $this->assertSame('registration_link', $byApply['reason']);

        $bySource = $detector->findDuplicate($normalizer->normalize([
            'title' => 'عنوان دیگر ۳',
            'company_name' => 'دیگر',
            'source_url' => 'https://jobs.example.gov.ir/list/dup',
            'external_id' => 'OTHER2',
            'job_source_id' => $source->id,
        ]));
        $this->assertTrue($bySource['is_duplicate']);
        $this->assertSame('source_url', $bySource['reason']);
    }

    public function test_duplicate_by_title_org_deadline_not_title_alone(): void
    {
        $source = $this->makeSource();
        $n = app(JobNormalizer::class);
        $d = app(DuplicateDetector::class);
        $p = app(JobPublisher::class);

        $first = $n->normalize([
            'title' => 'كارشناس IT',
            'company_name' => 'بانك ملت',
            'deadline' => '2026-11-01',
            'source_url' => 'https://jobs.example.gov.ir/a',
            'external_id' => 'T1',
        ]);
        $first['job_source_id'] = $source->id;
        $p->publish($first, $source);

        $sameTitleDifferentOrg = $n->normalize([
            'title' => 'کارشناس IT',
            'company_name' => 'بانک دیگر',
            'deadline' => '2026-11-01',
            'source_url' => 'https://jobs.example.gov.ir/b',
            'external_id' => 'T2',
        ]);
        $sameTitleDifferentOrg['job_source_id'] = $source->id;
        $this->assertFalse($d->findDuplicate($sameTitleDifferentOrg)['is_duplicate']);

        $sameTitleOrgDeadline = $n->normalize([
            'title' => 'کارشناس IT',
            'company_name' => 'بانک ملت',
            'deadline' => '2026-11-01',
            'source_url' => 'https://jobs.example.gov.ir/c',
            'external_id' => 'T3',
        ]);
        $sameTitleOrgDeadline['job_source_id'] = $source->id;
        $hit = $d->findDuplicate($sameTitleOrgDeadline);
        $this->assertTrue($hit['is_duplicate']);
        $this->assertSame('title_org_deadline', $hit['reason']);
    }

    public function test_updating_existing_aggregated_job_preserves_pending_and_source(): void
    {
        // Isolate upsert behavior from detail-page enrichment HTTP calls.
        config(['aggregation.detail_fetch.enabled' => false]);

        Http::fake([
            'jobs.example.gov.ir/*' => Http::sequence()
                ->push([[
                    'id' => 'UPD-1',
                    'title' => 'عنوان اولیه',
                    'description' => 'نسخه یک',
                    'education' => 'دیپلم',
                    'apply_url' => 'https://jobs.example.gov.ir/apply/upd',
                    'source_url' => 'https://jobs.example.gov.ir/list/upd',
                ]], 200)
                ->push([[
                    'id' => 'UPD-1',
                    'title' => 'عنوان به‌روز',
                    'description' => 'نسخه دو',
                    'education' => 'کارشناسی',
                    'apply_url' => 'https://jobs.example.gov.ir/apply/upd',
                    'source_url' => 'https://jobs.example.gov.ir/list/upd',
                ]], 200),
        ]);

        $source = $this->makeSource(['crawler_type' => JobCrawlerType::Api]);
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

        $post = JobPost::query()->where('external_id', 'UPD-1')->first();
        $this->assertSame('عنوان به‌روز', $post->title);
        $this->assertStringContainsString('نسخه دو', strip_tags((string) $post->description));
        $this->assertSame('کارشناسی', $post->education);
        $this->assertSame('pending', $post->status);
        $this->assertSame($source->id, $post->job_source_id);
        $this->assertSame('https://jobs.example.gov.ir/apply/upd', $post->registration_link);
        $this->assertSame('https://jobs.example.gov.ir/list/upd', $post->source_url);
    }

    public function test_missing_optional_fields_remain_null(): void
    {
        $source = $this->makeSource();
        $normalized = app(JobNormalizer::class)->normalize([
            'title' => 'عنوان حداقلی',
            'company_name' => 'سازمان',
            'external_id' => 'MIN-1',
            'source_url' => 'https://jobs.example.gov.ir/min',
        ]);
        $normalized['job_source_id'] = $source->id;

        $post = app(JobPublisher::class)->publish($normalized, $source);

        $this->assertNull($post->province);
        $this->assertNull($post->city);
        $this->assertNull($post->education);
        $this->assertNull($post->field_of_study);
        $this->assertNull($post->experience);
        $this->assertNull($post->employment_type);
        $this->assertNull($post->requirements);
        $this->assertNull($post->registration_deadline);
        $this->assertNull($post->exam_date);
        $this->assertNull($post->registration_starts_at);
        $this->assertSame('pending', $post->status);
        $this->assertSame($source->id, $post->job_source_id);
        $this->assertSame('https://jobs.example.gov.ir/min', $post->source_url);
    }

    public function test_manual_job_post_store_rules_unchanged_for_required_fields(): void
    {
        $request = new JobPostStoreRequest;
        $rules = $request->rules();

        $this->assertContains('required', $rules['title']);
        $this->assertContains('required', $rules['description']);
        $this->assertContains('required', $rules['job_classification_id']);
        $this->assertContains('required', $rules['registration_deadline']);
        // Aggregation quality columns are not forced onto the manual form.
        $this->assertArrayNotHasKey('education', $rules);
        $this->assertArrayNotHasKey('employment_type', $rules);
    }
}
