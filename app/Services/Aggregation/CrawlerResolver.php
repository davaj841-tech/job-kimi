<?php

namespace App\Services\Aggregation;

use App\Contracts\Aggregation\JobSourceCrawlerInterface;
use App\Enums\Aggregation\JobCrawlerType;
use App\Models\JobSource;
use App\Services\Aggregation\Crawlers\ApiCrawler;
use App\Services\Aggregation\Crawlers\HtmlCrawler;
use App\Services\Aggregation\Crawlers\JsonCrawler;
use App\Services\Aggregation\Crawlers\RssCrawler;
use App\Services\Aggregation\Parsers\BoardListingHtmlParser;
use App\Services\Aggregation\Parsers\EmploymentKeywordRssParser;
use App\Services\Aggregation\Parsers\HtmlJobParser;
use App\Services\Aggregation\Parsers\JsonFeedParser;
use App\Services\Aggregation\Parsers\OfficialAnnouncementHtmlParser;
use App\Services\Aggregation\Parsers\RssFeedParser;
use App\Services\Aggregation\Parsers\SourceParserRegistry;
use RuntimeException;

class CrawlerResolver
{
    /** @param  list<JobSourceCrawlerInterface>  $crawlers */
    public function __construct(
        protected array $crawlers = []
    ) {}

    public static function makeDefault(
        SafeHttpFetcher $fetcher,
        ?SourceParserRegistry $registry = null,
    ): self {
        $rss = app(RssFeedParser::class);
        $json = app(JsonFeedParser::class);
        $html = app(HtmlJobParser::class);
        $registry ??= new SourceParserRegistry([
            app(EmploymentKeywordRssParser::class),
            app(OfficialAnnouncementHtmlParser::class),
            app(BoardListingHtmlParser::class),
        ]);

        return new self([
            new RssCrawler($fetcher, $rss, $json, $html, $registry),
            new JsonCrawler($fetcher, $rss, $json, $html, $registry),
            new ApiCrawler($fetcher, $rss, $json, $html, $registry),
            new HtmlCrawler($fetcher, $rss, $json, $html, $registry),
        ]);
    }

    public function resolve(JobSource $source): JobSourceCrawlerInterface
    {
        foreach ($this->crawlers as $crawler) {
            if ($crawler->supports($source)) {
                return $crawler;
            }
        }

        if ($source->crawler_type === JobCrawlerType::Custom) {
            foreach ($this->crawlers as $crawler) {
                if ($crawler instanceof HtmlCrawler) {
                    return $crawler;
                }
            }
        }

        throw new RuntimeException('No crawler supports type: '.($source->crawler_type !== null ? $source->crawler_type->value : 'null'));
    }
}
