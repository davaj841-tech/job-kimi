<?php

namespace App\Imports;

use App\Models\JobClassification;
use App\Models\JobPost;
use App\Services\Aggregation\Support\DateNormalizer;
use App\Services\Aggregation\Support\PersianText;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class JobPostsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $skipped = 0;

    public int $duplicates = 0;

    /** @var array<int, string> */
    public array $errors = [];

    /** @var array<string, true> */
    protected array $seenInFile = [];

    protected DateNormalizer $dates;

    public function __construct(
        protected ?int $createdBy = null
    ) {
        $this->dates = new DateNormalizer;
    }

    /**
     * @param  Collection<int, mixed>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->normalizeRow($row);

            $title = trim((string) ($data['title'] ?? ''));
            $seoTag = trim((string) ($data['seo_tag'] ?? ''));
            $classificationName = trim((string) ($data['classification'] ?? ''));
            $description = trim((string) ($data['description'] ?? ''));
            $provincesRaw = trim((string) ($data['provinces'] ?? ''));
            $city = trim((string) ($data['city'] ?? ''));
            $deadlineRaw = $data['registration_deadline'] ?? null;
            $examRaw = $data['exam_date'] ?? null;
            $link = trim((string) ($data['registration_link'] ?? ''));
            $featured = $data['is_featured'] ?? false;

            if ($title === '' || $classificationName === '' || $description === '') {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: عنوان، طبقه‌بندی و شرح الزامی است.";

                continue;
            }

            $deadline = $this->parseDate($deadlineRaw);
            if (! $deadline) {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: تاریخ مهلت ثبت‌نام نامعتبر است. از تاریخ شمسی مثل ۱۴۰۵/۰۶/۱۵ یا میلادی 2026-09-06 استفاده کنید.";

                continue;
            }

            $examDate = null;
            if ($examRaw !== null && trim((string) $examRaw) !== '') {
                $examDate = $this->parseDate($examRaw);
                if (! $examDate) {
                    $this->skipped++;
                    $this->errors[] = "ردیف {$rowNumber}: تاریخ آزمون نامعتبر است.";

                    continue;
                }
            }

            if ($link !== '' && ! $this->isValidUrl($link)) {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: لینک ثبت‌نام نامعتبر است.";

                continue;
            }

            $classification = $this->resolveClassification($classificationName);
            $provinces = array_values(array_filter(array_map('trim', preg_split('/[,،]/u', $provincesRaw) ?: [])));

            if ($this->isDuplicate($title, $classification->name, $deadline, $link, $rowNumber)) {
                continue;
            }

            $seo = $seoTag !== '' ? preg_replace('/\s+/u', '_', $seoTag) : null;

            JobPost::query()->create([
                'title' => $title,
                'seo_tag' => $seo,
                'company_name' => $classification->name,
                'job_classification_id' => $classification->id,
                'description' => $description,
                'provinces' => $provinces,
                'province' => $provinces[0] ?? null,
                'city' => $city !== '' ? $city : null,
                'job_category' => null,
                'registration_deadline' => $deadline,
                'exam_date' => $examDate,
                'published_at' => now(),
                'registration_link' => $link !== '' ? $link : null,
                'is_featured' => $this->parseBool($featured),
                'status' => 'approved',
                'created_by' => $this->createdBy,
                'approved_by' => $this->createdBy,
            ]);

            $this->remember($title, $classification->name, $deadline, $link);
            $this->created++;
        }
    }

    /**
     * Accept Jalali (۱۴۰۵/۰۶/۱۵), Gregorian (2026-09-06), Excel serial dates,
     * or Persian ranges like «۱۴۰۵/۰۶/۰۷ تا ۱۴۰۵/۰۸/۰۶» / «از ۱۴۰۵/۰۶/۰۱».
     */
    protected function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                // Excel serial (days since 1899-12-30). Guard against Jalali-looking numbers like 14050615.
                $n = (float) $value;
                if ($n > 20000 && $n < 80000) {
                    return Carbon::instance(Date::excelToDateTimeObject($n))->startOfDay();
                }
            }

            $raw = trim((string) $value);
            $openEnded = preg_match('/در\s*حال\s*ثبت[\s‌]*نام|باز|نامشخص|اعلام\s*نشده/u', $raw) === 1;
            if ($openEnded) {
                // Keep open registrations visible for a while.
                return now()->addMonths(3)->startOfDay();
            }

            // Prefer the end date of a range: «از … تا …» / «… تا …»
            if (preg_match_all('/((?:۱۳|۱۴|13|14)\d{2}[\/\-.\s]\d{1,2}[\/\-.\s]\d{1,2})/u', $raw, $matches) && $matches[1] !== []) {
                $candidate = end($matches[1]);
                $normalized = $this->dates->normalize($candidate);
                if ($normalized !== null) {
                    return Carbon::parse($normalized)->startOfDay();
                }
            }

            $normalized = $this->dates->normalize($raw);
            if ($normalized === null) {
                return null;
            }

            return Carbon::parse($normalized)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveClassification(string $name): JobClassification
    {
        $normalized = PersianText::normalize($name) ?? $name;
        $candidates = array_values(array_unique(array_filter([$name, $normalized])));

        $existing = JobClassification::query()
            ->whereIn('name', $candidates)
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return JobClassification::query()->firstOrCreate(
            ['name' => $name],
            [
                'icon' => 'briefcase',
                'color' => '#1e3a5f',
                'is_active' => true,
                'sort_order' => 100,
            ]
        );
    }

    protected function isDuplicate(
        string $title,
        string $company,
        Carbon $deadline,
        string $link,
        int $rowNumber
    ): bool {
        $fingerprint = $this->fingerprint($title, $company, $deadline, $link);

        if (isset($this->seenInFile[$fingerprint])) {
            $this->skipped++;
            $this->duplicates++;
            $this->errors[] = "ردیف {$rowNumber}: این آگهی در همین فایل تکراری است.";

            return true;
        }

        if ($link !== '') {
            $byLink = JobPost::query()
                ->where('registration_link', $link)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();
            if ($byLink) {
                $this->skipped++;
                $this->duplicates++;
                $this->errors[] = "ردیف {$rowNumber}: آگهی با همین لینک ثبت‌نام قبلاً وارد شده است.";

                return true;
            }
        }

        $titleKey = PersianText::normalizeKey($title);
        $orgKey = PersianText::normalizeKey($company);
        $deadlineDate = $deadline->toDateString();

        $candidates = JobPost::query()
            ->whereDate('registration_deadline', $deadlineDate)
            ->whereIn('status', ['pending', 'approved'])
            ->whereNotNull('title')
            ->limit(80)
            ->get(['id', 'title', 'company_name']);

        foreach ($candidates as $candidate) {
            if (
                PersianText::normalizeKey((string) $candidate->title) === $titleKey
                && PersianText::normalizeKey((string) $candidate->company_name) === $orgKey
            ) {
                $this->skipped++;
                $this->duplicates++;
                $this->errors[] = "ردیف {$rowNumber}: این آگهی قبلاً با همین عنوان، سازمان و مهلت ثبت شده است.";

                return true;
            }
        }

        return false;
    }

    protected function remember(string $title, string $company, Carbon $deadline, string $link): void
    {
        $this->seenInFile[$this->fingerprint($title, $company, $deadline, $link)] = true;
    }

    protected function fingerprint(string $title, string $company, Carbon $deadline, string $link): string
    {
        if ($link !== '') {
            return 'link:'.mb_strtolower($link);
        }

        return 'tod:'.(PersianText::normalizeKey($title) ?? '').'|'
            .(PersianText::normalizeKey($company) ?? '').'|'
            .$deadline->toDateString();
    }

    protected function parseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $v = mb_strtolower(trim((string) $value));

        return in_array($v, ['1', 'true', 'yes', 'y', 'بله', 'آری', 'ویژه'], true);
    }

    /**
     * Accept ASCII and IDN/Persian path URLs that filter_var rejects.
     */
    protected function isValidUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return true;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if (! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        // Rebuild with percent-encoded path/query for non-ASCII characters.
        $path = $parts['path'] ?? '';
        if ($path !== '') {
            $segments = explode('/', $path);
            $encoded = array_map(static function (string $segment): string {
                return rawurlencode(rawurldecode($segment));
            }, $segments);
            $path = implode('/', $encoded);
        }

        $rebuilt = $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .$path
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');

        return (bool) filter_var($rebuilt, FILTER_VALIDATE_URL);
    }

    /**
     * @param  Collection<string, mixed>|array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeRow(Collection|array $row): array
    {
        $map = [
            'title' => ['title', 'عنوان'],
            'seo_tag' => ['seo_tag', 'برچسب_سئو', 'برچسب سئو'],
            'classification' => ['classification', 'طبقه‌بندی', 'طبقه_بندی', 'طبقه بندی', 'company_name', 'سازمان'],
            'description' => ['description', 'شرح', 'توضیحات'],
            'provinces' => ['provinces', 'province', 'استان‌ها', 'استان ها', 'استان'],
            'city' => ['city', 'شهر'],
            'registration_deadline' => [
                'registration_deadline', 'مهلت_ثبت_نام', 'مهلت ثبت‌نام', 'مهلت ثبت نام', 'مهلت',
            ],
            'exam_date' => ['exam_date', 'تاریخ_آزمون', 'تاریخ آزمون'],
            'registration_link' => [
                'registration_link', 'لینک_ثبت_نام', 'لینک ثبت‌نام', 'لینک ثبت نام', 'لینک',
            ],
            'is_featured' => ['is_featured', 'ویژه'],
        ];

        $flat = [];
        foreach ($row as $key => $value) {
            $k = $this->normalizeHeader((string) $key);
            if ($k === '' || is_int($key) || ctype_digit((string) $key)) {
                continue;
            }
            $flat[$k] = $value;
        }

        $out = [];
        foreach ($map as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $ak = $this->normalizeHeader($alias);
                if ($ak !== '' && array_key_exists($ak, $flat) && $flat[$ak] !== null && $flat[$ak] !== '') {
                    $out[$canonical] = $flat[$ak];
                    break;
                }
            }
        }

        return $out;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = trim(mb_strtolower($header));
        $header = str_replace(['‌', ' ', '-', '.', 'ـ'], ['_', '_', '_', '_', '_'], $header);
        $header = preg_replace('/_+/', '_', $header) ?? $header;

        return trim($header, '_');
    }
}
