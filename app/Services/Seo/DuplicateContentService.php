<?php

namespace App\Services\Seo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DuplicateContentService
{
    /**
     * @return list<array{id: mixed, title: string, similarity: float}>
     */
    public function findDuplicates(Model $model): array
    {
        $content = strip_tags((string) ($model->getAttribute('content') ?? $model->getAttribute('description') ?? ''));
        if (mb_strlen($content) < 100) {
            return [];
        }

        $threshold = config('seo.duplicate_threshold', 70);
        $modelClass = get_class($model);
        $duplicates = [];
        $compareLimit = (int) config('seo.duplicate_compare_limit', 100);

        $others = $modelClass::query()
            ->where('id', '!=', $model->getKey())
            ->limit($compareLimit)
            ->get();

        foreach ($others as $other) {
            $otherContent = strip_tags((string) ($other->getAttribute('content') ?? $other->getAttribute('description') ?? ''));
            if (mb_strlen($otherContent) < 100) {
                continue;
            }

            $similarity = $this->calculateSimilarity($content, $otherContent);
            if ($similarity >= $threshold) {
                $title = $other->getAttribute('title');
                $duplicates[] = [
                    'id' => $other->getKey(),
                    'title' => $title !== null ? (string) $title : 'N/A',
                    'similarity' => $similarity,
                ];
            }
        }

        return $duplicates;
    }

    /**
     * Bounded duplicate scan for audit pipeline (avoids O(n²) on large datasets).
     *
     * @return list<array{model: class-string, id: mixed, duplicates: list<array{id: mixed, title: string, similarity: float}>}>
     */
    public function auditBatch(?int $limit = null): array
    {
        $limit = $limit ?? (int) config('seo.duplicate_audit_batch', 50);
        $results = [];
        $checked = 0;

        foreach (config('seo.duplicate_audit_models', config('seo.analyze_on_change_models', [])) as $modelClass) {
            if ($checked >= $limit) {
                break;
            }

            $remaining = $limit - $checked;
            $models = $modelClass::query()->limit($remaining)->get();
            foreach ($models as $model) {
                $dupes = $this->findDuplicates($model);
                if ($dupes !== []) {
                    $results[] = [
                        'model' => $modelClass,
                        'id' => $model->getKey(),
                        'duplicates' => $dupes,
                    ];
                }
                $checked++;
                if ($checked >= $limit) {
                    break 2;
                }
            }
        }

        return $results;
    }

    protected function calculateSimilarity(string $a, string $b): float
    {
        $wordsA = array_unique(explode(' ', Str::limit($a, 2000, '')));
        $wordsB = array_unique(explode(' ', Str::limit($b, 2000, '')));

        $intersection = count(array_intersect($wordsA, $wordsB));
        $union = count(array_unique(array_merge($wordsA, $wordsB)));

        return $union > 0 ? round(($intersection / $union) * 100, 1) : 0;
    }
}
