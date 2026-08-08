<?php

namespace App\Enums\Aggregation;

enum JobEndpointType: string
{
    case Api = 'api';
    case Rss = 'rss';
    case Json = 'json';
    case Html = 'html';
    case Sitemap = 'sitemap';

    public function label(): string
    {
        return match ($this) {
            self::Api => 'API',
            self::Rss => 'RSS',
            self::Json => 'JSON',
            self::Html => 'HTML',
            self::Sitemap => 'Sitemap',
        };
    }
}
