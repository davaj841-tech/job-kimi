<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stats = $this->resultSummary();
        $exam = $this->exam;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'exam_id' => $this->exam_id,
            'score' => $this->score,
            'total_correct' => $stats['total_correct'],
            'total_wrong' => $stats['total_wrong'],
            'total_unanswered' => $stats['total_unanswered'],
            'total_questions' => $stats['total_questions'],
            'percentage' => $stats['percentage'],
            'passed' => $stats['passed'],
            'is_retry_wrong' => (bool) $this->is_retry_wrong,
            'retry_mode' => $this->retry_mode,
            'parent_attempt_id' => $this->parent_attempt_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'status' => $this->status,
            'rank' => $this->when(isset($this->rank), $this->rank),
            'questions' => $this->when(isset($this->result_questions), $this->result_questions),
            'exam' => $this->when($exam !== null, [
                'title' => $exam?->title,
                'total_marks' => $exam?->total_marks,
                'total_questions' => $exam?->total_questions,
                'passing_score' => $exam?->passing_score,
            ]),
            'answers' => $this->when($request->boolean('include_answers'), $this->answers),
        ];
    }
}
