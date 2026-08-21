<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\CmsPage;
use App\Models\Exam;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\PdfProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class SitemapService
{
    public function __construct(protected RobotsService $robotsService) {}

    public function generateIndex(): string
    {
        return Cache::remember('sitemap:index', config('seo.sitemap.cache_ttl', 3600), function () {
            $sitemaps = ['pages', 'jobs', 'exams', 'articles', 'blog', 'files'];
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

            foreach ($sitemaps as $name) {
                $xml .= "  <sitemap>\n    <loc>".e(url("/sitemaps/{$name}.xml"))."</loc>\n    <lastmod>".now()->toAtomString()."</lastmod>\n  </sitemap>\n";
            }

            $xml .= '</sitemapindex>';

            return $xml;
        });
    }

    public function generatePages(): string
    {
        return Cache::remember('sitemap:pages', config('seo.sitemap.cache_ttl', 3600), function () {
            $pages = $this->indexableQuery(CmsPage::query()->where('is_published', true))->get(['slug', 'updated_at']);
            $xml = $this->openUrlset();
            $xml .= $this->urlEntry(url('/'), now()->toAtomString(), 'daily', '1.0');

            foreach ($pages as $page) {
                if ($this->robotsService->matchesNoindexPattern('pages/'.$page->slug)) {
                    continue;
                }
                $loc = in_array($page->slug, ['terms', 'privacy', 'about', 'contact'], true)
                    ? url('/'.$page->slug)
                    : url('/page/'.$page->slug);
                $xml .= $this->urlEntry($loc, $page->updated_at?->toAtomString(), 'monthly', '0.6');
            }

            return $xml.'</urlset>';
        });
    }

    public function generateJobs(): string
    {
        return Cache::remember('sitemap:jobs', config('seo.sitemap.cache_ttl', 3600), function () {
            $jobs = $this->indexableQuery(JobPost::query()->where('status', 'approved'))
                ->orderByDesc('updated_at')
                ->limit(config('seo.sitemap.max_urls_per_file', 5000))
                ->get(['id', 'updated_at']);
            $xml = $this->openUrlset();
            $xml .= $this->urlEntry(url('/jobs'), now()->toAtomString(), 'daily', '0.9');

            foreach ($jobs as $job) {
                $xml .= $this->urlEntry(url("/jobs/{$job->id}"), $job->updated_at?->toAtomString(), 'weekly', '0.8');
            }

            return $xml.'</urlset>';
        });
    }

    public function generateExams(): string
    {
        return Cache::remember('sitemap:exams', config('seo.sitemap.cache_ttl', 3600), function () {
            $exams = $this->indexableQuery(Exam::query()->where('status', 'published'))->get(['slug', 'updated_at']);
            $xml = $this->openUrlset();
            $xml .= $this->urlEntry(url('/exams'), now()->toAtomString(), 'daily', '0.9');

            foreach ($exams as $exam) {
                $xml .= $this->urlEntry(url("/exams/{$exam->slug}"), $exam->updated_at?->toAtomString(), 'weekly', '0.8');
            }

            return $xml.'</urlset>';
        });
    }

    public function generateArticles(): string
    {
        return Cache::remember('sitemap:articles', config('seo.sitemap.cache_ttl', 3600), function () {
            $articles = $this->indexableQuery(GeneratedContent::query()->published())->get(['slug', 'updated_at', 'published_at']);
            $xml = $this->openUrlset();

            foreach ($articles as $article) {
                $xml .= $this->urlEntry(url("/articles/{$article->slug}"), ($article->updated_at ?? $article->published_at)?->toAtomString(), 'weekly', '0.75');
            }

            return $xml.'</urlset>';
        });
    }

    public function generateBlog(): string
    {
        return Cache::remember('sitemap:blog', config('seo.sitemap.cache_ttl', 3600), function () {
            $posts = $this->indexableQuery(BlogPost::query()->where('status', 'published'))->get(['slug', 'updated_at']);
            $xml = $this->openUrlset();

            foreach ($posts as $post) {
                $xml .= $this->urlEntry(url("/blog/{$post->slug}"), $post->updated_at?->toAtomString(), 'weekly', '0.7');
            }

            return $xml.'</urlset>';
        });
    }

    public function generateFiles(): string
    {
        return Cache::remember('sitemap:files', config('seo.sitemap.cache_ttl', 3600), function () {
            $files = $this->indexableQuery(PdfProduct::query()->where('is_active', true))->get(['id', 'updated_at']);
            $xml = $this->openUrlset();

            foreach ($files as $file) {
                $xml .= $this->urlEntry(url("/pdf-products/{$file->id}"), $file->updated_at?->toAtomString(), 'monthly', '0.5');
            }

            return $xml.'</urlset>';
        });
    }

    public function clearCache(): void
    {
        Cache::forget('sitemap:index');
        foreach (['pages', 'jobs', 'exams', 'articles', 'blog', 'files'] as $name) {
            Cache::forget("sitemap:{$name}");
        }
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function indexableQuery(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereDoesntHave('seoMeta')
                ->orWhereHas('seoMeta', fn (Builder $m) => $m->where('robots', 'not like', '%noindex%'));
        });
    }

    protected function openUrlset(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    }

    protected function urlEntry(string $loc, ?string $lastmod, string $changefreq, string $priority): string
    {
        $lastmod = $lastmod ?? now()->toAtomString();

        return "  <url>\n    <loc>".e($loc)."</loc>\n    <lastmod>".e($lastmod)."</lastmod>\n    <changefreq>".e($changefreq)."</changefreq>\n    <priority>".e($priority)."</priority>\n  </url>\n";
    }
}
