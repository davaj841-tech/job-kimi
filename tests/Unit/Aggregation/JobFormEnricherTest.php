<?php

namespace Tests\Unit\Aggregation;

use App\Models\JobClassification;
use App\Models\JobSource;
use App\Services\Aggregation\JobFormEnricher;
use App\Services\Aggregation\Support\DateNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobFormEnricherTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_from_text_finds_jalali_deadline(): void
    {
        $dates = new DateNormalizer;
        $out = $dates->extractFromText('مهلت ثبت نام تا 1404/05/20');

        $this->assertNotNull($out['registration_deadline']);
        $this->assertNull($out['exam_date']);
    }

    public function test_enricher_maps_bank_classification_and_builds_description(): void
    {
        config(['aggregation.detail_fetch.enabled' => false]);

        $classificationId = JobClassification::query()->firstOrCreate(
            ['name' => 'بانک‌ها'],
            ['sort_order' => 1, 'is_active' => true]
        )->id;

        $source = JobSource::factory()->create([
            'name' => 'بانک سینا',
            'slug' => 'bank-sina',
            'source_type' => 'bank',
        ]);

        $enricher = new JobFormEnricher;
        $out = $enricher->enrich([
            'title' => 'آزمون استخدامی بانک سینا — مهلت 1404/06/10',
            'company_name' => 'بانک سینا',
            'registration_link' => 'https://www.sinabank.ir/jobs/1',
            'source_url' => 'https://www.sinabank.ir/jobs/1',
            'external_id' => 'test-1',
        ], $source);

        $this->assertSame($classificationId, $out['job_classification_id']);
        $this->assertNotNull($out['registration_deadline']);
        $this->assertStringContainsString('<h3>عنوان آگهی</h3>', (string) $out['description']);
        $this->assertStringContainsString('بانک سینا', (string) $out['description']);
        $this->assertNotEmpty($out['seo_tag']);
    }
}
