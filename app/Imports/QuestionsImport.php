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

    public int $duplicates = 0;

    /** @var array<int, array<string, true>> */
    protected array $existingByExam = [];

    /** @var array<string, true> */
    protected array $seenInImport = [];

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(protected ?int $forcedExamId = null) {}

    /**
     * @param  Collection<int, mixed>  $rows
     */
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

            if ($this->isDuplicateQuestion($exam->id, $questionText, $rowNumber)) {
                continue;
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
            $this->rememberQuestion($exam->id, $questionText);
        }
    }

    protected function isDuplicateQuestion(int $examId, string $questionText, int $rowNumber): bool
    {
        $fingerprint = $this->questionFingerprint($examId, $questionText);

        if (isset($this->seenInImport[$fingerprint])) {
            $this->skipped++;
            $this->duplicates++;
            $this->errors[] = "ردیف {$rowNumber}: این سوال تکراری است (در همین فایل قبلاً آمده).";

            return true;
        }

        if ($this->questionExistsInExam($examId, $questionText)) {
            $this->skipped++;
            $this->duplicates++;
            $this->errors[] = "ردیف {$rowNumber}: این سوال قبلاً در این آزمون ثبت شده است.";

            return true;
        }

        return false;
    }

    protected function rememberQuestion(int $examId, string $questionText): void
    {
        $normalized = $this->normalizeQuestionText($questionText);
        $this->seenInImport[$this->questionFingerprint($examId, $questionText)] = true;

        if (! isset($this->existingByExam[$examId])) {
            $this->existingByExam[$examId] = [];
        }

        $this->existingByExam[$examId][$normalized] = true;
    }

    protected function questionExistsInExam(int $examId, string $questionText): bool
    {
        $this->loadExistingQuestionTexts($examId);

        return isset($this->existingByExam[$examId][$this->normalizeQuestionText($questionText)]);
    }

    protected function loadExistingQuestionTexts(int $examId): void
    {
        if (isset($this->existingByExam[$examId])) {
            return;
        }

        $this->existingByExam[$examId] = Question::query()
            ->where('exam_id', $examId)
            ->pluck('question_text')
            ->mapWithKeys(fn ($text) => [$this->normalizeQuestionText((string) $text) => true])
            ->all();
    }

    protected function questionFingerprint(int $examId, string $questionText): string
    {
        return $examId.'|'.hash('sha256', $this->normalizeQuestionText($questionText));
    }

    protected function normalizeQuestionText(string $text): string
    {
        $text = strip_tags($text);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return mb_strtolower($text);
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
            if ($k === '' || is_int($key) || ctype_digit((string) $key)) {
                // Fallback numeric columns rarely happen with heading row
                continue;
            }
            $flat[$k] = $value;
            // Also index by ASCII slug form (when default Excel slug formatter is active)
            $slug = Str::slug((string) $key, '_');
            if ($slug !== '' && $slug !== $k) {
                $flat[$slug] = $value;
            }
        }

        $out = [];
        foreach ($map as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                $candidates = array_unique(array_filter([
                    $this->normalizeHeader($alias),
                    Str::slug($alias, '_'),
                ]));
                foreach ($candidates as $ak) {
                    if ($ak !== '' && array_key_exists($ak, $flat) && $flat[$ak] !== null && $flat[$ak] !== '') {
                        $out[$canonical] = $flat[$ak];
                        break 2;
                    }
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
        Collection &$bySlug,
        Collection &$byName
    ): string {
        $name = trim($raw);
        $key = mb_strtolower($name);
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

        $slug = Str::slug($name, '-');
        if ($slug === '') {
            $slug = 'unmatched-'.substr(hash('sha256', $key), 0, 12);
        }
        $slug = Str::limit($slug, 64, '');

        if ($bySlug->has(strtolower($slug))) {
            return (string) $bySlug->get(strtolower($slug))->slug;
        }

        if (isset($alias[$slug])) {
            return $alias[$slug];
        }

        // نام درس در مدیریت نیست → به‌عنوان «نامرتبط» بساز تا ادمین اصلاح/ادغام کند
        $baseSlug = $slug;
        $i = 1;
        while (
            $bySlug->has(strtolower($slug))
            || ExamSubject::query()->where('slug', $slug)->exists()
        ) {
            $slug = Str::limit($baseSlug, 50, '').'-'.$i;
            $i++;
        }

        // اگر همین نام قبلاً با حروف متفاوت ثبت شده
        $existingName = ExamSubject::query()
            ->whereRaw('LOWER(name) = ?', [$key])
            ->first();
        if ($existingName) {
            $bySlug->put(strtolower((string) $existingName->slug), $existingName);
            $byName->put($key, $existingName);

            return (string) $existingName->slug;
        }

        $uniqueName = $name;
        if (ExamSubject::query()->where('name', $uniqueName)->exists()) {
            $uniqueName = Str::limit($name.' (وارداتی)', 100, '');
            $n = 2;
            while (ExamSubject::query()->where('name', $uniqueName)->exists()) {
                $uniqueName = Str::limit($name." (وارداتی {$n})", 100, '');
                $n++;
            }
        }

        $created = ExamSubject::query()->create([
            'name' => $uniqueName,
            'slug' => $slug,
            'icon' => '❓',
            'sort_order' => 9000 + (int) ExamSubject::query()->where('is_unmatched', true)->count(),
            'is_active' => true,
            'is_unmatched' => true,
        ]);

        $bySlug->put(strtolower((string) $created->slug), $created);
        $byName->put(mb_strtolower((string) $created->name), $created);
        $byName->put($key, $created);

        return (string) $created->slug;
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
