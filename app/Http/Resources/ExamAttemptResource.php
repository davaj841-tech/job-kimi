<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamAttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $exam = $this->relationLoaded('exam') ? $this->exam : null;
        $correct = (int) $this->total_correct;
        $wrong = (int) $this->total_wrong;
        $totalFromExam = (int) ($exam?->total_questions ?: 0);
        $total = max($totalFromExam, $correct + $wrong, 1);
        $percentage = round(($correct / $total) * 100, 2);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'exam_id' => $this->exam_id,
            'score' => $this->score,
            'total_correct' => $this->total_correct,
            'total_wrong' => $this->total_wrong,
            'total_unanswered' => max(0, $total - $correct - $wrong),
            'percentage' => $this->percentage ?? $percentage,
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
