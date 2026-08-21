<?php

namespace App\Http\Resources;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Exam
 *
 * @property-read Exam $resource
 */
class ExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'seo_tag' => $this->seo_tag,
            'description' => $this->description,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ]),
            'category_id' => $this->category_id,
            'job_post_id' => $this->job_post_id,
            'job_classification_id' => $this->job_classification_id,
            'classification' => $this->whenLoaded('classification', fn () => [
                'id' => $this->classification?->id,
                'name' => $this->classification?->name,
            ]),
            'duration_minutes' => $this->duration_minutes,
            'passing_score' => $this->passing_score,
            'total_questions' => $this->questions_count ?? $this->total_questions,
            'total_marks' => $this->total_marks,
            'has_negative_marking' => (bool) $this->has_negative_marking,
            'negative_mark_ratio' => (float) ($this->negative_mark_ratio ?? 0.3333),
            'is_free' => $this->is_free,
            'price' => $this->price,
            'subscription_required' => $this->subscription_required,
            'status' => $this->status,
            'is_random' => (bool) ($this->is_random ?? false),
            'avg_rating' => (float) ($this->avg_rating ?? 0),
            'ratings_count' => (int) ($this->ratings_count ?? 0),
            'attempts_count' => (int) ($this->attempts_count ?? 0),
            'user_attempt_count' => $this->when(isset($this->user_attempt_count), $this->user_attempt_count),
            'user_best_score' => $this->when(isset($this->user_best_score), $this->user_best_score),
            'is_eligible' => $this->when(isset($this->is_eligible), $this->is_eligible),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
