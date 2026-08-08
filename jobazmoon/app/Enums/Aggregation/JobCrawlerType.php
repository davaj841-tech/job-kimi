<?php

namespace App\Enums\Aggregation;

enum JobCrawlerType: string
{
    case Api = 'api';
    case Rss = 'rss';
    case Json = 'json';
    case Html = 'html';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Api => 'API رسمی',
            self::Rss => 'RSS / Atom',
            self::Json => 'JSON عمومی',
            self::Html => 'HTML ساختاریافته',
            self::Custom => 'پارسر اختصاصی',
        };
    }
}
