<?php

return [

    /* Safe production default: off until explicitly enabled */
    'enabled' => (bool) env('CONTENT_ENABLED', false),

    'daily_generation_enabled' => (bool) env('CONTENT_DAILY_GENERATION_ENABLED', false),

    'daily_generation_time' => env('CONTENT_DAILY_GENERATION_TIME', '09:00'),

    'timezone' => env('CONTENT_TIMEZONE', 'Asia/Tehran'),

    /*
    | draft | publish
    | draft: save GeneratedContent as draft (optional BlogPost draft mirror)
    | publish: mark GeneratedContent published (+ optional BlogPost published mirror)
    */
    'publish_mode' => env('CONTENT_PUBLISH_MODE', 'draft'),

    'minimum_content_length' => (int) env('CONTENT_MINIMUM_LENGTH', 280),

    'minimum_factual_score' => (int) env('CONTENT_MINIMUM_FACTUAL_SCORE', 3),

    'max_articles_per_day' => (int) env('CONTENT_MAX_ARTICLES_PER_DAY', 1),

    'lookback_days' => (int) env('CONTENT_LOOKBACK_DAYS', 14),

    'allowed_source_reliability' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CONTENT_ALLOWED_RELIABILITY', 'official,highly_trusted,trusted'))
    ))),

    'queue' => env('CONTENT_QUEUE', 'default'),

    /* Optional mirror into existing Laravel BlogPost table (not an external CMS) */
    'sync_to_blog' => (bool) env('CONTENT_SYNC_TO_BLOG', true),

    'blog_category' => env('CONTENT_BLOG_CATEGORY', 'استخدام'),

    'system_author_id' => (int) env('CONTENT_SYSTEM_AUTHOR_ID', 0),

];
