<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => $this['user'],
            'stats' => $this['stats'],
            'progress_chart' => $this['progress_chart'],
            'exam_chart' => $this['exam_chart'] ?? [],
            'recent_attempts' => $this['recent_attempts'],
            'available_exams' => $this['available_exams'],
            'kpis' => $this['kpis'] ?? [],
            'score_history' => $this['score_history'] ?? [],
            'score_growth' => $this['score_growth'] ?? '',
            'skill_labels' => $this['skill_labels'] ?? [],
            'skill_scores' => $this['skill_scores'] ?? [],
            'avg_skill_scores' => $this['avg_skill_scores'] ?? [],
            'strengths' => $this['strengths'] ?? [],
            'weaknesses' => $this['weaknesses'] ?? [],
            'suggestion' => $this['suggestion'] ?? '',
            'time_distribution' => $this['time_distribution'] ?? [],
            'recent_activity' => $this['recent_activity'] ?? [],
            'daily_plan' => $this['daily_plan'] ?? [],
            'streak' => $this['streak'] ?? [],
        ];
    }
}
