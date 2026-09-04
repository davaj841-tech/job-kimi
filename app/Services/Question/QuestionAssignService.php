<?php

namespace App\Services\Question;

use App\Models\Exam;
use App\Models\Question;
use App\Support\QuestionTextNormalizer;
use Illuminate\Support\Facades\DB;

class QuestionAssignService
{
    /**
     * Copy duplicate question groups into a target exam (skips if text already exists there).
     *
     * @param  list<string>  $fingerprints
     * @param  array<string, int>  $sourceQuestionIds  fingerprint => question id
     * @return array{created: int, skipped: int, details: list<array<string, mixed>>}
     */
    public function copyFingerprintsToExam(
        int $examId,
        array $fingerprints,
        array $sourceQuestionIds = []
    ): array {
        Exam::query()->findOrFail($examId);

        $created = 0;
        $skipped = 0;
        $details = [];

        DB::transaction(function () use ($examId, $fingerprints, $sourceQuestionIds, &$created, &$skipped, &$details) {
            foreach ($fingerprints as $fingerprint) {
                $fingerprint = (string) $fingerprint;
                if ($fingerprint === '') {
                    continue;
                }

                if ($this->examHasFingerprint($examId, $fingerprint)) {
                    $skipped++;
                    $details[] = [
                        'fingerprint' => $fingerprint,
                        'status' => 'skipped',
                        'reason' => 'already_in_exam',
                    ];

                    continue;
                }

                $sourceId = $sourceQuestionIds[$fingerprint] ?? null;
                $source = $this->resolveSourceQuestion($fingerprint, $sourceId);

                if (! $source) {
                    $skipped++;
                    $details[] = [
                        'fingerprint' => $fingerprint,
                        'status' => 'skipped',
                        'reason' => 'source_not_found',
                    ];

                    continue;
                }

                $copy = $this->cloneQuestion($source, $examId);
                $created++;
                $details[] = [
                    'fingerprint' => $fingerprint,
                    'status' => 'created',
                    'question_id' => $copy->id,
                    'source_question_id' => $source->id,
                ];
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'details' => $details,
        ];
    }

    /**
     * Resolve questions for duplicate fingerprints (for random exam pool).
     *
     * @param  list<string>  $fingerprints
     * @return list<Question>
     */
    public function questionsForFingerprints(
        array $fingerprints,
        int $classificationId,
        int $examId
    ): array {
        $out = [];

        foreach ($fingerprints as $fingerprint) {
            $fingerprint = (string) $fingerprint;
            if ($fingerprint === '') {
                continue;
            }

            $question = $this->resolveSourceQuestion($fingerprint, null, $classificationId, $examId);
            if ($question) {
                $out[] = $question;
            }
        }

        return $out;
    }

    protected function resolveSourceQuestion(
        string $fingerprint,
        ?int $preferredId = null,
        ?int $classificationId = null,
        ?int $examId = null
    ): ?Question {
        if ($preferredId) {
            $preferred = Question::query()->with('exam')->find($preferredId);
            if (
                $preferred
                && QuestionTextNormalizer::fingerprint((string) $preferred->question_text) === $fingerprint
            ) {
                return $preferred;
            }
        }

        $query = Question::query()
            ->with('exam:id,job_classification_id,status')
            ->orderByDesc('times_served')
            ->orderBy('id');

        if ($classificationId) {
            $query->whereHas('exam', function ($q) use ($classificationId, $examId) {
                $q->where('status', 'published')
                    ->where(function ($w) use ($classificationId, $examId) {
                        $w->where('job_classification_id', $classificationId);
                        if ($examId) {
                            $w->orWhere('id', $examId);
                        }
                    });
            });
        }

        foreach ($query->get() as $question) {
            if (QuestionTextNormalizer::fingerprint((string) $question->question_text) === $fingerprint) {
                return $question;
            }
        }

        return null;
    }

    protected function examHasFingerprint(int $examId, string $fingerprint): bool
    {
        $questions = Question::query()
            ->where('exam_id', $examId)
            ->get(['id', 'question_text']);

        foreach ($questions as $question) {
            if (QuestionTextNormalizer::fingerprint((string) $question->question_text) === $fingerprint) {
                return true;
            }
        }

        return false;
    }

    protected function cloneQuestion(Question $source, int $examId): Question
    {
        $copy = $source->replicate();
        $copy->exam_id = $examId;
        $copy->times_served = 0;
        $copy->save();

        Exam::query()->whereKey($examId)->increment('total_questions');

        return $copy;
    }
}
