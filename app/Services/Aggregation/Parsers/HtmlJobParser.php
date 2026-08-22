<?php

namespace App\Services\Aggregation\Parsers;

use App\Contracts\Aggregation\JobParserInterface;

/**
 * Minimal HTML parser: JSON-LD JobPosting + basic OpenGraph fallbacks.
 * No AI. No unrestricted scraping of arbitrary DOM.
 */
class HtmlJobParser implements JobParserInterface
{
    public function __construct(
        protected JsonFeedParser $jsonFeedParser = new JsonFeedParser
    ) {}

    public function parserType(): string
    {
        return 'html';
    }

    public function parse(mixed $payload, array $context = []): array
    {
        $html = is_string($payload) ? $payload : '';
        if ($html === '') {
            return [];
        }

        $items = $this->parseJsonLd($html, $context);

        if ($items === []) {
            $items = $this->parseEmbeddedJson($html, $context);
        }

        if ($items === []) {
            $og = $this->parseOpenGraph($html, $context);
            if ($og !== null) {
                $items[] = $og;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    protected function parseJsonLd(string $html, array $context): array
    {
        if (! preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            return [];
        }

        $items = [];
        foreach ($matches[1] as $json) {
            $decoded = json_decode(html_entity_decode(trim($json)), true);
            if (! is_array($decoded)) {
                continue;
            }
            $nodes = array_is_list($decoded) ? $decoded : [$decoded];
            if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                $nodes = $decoded['@graph'];
            }
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $type = $node['@type'] ?? null;
                $types = is_array($type) ? $type : [$type];
                if (! in_array('JobPosting', $types, true)) {
                    continue;
                }
                $apply = $node['applicationContact']['url'] ?? $node['url'] ?? null;
                $items[] = [
                    'title' => (string) ($node['title'] ?? ''),
                    'description' => trim(strip_tags((string) ($node['description'] ?? ''))),
                    'company_name' => $node['hiringOrganization']['name']
                        ?? $context['source_name']
                        ?? null,
                    'city' => $node['jobLocation']['address']['addressLocality'] ?? null,
                    'province' => $node['jobLocation']['address']['addressRegion'] ?? null,
                    'employment_type' => $node['employmentType'] ?? null,
                    'education' => $node['educationRequirements'] ?? null,
                    'experience' => $node['experienceRequirements'] ?? null,
                    'registration_link' => $apply,
                    'source_url' => $node['url'] ?? null,
                    '_endpoint_url' => $context['endpoint_url'] ?? null,
                    'registration_deadline' => $node['validThrough'] ?? null,
                    'published_at' => $node['datePosted'] ?? null,
                    'external_id' => $node['identifier'] ?? $node['url'] ?? null,
                ];
            }
        }

        return array_values(array_filter($items, fn ($row) => filled($row['title'])));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    protected function parseEmbeddedJson(string $html, array $context): array
    {
        if (! preg_match('/<script[^>]+id=["\']job-feed["\'][^>]*>(.*?)<\/script>/is', $html, $m)) {
            return [];
        }

        return $this->jsonFeedParser->parse($m[1], $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    protected function parseOpenGraph(string $html, array $context): ?array
    {
        $title = $this->metaContent($html, 'og:title') ?: $this->metaContent($html, 'twitter:title');
        $desc = $this->metaContent($html, 'og:description') ?: $this->metaContent($html, 'description');
        $url = $this->metaContent($html, 'og:url') ?: ($context['endpoint_url'] ?? null);

        if (! filled($title)) {
            return null;
        }

        return [
            'title' => $title,
            'description' => $desc ?? '',
            'company_name' => $context['source_name'] ?? null,
            'registration_link' => $url,
            'source_url' => $url,
            '_endpoint_url' => $context['endpoint_url'] ?? null,
            'external_id' => $url,
        ];
    }

    protected function metaContent(string $html, string $property): ?string
    {
        $patterns = [
            '/<meta[^>]+property=["\']'.preg_quote($property, '/').'["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']'.preg_quote($property, '/').'["\']/i',
            '/<meta[^>]+name=["\']'.preg_quote($property, '/').'["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']'.preg_quote($property, '/').'["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                return html_entity_decode(trim($m[1]));
            }
        }

        return null;
    }
}
