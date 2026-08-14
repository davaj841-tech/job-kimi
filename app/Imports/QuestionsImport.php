<?php

namespace App\Imports;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class QuestionsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $skipped = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(protected ?int $forcedExamId = null) {}

    public function collection(Collection $rows): void
    {
        $answerMap = [
            'الف' => 'a', 'ب' => 'b', 'ج' => 'c', 'د' => 'd',
            'ا' => 'a', '۱' => 'a', '1' => 'a',
            '۲' => 'b', '2' => 'b',
            '۳' => 'c', '3' => 'c',
            '۴' => 'd', '4' => 'd',
            'a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd',
            'A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd',
        ];
        $diffMap = [
            'آسان' => 'easy', 'ساده' => 'easy', 'easy' => 'easy',
            'متوسط' => 'medium', 'میانه' => 'medium', 'medium' => 'medium',
            'سخت' => 'hard', 'دشوار' => 'hard', 'hard' => 'hard',
        ];

        $subjectAlias = [
            'math' => 'math', 'ریاضی' => 'math', 'رياضيات' => 'math', 'ریاضیات' => 'math',
            'literature' => 'literature', 'ادبیات' => 'literature', 'فارسی' => 'literature', 'زبان فارسی' => 'literature',
            'islamic' => 'islamic', 'معارف' => 'islamic', 'دینی' => 'islamic', 'اسلامی' => 'islamic',
            'english' => 'english', 'انگلیسی' => 'english', 'زبان انگلیسی' => 'english', 'زبان' => 'english',
            'chemistry' => 'chemistry', 'شیمی' => 'chemistry',
            'physics' => 'physics', 'فیزیک' => 'physics',
            'iq' => 'iq', 'هوش' => 'iq', 'استعداد' => 'iq', 'استعداد تحصیلی' => 'iq',
            'general' => 'general', 'عمومی' => 'general', 'اطلاعات عمومی' => 'general', 'دانش عمومی' => 'general',
            'computer' => 'computer', 'کامپیوتر' => 'computer', 'رایانه' => 'computer', 'فناوری اطلاعات' => 'computer',
            'law' => 'law', 'حقوق' => 'law',
            'accounting' => 'accounting', 'حسابداری' => 'accounting',
            'management' => 'management', 'مدیریت' => 'management',
        ];

        $subjectsBySlug = ExamSubject::query()
            ->get(['id', 'name', 'slug'])
            ->keyBy(fn (ExamSubject $s) => strtolower((string) $s->slug));

        $subjectsByName = ExamSubject::query()
            ->get(['id', 'name', 'slug'])
            ->keyBy(fn (ExamSubject $s) => mb_strtolower(trim((string) $s->name)));

        $forcedExam = $this->forcedExamId
            ? Exam::query()->find($this->forcedExamId)
            : null;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->normalizeRow($row);

            $examSlug = trim((string) ($data['exam_slug'] ?? ''));
            $examTitle = trim((string) ($data['exam_title'] ?? ''));
            $questionText = trim((string) ($data['question_text'] ?? ''));
            $optionA = trim((string) ($data['option_a'] ?? ''));
            $optionB = trim((string) ($data['option_b'] ?? ''));
            $optionC = trim((string) ($data['option_c'] ?? ''));
            $optionD = trim((string) ($data['option_d'] ?? ''));
            $correctRaw = trim((string) ($data['correct_answer'] ?? ''));
            $correct = $answerMap[$correctRaw]
                ?? $answerMap[mb_strtolower($correctRaw)]
                ?? strtolower($correctRaw);

            $difficultyRaw = trim((string) ($data['difficulty'] ?? ''));
            $difficulty = $difficultyRaw === ''
                ? 'medium'
                : ($diffMap[$difficultyRaw] ?? $diffMap[mb_strtolower($difficultyRaw)] ?? 'medium');

            $subjectRaw = trim((string) ($data['subject'] ?? 'general'));
            $subject = $this->resolveSubject($subjectRaw, $subjectAlias, $subjectsBySlug, $subjectsByName);

            $explanation = trim((string) ($data['explanation'] ?? ''));
            $source = trim((string) ($data['source'] ?? ''));
            $examYear = trim((string) ($data['exam_year'] ?? ''));

            if ($questionText === '' || $optionA === '' || $optionB === '' || $optionC === '' || $optionD === '' || $correct === '') {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: فیلدهای الزامی ناقص است (متن سوال، گزینه‌ها، پاسخ).";

                continue;
            }

            if (! in_array($correct, ['a', 'b', 'c', 'd'], true)) {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: پاسخ صحیح نامعتبر است (الف/ب/ج/د یا a/b/c/d).";

                continue;
            }

            $exam = $forcedExam;
            if (! $exam) {
                $exam = $this->resolveExam($examSlug, $examTitle);
            }

            if (! $exam) {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: آزمون یافت نشد (نام یا شناسه آزمون را بررسی کنید).";

                continue;
            }

            if (! in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
                $difficulty = 'medium';
            }

            if ($source !== '' || $examYear !== '') {
                $meta = collect([
                    $examYear !== '' ? "سال {$examYear}" : null,
                    $source !== '' ? "منبع: {$source}" : null,
                ])->filter()->implode(' — ');
                $explanation = $explanation !== ''
                    ? "{$explanation}\n\n{$meta}"
                    : $meta;
            }

            Question::query()->create([
                'exam_id' => $exam->id,
                'question_text' => $questionText,
                'question_type' => 'multiple_choice',
                'option_a' => $optionA,
                'option_b' => $optionB,
                'option_c' => $optionC,
                'option_d' => $optionD,
                'correct_answer' => $correct,
                'explanation' => $explanation !== '' ? $explanation : null,
                'difficulty' => $difficulty,
                'subject' => $subject,
                'source' => $source !== '' ? Str::limit($source, 180, '') : null,
                'exam_year' => $examYear !== '' ? Str::limit($examYear, 20, '') : null,
            ]);

            $exam->increment('total_questions');
            $this->created++;
        }
    }

    /**
     * @param  Collection<string, mixed>|array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeRow(Collection|array $row): array
    {
        $map = [
            'exam_slug' => ['exam_slug', 'slug', 'شناسه_آزمون', 'شناسه آزمون'],
            'exam_title' => ['exam_title', 'exam_name', 'نام_آزمون', 'نام آزمون', 'آزمون'],
            'question_text' => ['question_text', 'question', 'متن_سوال', 'متن سوال', 'سوال'],
            'option_a' => ['option_a', 'گزینه_الف', 'گزینه الف', 'الف'],
            'option_b' => ['option_b', 'گزینه_ب', 'گزینه ب', 'ب'],
            'option_c' => ['option_c', 'گزینه_ج', 'گزینه ج', 'ج'],
            'option_d' => ['option_d', 'گزینه_د', 'گزینه د', 'د'],
            'correct_answer' => ['correct_answer', 'answer', 'پاسخ_صحیح', 'پاسخ صحیح', 'جواب', 'پاسخ'],
            'explanation' => ['explanation', 'توضیحات', 'توضیح', 'تحلیل'],
            'difficulty' => ['difficulty', 'سطح', 'سختی'],
            'subject' => ['subject', 'درس', 'ماده'],
            'source' => ['source', 'منبع', 'مرجع'],
            'exam_year' => ['exam_year', 'year', 'سال', 'سال_آزمون', 'سال آزمون'],
        ];

        $flat = [];
        foreach ($row as $key => $value) {
            $k = $this->normalizeHeader((string) $key);
            $flat[$k] = $value;
        }

        $out = [];
        foreach ($map as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $ak = $this->normalizeHeader($alias);
                if (array_key_exists($ak, $flat) && $flat[$ak] !== null && $flat[$ak] !== '') {
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

    /**
     * @param  Collection<string, ExamSubject>  $bySlug
     * @param  Collection<string, ExamSubject>  $byName
     * @param  array<string, string>  $alias
     */
    protected function resolveSubject(
        string $raw,
        array $alias,
        Collection $bySlug,
        Collection $byName
    ): string {
        $key = mb_strtolower(trim($raw));
        if ($key === '') {
            return 'general';
        }

        if (isset($alias[$key])) {
            return $alias[$key];
        }
        if (isset($alias[$raw])) {
            return $alias[$raw];
        }

        if ($bySlug->has($key)) {
            return (string) $bySlug->get($key)->slug;
        }
        if ($byName->has($key)) {
            return (string) $byName->get($key)->slug;
        }

        // Prefer a stable slug; fall back to general for empty labels
        $slug = Str::slug($raw, '-');
        if ($slug === '') {
            $slug = 'general';
        }

        if ($bySlug->has($slug)) {
            return (string) $bySlug->get($slug)->slug;
        }

        return Str::limit(isset($alias[$slug]) ? $alias[$slug] : $slug, 64, '');
    }

    protected function resolveExam(string $slug, string $title): ?Exam
    {
        if ($slug !== '') {
            $exam = Exam::query()->where('slug', $slug)->first();
            if ($exam) {
                return $exam;
            }
        }

        if ($title !== '') {
            $exam = Exam::query()->where('title', $title)->first();
            if ($exam) {
                return $exam;
            }

            return Exam::query()
                ->where('title', 'like', '%'.$title.'%')
                ->orderBy('id')
                ->first();
        }

        return null;
    }
}
