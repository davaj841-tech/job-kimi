<?php

namespace App\Imports;

use App\Models\JobClassification;
use App\Models\JobPost;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class JobPostsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $skipped = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(
        protected ?int $createdBy = null
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $title = trim((string) ($row['title'] ?? $row['عنوان'] ?? ''));
            $seoTag = trim((string) ($row['seo_tag'] ?? $row['برچسب_سئو'] ?? $row['برچسب سئو'] ?? ''));
            $classificationName = trim((string) ($row['classification'] ?? $row['طبقه‌بندی'] ?? $row['طبقه_بندی'] ?? $row['company_name'] ?? ''));
            $description = trim((string) ($row['description'] ?? $row['شرح'] ?? ''));
            $provincesRaw = trim((string) ($row['provinces'] ?? $row['province'] ?? $row['استان‌ها'] ?? $row['استان'] ?? ''));
            $city = trim((string) ($row['city'] ?? $row['شهر'] ?? ''));
            $deadlineRaw = $row['registration_deadline'] ?? $row['مهلت_ثبت_نام'] ?? $row['مهلت ثبت‌نام'] ?? null;
            $examRaw = $row['exam_date'] ?? $row['تاریخ_آزمون'] ?? $row['تاریخ آزمون'] ?? null;
            $link = trim((string) ($row['registration_link'] ?? $row['لینک_ثبت_نام'] ?? $row['لینک ثبت‌نام'] ?? ''));
            $featured = $row['is_featured'] ?? $row['ویژه'] ?? false;

            if ($title === '' || $classificationName === '' || $description === '') {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: عنوان، طبقه‌بندی و شرح الزامی است.";

                continue;
            }

            $deadline = $this->parseDate($deadlineRaw);
            if (! $deadline) {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: تاریخ مهلت ثبت‌نام نامعتبر است.";

                continue;
            }

            $classification = JobClassification::query()->firstOrCreate(
                ['name' => $classificationName],
                ['icon' => 'briefcase', 'color' => '#1e3a5f', 'is_active' => true, 'sort_order' => 100]
            );

            $provinces = array_values(array_filter(array_map('trim', preg_split('/[,،]/u', $provincesRaw) ?: [])));

            if ($link !== '' && ! filter_var($link, FILTER_VALIDATE_URL)) {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: لینک ثبت‌نام نامعتبر است.";

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
                'exam_date' => $examRaw !== null && $examRaw !== '' ? $this->parseDate($examRaw) : null,
                'registration_link' => $link !== '' ? $link : null,
                'is_featured' => filter_var($featured, FILTER_VALIDATE_BOOLEAN),
                'status' => 'pending',
                'created_by' => $this->createdBy,
            ]);

            $this->created++;
        }
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject((float) $value));
            }

            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
