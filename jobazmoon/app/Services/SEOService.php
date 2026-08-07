<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\JobPost;
use App\Models\Setting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class SEOService
{
    public function generateJobPostSchema(JobPost $job): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $job->title,
            'description' => $job->description,
            'datePosted' => optional($job->created_at)->toIso8601String(),
            'validThrough' => optional($job->registration_deadline)->toIso8601String(),
            'hiringOrganization' => [
                '@type' => 'Organization',
                'name' => $job->company_name,
            ],
            'jobLocation' => [
                '@type' => 'Place',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $job->city,
                    'addressRegion' => $job->province,
                    'addressCountry' => 'IR',
                ],
            ],
        ];
    }

    public function generateBlogSchema(BlogPost $post): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->excerpt ?? $post->meta_description,
            'image' => $post->featured_image ? URL::to($post->featured_image) : null,
            'datePublished' => optional($post->created_at)->toIso8601String(),
            'dateModified' => optional($post->updated_at)->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $post->creator?->name ?? Setting::get('site_name', 'JobAzmoon'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => Setting::get('site_name', 'JobAzmoon'),
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => url('/blog/'.$post->slug),
            ],
        ];
    }

    public function generateBreadcrumbSchema(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(function ($item, $index) {
                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ];
            })->all(),
        ];
    }

    public function generateSitemap(): Response
    {
        $jobs = JobPost::query()
            ->where('status', 'approved')
            ->orderByDesc('updated_at')
            ->get(['id', 'updated_at']);

        $posts = BlogPost::query()
            ->where('status', 'published')
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        $xml .= $this->urlEntry(url('/'), now()->toAtomString(), 'daily', '1.0');
        $xml .= $this->urlEntry(url('/jobs'), now()->toAtomString(), 'daily', '0.9');
        $xml .= $this->urlEntry(url('/blog'), now()->toAtomString(), 'daily', '0.9');
        $xml .= $this->urlEntry(url('/exams'), now()->toAtomString(), 'daily', '0.9');

        foreach ($jobs as $job) {
            $xml .= $this->urlEntry(
                url('/jobs/'.$job->id),
                optional($job->updated_at)->toAtomString() ?? now()->toAtomString(),
                'weekly',
                '0.8'
            );
        }

        foreach ($posts as $post) {
            $xml .= $this->urlEntry(
                url('/blog/'.$post->slug),
                optional($post->updated_at)->toAtomString() ?? now()->toAtomString(),
                'weekly',
                '0.7'
            );
        }

        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    protected function urlEntry(string $loc, string $lastmod, string $changefreq, string $priority): string
    {
        return "  <url>\n"
            .'    <loc>'.e($loc)."</loc>\n"
            .'    <lastmod>'.e($lastmod)."</lastmod>\n"
            .'    <changefreq>'.e($changefreq)."</changefreq>\n"
            .'    <priority>'.e($priority)."</priority>\n"
            ."  </url>\n";
    }
}
