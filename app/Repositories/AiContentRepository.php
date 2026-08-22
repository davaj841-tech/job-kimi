<?php

namespace App\Repositories;

use App\Models\AiContent;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AiContentRepository
{
    /**
     * @return Collection<int, AiContent>
     */
    public function getPending(): Collection
    {
        return AiContent::query()->where('status', 'pending')->latest()->get();
    }

    /**
     * @return Collection<int, AiContent>
     */
    public function getByType(string $type): Collection
    {
        return AiContent::query()->where('type', $type)->latest()->get();
    }

    public function getTodayCount(?string $type = null): int
    {
        return AiContent::query()
            ->whereDate('created_at', today())
            ->when($type, fn ($q) => $q->where('type', $type))
            ->count();
    }

    public function findById(int $id): ?AiContent
    {
        return AiContent::query()->find($id);
    }

    public function approve(int $id, int $adminId): AiContent
    {
        $content = $this->findById($id);

        if (! $content) {
            throw new \RuntimeException('محتوای AI یافت نشد.');
        }

        if ($content->type === 'exam_question') {
            $this->approveExamQuestions($content, $adminId);
        } else {
            $content->update([
                'status' => 'approved',
                'reviewed_by' => $adminId,
            ]);
        }

        return $content->fresh();
    }

    public function reject(int $id, int $adminId): AiContent
    {
        $content = $this->findById($id);

        if (! $content) {
            throw new \RuntimeException('محتوای AI یافت نشد.');
        }

        $content->update([
            'status' => 'rejected',
            'reviewed_by' => $adminId,
        ]);

        return $content->fresh();
    }

    protected function approveExamQuestions(AiContent $content, int $adminId): void
    {
        DB::transaction(function () use ($content, $adminId) {
            $metadata = $content->metadata ?? [];
            $questions = $metadata['generated_questions'] ?? null;

            if (! is_array($questions)) {
                $decoded = json_decode((string) $content->generated_content, true);
                $questions = is_array($decoded) ? $decoded : [];
            }

            $examId = (int) ($metadata['exam_id'] ?? 0);
            $exam = Exam::query()->find($examId);

            if (! $exam) {
                throw new \RuntimeException('آزمون مرتبط یافت نشد.');
            }

            $created = 0;
            $validSubjects = ['math', 'literature', 'islamic', 'english', 'chemistry', 'physics', 'iq', 'general'];
            $validDifficulty = ['easy', 'medium', 'hard'];

            foreach ($questions as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $correct = strtolower((string) ($item['correct_answer'] ?? ''));
                if (! in_array($correct, ['a', 'b', 'c', 'd'], true)) {
                    continue;
                }

                if (blank($item['question_text'] ?? null)
                    || blank($item['option_a'] ?? null)
                    || blank($item['option_b'] ?? null)
                    || blank($item['option_c'] ?? null)
                    || blank($item['option_d'] ?? null)
                ) {
                    continue;
                }

                $subject = strtolower((string) ($item['subject'] ?? $metadata['subject'] ?? 'general'));
                if (! in_array($subject, $validSubjects, true)) {
                    $subject = 'general';
                }

                $difficulty = strtolower((string) ($item['difficulty'] ?? $metadata['difficulty'] ?? 'medium'));
                if (! in_array($difficulty, $validDifficulty, true)) {
                    $difficulty = 'medium';
                }

                Question::query()->create([
                    'exam_id' => $exam->id,
                    'question_text' => (string) $item['question_text'],
                    'question_type' => 'multiple_choice',
                    'option_a' => (string) $item['option_a'],
                    'option_b' => (string) $item['option_b'],
                    'option_c' => (string) $item['option_c'],
                    'option_d' => (string) $item['option_d'],
                    'correct_answer' => $correct,
                    'explanation' => $item['explanation'] ?? null,
                    'difficulty' => $difficulty,
                    'subject' => $subject,
                ]);

                $created++;
            }

            if ($created > 0) {
                $exam->increment('total_questions', $created);
            }

            $content->update([
                'status' => 'approved',
                'reviewed_by' => $adminId,
                'metadata' => array_merge($metadata, [
                    'approved_questions_count' => $created,
                ]),
            ]);
        });
    }
}
