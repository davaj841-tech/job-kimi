<?php

namespace App\Services\Seo;

use App\Models\Seo\SeoKeyword;

class CannibalizationService
{
    /**
     * @return list<array{keyword: string|null, count: int, pages: list<array{type: string, id: mixed, title: string}>}>
     */
    public function findCannibalization(): array
    {
        $threshold = config('seo.cannibalization_threshold', 2);

        return SeoKeyword::query()
            ->selectRaw('focus_keyword, COUNT(*) as count')
            ->groupBy('focus_keyword')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->orderByDesc('count')
            ->get()
            ->map(function (SeoKeyword $row) {
                $pages = SeoKeyword::where('focus_keyword', $row->focus_keyword)
                    ->with('keywordable')
                    ->get()
                    ->map(function (SeoKeyword $k) {
                        $related = $k->keywordable;
                        $title = $related !== null ? $related->getAttribute('title') : null;

                        return [
                            'type' => class_basename($k->keywordable_type),
                            'id' => $k->keywordable_id,
                            'title' => $title !== null ? (string) $title : 'N/A',
                        ];
                    })
                    ->all();

                return [
                    'keyword' => $row->focus_keyword,
                    'count' => (int) ($row->count ?? 0),
                    'pages' => $pages,
                ];
            })
            ->values()
            ->all();
    }
}
