<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'exam_title' => $this->whenLoaded('exam', fn () => $this->exam?->title),
            'question_text' => $this->question_text,
            'question_type' => $this->question_type,
            'option_a' => $this->option_a,
            'option_b' => $this->option_b,
            'option_c' => $this->option_c,
            'option_d' => $this->option_d,
            'correct_answer' => $this->correct_answer,
            'correct_answer_label' => \App\Services\ReportCardPDFService::optionLetter($this->correct_answer),
            'explanation' => $this->explanation,
            'difficulty' => $this->difficulty,
            'subject' => $this->subject,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
