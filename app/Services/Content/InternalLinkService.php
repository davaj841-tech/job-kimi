<?php

namespace App\Services\Content;

use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Services\Aggregation\SafeHttpFetcher;
use Illuminate\Support\Str;

class InternalLinkService
{
    public function __construct(
        protected SafeHttpFetcher $httpGuard,
    ) {}

    /**
     * Append a small, natural "related links" block (not spammy).
     */
    public function appendLinks(string $html, JobPost $job): string
    {
        $links = [];

        $apply = $this->safeUrl($job->registration_link);
        $source = $this->safeUrl($job->source_url);

        if ($apply !== '') {
            $links[] = '<li><a href="'.$this->e($apply).'" rel="noopener noreferrer" target="_blank">لینک ثبت‌نام / مشاهده آگهی</a></li>';
        } elseif ($source !== '') {
            $links[] = '<li><a href="'.$this->e($source).'" rel="noopener noreferrer" target="_blank">مشاهده منبع رسمی آگهی</a></li>';
        }

        if ($job->id) {
            $links[] = '<li><a href="'.e(url('/jobs/'.(int) $job->id)).'">جزئیات آگهی در جاب‌آزمون</a></li>';
        }

        $related = JobPost::query()
            ->where('status', 'approved')
            ->where('id', '!=', $job->id)
            ->when($job->job_classification_id, fn ($q) => $q->where('job_classification_id', $job->job_classification_id))
            ->latest('id')
            ->limit(2)
            ->get(['id', 'title']);

        foreach ($related as $rel) {
            $links[] = '<li><a href="'.e(url('/jobs/'.(int) $rel->id)).'">'.$this->e(Str::limit((string) $rel->title, 70)).'</a></li>';
        }

        $relatedArticles = GeneratedContent::query()
            ->published()
            ->where('job_post_id', '!=', $job->id)
            ->when($job->company_name, fn ($q) => $q->whereHas('jobPost', fn ($j) => $j->where('company_name', $job->company_name)))
            ->latest('published_at')
            ->limit(1)
            ->get(['id', 'title', 'slug']);

        foreach ($relatedArticles as $article) {
            $links[] = '<li><a href="'.e(url('/articles/'.$article->slug)).'">'.$this->e(Str::limit((string) $article->title, 70)).'</a></li>';
        }

        if ($links === []) {
            return $html;
        }

        $block = '<h3>لینک‌های مرتبط</h3><ul>'.implode('', array_slice($links, 0, 4)).'</ul>';

        return rtrim($html)."\n\n".$block;
    }

    protected function safeUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return '';
        }
        $parts = parse_url($url);
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        $host = $parts['host'] ?? null;
        if (! is_string($host) || $host === '') {
            return '';
        }
        if ($this->httpGuard->isBlockedHost($host)) {
            return '';
        }

        return Str::limit($url, 500, '');
    }

    protected function e(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
