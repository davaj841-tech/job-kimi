<?php

namespace App\Services\Question;

use App\Models\Question;
use App\Support\QuestionTextNormalizer;
use Illuminate\Support\Str;

class QuestionDuplicateService
{
    /**
     * Find questions that share the same normalized text across different exams.
     *
     * @return array{groups: list<array<string, mixed>>, total_groups: int, total_questions: int}
     */
    public function findCrossExamDuplicates(
        int $page = 1,
        int $perPage = 20,
        ?int $jobClassificationId = null
    ): array {
        $questions = Question::query()
            ->with('exam:id,title,slug,job_classification_id')
            ->when($jobClassificationId, function ($q) use ($jobClassificationId) {
                $q->whereHas('exam', fn ($e) => $e->where('job_classification_id', $jobClassificationId));
            })
            ->orderBy('id')
            ->get(['id', 'exam_id', 'question_text', 'subject', 'difficulty', 'correct_answer']);

        /** @var array<string, list<array<string, mixed>>> $buckets */
        $buckets = [];

        foreach ($questions as $question) {
            $normalized = QuestionTextNormalizer::normalize((string) $question->question_text);
            if ($normalized === '') {
                continue;
            }

            $hash = QuestionTextNormalizer::fingerprint((string) $question->question_text);
            $buckets[$hash][] = [
                'id' => $question->id,
                'exam_id' => $question->exam_id,
                'exam_title' => $question->exam?->title,
                'exam_slug' => $question->exam?->slug,
                'question_preview' => Str::limit(strip_tags((string) $question->question_text), 160),
                'subject' => $question->subject,
                'difficulty' => $question->difficulty,
                'correct_answer' => $question->correct_answer,
            ];
        }

        $groups = [];
        foreach ($buckets as $hash => $items) {
            if (count($items) < 2) {
                continue;
            }

            $examIds = array_unique(array_map(fn ($row) => (int) $row['exam_id'], $items));
            if (count($examIds) < 2) {
                continue;
            }

            $groups[] = [
                'fingerprint' => $hash,
                'count' => count($items),
                'exam_count' => count($examIds),
                'preview' => $items[0]['question_preview'] ?? '',
                'subject' => $items[0]['subject'] ?? null,
                'questions' => array_values($items),
            ];
        }

        usort($groups, fn ($a, $b) => ($b['count'] <=> $a['count']) ?: strcmp((string) $a['preview'], (string) $b['preview']));

        $totalGroups = count($groups);
        $totalQuestions = array_sum(array_map(fn ($g) => (int) $g['count'], $groups));
        $offset = max(0, ($page - 1) * $perPage);
        $paged = array_slice($groups, $offset, $perPage);

        return [
            'groups' => array_values($paged),
            'total_groups' => $totalGroups,
            'total_questions' => $totalQuestions,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($totalGroups / max(1, $perPage))),
                'total' => $totalGroups,
            ],
        ];
    }
}
