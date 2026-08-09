<?php

namespace App\Services\Aggregation\Parsers;

use App\Contracts\Aggregation\JobParserInterface;
use Illuminate\Support\Str;

class RssFeedParser implements JobParserInterface
{
    public function parserType(): string
    {
        return 'rss';
    }

    public function parse(mixed $payload, array $context = []): array
    {
        $xml = is_string($payload) ? $payload : '';
        if ($xml === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($feed === false) {
            return [];
        }

        $items = [];

        // RSS 2.0
        if (isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                $items[] = $this->mapItem($item, $context);
            }
        }

        // Atom
        if ($items === [] && isset($feed->entry)) {
            foreach ($feed->entry as $entry) {
                $link = '';
                if (isset($entry->link)) {
                    foreach ($entry->link as $l) {
                        $attrs = $l->attributes();
                        $href = (string) ($attrs['href'] ?? $l);
                        if ($href !== '') {
                            $link = $href;
                            break;
                        }
                    }
                }
                $items[] = [
                    'title' => trim((string) ($entry->title ?? '')),
                    'description' => trim(strip_tags((string) ($entry->summary ?? $entry->content ?? ''))),
                    'registration_link' => $link !== '' ? $link : null,
                    // Prefer the per-item link as listing URL; feed URL is crawl provenance only.
                    'source_url' => $link !== '' ? $link : null,
                    '_endpoint_url' => $context['endpoint_url'] ?? null,
                    'published_at' => (string) ($entry->updated ?? $entry->published ?? ''),
                    'company_name' => $context['source_name'] ?? null,
                    'external_id' => (string) ($entry->id ?? ($link !== '' ? $link : '')),
                ];
            }
        }

        return array_values(array_filter($items, fn ($row) => filled($row['title'] ?? null)));
    }

    protected function mapItem(\SimpleXMLElement $item, array $context): array
    {
        $link = trim((string) ($item->link ?? ''));
        $guid = trim((string) ($item->guid ?? $link));

        return [
            'title' => trim((string) ($item->title ?? '')),
            'description' => trim(strip_tags((string) ($item->description ?? ''))),
            'registration_link' => $link !== '' ? $link : null,
            'source_url' => $link !== '' ? $link : null,
            '_endpoint_url' => $context['endpoint_url'] ?? null,
            'published_at' => (string) ($item->pubDate ?? ''),
            'company_name' => $context['source_name'] ?? null,
            'external_id' => $guid !== '' ? $guid : null,
            'province' => null,
            'city' => null,
        ];
    }
}
