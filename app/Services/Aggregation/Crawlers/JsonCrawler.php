<?php

namespace App\Services\Aggregation\Crawlers;

use App\Enums\Aggregation\JobCrawlerType;

class JsonCrawler extends AbstractHttpCrawler
{
    protected function expectedType(): JobCrawlerType
    {
        return JobCrawlerType::Json;
    }
}
