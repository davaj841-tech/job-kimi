<?php

namespace Tests\Unit\Aggregation;

use App\Models\JobSource;
use App\Services\Aggregation\JobDetailPageExtractor;
use App\Services\Aggregation\SafeHttpFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobDetailPageExtractorTest extends TestCase
{
    use RefreshDatabase;

    public function test_extracts_education_and_deadline_from_detail_html(): void
    {
        config([
            'aggregation.detail_fetch.enabled' => true,
            'aggregation.detail_fetch.default_deadline_days' => 30,
        ]);

        Http::fake([
            'https://example.test/job/1' => Http::response(
                '<html><body><article><h1>استخدام</h1>'
                .'<p>مهلت ثبت نام تا 1404/07/15</p>'
                .'<p>حداقل مدرک: کارشناسی</p>'
                .'<p>رشته تحصیلی: مهندسی کامپیوتر</p>'
                .'<p>شرایط احراز: داوطلبان دارای کارت پایان خدمت</p>'
                .'</article></body></html>',
                200
            ),
        ]);

        $source = JobSource::factory()->create([
            'domain' => 'example.test',
            'official_url' => 'https://example.test/',
        ]);

        $extractor = app(JobDetailPageExtractor::class);
        $out = $extractor->enrichFromUrl([
            'title' => 'آزمون استخدامی نمونه',
            'company_name' => 'سازمان نمونه',
            'source_url' => 'https://example.test/job/1',
            'registration_link' => 'https://example.test/job/1',
            'description' => 'آزمون استخدامی نمونه',
        ], $source);

        $this->assertNotNull($out['registration_deadline']);
        $this->assertSame('کارشناسی', $out['education']);
        $this->assertSame('مهندسی کامپیوتر', $out['field_of_study']);
        $this->assertStringContainsString('کارت پایان خدمت', (string) $out['requirements']);
        $this->assertNotNull($out['published_at']);
    }

    public function test_skips_fetch_when_disabled(): void
    {
        config(['aggregation.detail_fetch.enabled' => false]);

        Http::fake();

        $source = JobSource::factory()->create();
        $extractor = new JobDetailPageExtractor(app(SafeHttpFetcher::class));
        $out = $extractor->enrichFromUrl([
            'title' => 'test',
            'source_url' => 'https://example.test/job/1',
        ], $source);

        $this->assertSame('test', $out['title']);
        Http::assertNothingSent();
    }
}
