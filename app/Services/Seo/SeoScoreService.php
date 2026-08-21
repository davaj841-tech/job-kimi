<?php

namespace App\Services\Seo;

use App\Models\Seo\SeoAnalysis;

class SeoScoreService
{
    public function getAverageScore(): float
    {
        return (float) SeoAnalysis::query()->avg('score') ?: 0;
    }

    /**
     * @return array{excellent: int, good: int, needs_improvement: int, poor: int}
     */
    public function getScoreDistribution(): array
    {
        return [
            'excellent' => SeoAnalysis::where('status', 'excellent')->count(),
            'good' => SeoAnalysis::where('status', 'good')->count(),
            'needs_improvement' => SeoAnalysis::where('status', 'needs_improvement')->count(),
            'poor' => SeoAnalysis::where('status', 'poor')->count(),
        ];
    }

    /**
     * @return list<array{model: string, id: mixed, title: string, score: int|null, status: string|null}>
     */
    public function getTopIssues(int $limit = 10): array
    {
        return SeoAnalysis::query()
            ->where('score', '<', 75)
            ->orderBy('score')
            ->limit($limit)
            ->with('analyzable')
            ->get()
            ->map(function (SeoAnalysis $a) {
                $related = $a->analyzable;
                $title = $related !== null ? $related->getAttribute('title') : null;

                return [
                    'model' => class_basename($a->analyzable_type),
                    'id' => $a->analyzable_id,
                    'title' => $title !== null ? (string) $title : 'N/A',
                    'score' => $a->score,
                    'status' => $a->status,
                ];
            })
            ->all();
    }
}
