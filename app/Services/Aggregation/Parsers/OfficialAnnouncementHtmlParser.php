<?php

namespace App\Services\Aggregation\Parsers;

use App\Contracts\Aggregation\JobParserInterface;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Conservative official-site announcement parser.
 * Collects only hyperlinks whose visible text matches employment keywords.
 * Not unrestricted HTML scraping.
 */
class OfficialAnnouncementHtmlParser implements JobParserInterface
{
    public function parserType(): string
    {
        return 'official_announcement_html';
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
        $keywords = config('aggregation.employment_keywords', []);
        $base = $context['endpoint_url'] ?? null;
        $seen = [];
        $items = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $anchor) {
            $title = trim(preg_replace('/\s+/u', ' ', $anchor->textContent ?? '') ?? '');
            $href = trim((string) $anchor->getAttribute('href'));
            if ($title === '' || $href === '' || str_starts_with($href, '#')) {
                continue;
            }

            if (! $this->matchesKeywords($title, $keywords)) {
                continue;
            }

            $url = $this->absolutize($href, $base);
            if ($url === null) {
                continue;
            }

            $key = mb_strtolower($title).'|'.$url;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $items[] = [
                'title' => Str::limit($title, 255, ''),
                'description' => $title,
                'company_name' => $context['source_name'] ?? null,
                'registration_link' => $url,
                'source_url' => $url,
                '_endpoint_url' => $base,
                'external_id' => hash('sha256', $url),
                'published_at' => null,
            ];
        }

        return $items;
    }

    /**
     * @param  list<string>  $keywords
     */
    protected function matchesKeywords(string $title, array $keywords): bool
    {
        $haystack = Str::lower($title);
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($haystack, Str::lower((string) $keyword))) {
                return true;
            }
        }

        return false;
    }

    protected function absolutize(string $href, ?string $base): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        if ($base === null || $base === '') {
            return null;
        }

        $parts = parse_url($base);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];
        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        if (str_starts_with($href, '//')) {
            return $parts['scheme'].':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $path = $parts['path'] ?? '/';
        $dir = rtrim(Str::beforeLast($path, '/'), '/');

        return $origin.$dir.'/'.$href;
    }
}
