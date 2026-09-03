<?php

use App\Models\BlogPost;
use App\Models\CmsPage;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\PdfProduct;
use App\Models\Question;

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
        Exam::class,
        JobPost::class,
        BlogPost::class,
        CmsPage::class,
        GeneratedContent::class,
        PdfProduct::class,
    ],

    'analyze_on_change_models' => [
        Exam::class,
        JobPost::class,
        BlogPost::class,
        CmsPage::class,
        GeneratedContent::class,
        PdfProduct::class,
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
        'exam' => Exam::class,
        'job' => JobPost::class,
        'article' => GeneratedContent::class,
        'blog' => BlogPost::class,
        'page' => CmsPage::class,
        'pdf' => PdfProduct::class,
        'category' => ExamCategory::class,
        'question' => Question::class,
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

    'list_pages' => [
        'jobs' => [
            'title' => 'آگهی‌های استخدامی | جاب‌آزمون',
            'description' => 'جدیدترین آگهی‌های استخدامی دولتی و خصوصی — بانک، نفت، آموزش و پرورش، شهرداری و شرکت‌های بورسی',
        ],
        'exams' => [
            'title' => 'آزمون‌های استخدامی آنلاین | جاب‌آزمون',
            'description' => 'آزمون‌های تمرینی و سنجش آمادگی استخدام — سوالات چندگزینه‌ای با پاسخنامه و تحلیل عملکرد',
        ],
        'blog' => [
            'title' => 'بلاگ استخدامی | جاب‌آزمون',
            'description' => 'مقالات و نکات آمادگی آزمون‌های استخدامی، مصاحبه شغلی و رزومه‌نویسی',
        ],
        'articles' => [
            'title' => 'مقالات استخدامی | جاب‌آزمون',
            'description' => 'راهنمای جامع آزمون‌های استخدامی، منابع مطالعه و استراتژی قبولی',
        ],
        'pdfs' => [
            'title' => 'فروشگاه جزوه و نمونه سوال | جاب‌آزمون',
            'description' => 'دانلود جزوه، نمونه سوال و منابع آموزشی آزمون‌های استخدامی',
        ],
    ],

    'automation' => [
        'auto_optimize_on_create' => env('SEO_AUTO_OPTIMIZE', true),
        'auto_optimize_min_score' => 75,
        'sitemap_invalidate_on_change' => true,
        'ping_search_engines' => env('SEO_PING_ENGINES', true),
        'ping_debounce_seconds' => 300,
        'ping_endpoints' => [
            'google' => 'https://www.google.com/ping?sitemap={url}',
            'bing' => 'https://www.bing.com/ping?sitemap={url}',
        ],
    ],

];
