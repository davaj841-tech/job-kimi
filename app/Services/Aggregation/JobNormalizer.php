<?php

namespace App\Services\Aggregation;

use App\Contracts\Aggregation\JobNormalizerInterface;
use App\Services\Aggregation\Support\DateNormalizer;
use App\Services\Aggregation\Support\IranGeoNormalizer;
use App\Services\Aggregation\Support\PersianText;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class JobNormalizer implements JobNormalizerInterface
{
    public function __construct(
        protected IranGeoNormalizer $geo = new IranGeoNormalizer,
        protected DateNormalizer $dates = new DateNormalizer,
    ) {}

    public function normalize(array $raw): array
    {
        $title = $this->nullableLimited(Arr::get($raw, 'title'), 255);
        $company = $this->nullableLimited(
            Arr::get($raw, 'company_name')
            ?? Arr::get($raw, 'organization')
            ?? Arr::get($raw, 'company'),
            190
        );

        $description = $this->nullableLimited(Arr::get($raw, 'description'), 100000);
        $requirements = $this->nullableLimited(
            Arr::get($raw, 'requirements') ?? Arr::get($raw, 'requirement'),
            100000
        );

        $rawApply = Arr::get($raw, 'registration_link')
            ?? Arr::get($raw, 'apply_url')
            ?? Arr::get($raw, 'application_url');
        $rawSource = Arr::get($raw, 'source_url')
            ?? Arr::get($raw, 'listing_url');
        // `_endpoint_url` is crawl provenance only — never promote shared feed URLs to per-job source_url.

        $applyUrl = $this->nullableHttpUrl($rawApply);
        // Never copy application URL into source_url.
        $sourceUrl = $this->nullableHttpUrl($rawSource);

        $province = $this->geo->normalizeProvince(
            is_string(Arr::get($raw, 'province')) ? Arr::get($raw, 'province') : null
        );
        $city = $this->geo->normalizeCity(
            is_string(Arr::get($raw, 'city')) ? Arr::get($raw, 'city') : null
        );

        $normalized = [
            'title' => $title,
            'company_name' => $company,
            'description' => $description,
            'requirements' => $requirements,
            'education' => $this->nullableLimited(Arr::get($raw, 'education') ?? Arr::get($raw, 'degree'), 190),
            'field_of_study' => $this->nullableLimited(Arr::get($raw, 'field_of_study') ?? Arr::get($raw, 'major'), 190),
            'experience' => $this->nullableLimited(Arr::get($raw, 'experience') ?? Arr::get($raw, 'experience_years'), 190),
            'employment_type' => $this->normalizeEmploymentType(Arr::get($raw, 'employment_type') ?? Arr::get($raw, 'contract_type')),
            'province' => $province,
            'city' => $city,
            'provinces' => $province ? [$province] : null,
            'job_category' => $this->nullableLimited(Arr::get($raw, 'job_category') ?? Arr::get($raw, 'category'), 190),
            'registration_starts_at' => $this->dates->normalize(
                Arr::get($raw, 'registration_starts_at') ?? Arr::get($raw, 'registration_start') ?? Arr::get($raw, 'start_date')
            ),
            'registration_deadline' => $this->dates->normalize(
                Arr::get($raw, 'registration_deadline') ?? Arr::get($raw, 'deadline') ?? Arr::get($raw, 'validThrough')
            ),
            'exam_date' => $this->dates->normalize(Arr::get($raw, 'exam_date')),
            'published_at' => $this->dates->normalize(
                Arr::get($raw, 'published_at') ?? Arr::get($raw, 'publication_date') ?? Arr::get($raw, 'pubDate')
            ),
            'registration_link' => $applyUrl,
            'source_url' => $sourceUrl,
            'external_id' => $this->nullableLimited(
                Arr::get($raw, 'external_id') ?? Arr::get($raw, 'id') ?? Arr::get($raw, 'guid'),
                190
            ),
            'job_source_id' => Arr::get($raw, 'job_source_id'),
            '_endpoint_url' => is_string(Arr::get($raw, '_endpoint_url')) ? Arr::get($raw, '_endpoint_url') : null,
            '_had_invalid_registration_link' => $this->wasInvalidUrl($rawApply),
            '_had_invalid_source_url' => $this->wasInvalidUrl(
                Arr::get($raw, 'source_url') ?? Arr::get($raw, 'listing_url')
            ),
        ];

        $titleKey = PersianText::normalizeKey($normalized['title']);
        $orgKey = PersianText::normalizeKey($normalized['company_name']);
        $normalized['title_key'] = $titleKey;
        $normalized['organization_key'] = $orgKey;
        $normalized['content_hash'] = $this->hashFor($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function withRecomputedHash(array $normalized): array
    {
        $normalized['title_key'] = $normalized['title_key'] ?? PersianText::normalizeKey($normalized['title'] ?? null);
        $normalized['organization_key'] = PersianText::normalizeKey($normalized['company_name'] ?? null);
        $normalized['content_hash'] = $this->hashFor($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function hashFor(array $normalized): string
    {
        return hash('sha256', implode('|', [
            (string) ($normalized['title_key'] ?? ''),
            (string) ($normalized['organization_key'] ?? ''),
            (string) ($normalized['registration_link'] ?? ''),
            (string) ($normalized['source_url'] ?? ''),
            (string) ($normalized['registration_deadline'] ?? ''),
            (string) ($normalized['external_id'] ?? ''),
        ]));
    }

    protected function nullableLimited(mixed $value, int $limit): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $v = PersianText::normalize((string) $value);
        if ($v === null) {
            return null;
        }
        // External feed/HTML content must never be trusted as HTML.
        $v = html_entity_decode(strip_tags($v), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $v = PersianText::normalize($v) ?? $v;
        $v = PersianText::toEnglishDigits($v) ?? $v;
        $v = trim(preg_replace('/\s+/u', ' ', $v) ?? $v);

        return $v === '' ? null : Str::limit($v, $limit, '');
    }

    protected function nullableHttpUrl(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $v = PersianText::toEnglishDigits(PersianText::normalize((string) $value));
        if ($v === null) {
            return null;
        }
        $v = trim($v);
        if (! preg_match('#^https?://#i', $v)) {
            return null;
        }
        if (! filter_var($v, FILTER_VALIDATE_URL)) {
            return null;
        }

        return Str::limit($v, 500, '');
    }

    protected function wasInvalidUrl(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (! is_string($value) && ! is_numeric($value)) {
            return true;
        }

        return trim((string) $value) !== '' && $this->nullableHttpUrl($value) === null;
    }

    protected function normalizeEmploymentType(mixed $value): ?string
    {
        $v = PersianText::normalizeKey(is_string($value) || is_numeric($value) ? (string) $value : null);
        if ($v === null) {
            return null;
        }

        $map = [
            'full time' => 'full_time',
            'fulltime' => 'full_time',
            'تمام وقت' => 'full_time',
            'تماموقت' => 'full_time',
            'part time' => 'part_time',
            'پاره وقت' => 'part_time',
            'پارهوقت' => 'part_time',
            'contract' => 'contract',
            'پیمانی' => 'contract',
            'قراردادی' => 'contract',
            'internship' => 'internship',
            'کارآموزی' => 'internship',
            'military' => 'military',
            'امریه' => 'military',
        ];

        foreach ($map as $alias => $canonical) {
            $aliasKey = PersianText::normalizeKey($alias);
            if ($aliasKey && ($v === $aliasKey || str_contains($v, $aliasKey))) {
                return $canonical;
            }
        }

        return Str::limit(str_replace(' ', '_', $v), 80, '');
    }
}
