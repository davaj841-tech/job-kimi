<?php

namespace App\Services\Seo;

use App\Models\JobPost;
use App\Models\Seo\SeoMeta;
use App\Support\Utf8Text;
use App\Traits\HasSeo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeoAutoOptimizer
{
    public function __construct(
        protected SeoManager $seoManager,
        protected CanonicalService $canonicalService,
    ) {}

    public function optimize(Model $model): bool
    {
        if (! in_array(HasSeo::class, class_uses_recursive($model), true)) {
            return false;
        }

        $model->loadMissing(['seoMeta', 'seoKeyword']);
        $metaRaw = $model->getRelationValue('seoMeta');
        $meta = $metaRaw instanceof SeoMeta ? $metaRaw : null;

        $changed = false;
        $metaData = [];

        if (! filled($meta?->title)) {
            $generated = $this->generateTitle($model);
            if ($generated) {
                $metaData['title'] = $generated;
                $metaData['og_title'] = $generated;
                $changed = true;
            }
        }

        if (! filled($meta?->description)) {
            $generated = $this->generateDescription($model);
            if ($generated) {
                $metaData['description'] = $generated;
                $metaData['og_description'] = Utf8Text::limit($generated, 200, '');
                $changed = true;
            }
        }

        if (! filled($meta?->canonical)) {
            $canonical = $this->canonicalService->generateCanonical($model);
            if ($canonical) {
                $metaData['canonical'] = $canonical;
                $changed = true;
            }
        }

        if (! filled($meta?->og_image)) {
            $image = $this->resolveImage($model);
            if ($image) {
                $metaData['og_image'] = $image;
                $changed = true;
            }
        }

        if ($metaData !== []) {
            $this->seoManager->updateMeta($model, $metaData);
        }

        $keywordRaw = $model->getRelationValue('seoKeyword');
        if (! filled($keywordRaw?->focus_keyword)) {
            $keyword = $this->generateFocusKeyword($model);
            if ($keyword) {
                $this->seoManager->updateKeyword($model, [
                    'focus_keyword' => $keyword,
                    'search_intent' => 'informational',
                ]);
                $changed = true;
            }
        }

        return $changed;
    }

    protected function generateTitle(Model $model): ?string
    {
        $base = $model->getAttribute('meta_title')
            ?? $model->getAttribute('title')
            ?? $model->getAttribute('name');

        if (! filled($base)) {
            return null;
        }

        $site = (string) config('seo.site_name', 'جاب‌آزمون');
        $suffix = " | {$site}";
        $max = 60;
        $title = (string) $base;

        if ($model instanceof JobPost) {
            $org = $model->getAttribute('company_name') ?? $model->getAttribute('classification_name');
            if ($org) {
                $title = "{$title} — {$org}";
            }
        }

        if (mb_strlen($title) + mb_strlen($suffix) <= $max) {
            return $title.$suffix;
        }

        return mb_substr($title, 0, max(10, $max - mb_strlen($suffix))).$suffix;
    }

    protected function generateDescription(Model $model): ?string
    {
        $source = $model->getAttribute('meta_description')
            ?? $model->getAttribute('excerpt')
            ?? $model->getAttribute('description')
            ?? $model->getAttribute('content');

        if (! filled($source)) {
            return null;
        }

        // Sanitize before /u regex — invalid bytes make preg_replace return null.
        $plain = Utf8Text::sanitize(strip_tags((string) $source));
        $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);

        if ($plain === '') {
            return null;
        }

        if (mb_strlen($plain, 'UTF-8') <= 160) {
            return $plain;
        }

        $cut = mb_substr($plain, 0, 157, 'UTF-8');
        $lastSpace = mb_strrpos($cut, ' ', 0, 'UTF-8');
        if ($lastSpace !== false && $lastSpace > 100) {
            $cut = mb_substr($cut, 0, $lastSpace, 'UTF-8');
        }

        return Utf8Text::sanitize(rtrim($cut, '،,.')).'...';
    }

    protected function generateFocusKeyword(Model $model): ?string
    {
        if ($model instanceof JobPost && filled($model->getAttribute('seo_tag'))) {
            return str_replace('_', ' ', (string) $model->getAttribute('seo_tag'));
        }

        if (filled($model->getAttribute('seo_tag'))) {
            return str_replace('_', ' ', (string) $model->getAttribute('seo_tag'));
        }

        $title = $model->getAttribute('title') ?? $model->getAttribute('name');
        if (! filled($title)) {
            return null;
        }

        $words = preg_split('/\s+/u', strip_tags((string) $title)) ?: [];
        $words = array_values(array_filter($words, fn ($w) => mb_strlen($w) > 2));

        if (count($words) >= 2) {
            return implode(' ', array_slice($words, 0, 3));
        }

        return Str::limit(strip_tags((string) $title), 40, '');
    }

    protected function resolveImage(Model $model): ?string
    {
        foreach (['featured_image', 'thumbnail', 'cover'] as $field) {
            if (filled($model->getAttribute($field))) {
                return (string) $model->getAttribute($field);
            }
        }

        return null;
    }
}
