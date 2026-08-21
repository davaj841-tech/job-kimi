<?php

return [

    'default_title' => env('SEO_DEFAULT_TITLE', 'جاب‌آزمون — آمادگی آزمون‌های استخدامی'),

    'default_description' => env('SEO_DEFAULT_DESCRIPTION', 'پلتفرم آمادگی آزمون‌های استخدامی، آگهی شغلی و منابع آموزشی'),

    'site_name' => env('APP_NAME', 'جاب‌آزمون'),

    'score_weights' => [
        'title' => 15,
        'description' => 12,
        'h1' => 10,
        'keyword_in_title' => 10,
        'keyword_in_description' => 8,
        'keyword_in_content' => 8,
        'content_length' => 10,
        'images' => 7,
        'internal_links' => 8,
        'schema' => 7,
        'canonical' => 5,
    ],

    'duplicate_threshold' => 70, // percent similarity

    'duplicate_audit_batch' => 50,

    'duplicate_compare_limit' => 100,

    'duplicate_audit_models' => [
        \App\Models\Exam::class,
        \App\Models\JobPost::class,
        \App\Models\BlogPost::class,
        \App\Models\CmsPage::class,
        \App\Models\GeneratedContent::class,
        \App\Models\PdfProduct::class,
    ],

    'analyze_on_change_models' => [
        \App\Models\Exam::class,
        \App\Models\JobPost::class,
        \App\Models\BlogPost::class,
        \App\Models\CmsPage::class,
        \App\Models\GeneratedContent::class,
        \App\Models\PdfProduct::class,
    ],

    'cannibalization_threshold' => 2, // max pages with same focus keyword

    'keyword_density' => [
        'min' => 0.5, // percent
        'max' => 3.0, // percent (above = stuffing)
    ],

    'content_length' => [
        'min' => 300, // minimum words for good score
        'ideal' => 800, // ideal word count
    ],

    'sitemap' => [
        'cache_ttl' => 3600, // seconds
        'max_urls_per_file' => 5000,
    ],

    'robots_defaults' => [
        'index' => true,
        'follow' => true,
    ],

    'noindex_patterns' => [
        'admin/*',
        'filament/*',
        'api/*',
        'install/*',
    ],

    'seoable_models' => [
        'exam' => \App\Models\Exam::class,
        'job' => \App\Models\JobPost::class,
        'article' => \App\Models\GeneratedContent::class,
        'blog' => \App\Models\BlogPost::class,
        'page' => \App\Models\CmsPage::class,
        'pdf' => \App\Models\PdfProduct::class,
        'category' => \App\Models\ExamCategory::class,
        'question' => \App\Models\Question::class,
    ],

    'schema' => [
        'organization' => [
            'name' => env('SEO_ORG_NAME', 'جاب‌آزمون'),
            'url' => env('APP_URL', 'https://jobazmoon.ir'),
            'logo' => env('SEO_ORG_LOGO'),
        ],
    ],

    'broken_links' => [
        'check_interval_hours' => 24,
        'timeout_seconds' => 10,
        'max_concurrent' => 5,
    ],

    'redirects' => [
        'allowed_status_codes' => [301, 302, 410],
        'max_chain_depth' => 10,
    ],

];
