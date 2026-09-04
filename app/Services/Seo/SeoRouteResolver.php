<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\CmsPage;
use App\Models\Exam;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\PdfProduct;
use App\Support\LegalPages;

class SeoRouteResolver
{
    public function __construct(protected SeoManager $seoManager) {}

    /**
     * @return array{meta: array<string, mixed>, schemas: list<array<string, mixed>>, schema: array<string, mixed>|list<array<string, mixed>>, score: int, breadcrumb?: list<array{name: string, url: string}>|null}|null
     */
    public function resolve(string $path): ?array
    {
        $path = trim($path, '/');

        if ($path === '') {
            return $this->seoManager->buildHomePayload();
        }

        if ($page = config("seo.list_pages.{$path}")) {
            return $this->seoManager->buildPagePayload(
                (string) $page['title'],
                $page['description'] ?? null,
                url('/'.$path),
                [
                    ['name' => 'خانه', 'url' => url('/')],
                    ['name' => (string) $page['title'], 'url' => url('/'.$path)],
                ],
            );
        }

        if (in_array($path, ['terms', 'privacy', 'about', 'contact'], true)) {
            return $this->resolveLegalPage($path);
        }

        $segments = explode('/', $path, 3);

        return match ($segments[0] ?? '') {
            'jobs' => $this->resolveJob($segments),
            'exams' => $this->resolveExam($segments),
            'blog' => $this->resolveBlog($segments),
            'articles' => $this->resolveArticle($segments),
            'pdfs' => $this->resolvePdf($segments),
            'page' => $this->resolveCmsPage($segments[1] ?? ''),
            default => null,
        };
    }

    /**
     * @param  list<string>  $segments
     */
    protected function resolveJob(array $segments): ?array
    {
        $id = $segments[1] ?? null;
        if (! $id || ! ctype_digit($id)) {
            return null;
        }

        $job = JobPost::query()
            ->where('status', 'approved')
            ->find((int) $id);

        if (! $job) {
            return null;
        }

        return $this->seoManager->buildPublicPayload($job, [
            ['name' => 'خانه', 'url' => url('/')],
            ['name' => 'آگهی‌ها', 'url' => url('/jobs')],
            ['name' => $job->title, 'url' => url("/jobs/{$job->id}")],
        ]);
    }

    /**
     * @param  list<string>  $segments
     */
    protected function resolveExam(array $segments): ?array
    {
        $slug = $segments[1] ?? null;
        if (! $slug) {
            return null;
        }

        $exam = Exam::query()
            ->where('status', 'published')
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (ctype_digit($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->first();

        if (! $exam) {
            return null;
        }

        return $this->seoManager->buildPublicPayload($exam, [
            ['name' => 'خانه', 'url' => url('/')],
            ['name' => 'آزمون‌ها', 'url' => url('/exams')],
            ['name' => $exam->title, 'url' => url("/exams/{$exam->slug}")],
        ]);
    }

    /**
     * @param  list<string>  $segments
     */
    protected function resolveBlog(array $segments): ?array
    {
        $slug = $segments[1] ?? null;
        if (! $slug) {
            return null;
        }

        $post = BlogPost::query()
            ->where('status', 'published')
            ->where('slug', $slug)
            ->first();

        if (! $post) {
            return null;
        }

        return $this->seoManager->buildPublicPayload($post, [
            ['name' => 'خانه', 'url' => url('/')],
            ['name' => 'بلاگ', 'url' => url('/blog')],
            ['name' => $post->title, 'url' => url("/blog/{$post->slug}")],
        ]);
    }

    /**
     * @param  list<string>  $segments
     */
    protected function resolveArticle(array $segments): ?array
    {
        $slug = $segments[1] ?? null;
        if (! $slug) {
            return null;
        }

        $article = GeneratedContent::query()->published()->where('slug', $slug)->first();

        if (! $article) {
            return null;
        }

        return $this->seoManager->buildPublicPayload($article, [
            ['name' => 'خانه', 'url' => url('/')],
            ['name' => 'مقالات', 'url' => url('/articles')],
            ['name' => $article->title, 'url' => url("/articles/{$article->slug}")],
        ]);
    }

    /**
     * @param  list<string>  $segments
     */
    protected function resolvePdf(array $segments): ?array
    {
        $id = $segments[1] ?? null;
        if (! $id || ! ctype_digit($id)) {
            return null;
        }

        $pdf = PdfProduct::query()->where('is_active', true)->find((int) $id);

        if (! $pdf) {
            return null;
        }

        return $this->seoManager->buildPublicPayload($pdf, [
            ['name' => 'خانه', 'url' => url('/')],
            ['name' => 'فروشگاه', 'url' => url('/pdfs')],
            ['name' => $pdf->title, 'url' => url("/pdfs/{$pdf->id}")],
        ]);
    }

    protected function resolveCmsPage(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        $page = CmsPage::query()->where('is_published', true)->where('slug', $slug)->first();

        if (! $page) {
            return null;
        }

        $url = in_array($page->slug, ['terms', 'privacy', 'about', 'contact'], true)
            ? url('/'.$page->slug)
            : url('/page/'.$page->slug);

        return $this->seoManager->buildPublicPayload($page, [
            ['name' => 'خانه', 'url' => url('/')],
            ['name' => $page->title, 'url' => $url],
        ]);
    }

    protected function resolveLegalPage(string $slug): ?array
    {
        $pages = LegalPages::defaults();
        $page = collect($pages)->firstWhere('slug', $slug);

        if (! $page) {
            $cms = CmsPage::query()->where('is_published', true)->where('slug', $slug)->first();
            if ($cms) {
                return $this->seoManager->buildPublicPayload($cms, [
                    ['name' => 'خانه', 'url' => url('/')],
                    ['name' => $cms->title, 'url' => url('/'.$slug)],
                ]);
            }

            return null;
        }

        return $this->seoManager->buildPagePayload(
            (string) $page['title'],
            mb_substr(strip_tags((string) $page['content']), 0, 160),
            url('/'.$slug),
            [
                ['name' => 'خانه', 'url' => url('/')],
                ['name' => (string) $page['title'], 'url' => url('/'.$slug)],
            ],
        );
    }
}
