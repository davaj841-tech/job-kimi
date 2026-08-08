<?php

namespace Tests\Unit\Aggregation;

use App\Services\Aggregation\JobNormalizer;
use App\Services\Aggregation\JobValidator;
use App\Services\Aggregation\Support\DateNormalizer;
use App\Services\Aggregation\Support\IranGeoNormalizer;
use App\Services\Aggregation\Support\PersianText;
use PHPUnit\Framework\TestCase;

class Phase4NormalizationUnitTest extends TestCase
{
    public function test_persian_text_normalizes_whitespace_and_zwnj(): void
    {
        $raw = "  کارشناس   اداری  ";
        $this->assertSame('کارشناس اداری', PersianText::normalize($raw));
    }

    public function test_arabic_characters_normalize_to_persian(): void
    {
        // Arabic yeh/kaf → Persian
        $this->assertSame('کارشناسی', PersianText::normalize('كارشناسي'));
        $this->assertSame('بانک', PersianText::normalize('بانك'));
    }

    public function test_persian_and_english_digits_normalize(): void
    {
        $this->assertSame('1403/01/15', PersianText::toEnglishDigits('۱۴۰۳/۰۱/۱۵'));
        $this->assertSame('1403/01/15', PersianText::toEnglishDigits('١٤٠٣/٠١/١٥'));
        $this->assertSame('2026', PersianText::toEnglishDigits('2026'));
    }

    public function test_date_normalizer_jalali_and_gregorian(): void
    {
        $dates = new DateNormalizer;

        $jalali = $dates->normalize('۱۴۰۳/۰۱/۱۵');
        $this->assertNotNull($jalali);
        $this->assertStringStartsWith('2024-04-03', $jalali);

        $greg = $dates->normalize('2026-12-01');
        $this->assertSame('2026-12-01 00:00:00', $greg);

        $this->assertNull($dates->normalize('not-a-date'));
        $this->assertNull($dates->normalize(null));
    }

    public function test_province_and_city_matching(): void
    {
        $geo = new IranGeoNormalizer;

        $this->assertSame('تهران', $geo->normalizeProvince('استان تهران'));
        $this->assertSame('آذربایجان شرقی', $geo->normalizeProvince('آذربايجان شرقي'));
        $this->assertSame('کهگیلویه و بویراحمد', $geo->normalizeProvince('کهگیلویه و بویر احمد'));
        $this->assertNull($geo->normalizeProvince('nowhere-land'));

        $this->assertSame('کرج', $geo->normalizeCity('شهر کرج'));
        $this->assertNull($geo->normalizeCity(''));
    }

    public function test_organization_and_title_normalization(): void
    {
        $n = new JobNormalizer;
        $out = $n->normalize([
            'title' => '  استخدام  كارشناس  ',
            'organization' => 'بانك ملت',
            'external_id' => 'X1',
            'source_url' => 'https://jobs.example.gov.ir/x1',
        ]);

        $this->assertSame('استخدام کارشناس', $out['title']);
        $this->assertSame('بانک ملت', $out['company_name']);
        $this->assertSame('استخدام کارشناس', $out['title_key']);
        $this->assertSame('بانک ملت', $out['organization_key']);
    }

    public function test_employment_type_and_quality_fields(): void
    {
        $n = new JobNormalizer;
        $out = $n->normalize([
            'title' => 'کارشناس',
            'company_name' => 'سازمان',
            'education' => 'کارشناسی',
            'field_of_study' => 'مدیریت',
            'experience' => '۳ سال',
            'employment_type' => 'تمام وقت',
            'requirements' => 'مسلط به رایانه',
            'registration_start' => '1403/01/01',
            'deadline' => '1403/02/01',
            'exam_date' => '1403/03/01',
            'publication_date' => '2026-01-01',
            'apply_url' => 'https://jobs.example.gov.ir/apply/1',
            'source_url' => 'https://jobs.example.gov.ir/feed',
            'external_id' => 'Q1',
        ]);

        $this->assertSame('full_time', $out['employment_type']);
        $this->assertSame('کارشناسی', $out['education']);
        $this->assertSame('مدیریت', $out['field_of_study']);
        $this->assertSame('3 سال', $out['experience']);
        $this->assertSame('مسلط به رایانه', $out['requirements']);
        $this->assertNotNull($out['registration_starts_at']);
        $this->assertNotNull($out['registration_deadline']);
        $this->assertNotNull($out['exam_date']);
        $this->assertNotNull($out['published_at']);
        $this->assertSame('https://jobs.example.gov.ir/apply/1', $out['registration_link']);
        $this->assertSame('https://jobs.example.gov.ir/feed', $out['source_url']);
    }

