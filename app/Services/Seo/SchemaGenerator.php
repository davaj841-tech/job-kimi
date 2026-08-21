<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\CmsPage;
use App\Models\Exam;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class SchemaGenerator
{
    /**
     * @return list<array<string, mixed>>
     */
    public function generate(Model $model): array
    {
        $schemas = [];
        $schemas[] = $this->websiteSchema();

        if ($model instanceof JobPost) {
            $schemas[] = $this->jobPostingSchema($model);
        } elseif ($model instanceof BlogPost || $model instanceof GeneratedContent) {
            $schemas[] = $this->articleSchema($model);
        } elseif ($model instanceof Exam) {
            $schemas[] = $this->quizSchema($model);
        } elseif ($model instanceof CmsPage) {
            $schemas[] = $this->webPageSchema($model);
        }

        if (method_exists($model, 'seoFaqs') && $model->seoFaqs()->exists()) {
            $schemas[] = $this->faqSchema($model);
        }

        return $schemas;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function primarySchema(Model $model): ?array
    {
        $type = match (true) {
            $model instanceof JobPost => 'JobPosting',
            $model instanceof BlogPost, $model instanceof GeneratedContent => 'Article',
            $model instanceof Exam => 'Quiz',
            $model instanceof CmsPage => 'WebPage',
            default => null,
        };

        if (! $type) {
            return null;
        }

        /** @var array<string, mixed>|null $primary */
        $primary = collect($this->generate($model))->firstWhere('@type', $type);

        return $primary;
    }

    /**
     * @param  list<array{name: string, url: string}>|array<int, array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public function breadcrumbSchema(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn (array $item, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function websiteSchema(): array
    {
        $name = config('seo.schema.organization.name', config('seo.site_name'));

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $name,
            'url' => config('app.url'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function organizationSchema(): array
    {
        /** @var array<string, mixed> $org */
        $org = config('seo.schema.organization', []);
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $org['name'] ?? config('seo.site_name'),
            'url' => $org['url'] ?? config('app.url'),
        ];

        if (! empty($org['logo'])) {
            $schema['logo'] = URL::to((string) $org['logo']);
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    protected function jobPostingSchema(JobPost $job): array
    {
        $siteName = Setting::get('site_name', config('seo.site_name'));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $job->title,
            'description' => $job->description,
            'datePosted' => $job->created_at?->toIso8601String(),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => url('/jobs/'.$job->getKey()),
            ],
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => config('app.url'),
            ],
        ];

        if ($job->registration_deadline) {
            $schema['validThrough'] = $job->registration_deadline->toIso8601String();
        }

        if ($job->company_name) {
            $schema['hiringOrganization'] = ['@type' => 'Organization', 'name' => $job->company_name];
        }

        if ($job->province || $job->city) {
            $schema['jobLocation'] = [
                '@type' => 'Place',
                'address' => array_filter([
                    '@type' => 'PostalAddress',
                    'addressLocality' => $job->city,
                    'addressRegion' => $job->province,
                    'addressCountry' => 'IR',
                ]),
            ];
        }

        return array_merge($schema, $this->maybeFaqHasPart($job) ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function articleSchema(BlogPost|GeneratedContent $article): array
    {
        $siteName = Setting::get('site_name', config('seo.site_name'));
        $canonical = app(CanonicalService::class)->getCanonical($article);

        $publishedAt = $article instanceof GeneratedContent
            ? ($article->published_at ?? $article->created_at)
            : $article->created_at;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->title,
            'description' => $article->excerpt ?? $article->getAttribute('meta_description'),
            'datePublished' => $publishedAt?->toIso8601String(),
            'dateModified' => $article->updated_at?->toIso8601String(),
            'publisher' => ['@type' => 'Organization', 'name' => $siteName],
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => config('app.url'),
            ],
        ];

        if ($canonical) {
            $schema['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => $canonical];
        }

        if ($article instanceof BlogPost) {
            if ($article->featured_image) {
                $schema['image'] = URL::to($article->featured_image_url ?? $article->featured_image);
            }
            $schema['author'] = [
                '@type' => 'Person',
                'name' => ($article->creator !== null ? $article->creator->name : null) ?? $siteName,
            ];
        } else {
            $schema['author'] = ['@type' => 'Organization', 'name' => $siteName];
            if ($article->relationLoaded('jobPost') && $article->jobPost?->company_name) {
                $schema['about'] = $article->jobPost->company_name;
            }
        }

        return array_merge($schema, $this->maybeFaqHasPart($article) ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function quizSchema(Exam $exam): array
    {
        $siteName = Setting::get('site_name', config('seo.site_name'));

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Quiz',
            'name' => $exam->title,
            'description' => $exam->description,
            'educationalAlignment' => $exam->category?->name,
            'publisher' => ['@type' => 'Organization', 'name' => $siteName],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => url('/exams/'.$exam->slug),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function webPageSchema(CmsPage $page): array
    {
        $siteName = Setting::get('site_name', config('seo.site_name'));

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $page->title,
            'description' => $page->meta_description,
            'publisher' => ['@type' => 'Organization', 'name' => $siteName],
            'url' => app(CanonicalService::class)->getCanonical($page) ?? url('/page/'.$page->slug),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function faqSchema(Model $model): array
    {
        if (! method_exists($model, 'seoFaqs')) {
            return [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => [],
            ];
        }

        $faqs = $model->seoFaqs()->get();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq->answer],
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function maybeFaqHasPart(Model $model): ?array
    {
        if (! method_exists($model, 'seoFaqs')) {
            return null;
        }

        $faqs = $model->seoFaqs()->get(['question', 'answer']);
        if ($faqs->isEmpty()) {
            return null;
        }

        return [
            'hasPart' => [[
                '@type' => 'FAQPage',
                'mainEntity' => $faqs->map(fn ($faq) => [
                    '@type' => 'Question',
                    'name' => $faq->question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $faq->answer,
                    ],
                ])->all(),
            ]],
        ];
    }
}
