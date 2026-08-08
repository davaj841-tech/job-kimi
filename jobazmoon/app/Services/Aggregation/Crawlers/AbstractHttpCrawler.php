<?php

namespace App\Services\Aggregation\Crawlers;

use App\Contracts\Aggregation\JobSourceCrawlerInterface;
use App\Enums\Aggregation\JobCrawlerType;
use App\Enums\Aggregation\JobEndpointType;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use App\Services\Aggregation\Parsers\HtmlJobParser;
use App\Services\Aggregation\Parsers\JsonFeedParser;
use App\Services\Aggregation\Parsers\RssFeedParser;
use App\Services\Aggregation\Parsers\SourceParserRegistry;
use App\Services\Aggregation\SafeHttpFetcher;
use RuntimeException;
use Throwable;

abstract class AbstractHttpCrawler implements JobSourceCrawlerInterface
{
    public function __construct(
        protected SafeHttpFetcher $fetcher,
        protected RssFeedParser $rssParser,
        protected JsonFeedParser $jsonParser,
        protected HtmlJobParser $htmlParser,
        protected ?SourceParserRegistry $sourceParsers = null,
    ) {}

    abstract protected function expectedType(): JobCrawlerType;

    public function supports(JobSource $source): bool
    {
        return $source->crawler_type === $this->expectedType();
    }

    public function crawl(JobSource $source, ?JobSourceEndpoint $endpoint = null): array
    {
        if (! $source->is_enabled || ! $source->is_approved) {
            throw new RuntimeException('Source is not enabled and approved.');
        }

        $endpoints = $endpoint
            ? collect([$endpoint])
            : $source->endpoints()->enabled()->orderBy('sort_order')->orderBy('id')->get();

        if ($endpoints->isEmpty()) {
            $fallbackUrl = $source->official_url;
            if (! filled($fallbackUrl)) {
                return [];
            }
            $endpoints = collect([
                new JobSourceEndpoint([
                    'job_source_id' => $source->id,
                    'url' => $fallbackUrl,
                    'endpoint_type' => $this->defaultEndpointType(),
                    'http_method' => 'GET',
                    'is_enabled' => true,
                ]),
            ]);
        }

        $all = [];
        foreach ($endpoints as $ep) {
            try {
                $all = array_merge($all, $this->crawlEndpoint($source, $ep));
            } catch (Throwable $e) {
                // Re-throw so orchestrator records crawl_failed for this source.
                // Other sources remain unaffected because dispatch/pilot loops per source.
                throw $e;
            }
        }

        return $all;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function crawlEndpoint(JobSource $source, JobSourceEndpoint $endpoint): array
    {
        if (strtoupper((string) $endpoint->http_method) !== 'GET') {
            throw new RuntimeException('Only GET endpoints are supported.');
        }

        $response = $this->fetcher->get($endpoint->url, $source);
        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().' for '.$endpoint->url);
        }

        $context = [
            'source_name' => $source->name,
            'endpoint_url' => $endpoint->url,
            'source_id' => $source->id,
            'source_slug' => $source->slug,
            'http_status' => $response->status(),
        ];

        $items = $this->parsePayload($endpoint, $response->body(), $response->json(), $context);

        return array_map(function (array $item) use ($source, $endpoint, $response) {
            $item['job_source_id'] = $source->id;
            $item['_endpoint_url'] = $item['_endpoint_url'] ?? $endpoint->url;
            $item['_http_status'] = $response->status();

            return $item;
        }, $items);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    protected function parsePayload(JobSourceEndpoint $endpoint, string $body, mixed $json, array $context): array
    {
        $specific = $this->sourceParsers?->get($endpoint->parser_type);
        if ($specific !== null) {
            try {
                return $specific->parse(
                    in_array($endpoint->parser_type, ['employment_keyword_rss'], true) ? $body : ($json ?? $body),
                    $context
                );
            } catch (Throwable $e) {
                throw new RuntimeException(
                    'Source-specific parser ['.$endpoint->parser_type.'] failed: '.$e->getMessage(),
                    0,
                    $e
                );
            }
        }

        $type = $endpoint->endpoint_type?->value
            ?? $endpoint->parser_type
            ?? $this->expectedType()->value;

        return match ($type) {
            'rss', 'atom', 'sitemap' => $this->rssParser->parse($body, $context),
            'json', 'api' => $this->jsonParser->parse($json ?? $body, $context),
            default => $this->htmlParser->parse($body, $context),
        };
    }

    protected function defaultEndpointType(): JobEndpointType
    {
        return match ($this->expectedType()) {
            JobCrawlerType::Rss => JobEndpointType::Rss,
            JobCrawlerType::Json, JobCrawlerType::Api => JobEndpointType::Json,
            default => JobEndpointType::Html,
        };
    }
}
