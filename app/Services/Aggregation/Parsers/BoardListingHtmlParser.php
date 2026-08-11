<?php

namespace App\Services\Aggregation\Parsers;

use App\Contracts\Aggregation\JobParserInterface;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Listing parser for commercial job boards (ایران‌استخدام / ای‌استخدام).
 * Keeps only government / reputable-organization announcements, then
 * rewrites payload for JobAzmoon (pending review via JobPublisher).
 */
class BoardListingHtmlParser implements JobParserInterface
{
    public function parserType(): string
    {
        return 'board_listing_html';
    }

    public function parse(mixed $payload, array $context = []): array
    {
        $html = is_string($payload) ? $payload : '';
        if ($html === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $base = $context['endpoint_url'] ?? null;
        $sourceName = (string) ($context['source_name'] ?? 'منبع استخدام');
        $seen = [];
        $items = [];
        $max = (int) config('aggregation.board_listing.max_items', 80);

        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            if (count($items) >= $max) {
                break;
            }

            $title = trim(preg_replace('/\s+/u', ' ', $anchor->textContent ?? '') ?? '');
            $href = trim((string) $anchor->getAttribute('href'));
            if ($title === '' || $href === '' || str_starts_with($href, '#')) {
                continue;
            }

            if (mb_strlen($title) < 12 || mb_strlen($title) > 220) {
                continue;
            }

            if (! $this->looksLikeJobTitle($title)) {
                continue;
            }

            if (! $this->isTrustedListing($title)) {
                continue;
            }

            $url = $this->absolutize($href, $base);
            if ($url === null || $this->isNoiseUrl($url)) {
                continue;
            }

            $key = mb_strtolower($title).'|'.$url;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $cleanTitle = $this->rewriteTitle($title);
            $company = $this->extractCompany($cleanTitle) ?: $sourceName;

            $items[] = [
                'title' => Str::limit($cleanTitle, 255, ''),
                'company_name' => Str::limit($company, 190, ''),
                'description' => $this->buildDescription($cleanTitle, $company, $sourceName, $url),
                'job_category' => $this->detectCategory($cleanTitle),
                'registration_link' => $url,
                'source_url' => $url,
                '_endpoint_url' => $base,
                'external_id' => 'board:'.hash('sha256', $url),
                'published_at' => null,
            ];
        }

        return $items;
    }

    protected function looksLikeJobTitle(string $title): bool
    {
        $haystack = Str::lower($title);
        foreach (config('aggregation.employment_keywords', []) as $keyword) {
            if ($keyword !== '' && str_contains($haystack, Str::lower((string) $keyword))) {
                return true;
            }
        }

        return str_contains($haystack, 'استخدام');
    }

    protected function isTrustedListing(string $title): bool
    {
        $haystack = Str::lower($title);
        foreach (config('aggregation.board_listing.trust_keywords', []) as $keyword) {
            if ($keyword !== '' && str_contains($haystack, Str::lower((string) $keyword))) {
                return true;
            }
        }

        return false;
    }

    protected function isNoiseUrl(string $url): bool
    {
        $path = Str::lower((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        foreach (config('aggregation.board_listing.skip_path_contains', []) as $needle) {
            if ($needle !== '' && str_contains($path, Str::lower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    protected function rewriteTitle(string $title): string
    {
        $clean = preg_replace('/\s+/u', ' ', $title) ?? $title;
        $clean = preg_replace('/(خبر جدید|در حال ثبت نام|در انتظار دریافت کارت|اعلام نتایج|مراحل استخدام|جدید)$/u', '', $clean) ?? $clean;

        return trim($clean);
    }

    protected function extractCompany(string $title): ?string
    {
        if (preg_match('/^استخدام\s+(?:در\s+)?(.+)$/u', $title, $m)) {
            $name = trim($m[1]);
            $name = preg_replace('/\s*[-–|].*$/u', '', $name) ?? $name;

            return $name !== '' ? Str::limit(trim($name), 120, '') : null;
        }

        if (preg_match('/آزمون\s+استخدامی\s+(.+)$/u', $title, $m)) {
            return Str::limit(trim($m[1]), 120, '');
        }

        return null;
    }

    protected function detectCategory(string $title): string
    {
        $haystack = Str::lower($title);
        if (str_contains($haystack, 'بانک')) {
            return 'بانک';
        }
        if (str_contains($haystack, 'آموزش') || str_contains($haystack, 'پرورش')) {
            return 'آموزش و پرورش';
        }
        if (str_contains($haystack, 'وزارت') || str_contains($haystack, 'دستگاه') || str_contains($haystack, 'دولت')) {
            return 'دولتی';
        }

        return 'سازمان‌ها و شرکت‌های معتبر';
    }

    protected function buildDescription(string $title, string $company, string $sourceName, string $url): string
    {
        return implode("\n", [
            $title,
            '',
            'سازمان / شرکت: '.$company,
            'دسته‌بندی: آگهی دولتی یا شرکت معتبر',
            '',
            'جزئیات و ثبت‌نام از طریق لینک منبع قابل مشاهده است.',
            'منبع گردآوری: '.$sourceName,
            'لینک منبع: '.$url,
            '',
            'این آگهی پس از بررسی در جاب‌آزمون منتشر می‌شود.',
        ]);
    }

    protected function absolutize(string $href, ?string $base): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        if ($base === null || $base === '') {
            return null;
        }

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$href;
        }

        $parts = parse_url($base);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $dir = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $origin.$dir.'/'.$href;
    }
}
