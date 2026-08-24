<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\GeneratedContent;
use App\Models\Seo\SeoAnalysis;
use App\Models\Seo\SeoKeyword;
use App\Models\Seo\SeoMeta;
use Illuminate\Database\Eloquent\Model;

class SeoManager
{
    public function __construct(
        protected SeoAnalyzer $analyzer,
        protected MetaGenerator $metaGenerator,
        protected SchemaGenerator $schemaGenerator,
    ) {}

    public function analyze(Model $model): SeoAnalysis
    {
        return $this->analyzer->analyze($model);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(Model $model): array
    {
        return $this->metaGenerator->generate($model);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSchema(Model $model): array
    {
        return $this->schemaGenerator->generate($model);
    }

    /**
     * @param  list<array{name: string, url: string}>|null  $breadcrumbs
     * @return array{meta: array<string, mixed>, schemas: list<array<string, mixed>>, schema: array<string, mixed>|list<array<string, mixed>>, breadcrumb: list<array{name: string, url: string}>|null, score: int}
     */
    public function buildPublicPayload(Model $model, ?array $breadcrumbs = null): array
    {
        $model->loadMissing(['seoMeta', 'seoKeyword', 'seoAnalysis', 'seoFaqs']);

        if ($model instanceof BlogPost) {
            $model->loadMissing('creator');
        }

        if ($model instanceof GeneratedContent) {
            $model->loadMissing('jobPost');
        }

        $meta = $this->metaGenerator->generate($model);
        $schemas = $this->schemaGenerator->generate($model);

        if ($breadcrumbs) {
            $schemas[] = $this->schemaGenerator->breadcrumbSchema($breadcrumbs);
        }

        $primary = $this->schemaGenerator->primarySchema($model);

        return [
            'meta' => $meta,
            'schemas' => $schemas,
            'schema' => $primary ?? $schemas,
            'breadcrumb' => $breadcrumbs,
            'score' => $this->getScore($model),
        ];
    }

    /**
     * @return array{meta: array<string, mixed>, schemas: list<array<string, mixed>>, schema: list<array<string, mixed>>, score: int}
     */
    public function buildHomePayload(): array
    {
        $meta = $this->metaGenerator->generateForPage(
            (string) config('seo.default_title'),
            config('seo.default_description') !== null ? (string) config('seo.default_description') : null,
            url('/'),
        );

        $schemas = [
            $this->schemaGenerator->websiteSchema(),
            $this->schemaGenerator->organizationSchema(),
        ];

        return [
            'meta' => $meta,
            'schemas' => $schemas,
            'schema' => $schemas,
            'score' => 0,
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>|null  $breadcrumbs
     * @return array{meta: array<string, mixed>, schemas: list<array<string, mixed>>, schema: list<array<string, mixed>>, score: int}
     */
    public function buildPagePayload(string $title, ?string $description, string $canonical, ?array $breadcrumbs = null): array
    {
        $meta = $this->metaGenerator->generateForPage($title, $description, $canonical);
        $schemas = [$this->schemaGenerator->websiteSchema()];

        if ($breadcrumbs) {
            $schemas[] = $this->schemaGenerator->breadcrumbSchema($breadcrumbs);
        }

        return [
            'meta' => $meta,
            'schemas' => $schemas,
            'schema' => $schemas,
            'score' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMeta(Model $model, array $data): SeoMeta
    {
        if (method_exists($model, 'seoMeta')) {
            return $model->seoMeta()->updateOrCreate(
                ['seoable_type' => $model->getMorphClass(), 'seoable_id' => $model->getKey()],
                $data
            );
        }

        return SeoMeta::query()->updateOrCreate(
            ['seoable_type' => $model->getMorphClass(), 'seoable_id' => $model->getKey()],
            $data
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateKeyword(Model $model, array $data): SeoKeyword
    {
        if (method_exists($model, 'seoKeyword')) {
            return $model->seoKeyword()->updateOrCreate(
                ['keywordable_type' => $model->getMorphClass(), 'keywordable_id' => $model->getKey()],
                $data
            );
        }

        return SeoKeyword::query()->updateOrCreate(
            ['keywordable_type' => $model->getMorphClass(), 'keywordable_id' => $model->getKey()],
            $data
        );
    }

    public function getScore(Model $model): int
    {
        $analysisRaw = $model->getRelationValue('seoAnalysis');
        $analysis = $analysisRaw instanceof SeoAnalysis ? $analysisRaw : null;

        return ($analysis !== null ? $analysis->score : null) ?? 0;
    }
}
