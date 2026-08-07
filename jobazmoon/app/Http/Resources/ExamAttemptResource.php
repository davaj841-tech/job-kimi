<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $exam = $this->relationLoaded('exam') ? $this->exam : null;
        $totalMarks = (float) ($exam?->total_marks ?: 0);
        $percentage = $totalMarks > 0
            ? round(((float) $this->score / $totalMarks) * 100, 2)
            : 0;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'exam_id' => $this->exam_id,
            'score' => $this->score,
            'total_correct' => $this->total_correct,
            'total_wrong' => $this->total_wrong,
            'percentage' => $this->percentage ?? $percentage,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'status' => $this->status,
            'rank' => $this->when(isset($this->rank), $this->rank),
            'questions' => $this->when(isset($this->result_questions), $this->result_questions),
            'exam' => $this->when($exam !== null, [
                'title' => $exam?->title,
                'total_marks' => $exam?->total_marks,
                'passing_score' => $exam?->passing_score,
            ]),
            'answers' => $this->when($request->boolean('include_answers'), $this->answers),
        ];
    }
}
