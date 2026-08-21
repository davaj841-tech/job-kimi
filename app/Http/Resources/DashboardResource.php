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
        ];
    }
}
