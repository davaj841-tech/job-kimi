<?php

namespace App\Services\Aggregation;

use App\Models\JobSource;
use App\Services\Aggregation\Support\DateNormalizer;
use App\Services\Aggregation\Support\PersianText;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fetches announcement detail pages and extracts form-relevant fields.
 */
class JobDetailPageExtractor
{
    public function __construct(
        protected SafeHttpFetcher $fetcher,
        protected DateNormalizer $dates = new DateNormalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function enrichFromUrl(array $normalized, JobSource $source): array
    {
        if (! config('aggregation.detail_fetch.enabled', true)) {
            return $normalized;
        }

        $url = $this->pickDetailUrl($normalized);
        if ($url === null) {
            return $normalized;
        }

        try {
            $response = $this->fetcher->get($url, $source);
            if (! $response->successful()) {
                return $normalized;
            }

            $html = (string) $response->body();
            if ($html === '') {
                return $normalized;
            }

            return $this->mergeExtracted($normalized, $html, $url);
        } catch (\Throwable $e) {
            Log::debug('JobDetailPageExtractor skipped', [
                'source_id' => $source->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $normalized;
        }
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    protected function pickDetailUrl(array $normalized): ?string
    {
        foreach (['source_url', 'registration_link'] as $key) {
            $url = $normalized[$key] ?? null;
            if (is_string($url) && str_starts_with(strtolower($url), 'http')) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    protected function mergeExtracted(array $normalized, string $html, string $url): array
    {
        $plain = $this->htmlToPlainText($html);
        if ($plain === '') {
            return $normalized;
        }

        $scanText = trim(implode("\n", array_filter([
            is_string($normalized['title'] ?? null) ? $normalized['title'] : null,
            $plain,
        ])));

        $extracted = $this->dates->extractFromText($scanText);
        foreach (['registration_deadline', 'registration_starts_at', 'exam_date'] as $field) {
            if (empty($normalized[$field]) && ! empty($extracted[$field])) {
                $normalized[$field] = $extracted[$field];
            }
        }

        if (empty($normalized['registration_deadline'])) {
            $normalized['registration_deadline'] = now()->addDays(
                (int) config('aggregation.detail_fetch.default_deadline_days', 45)
            )->endOfDay()->toDateTimeString();
        }

        if (empty($normalized['published_at'])) {
            $normalized['published_at'] = now()->toDateTimeString();
        }

        foreach ($this->extractStructuredFields($plain) as $field => $value) {
            if (empty($normalized[$field]) && filled($value)) {
                $normalized[$field] = $value;
            }
        }

        if (mb_strlen($plain) > mb_strlen(strip_tags((string) ($normalized['description'] ?? '')))) {
            $normalized['description'] = $this->sanitizeDescriptionHtml($html, $plain);
        }

        if (empty($normalized['source_url'])) {
            $normalized['source_url'] = $url;
        }

        return $normalized;
    }

    protected function htmlToPlainText(string $html): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return '';
        }

        // Preserve block boundaries before flattening text.
        foreach (['p', 'div', 'li', 'br', 'h1', 'h2', 'h3', 'h4', 'tr'] as $tag) {
            foreach ($dom->getElementsByTagName($tag) as $node) {
                if ($node instanceof \DOMElement && $tag !== 'br') {
                    $node->appendChild($dom->createTextNode("\n"));
                }
            }
        }

        foreach (['script', 'style', 'nav', 'header', 'footer', 'noscript'] as $tag) {
            while (($nodes = $dom->getElementsByTagName($tag))->length > 0) {
                $node = $nodes->item(0);
                $node?->parentNode?->removeChild($node);
            }
        }

        $xpath = new DOMXPath($dom);
        $candidates = [
            '//article',
            '//*[contains(@class,"content")]',
            '//*[contains(@class,"entry-content")]',
            '//*[contains(@class,"post-content")]',
            '//*[@id="content"]',
            '//main',
        ];

        $text = '';
        foreach ($candidates as $query) {
            $nodes = $xpath->query($query);
            if ($nodes !== false && $nodes->length > 0) {
                $text = trim(preg_replace('/\s+/u', ' ', $nodes->item(0)?->textContent ?? '') ?? '');
                if (mb_strlen($text) > 120) {
                    break;
                }
            }
        }

        if ($text === '') {
            $text = trim(preg_replace('/\s+/u', ' ', $dom->textContent ?? '') ?? '');
        }

        $max = (int) config('aggregation.detail_fetch.max_chars', 50_000);

        return Str::limit($text, $max, '');
    }

    /**
     * @return array<string, string>
     */
    protected function extractStructuredFields(string $plain): array
    {
        $fields = [];

        if (preg_match('/(?:حداقل\s*)?(?:مدرک|تحصیلات)[:\s\-–]+([\p{L}]+)/u', $plain, $m)) {
            $fields['education'] = trim($m[1]);
        }

        if (preg_match('/(?:رشته(?:\s*تحصیلی)?)[:\s\-–]+([\p{L}\s]{3,80}?)(?=\s*شرایط|\s*سابقه|\s*محل|$)/u', $plain, $m)) {
            $fields['field_of_study'] = trim($m[1]);
        }

        if (preg_match('/(?:سابقه\s*کار)[:\s\-–]+([\p{L}\s\d]{3,80})/u', $plain, $m)) {
            $fields['experience'] = trim($m[1]);
        }

        if (preg_match('/(?:شرایط\s*(?:احراز|شرکت|داوطلبان))[:\s\-–]*(.{10,800})/u', $plain, $m)) {
            $fields['requirements'] = trim($m[1]);
        } elseif (preg_match('/(داوطلبان\s*دارای.{10,500})/u', $plain, $m)) {
            $fields['requirements'] = trim($m[0]);
        }

        if (preg_match('/(?:محل\s*(?:خدمت|کار|امتحان))[:\s\-–]+([^\n\.؛]{2,80})/u', $plain, $m)) {
            $fields['city'] = trim($m[1]);
        }

        foreach ($fields as $key => $value) {
            $fields[$key] = mb_strlen($value) > 500 ? mb_substr($value, 0, 500) : $value;
        }

        return $fields;
    }

    protected function sanitizeDescriptionHtml(string $html, string $plainFallback): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return '<p>'.e($plainFallback).'</p>';
        }

        $xpath = new DOMXPath($dom);
        $node = null;
        foreach (['//article', '//*[contains(@class,"content")]', '//main', '//body'] as $query) {
            $nodes = $xpath->query($query);
            if ($nodes !== false && $nodes->length > 0) {
                $node = $nodes->item(0);
                break;
            }
        }

        if (! $node) {
            return '<p>'.e($plainFallback).'</p>';
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        $inner = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $inner) ?? $inner;
        $inner = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $inner) ?? $inner;

        if (mb_strlen(strip_tags($inner)) < 80) {
            return '<p>'.e($plainFallback).'</p>';
        }

        return Str::limit($inner, (int) config('aggregation.detail_fetch.max_description_chars', 80_000), '');
    }
}
