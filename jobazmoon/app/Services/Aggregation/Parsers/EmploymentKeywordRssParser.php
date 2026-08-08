<?php

namespace App\Services\Aggregation\Parsers;

use App\Contracts\Aggregation\JobParserInterface;
use Illuminate\Support\Str;

/**
 * Official RSS feeds that mix news + occasional recruitment notices.
 * Only items whose title matches employment keywords are returned.
 * Description-only hits are ignored to avoid importing general news.
 */
class EmploymentKeywordRssParser implements JobParserInterface
{
    public function __construct(
        protected RssFeedParser $rss = new RssFeedParser,
    ) {}

    public function parserType(): string
    {
        return 'employment_keyword_rss';
    }

    public function parse(mixed $payload, array $context = []): array
    {
        $items = $this->rss->parse($payload, $context);
        $keywords = config('aggregation.employment_keywords', []);

        return array_values(array_filter($items, function (array $item) use ($keywords) {
            $title = (string) ($item['title'] ?? '');

            return $this->matchesKeywords($title, $keywords);
        }));
    }

    /**
     * @param  list<string>  $keywords
     */
    protected function matchesKeywords(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            $keyword = (string) $keyword;
            if ($keyword !== '' && mb_stripos($text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
