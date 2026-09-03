<?php

declare(strict_types=1);

namespace App\Services\Exam;

use App\Imports\QuestionsImport;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\JobClassification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Creates a new exam from Excel and imports its questions in one step.
 * Separate from QuestionsImport (which requires an existing exam_id).
 */
final class ExamFullImportService
{
    /**
     * @return array{
     *   exam: array{id: int, title: string, slug: string|null, total_questions: int},
     *   created: int,
     *   skipped: int,
     *   duplicates: int,
     *   errors: list<string>
     * }
     */
    public function import(UploadedFile $file, User $user): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());

        [$examMeta, $questionRows, $parseErrors] = $this->parseWorkbook($spreadsheet);

        if ($parseErrors !== []) {
            throw new RuntimeException(implode(' ', $parseErrors));
        }

        if ($examMeta['title'] === '') {
            throw new RuntimeException('نام آزمون در فایل یافت نشد. شیت «آزمون» یا ستون نام_آزمون را پر کنید.');
        }

        if ($questionRows->isEmpty()) {
            throw new RuntimeException('هیچ سوالی در فایل یافت نشد.');
        }

        return DB::transaction(function () use ($examMeta, $questionRows, $user) {
            $exam = $this->createExam($examMeta, $user);

            $import = new QuestionsImport($exam->id);
            $import->collection($questionRows);

            $exam->refresh();

            return [
                'exam' => [
                    'id' => (int) $exam->id,
                    'title' => (string) $exam->title,
                    'slug' => $exam->slug,
                    'total_questions' => (int) $exam->total_questions,
                ],
                'created' => $import->created,
                'skipped' => $import->skipped,
                'duplicates' => $import->duplicates,
                'errors' => $import->errors,
            ];
        });
    }

    /**
     * @return Spreadsheet
     */
    public function buildSampleSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $examSheet = $spreadsheet->getActiveSheet();
        $examSheet->setTitle('آزمون');

        $examHeaders = [
            'نام_آزمون',
            'برچسب_سئو',
            'توضیحات',
            'مدت_دقیقه',
            'نمره_قبولی',
            'مجموع_نمرات',
            'رایگان',
            'قیمت',
            'نیاز_اشتراک',
            'وضعیت',
            'طبقه_بندی',
        ];
        foreach ($examHeaders as $i => $h) {
            $examSheet->setCellValue([$i + 1, 1], $h);
        }
        $examSheet->fromArray([[
            'آزمون نمونه ورود کامل',
            'آزمون_نمونه_ورود_کامل',
            'این یک آزمون نمونه برای ورود یکجای نام آزمون و سوالات است.',
            60,
            50,
            100,
            'بله',
            0,
            'any',
            'published',
            '',
        ]], null, 'A2');

        $qSheet = $spreadsheet->createSheet();
        $qSheet->setTitle('سوالات');
        $qHeaders = [
            'درس', 'متن_سوال', 'گزینه_الف', 'گزینه_ب', 'گزینه_ج', 'گزینه_د',
            'پاسخ_صحیح', 'توضیحات', 'سطح', 'سال', 'منبع',
        ];
        foreach ($qHeaders as $i => $h) {
            $qSheet->setCellValue([$i + 1, 1], $h);
        }
        $qSheet->fromArray([
            [
                'ریاضی', 'حاصل ۲×۳ کدام است؟', '۵', '۶', '۷', '۸',
                'ب', '۲×۳=۶', 'آسان', '1403', 'نمونه جاب‌آزمون',
            ],
            [
                'ادبیات', 'جمع مکسر «کتاب» کدام است؟', 'کتب', 'کتاب‌ها', 'کتابان', 'مکاتیب',
                'الف', 'جمع مکسر کتاب، کُتُب است.', 'متوسط', '1402', 'نمونه جاب‌آزمون',
            ],
            [
                'معارف', 'اولین ماه قمری کدام است؟', 'محرم', 'صفر', 'رمضان', 'ذی‌حجه',
                'الف', 'تقویم قمری با محرم آغاز می‌شود.', 'آسان', '1403', 'نمونه جاب‌آزمون',
            ],
        ], null, 'A2');

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @return array{0: array<string, mixed>, 1: Collection<int, Collection<string, mixed>>, 2: list<string>}
     */
    protected function parseWorkbook(Spreadsheet $spreadsheet): array
    {
        $errors = [];
        $examSheet = $this->findSheet($spreadsheet, ['آزمون', 'exam', 'اطلاعات_آزمون', 'اطلاعات آزمون']);
        $questionSheet = $this->findSheet($spreadsheet, ['سوالات', 'questions', 'سوال']);

        if ($examSheet && $questionSheet) {
            $examMeta = $this->readExamMetaFromSheet($examSheet);
            $questionRows = $this->sheetToKeyedRows($questionSheet);

            return [$examMeta, $questionRows, $errors];
        }

        // Single-sheet fallback: exam fields + question fields on the same rows.
        $sheet = $spreadsheet->getSheet(0);
        $rows = $this->sheetToKeyedRows($sheet);
        if ($rows->isEmpty()) {
            return [['title' => ''], collect(), ['فایل خالی است یا ردیف داده ندارد.']];
        }

        $first = $rows->first();
        $examMeta = $this->examMetaFromRow($first instanceof Collection ? $first->all() : (array) $first);

        // Drop pure exam-only columns noise; QuestionsImport ignores unknown keys.
        return [$examMeta, $rows, $errors];
    }

    /**
     * @param  list<string>  $names
     */
    protected function findSheet(Spreadsheet $spreadsheet, array $names): ?Worksheet
    {
        $wanted = array_map(fn (string $n) => $this->normalizeHeader($n), $names);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $title = $this->normalizeHeader($sheet->getTitle());
            if (in_array($title, $wanted, true)) {
                return $sheet;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function readExamMetaFromSheet(Worksheet $sheet): array
    {
        $rows = $this->sheetToKeyedRows($sheet);
        $first = $rows->first();

        if (! $first instanceof Collection) {
            return ['title' => ''];
        }

        return $this->examMetaFromRow($first->all());
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function examMetaFromRow(array $row): array
    {
        $get = function (array $aliases) use ($row): string {
            foreach ($aliases as $alias) {
                foreach ($row as $key => $value) {
                    if ($this->normalizeHeader((string) $key) === $this->normalizeHeader($alias)) {
                        return trim((string) $value);
                    }
                }
            }

            return '';
        };

        return [
            'title' => $get(['نام_آزمون', 'نام آزمون', 'آزمون', 'exam_title', 'title', 'exam_name']),
            'seo_tag' => $get(['برچسب_سئو', 'برچسب سئو', 'seo_tag', 'seo']),
            'description' => $get(['توضیحات', 'توضیح', 'description']),
            'duration_minutes' => $get(['مدت_دقیقه', 'مدت دقیقه', 'مدت', 'duration_minutes', 'duration']),
            'passing_score' => $get(['نمره_قبولی', 'نمره قبولی', 'passing_score']),
            'total_marks' => $get(['مجموع_نمرات', 'مجموع نمرات', 'total_marks']),
            'is_free' => $get(['رایگان', 'is_free', 'free']),
            'price' => $get(['قیمت', 'price']),
            'subscription_required' => $get(['نیاز_اشتراک', 'نیاز اشتراک', 'اشتراک', 'subscription_required']),
            'status' => $get(['وضعیت', 'status']),
            'classification' => $get(['طبقه_بندی', 'طبقه بندی', 'classification', 'job_classification']),
        ];
    }

    /**
     * @return Collection<int, Collection<string, mixed>>
     */
    protected function sheetToKeyedRows(Worksheet $sheet): Collection
    {
        $matrix = $sheet->toArray(null, true, true, false);
        if ($matrix === []) {
            return collect();
        }

        $headerRow = array_shift($matrix);
        if (! is_array($headerRow)) {
            return collect();
        }

        $headers = [];
        foreach ($headerRow as $i => $h) {
            $headers[$i] = trim((string) $h);
        }

        $out = collect();
        foreach ($matrix as $row) {
            if (! is_array($row)) {
                continue;
            }
            $assoc = [];
            $hasValue = false;
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $val = $row[$i] ?? null;
                if ($val !== null && trim((string) $val) !== '') {
                    $hasValue = true;
                }
                $assoc[$header] = $val;
            }
            if ($hasValue) {
                $out->push(collect($assoc));
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function createExam(array $meta, User $user): Exam
    {
        $title = trim((string) ($meta['title'] ?? ''));
        $seoTag = $this->makeUniqueSeoTag(
            trim((string) ($meta['seo_tag'] ?? '')) !== ''
                ? (string) $meta['seo_tag']
                : $title
        );

        $isFree = $this->parseBool($meta['is_free'] ?? 'بله', true);
        $price = $this->parseNumber($meta['price'] ?? null, 0);
        if ($isFree) {
            $price = 0;
        }

        $subscription = strtolower(trim((string) ($meta['subscription_required'] ?? '')));
        if (! in_array($subscription, ['free', 'paid', 'any'], true)) {
            $subscription = $isFree ? 'any' : 'paid';
        }

        $status = strtolower(trim((string) ($meta['status'] ?? 'published')));
        if (! in_array($status, ['draft', 'published', 'archived'], true)) {
            $status = 'published';
        }

        $categoryId = ExamCategory::query()->firstOrCreate(
            ['slug' => 'general'],
            ['name' => 'عمومی', 'icon' => 'book']
        )->id;

        $classificationId = $this->resolveClassification(trim((string) ($meta['classification'] ?? '')));

        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'exam';
        }
        $slug = $slug.'-'.Str::lower(Str::random(5));
        while (Exam::query()->where('slug', $slug)->exists()) {
            $slug = Str::slug($title).'-'.Str::lower(Str::random(6));
            if (Str::slug($title) === '') {
                $slug = 'exam-'.Str::lower(Str::random(8));
            }
        }

        return Exam::query()->create([
            'title' => Str::limit($title, 255, ''),
            'slug' => $slug,
            'seo_tag' => $seoTag,
            'category_id' => $categoryId,
            'job_classification_id' => $classificationId,
            'description' => trim((string) ($meta['description'] ?? '')) ?: null,
            'duration_minutes' => max(5, min(300, (int) $this->parseNumber($meta['duration_minutes'] ?? null, 60))),
            'passing_score' => max(0, (float) $this->parseNumber($meta['passing_score'] ?? null, 50)),
            'total_marks' => max(1, (float) $this->parseNumber($meta['total_marks'] ?? null, 100)),
            'total_questions' => 0,
            'has_negative_marking' => false,
            'negative_mark_ratio' => 0.3333,
            'is_free' => $isFree,
            'price' => $price,
            'subscription_required' => $subscription,
            'status' => $status,
            'is_random' => false,
            'created_by' => $user->id,
        ]);
    }

    protected function makeUniqueSeoTag(string $raw): string
    {
        $tag = preg_replace('/\s+/u', '_', trim($raw)) ?? '';
        $tag = preg_replace('/_+/u', '_', $tag) ?? $tag;
        $tag = trim($tag, '_');
        $tag = preg_replace('/[^\p{L}\p{N}_-]+/u', '', $tag) ?? $tag;
        if ($tag === '') {
            $tag = 'exam_'.Str::lower(Str::random(8));
        }
        $tag = Str::limit($tag, 180, '');

        $base = $tag;
        $i = 1;
        while (Exam::query()->where('seo_tag', $tag)->exists()) {
            $tag = Str::limit($base, 170, '').'_'.$i;
            $i++;
        }

        return $tag;
    }

    protected function resolveClassification(string $name): ?int
    {
        if ($name === '') {
            return JobClassification::query()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->value('id');
        }

        $found = JobClassification::query()
            ->whereNull('parent_id')
            ->where(function ($q) use ($name) {
                $q->where('name', $name)
                    ->orWhere('slug', Str::slug($name));
            })
            ->first();

        return $found?->id;
    }

    protected function parseBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $v = mb_strtolower(trim((string) $value));

        return in_array($v, ['1', 'true', 'yes', 'y', 'بله', 'آری', 'رایگان', 'free'], true);
    }

    protected function parseNumber(mixed $value, float|int $default): float|int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $v = str_replace([',', '،', ' '], '', trim((string) $value));
        if (! is_numeric($v)) {
            return $default;
        }

        return str_contains($v, '.') ? (float) $v : (int) $v;
    }

    protected function normalizeHeader(string $header): string
    {
        $header = trim(mb_strtolower($header));
        $header = str_replace(['‌', ' ', '-', '.', 'ـ'], ['_', '_', '_', '_', '_'], $header);
        $header = preg_replace('/_+/', '_', $header) ?? $header;

        return trim($header, '_');
    }
}
