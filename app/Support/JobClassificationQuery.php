<?php

namespace App\Support;

use App\Models\JobClassification;
use Illuminate\Database\Eloquent\Builder;

class JobClassificationQuery
{
    /**
     * @return list<int>
     */
    public static function parseFilterIds(mixed $single, mixed $multi = null): array
    {
        $ids = [];

        if (is_array($multi)) {
            $ids = array_map(intval(...), array_filter($multi, fn ($v) => $v !== null && $v !== ''));
        } elseif (is_string($multi) && trim($multi) !== '') {
            $ids = array_map(intval(...), array_filter(explode(',', $multi), fn ($v) => trim($v) !== ''));
        }

        if ($single !== null && $single !== '') {
            $ids[] = (int) $single;
        }

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function expandWithDescendants(array $ids): array
    {
        $expanded = [];

        foreach ($ids as $id) {
            $class = JobClassification::query()->find($id);
            if ($class) {
                $expanded = array_merge($expanded, $class->descendantAndSelfIds());
            } else {
                $expanded[] = $id;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, mixed>  $filters
     */
    public static function applyClassificationFilter(Builder $query, array $filters, string $column = 'job_classification_id'): void
    {
        $ids = self::parseFilterIds(
            $filters['job_classification_id'] ?? null,
            $filters['job_classification_ids'] ?? null,
        );

        if ($ids === []) {
            return;
        }

        $query->whereIn($column, self::expandWithDescendants($ids));
    }
}
