<?php

namespace App\Services\Aggregation\Crawlers;

use App\Enums\Aggregation\JobCrawlerType;

class RssCrawler extends AbstractHttpCrawler
{
    protected function expectedType(): JobCrawlerType
    {
        return JobCrawlerType::Rss;
    }
}
