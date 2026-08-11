<?php

namespace App\Imports;

use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Question;
use Illuminate\Support\Collection;
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
            'a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd',
        ];
        $diffMap = [
            'آسان' => 'easy', 'متوسط' => 'medium', 'سخت' => 'hard',
            'easy' => 'easy', 'medium' => 'medium', 'hard' => 'hard',
        ];

        $validSubjects = ExamSubject::query()
            ->pluck('slug')
            ->map(fn ($s) => strtolower((string) $s))
            ->filter()
            ->values()
            ->all();

        if ($validSubjects === []) {
            $validSubjects = ['math', 'literature', 'islamic', 'english', 'chemistry', 'physics', 'iq', 'general'];
        }

        $forcedExam = $this->forcedExamId
            ? Exam::query()->find($this->forcedExamId)
            : null;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $examSlug = trim((string) ($row['exam_slug'] ?? ''));
            $questionText = trim((string) ($row['question_text'] ?? ''));
            $optionA = trim((string) ($row['option_a'] ?? $row['گزینه_الف'] ?? ''));
            $optionB = trim((string) ($row['option_b'] ?? $row['گزینه_ب'] ?? ''));
            $optionC = trim((string) ($row['option_c'] ?? $row['گزینه_ج'] ?? ''));
            $optionD = trim((string) ($row['option_d'] ?? $row['گزینه_د'] ?? ''));
            $correctRaw = trim((string) ($row['correct_answer'] ?? $row['پاسخ_صحیح'] ?? ''));
            $correct = $answerMap[mb_strtolower($correctRaw)] ?? $answerMap[$correctRaw] ?? strtolower($correctRaw);

            // Empty level → medium (required behavior for Excel/CSV import)
            $difficultyRaw = trim((string) ($row['difficulty'] ?? $row['سطح'] ?? ''));
            if ($difficultyRaw === '') {
                $difficulty = 'medium';
            } else {
                $difficulty = $diffMap[$difficultyRaw] ?? $diffMap[mb_strtolower($difficultyRaw)] ?? 'medium';
            }

            $subject = strtolower(trim((string) ($row['subject'] ?? $row['درس'] ?? 'general')));
            $explanation = trim((string) ($row['explanation'] ?? $row['توضیحات'] ?? ''));

            if ($questionText === '' || $optionA === '' || $optionB === '' || $optionC === '' || $optionD === '' || $correct === '') {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: فیلدهای الزامی ناقص است.";

                continue;
            }

            if (! in_array($correct, ['a', 'b', 'c', 'd'], true)) {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: پاسخ صحیح نامعتبر است (الف/ب/ج/د).";

                continue;
            }

            $exam = $forcedExam;
            if (! $exam) {
                if ($examSlug === '') {
                    $this->skipped++;
                    $this->errors[] = "ردیف {$rowNumber}: آزمون انتخاب نشده است.";

                    continue;
                }
                $exam = Exam::query()->where('slug', $examSlug)->first();
            }

            if (! $exam) {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: آزمون یافت نشد.";

                continue;
            }

            if (! in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
                $difficulty = 'medium';
            }

            if (! in_array($subject, $validSubjects, true)) {
                $subject = in_array('general', $validSubjects, true) ? 'general' : ($validSubjects[0] ?? 'general');
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
            ]);

            $exam->increment('total_questions');
            $this->created++;
        }
    }
}
