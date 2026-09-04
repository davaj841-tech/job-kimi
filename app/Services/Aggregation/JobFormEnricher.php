<?php

namespace App\Services\Aggregation;

use App\Models\JobClassification;
use App\Models\JobSource;
use App\Services\Aggregation\Support\DateNormalizer;
use App\Services\Aggregation\Support\IranGeoNormalizer;
use App\Services\Aggregation\Support\PersianText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Morilog\Jalali\Jalalian;

/**
 * Maps normalized crawler items onto JobPost form fields (classification, dates, description HTML).
 */
class JobFormEnricher
{
    public function __construct(
        protected DateNormalizer $dates = new DateNormalizer,
        protected IranGeoNormalizer $geo = new IranGeoNormalizer,
        protected ?JobDetailPageExtractor $detailPages = null,
    ) {}

    protected function detailPages(): JobDetailPageExtractor
    {
        return $this->detailPages ??= app(JobDetailPageExtractor::class);
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    public function enrich(array $normalized, JobSource $source): array
    {
        $normalized = $this->detailPages()->enrichFromUrl($normalized, $source);

        $scanText = implode("\n", array_filter([
            is_string($normalized['title'] ?? null) ? $normalized['title'] : null,
            is_string($normalized['description'] ?? null) ? $normalized['description'] : null,
            is_string($normalized['company_name'] ?? null) ? $normalized['company_name'] : null,
        ]));

        $extracted = $this->dates->extractFromText($scanText);
        foreach (['registration_deadline', 'registration_starts_at', 'exam_date'] as $field) {
            if (empty($normalized[$field]) && ! empty($extracted[$field])) {
                $normalized[$field] = $extracted[$field];
            }
        }

        if ($this->isNationwide($scanText)) {
            $normalized['province'] = 'سراسر کشور';
            $normalized['provinces'] = ['سراسر کشور'];
        } elseif (empty($normalized['province'])) {
            $province = $this->extractProvinceFromText($scanText);
            if ($province !== null) {
                $normalized['province'] = $province;
                $normalized['provinces'] = [$province];
            }
        }

        if (empty($normalized['job_classification_id'])) {
            $classificationId = $this->inferClassificationId(
                (string) ($normalized['title'] ?? ''),
                (string) ($normalized['company_name'] ?? $source->name),
                $source
            );
            if ($classificationId !== null) {
                $normalized['job_classification_id'] = $classificationId;
            }
        }

        if (empty($normalized['employment_type'])) {
            $employmentType = $this->inferEmploymentType($scanText);
            if ($employmentType !== null) {
                $normalized['employment_type'] = $employmentType;
            }
        }

        $normalized['description'] = $this->buildFormDescription($normalized, $source);

        if (empty($normalized['seo_tag'])) {
            $normalized['seo_tag'] = $this->suggestSeoTag($normalized);
        }

        return $normalized;
    }

    protected function isNationwide(string $text): bool
    {
        $key = PersianText::normalizeKey($text) ?? '';

        foreach (['سراسر کشور', 'سراسری', 'کشوری', 'تمام استان'] as $phrase) {
            $p = PersianText::normalizeKey($phrase);
            if ($p && str_contains($key, $p)) {
                return true;
            }
        }

        return false;
    }

    protected function extractProvinceFromText(string $text): ?string
    {
        $normalized = PersianText::normalize($text);
        if ($normalized === null) {
            return null;
        }

        foreach (IranGeoNormalizer::PROVINCES as $province) {
            if (str_contains($normalized, $province)) {
                return $this->geo->normalizeProvince($province);
            }
        }

        if (preg_match('/استان\s+([\p{L}\s]+)/u', $normalized, $m)) {
            return $this->geo->normalizeProvince(trim($m[1]));
        }

        return null;
    }

    protected function inferClassificationId(string $title, string $company, JobSource $source): ?int
    {
        $haystack = PersianText::normalizeKey($title.' '.$company.' '.$source->name) ?? '';

        if (str_contains($haystack, 'شهرداری') || str_contains((string) $source->slug, 'municipality')) {
            return $this->classificationIdByName('شهرداری‌ها');
        }

        foreach (config('aggregation.classification_rules', []) as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            foreach ($rule['keywords'] ?? [] as $keyword) {
                $key = PersianText::normalizeKey(is_string($keyword) ? $keyword : null);
                if ($key && str_contains($haystack, $key)) {
                    return $this->classificationIdByName((string) ($rule['classification'] ?? ''));
                }
            }
        }

        $sourceType = $source->source_type?->value ?? '';

        return match ($sourceType) {
            'bank' => $this->classificationIdByName('بانک‌ها'),
            'ministry', 'government', 'public_institution' => $this->classificationIdByName('وزارتخانه‌ها و سازمان‌های دولتی'),
            'university' => $this->classificationIdByName('وزارتخانه‌ها و سازمان‌های دولتی'),
            'company' => $this->classificationIdByName('دستگاه‌های اجرایی'),
            default => $this->classificationIdByName('سایر'),
        };
    }

    protected function classificationIdByName(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        /** @var array<string, int> $map */
        $map = Cache::remember('aggregation.job_classification_name_map', 3600, static function () {
            return JobClassification::query()
                ->where('is_active', true)
                ->pluck('id', 'name')
                ->all();
        });

        return $map[$name] ?? null;
    }

    protected function inferEmploymentType(string $text): ?string
    {
        $key = PersianText::normalizeKey($text) ?? '';

        $map = [
            'پیمانی' => 'contract',
            'قراردادی' => 'contract',
            'رسمی' => 'full_time',
            'امریه' => 'military',
        ];

        foreach ($map as $phrase => $type) {
            $p = PersianText::normalizeKey($phrase);
            if ($p && str_contains($key, $p)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function buildFormDescription(array $normalized, JobSource $source): string
    {
        $existing = is_string($normalized['description'] ?? null) ? $normalized['description'] : '';
        if (
            mb_strlen(strip_tags($existing)) > 140
            && (str_contains($existing, '<h3>') || str_contains($existing, 'سازمان برگزارکننده'))
        ) {
            return $existing;
        }

        $title = (string) ($normalized['title'] ?? '');
        $company = (string) ($normalized['company_name'] ?? $source->name);
        $blocks = [
            '<h3>عنوان آگهی</h3>',
            '<p>'.e($title).'</p>',
            '<h3>سازمان برگزارکننده</h3>',
            '<p>'.e($company).'</p>',
        ];

        $plainBody = trim(strip_tags($existing));
        if ($plainBody !== '' && $plainBody !== $title) {
            $blocks[] = '<h3>شرح آگهی</h3>';
            $blocks[] = '<p>'.e($plainBody).'</p>';
        }

        if (! empty($normalized['job_category'])) {
            $blocks[] = '<h3>دسته</h3>';
            $blocks[] = '<p>'.e((string) $normalized['job_category']).'</p>';
        }

        if (! empty($normalized['registration_starts_at'])) {
            $blocks[] = '<h3>شروع ثبت‌نام</h3>';
            $blocks[] = '<p>'.e($this->formatDateForDisplay((string) $normalized['registration_starts_at'])).'</p>';
        }

        if (! empty($normalized['registration_deadline'])) {
            $blocks[] = '<h3>مهلت ثبت‌نام</h3>';
            $blocks[] = '<p>'.e($this->formatDateForDisplay((string) $normalized['registration_deadline'])).'</p>';
        }

        if (! empty($normalized['exam_date'])) {
            $blocks[] = '<h3>تاریخ آزمون</h3>';
            $blocks[] = '<p>'.e($this->formatDateForDisplay((string) $normalized['exam_date'])).'</p>';
        }

        $location = null;
        if (! empty($normalized['provinces']) && is_array($normalized['provinces'])) {
            $location = implode('، ', array_filter($normalized['provinces']));
        } elseif (! empty($normalized['province'])) {
            $location = (string) $normalized['province'];
        }
        if ($location !== null && $location !== '') {
            $blocks[] = '<h3>محل خدمت</h3>';
            $blocks[] = '<p>'.e($location).'</p>';
        }

        if (! empty($normalized['requirements'])) {
            $blocks[] = '<h3>شرایط احراز</h3>';
            $blocks[] = '<p>'.e((string) $normalized['requirements']).'</p>';
        }

        if (! empty($normalized['registration_link'])) {
            $blocks[] = '<h3>لینک ثبت‌نام</h3>';
            $blocks[] = '<p><a href="'.e((string) $normalized['registration_link']).'" rel="noopener noreferrer" target="_blank">ثبت‌نام در سایت رسمی</a></p>';
        }

        $blocks[] = '<p><em>منبع رسمی: '.e($source->name).' — وضعیت: در انتظار بررسی و تأیید.</em></p>';

        return implode("\n", $blocks);
    }

    protected function formatDateForDisplay(string $datetime): string
    {
        try {
            $carbon = \Carbon\Carbon::parse($datetime);

            return Jalalian::fromCarbon($carbon)->format('Y/m/d');
        } catch (\Throwable) {
            return Str::before($datetime, ' ');
        }
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function suggestSeoTag(array $normalized): ?string
    {
        $title = PersianText::normalizeKey((string) ($normalized['title'] ?? ''));
        if ($title === null || $title === '') {
            return null;
        }

        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '_', $title) ?? $title;
        $slug = trim($slug, '_');

        return Str::limit($slug, 120, '');
    }

}
