<?php

namespace App\Services\Seo;

use App\Models\Seo\SeoLink;
use App\Traits\HasSeo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InternalLinkExtractor
{
    public function extract(Model $model): int
    {
        if (! in_array(HasSeo::class, class_uses_recursive($model), true)) {
            return 0;
        }

        $content = (string) ($model->getAttribute('content') ?? $model->getAttribute('description') ?? '');
        if ($content === '') {
            return 0;
        }

        preg_match_all('/href\s*=\s*["\']([^"\']+)["\']/i', $content, $matches);
        $urls = array_unique($matches[1] ?? []);
        $appUrl = rtrim((string) config('app.url'), '/');
        $created = 0;

        SeoLink::query()
            ->where('linkable_type', $model->getMorphClass())
            ->where('linkable_id', $model->getKey())
            ->delete();

        foreach ($urls as $url) {
            $isInternal = Str::startsWith($url, [$appUrl, '/']);
            if (! $isInternal) {
                continue;
            }

            SeoLink::query()->create([
                'linkable_type' => $model->getMorphClass(),
                'linkable_id' => $model->getKey(),
                'target_url' => Str::startsWith($url, '/') ? $appUrl.$url : $url,
                'target_type' => 'internal',
                'anchor_text' => null,
                'is_broken' => false,
            ]);
            $created++;
        }

        return $created;
    }
}