    public function test_does_not_invent_missing_information(): void
    {
        $n = new JobNormalizer;
        $out = $n->normalize([
            'title' => 'عنوان',
            'company_name' => 'سازمان',
            'external_id' => 'M1',
            'source_url' => 'https://jobs.example.gov.ir/m1',
        ]);

        $this->assertNull($out['province']);
        $this->assertNull($out['city']);
        $this->assertNull($out['education']);
        $this->assertNull($out['registration_deadline']);
        $this->assertNull($out['exam_date']);
        $this->assertNull($out['registration_link']);
    }

    public function test_invalid_urls_are_flagged_and_nulled(): void
    {
        $n = new JobNormalizer;
        $out = $n->normalize([
            'title' => 'عنوان',
            'company_name' => 'سازمان',
            'registration_link' => 'javascript:alert(1)',
            'source_url' => 'not-a-url',
            'external_id' => 'BAD',
        ]);

        $this->assertNull($out['registration_link']);
        $this->assertNull($out['source_url']);
        $this->assertTrue($out['_had_invalid_registration_link']);
        $this->assertTrue($out['_had_invalid_source_url']);
    }

    public function test_validator_rejects_malformed_and_incomplete_records(): void
    {
        $v = new JobValidator;

        $this->assertFalse($v->validate(['title' => 'ab', 'company_name' => 'X'])['valid']);
        $this->assertFalse($v->validate([
            'title' => 'عنوان کامل',
            'company_name' => 'سازمان',
        ])['valid']); // missing provenance

        $this->assertFalse($v->validate([
            'title' => 'عنوان کامل',
            'company_name' => 'سازمان',
            'external_id' => '1',
            '_had_invalid_registration_link' => true,
        ])['valid']);

        $this->assertFalse($v->validate([
            'title' => 'عنوان کامل',
            'company_name' => 'نامشخص',
            'external_id' => '1',
        ])['valid']);

        $ok = $v->validate([
            'title' => 'عنوان کامل',
            'company_name' => 'سازمان',
            'external_id' => '1',
            'source_url' => 'https://jobs.example.gov.ir/1',
            'registration_starts_at' => '2026-01-01 00:00:00',
            'registration_deadline' => '2026-02-01 00:00:00',
            'exam_date' => '2026-03-01 00:00:00',
        ]);
        $this->assertTrue($ok['valid']);

        $badRange = $v->validate([
            'title' => 'عنوان کامل',
            'company_name' => 'سازمان',
            'external_id' => '1',
            'registration_starts_at' => '2026-03-01 00:00:00',
            'registration_deadline' => '2026-02-01 00:00:00',
        ]);
        $this->assertFalse($badRange['valid']);
    }

    public function test_apply_and_source_urls_remain_separate(): void
    {
        $n = new JobNormalizer;
        $out = $n->normalize([
            'title' => 'عنوان',
            'company_name' => 'سازمان',
            'apply_url' => 'https://jobs.example.gov.ir/apply/9',
            'source_url' => 'https://jobs.example.gov.ir/listing/9',
            'external_id' => '9',
        ]);

        $this->assertSame('https://jobs.example.gov.ir/apply/9', $out['registration_link']);
        $this->assertSame('https://jobs.example.gov.ir/listing/9', $out['source_url']);
        $this->assertNotSame($out['registration_link'], $out['source_url']);
    }
}
