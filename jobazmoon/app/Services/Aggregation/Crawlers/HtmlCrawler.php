<?php

namespace App\Services\Aggregation\Crawlers;

use App\Enums\Aggregation\JobCrawlerType;

class HtmlCrawler extends AbstractHttpCrawler
{
    protected function expectedType(): JobCrawlerType
    {
        return JobCrawlerType::Html;
    }
}
