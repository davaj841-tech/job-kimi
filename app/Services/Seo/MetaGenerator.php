<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\Seo\SeoAnalysis;
use App\Models\Seo\SeoKeyword;
use App\Models\Seo\SeoMeta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class MetaGenerator
{
    public function __construct(
        protected CanonicalService $canonicalService,
        protected RobotsService $robotsService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generate(Model $model): array
    {
        $metaRaw = $model->getRelationValue('seoMeta');
        $meta = $metaRaw instanceof SeoMeta ? $metaRaw : null;

        $keywordRaw = $model->getRelationValue('seoKeyword');
        $keyword = $keywordRaw instanceof SeoKeyword ? $keywordRaw : null;

        $analysisRaw = $model->getRelationValue('seoAnalysis');
        $analysis = $analysisRaw instanceof SeoAnalysis ? $analysisRaw : null;

        $title = ($meta !== null ? $meta->title : null)
            ?? $model->getAttribute('meta_title')
            ?? $model->getAttribute('title')
            ?? $model->getAttribute('name')
            ?? config('seo.default_title');
        $description = ($meta !== null ? $meta->description : null)
            ?? $model->getAttribute('meta_description')
            ?? $model->getAttribute('excerpt')
            ?? $model->getAttribute('description')
            ?? config('seo.default_description');

        $robots = $this->robotsService->forModel($model);
        $canonical = $this->canonicalService->getCanonical($model);
        $ogTitle = ($meta !== null ? $meta->og_title : null) ?? $title;
        $ogDescription = ($meta !== null ? $meta->og_description : null) ?? mb_substr(strip_tags((string) $description), 0, 200);
        $ogImage = ($meta !== null ? $meta->og_image : null) ?? $this->resolveImage($model);
        $twitterTitle = ($meta !== null ? $meta->og_title : null) ?? $title;
        $twitterDescription = ($meta !== null ? $meta->og_description : null) ?? mb_substr(strip_tags((string) $description), 0, 200);
        $twitterImage = ($meta !== null ? $meta->og_image : null) ?? $ogImage;

        return $this->formatPayload(
            title: (string) $title,
            description: $description !== null ? (string) $description : null,
            canonical: $canonical,
            robots: $robots,
            ogTitle: (string) $ogTitle,
            ogDescription: (string) $ogDescription,
            ogImage: $ogImage,
            ogType: $this->resolveOgType($model),
            twitterTitle: (string) $twitterTitle,
            twitterDescription: (string) $twitterDescription,
            twitterImage: $twitterImage,
            twitterCard: ($meta !== null ? $meta->twitter_card : null) ?? 'summary_large_image',
            focusKeyword: $keyword !== null ? $keyword->focus_keyword : null,
            score: $analysis !== null ? $analysis->score : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function generateForPage(string $title, ?string $description = null, ?string $canonical = null, ?string $ogType = 'website'): array
    {
        $robots = $this->robotsService->defaults();

        return $this->formatPayload(
            title: $title,
            description: $description ?? config('seo.default_description'),
            canonical: $canonical ?? url('/'),
            robots: $robots,
            ogTitle: $title,
            ogDescription: mb_substr(strip_tags((string) ($description ?? config('seo.default_description'))), 0, 200),
            ogImage: null,
            ogType: $ogType ?? 'website',
            twitterTitle: $title,
            twitterDescription: mb_substr(strip_tags((string) ($description ?? config('seo.default_description'))), 0, 200),
            twitterImage: null,
            twitterCard: 'summary_large_image',
        );
    }

    /**
     * @param  array{index: bool, follow: bool}  $robots
     * @return array<string, mixed>
     */
    protected function formatPayload(
        string $title,
        ?string $description,
        ?string $canonical,
        array $robots,
        string $ogTitle,
        string $ogDescription,
        ?string $ogImage,
        string $ogType,
        string $twitterTitle,
        string $twitterDescription,
        ?string $twitterImage,
        string $twitterCard,
        ?string $focusKeyword = null,
        ?int $score = null,
    ): array {
        $plainDescription = mb_substr(strip_tags((string) $description), 0, 320);

        return [
            'meta_title' => mb_substr($title, 0, 160),
            'meta_description' => $plainDescription,
            'canonical_url' => $canonical,
            'robots_index' => $robots['index'],
            'robots_follow' => $robots['follow'],
            'robots' => $this->robotsService->toString($robots['index'], $robots['follow']),
            'og_title' => mb_substr($ogTitle, 0, 160),
            'og_description' => $ogDescription,
            'og_image' => $ogImage ? URL::to($ogImage) : null,
            'og_type' => $ogType,
            'twitter_title' => mb_substr($twitterTitle, 0, 160),
            'twitter_description' => $twitterDescription,
            'twitter_image' => $twitterImage ? URL::to($twitterImage) : null,
            'twitter_card' => $twitterCard,
            'focus_keyword' => $focusKeyword,
            'seo_score' => $score,
        ];
    }

    protected function resolveOgType(Model $model): string
    {
        return match (true) {
            $model instanceof JobPost,
            $model instanceof BlogPost,
            $model instanceof GeneratedContent => 'article',
            default => 'website',
        };
    }

    protected function resolveImage(Model $model): ?string
    {
        $featured = $model->getAttribute('featured_image');
        if (filled($featured)) {
            return (string) $featured;
        }

        $thumbnail = $model->getAttribute('thumbnail');
        if (filled($thumbnail)) {
            return (string) $thumbnail;
        }

        return null;
    }
}
