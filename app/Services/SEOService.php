<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Services\Seo\SchemaGenerator;
use App\Services\Seo\SeoManager;
use App\Services\Seo\SitemapService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;

/**
 * Legacy SEO bridge — delegates to the unified SeoManager / SchemaGenerator stack.
 */
class SEOService
{
    public function __construct(
        protected SchemaGenerator $schemaGenerator,
        protected SeoManager $seoManager,
        protected SitemapService $sitemapService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function generateJobPostSchema(JobPost $job): array
    {
        return $this->schemaGenerator->primarySchema($job->loadMissing(['seoFaqs']))
            ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateBlogSchema(BlogPost $post): array
    {
        $post->loadMissing(['seoFaqs', 'creator']);

        return $this->schemaGenerator->primarySchema($post) ?? [];
    }

    /**
     * @param  list<array{name: string, url: string}>|array<int, array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public function generateBreadcrumbSchema(array $items): array
    {
        return $this->schemaGenerator->breadcrumbSchema($items);
    }

    /**
     * @return array<string, mixed>
     */
    public function generateGeneratedContentSchema(GeneratedContent $content): array
    {
        $content->loadMissing(['seoFaqs', 'jobPost']);

        return $this->schemaGenerator->primarySchema($content) ?? [];
    }

    public function generateSitemap(): Response
    {
        $xml = $this->sitemapService->generateIndex();

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * @param  list<array{name: string, url: string}>|null  $breadcrumbs
     * @return array{meta: array<string, mixed>, schemas: list<array<string, mixed>>, schema: mixed, breadcrumb?: mixed, score: int}
     */
    public function buildPublicPayload(Model $model, ?array $breadcrumbs = null): array
    {
        return $this->seoManager->buildPublicPayload($model, $breadcrumbs);
    }
}
