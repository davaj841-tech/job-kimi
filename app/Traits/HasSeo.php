<?php

namespace App\Traits;

use App\Models\Seo\SeoAnalysis;
use App\Models\Seo\SeoFaq;
use App\Models\Seo\SeoKeyword;
use App\Models\Seo\SeoLink;
use App\Models\Seo\SeoMeta;
use App\Services\Seo\RobotsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasSeo
{
    /**
     * @return MorphOne<SeoMeta, $this>
     */
    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /**
     * @return MorphOne<SeoKeyword, $this>
     */
    public function seoKeyword(): MorphOne
    {
        return $this->morphOne(SeoKeyword::class, 'keywordable');
    }

    /**
     * @return MorphOne<SeoAnalysis, $this>
     */
    public function seoAnalysis(): MorphOne
    {
        return $this->morphOne(SeoAnalysis::class, 'analyzable');
    }

    /**
     * @return MorphMany<SeoFaq, $this>
     */
    public function seoFaqs(): MorphMany
    {
        return $this->morphMany(SeoFaq::class, 'faqable')->orderBy('sort_order');
    }

    /**
     * @return MorphMany<SeoLink, $this>
     */
    public function seoLinks(): MorphMany
    {
        return $this->morphMany(SeoLink::class, 'linkable');
    }

    public function getSeoTitle(): string
    {
        $meta = $this->getRelationValue('seoMeta');
        $fromMeta = $meta instanceof SeoMeta ? $meta->title : null;

        return (string) (
            $fromMeta
            ?? $this->getAttribute('meta_title')
            ?? $this->getAttribute('title')
            ?? config('seo.default_title')
        );
    }

    public function getSeoDescription(): string
    {
        $meta = $this->getRelationValue('seoMeta');
        $fromMeta = $meta instanceof SeoMeta ? $meta->description : null;

        return (string) (
            $fromMeta
            ?? $this->getAttribute('meta_description')
            ?? $this->getAttribute('excerpt')
            ?? $this->getAttribute('description')
            ?? config('seo.default_description')
        );
    }

    public function getSeoScore(): int
    {
        $analysis = $this->getRelationValue('seoAnalysis');

        return (int) ($analysis instanceof SeoAnalysis ? ($analysis->score ?? 0) : 0);
    }

    public function getSeoStatus(): string
    {
        $analysis = $this->getRelationValue('seoAnalysis');

        return (string) ($analysis instanceof SeoAnalysis ? ($analysis->status ?? 'pending') : 'pending');
    }

    public function isSeoIndexable(): bool
    {
        return app(RobotsService::class)->isIndexable($this);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSeoIndexable($query)
    {
        return $query->where(function ($q) {
            $q->whereDoesntHave('seoMeta')
                ->orWhereHas('seoMeta', fn ($m) => $m->where('robots', 'not like', '%noindex%'));
        });
    }
}
