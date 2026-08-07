<?php

namespace App\Imports;

use App\Models\Exam;
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

    public function collection(Collection $rows): void
    {
        $validAnswers = ['a', 'b', 'c', 'd'];
        $validDifficulty = ['easy', 'medium', 'hard'];
        $validSubjects = ['math', 'literature', 'islamic', 'english', 'chemistry', 'physics', 'iq', 'general'];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $examSlug = trim((string) ($row['exam_slug'] ?? ''));
            $questionText = trim((string) ($row['question_text'] ?? ''));
            $optionA = trim((string) ($row['option_a'] ?? ''));
            $optionB = trim((string) ($row['option_b'] ?? ''));
            $optionC = trim((string) ($row['option_c'] ?? ''));
            $optionD = trim((string) ($row['option_d'] ?? ''));
            $correct = strtolower(trim((string) ($row['correct_answer'] ?? '')));
            $difficulty = strtolower(trim((string) ($row['difficulty'] ?? '')));
            $subject = strtolower(trim((string) ($row['subject'] ?? '')));
            $explanation = trim((string) ($row['explanation'] ?? ''));

            if ($examSlug === '' || $questionText === '' || $optionA === '' || $optionB === '' || $optionC === '' || $optionD === '' || $correct === '') {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: فیلدهای الزامی ناقص است.";
                continue;
            }

            if (! in_array($correct, $validAnswers, true)) {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: پاسخ صحیح نامعتبر است.";
                continue;
            }

            $exam = Exam::query()->where('slug', $examSlug)->first();
            if (! $exam) {
                $this->skipped++;
                $this->errors[] = "ردیف {$rowNumber}: آزمون با اسلاگ {$examSlug} یافت نشد.";
                continue;
            }

            if (! in_array($difficulty, $validDifficulty, true)) {
                $difficulty = 'medium';
            }

            if (! in_array($subject, $validSubjects, true)) {
                $subject = 'general';
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
