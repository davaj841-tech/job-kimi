<?php

namespace App\Services\Aggregation\Crawlers;

use App\Enums\Aggregation\JobCrawlerType;

class ApiCrawler extends AbstractHttpCrawler
{
    protected function expectedType(): JobCrawlerType
    {
        return JobCrawlerType::Api;
    }
}
