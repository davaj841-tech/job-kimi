<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aggregator queue name
    |--------------------------------------------------------------------------
    */
    'queue' => env('AGGREGATION_QUEUE', 'crawlers'),

    /*
    |--------------------------------------------------------------------------
    | Legacy AI CrawlJobsJob (do not remove in Phase 10)
    |--------------------------------------------------------------------------
    |
    | Legacy daily CrawlJobsJob bypasses SafeHttpFetcher. Keep scheduled only
    | when explicitly enabled. Prefer the official aggregation pipeline.
    |
    */
    'enable_legacy_crawl_jobs_schedule' => (bool) env('AGGREGATION_ENABLE_LEGACY_CRAWL_JOB', false),

    /*
    |--------------------------------------------------------------------------
    | HTTP fetcher limits
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout_seconds' => (int) env('AGGREGATION_HTTP_TIMEOUT', 30),
        'max_bytes' => (int) env('AGGREGATION_HTTP_MAX_BYTES', 2_000_000),
        'max_redirects' => (int) env('AGGREGATION_HTTP_MAX_REDIRECTS', 3),
        'endpoint_delay_ms' => (int) env('AGGREGATION_HTTP_ENDPOINT_DELAY_MS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stuck crawler-run TTL (minutes)
    |--------------------------------------------------------------------------
    |
    | Runs still marked "running" older than this are ignored by busy-source
    | detection so a crashed worker cannot permanently block a source.
    |
    */
    'stuck_run_minutes' => (int) env('AGGREGATION_STUCK_RUN_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Schedule defaults (admin-overridable via settings.aggregation_schedule)
    |--------------------------------------------------------------------------
    |
    | Actual times live in the settings table / admin panel.
    | schedule_timezone is independent of APP_TIMEZONE.
    | Laravel must run: * * * * * php artisan schedule:run
    |
    */
    'schedule' => [
        'timezone_default' => env('AGGREGATION_SCHEDULE_TIMEZONE', 'Asia/Tehran'),
        'max_times' => 24,
        'max_concurrent_default' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Source health (Phase 9)
    |--------------------------------------------------------------------------
    |
    | Automatic transitions never set is_approved=true and never leave
    | manual_only without an administrator changing quality_status.
    |
    */
    'health' => [
        'consecutive_failure_threshold' => (int) env('AGGREGATION_HEALTH_FAILURE_THRESHOLD', 3),
        'consecutive_empty_warning' => (int) env('AGGREGATION_HEALTH_EMPTY_WARNING', 5),
        'high_rejection_rate' => (float) env('AGGREGATION_HEALTH_REJECTION_RATE', 0.8),
        'high_rejection_min_found' => (int) env('AGGREGATION_HEALTH_REJECTION_MIN', 3),
        'stale_success_days' => (int) env('AGGREGATION_HEALTH_STALE_DAYS', 7),
        // Backoff minutes by consecutive failure count (index 0 unused).
        'backoff_minutes' => [0, 30, 120, 360, 1440],
    ],

    /*
    |--------------------------------------------------------------------------
    | Official employment sources (Phases 5–8)
    |--------------------------------------------------------------------------
    |
    | Database-driven via PilotJobSourceSeeder / OfficialJobSourceSeeder.
    | Do NOT hard-code these URLs inside crawler classes.
    | Reliability > quantity. quality_status is Phase 8 classification.
    |
    | quality_status:
    |   active | limited | temporarily_unavailable | manual_only
    |
    */
    'official_sources' => [

        // ── Priority 1: exam / recruitment authorities ──────────────────────
        [
            'slug' => 'sanjesh-org',
            'name' => 'سازمان سنجش آموزش کشور',
            'official_url' => 'https://www.sanjesh.org/',
            'domain' => 'sanjesh.org',
            'source_type' => 'exam_authority',
            'reliability_level' => 'official',
            'priority' => 1,
            'is_enabled' => true,
            'is_approved' => true,
            'quality_status' => 'temporarily_unavailable',
            'crawler_type' => 'html',
            'crawl_frequency' => 'daily',
            'schedule_mode' => 'global',
            'notes' => 'Official exam authority. Phase 8 probe: HTTP 403 from aggregator environment.',
            'quality_notes' => 'Phase 8 (2026-08-08): homepage returned 403 Forbidden. Auto-crawl disabled; keep for revalidation.',
            'endpoints' => [
                [
                    'url' => 'https://www.sanjesh.org/',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'official_announcement_html',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
            ],
        ],

        [
            'slug' => 'hrtc-jihad-exam',
            'name' => 'مرکز آزمون جهاد دانشگاهی',
            'official_url' => 'https://hrtc.ir/',
            'domain' => 'hrtc.ir',
            'source_type' => 'exam_authority',
            'reliability_level' => 'official',
            'priority' => 2,
            'is_enabled' => true,
            'is_approved' => true,
            'quality_status' => 'temporarily_unavailable',
            'crawler_type' => 'html',
            'crawl_frequency' => 'daily',
            'schedule_mode' => 'global',
            'notes' => 'Official civil-service exam registration portal.',
            'quality_notes' => 'Phase 8 (2026-08-08): HTTP 403 Forbidden. Auto-crawl disabled.',
            'endpoints' => [
                [
                    'url' => 'https://hrtc.ir/',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'official_announcement_html',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
            ],
        ],

        [
            'slug' => 'aro-gov',
            'name' => 'سازمان اداری و استخدامی کشور',
            'official_url' => 'https://www.aro.gov.ir/',
            'domain' => 'aro.gov.ir',
            'source_type' => 'government',
            'reliability_level' => 'official',
            'priority' => 3,
            'is_enabled' => true,
            'is_approved' => true,
            'quality_status' => 'temporarily_unavailable',
            'crawler_type' => 'html',
            'crawl_frequency' => 'daily',
            'schedule_mode' => 'global',
            'notes' => 'Official administrative & employment organization.',
            'quality_notes' => 'Phase 8 (2026-08-08): connection timeout from aggregator environment.',
            'endpoints' => [
                [
                    'url' => 'https://www.aro.gov.ir/',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'official_announcement_html',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
            ],
        ],

        // ── Priority 2: banks / monetary authority ─────────────────────────
        [
            'slug' => 'cbi-central-bank',
            'name' => 'بانک مرکزی جمهوری اسلامی ایران',
            'official_url' => 'https://www.cbi.ir/',
            'domain' => 'cbi.ir',
            'source_type' => 'bank',
            'reliability_level' => 'official',
            'priority' => 5,
            'is_enabled' => true,
            'is_approved' => true,
            'quality_status' => 'limited',
            'crawler_type' => 'rss',
            'crawl_frequency' => 'daily',
            'schedule_mode' => 'global',
            'notes' => 'Official CBI news RSS; employment-keyword filter only (no generic news import). Prefer www host.',
            'quality_notes' => 'Phase 8: www.cbi.ir RSS reachable (HTTP 200, ~10 items). Non-www host timed out. Employment items are sporadic → limited.',
            'endpoints' => [
                [
                    'url' => 'https://www.cbi.ir/NewsRss.aspx?ln=fa',
                    'endpoint_type' => 'rss',
                    'http_method' => 'GET',
                    'parser_type' => 'employment_keyword_rss',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
            ],
        ],

        [
            'slug' => 'bank-mellat',
            'name' => 'بانک ملت',
            'official_url' => 'https://www.bankmellat.ir/',
            'domain' => 'bankmellat.ir',
            'source_type' => 'bank',
            'reliability_level' => 'highly_trusted',
            'priority' => 10,
            'is_enabled' => true,
            'is_approved' => true,
            'quality_status' => 'temporarily_unavailable',
            'crawler_type' => 'html',
            'crawl_frequency' => 'daily',
            'schedule_mode' => 'global',
            'notes' => 'Major state-owned bank official site; recruitment announcement links only.',
            'quality_notes' => 'Phase 8 (2026-08-08): connection timeout. Auto-crawl disabled.',
            'endpoints' => [
                [
                    'url' => 'https://www.bankmellat.ir/',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'official_announcement_html',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
            ],
        ],

        [
            'slug' => 'bank-melli',
            'name' => 'بانک ملی ایران',
            'official_url' => 'https://bmi.ir/',
            'domain' => 'bmi.ir',
            'source_type' => 'bank',
            'reliability_level' => 'highly_trusted',
            'priority' => 11,
            'is_enabled' => true,
            'is_approved' => true,
            'quality_status' => 'temporarily_unavailable',
            'crawler_type' => 'html',
            'crawl_frequency' => 'daily',
            'schedule_mode' => 'global',
            'notes' => 'Largest state-owned bank. Homepage/career pages timed out in Phase 8 probe.',
            'quality_notes' => 'Phase 8 (2026-08-08): connection/operation timeout. Kept for future revalidation; not auto-crawled.',
            'endpoints' => [
                [
                    'url' => 'https://bmi.ir/',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'official_announcement_html',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
            ],
        ],

        // ── Priority 3: major public orgs / universities ───────────────────
        [
            'slug' => 'tamin-social-security',
            'name' => 'سازمان تأمین اجتماعی',
            'official_url' => 'https://www.tamin.ir/',
            'domain' => 'tamin.ir',
            'source_type' => 'public_institution',
            'reliability_level' => 'official',
            'priority' => 20,
            'is_enabled' => true,
            'is_approved' => true,
            'quality_status' => 'temporarily_unavailable',
            'crawler_type' => 'html',
            'crawl_frequency' => 'daily',
            'schedule_mode' => 'global',
            'notes' => 'Major public social-security organization official site.',
            'quality_notes' => 'Phase 8 (2026-08-08): HTTP 403 Forbidden (likely WAF). Auto-crawl disabled.',
            'endpoints' => [
                [
                    'url' => 'https://www.tamin.ir/',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'official_announcement_html',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
            ],
        ],

        [
            'slug' => 'ministry-interior',
            'name' => 'وزارت کشور',
            'official_url' => 'https://www.moi.ir/',
            'domain' => 'moi.ir',
            'source_type' => 'ministry',
            'reliability_level' => 'official',
            'priority' => 15,
            'is_enabled' => true,
            'is_approved' => true,
            'quality_status' => 'temporarily_unavailable',
            'crawler_type' => 'html',
            'crawl_frequency' => 'daily',
            'schedule_mode' => 'global',
            'notes' => 'Ministry of Interior official portal.',
            'quality_notes' => 'Phase 8 (2026-08-08): HTTP 403 Forbidden. Auto-crawl disabled.',
            'endpoints' => [
                [
                    'url' => 'https://www.moi.ir/',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'official_announcement_html',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
            ],
        ],

        [
            'slug' => 'iust-university',
            'name' => 'دانشگاه علم و صنعت ایران',
            'official_url' => 'https://www.iust.ac.ir/',
            'domain' => 'iust.ac.ir',
            'source_type' => 'university',
            'reliability_level' => 'official',
            'priority' => 30,
            'is_enabled' => true,
            'is_approved' => true,
            'quality_status' => 'limited',
            'crawler_type' => 'html',
            'crawl_frequency' => 'daily',
            'schedule_mode' => 'global',
            'notes' => 'Official university homepage; employment-keyword announcement links only (e.g. فراخوان جذب).',
            'quality_notes' => 'Phase 8: HTTP 200 reachable. Announcement links include research/postdoc calls; not a dedicated careers API → limited.',
            'endpoints' => [
                [
                    'url' => 'https://www.iust.ac.ir/',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'official_announcement_html',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
            ],
        ],

        [
            'slug' => 'nioc-national-oil',
            'name' => 'شرکت ملی نفت ایران',
            'official_url' => 'https://www.nioc.ir/',
            'domain' => 'nioc.ir',
            'source_type' => 'company',
            'reliability_level' => 'highly_trusted',
            'priority' => 25,
            'is_enabled' => false,
            'is_approved' => true,
            'quality_status' => 'manual_only',
            'crawler_type' => 'html',
            'crawl_frequency' => 'weekly',
            'schedule_mode' => 'global',
            'notes' => 'Major national oil company. Homepage reachable but no crawlable employment section verified in Phase 8.',
            'quality_notes' => 'Phase 8: HTTP 200 on homepage/portal; no employment-keyword anchors found on probed pages. Manual review needed for dedicated careers URL.',
            'endpoints' => [
                [
                    'url' => 'https://www.nioc.ir/',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'official_announcement_html',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
            ],
        ],

        [
            'slug' => 'sharif-university',
            'name' => 'دانشگاه صنعتی شریف',
            'official_url' => 'https://www.sharif.ir/',
            'domain' => 'sharif.ir',
            'source_type' => 'university',
            'reliability_level' => 'official',
            'priority' => 31,
            'is_enabled' => false,
            'is_approved' => true,
            'quality_status' => 'manual_only',
            'crawler_type' => 'html',
            'crawl_frequency' => 'weekly',
            'schedule_mode' => 'global',
            'notes' => 'Major technical university. Homepage reachable; dedicated career host not confirmed reachable.',
            'quality_notes' => 'Phase 8: www.sharif.ir HTTP 200 without employment-keyword anchors. career.sharif.edu timed out. Manual careers URL discovery needed.',
            'endpoints' => [
                [
                    'url' => 'https://www.sharif.ir/',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'official_announcement_html',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
            ],
        ],

        // ── Board aggregators: government + reputable orgs only ─────────────
        [
            'slug' => 'iranestekhdam',
            'name' => 'ایران استخدام',
            'official_url' => 'https://iranestekhdam.ir/',
            'domain' => 'iranestekhdam.ir',
            'source_type' => 'exam_authority',
            'reliability_level' => 'trusted',
            'priority' => 40,
            'is_enabled' => true,
            'is_approved' => true,
            'quality_status' => 'limited',
            'crawler_type' => 'html',
            'crawl_frequency' => 'daily',
            'schedule_mode' => 'global',
            'notes' => 'Commercial board — filtered to government/reputable orgs; posts stay pending.',
            'quality_notes' => 'Uses board_listing_html parser with trust keyword filter.',
            'endpoints' => [
                [
                    'url' => 'https://iranestekhdam.ir/category/%D8%A7%D8%B3%D8%AA%D8%AE%D8%AF%D8%A7%D9%85-%D8%AF%D9%88%D9%84%D8%AA%DB%8C-%D8%B3%D8%A7%D8%B2%D9%85%D8%A7%D9%86%DB%8C-%D8%B3%D8%B1%D8%A7%D8%B3%D8%B1%DB%8C',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'board_listing_html',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
                [
                    'url' => 'https://iranestekhdam.ir/%D8%A7%D8%B3%D8%AA%D8%AE%D8%AF%D8%A7%D9%85-%D9%87%D8%A7%DB%8C-%D8%AC%D8%AF%DB%8C%D8%AF',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'board_listing_html',
                    'is_enabled' => true,
                    'sort_order' => 1,
                ],
            ],
        ],

        [
            'slug' => 'e-estekhdam',
            'name' => 'ای استخدام',
            'official_url' => 'https://www.e-estekhdam.com/',
            'domain' => 'e-estekhdam.com',
            'source_type' => 'exam_authority',
            'reliability_level' => 'trusted',
            'priority' => 41,
            'is_enabled' => true,
            'is_approved' => true,
            'quality_status' => 'limited',
            'crawler_type' => 'html',
            'crawl_frequency' => 'daily',
            'schedule_mode' => 'global',
            'notes' => 'Commercial board — filtered to government/nationwide reputable listings; pending review.',
            'quality_notes' => 'Uses board_listing_html parser with trust keyword filter.',
            'endpoints' => [
                [
                    'url' => 'https://www.e-estekhdam.com/%D8%A7%D8%B3%D8%AA%D8%AE%D8%AF%D8%A7%D9%85-%D9%87%D8%A7%DB%8C-%D8%B3%D8%B1%D8%A7%D8%B3%D8%B1%DB%8C',
                    'endpoint_type' => 'html',
                    'http_method' => 'GET',
                    'parser_type' => 'board_listing_html',
                    'is_enabled' => true,
                    'sort_order' => 0,
                ],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Backward-compatible alias used by older Phase 5 references
    |--------------------------------------------------------------------------
    |
    | Prefer official_sources. Seeders fall back to this key if needed.
    |
    */
    'pilot_sources' => [],

    /*
    |--------------------------------------------------------------------------
    | Employment keyword filters for announcement parsers
    |--------------------------------------------------------------------------
    */
    'employment_keywords' => [
        'استخدام',
        'آزمون استخدامی',
        'فراخوان جذب',
        'دعوت به همکاری',
        'جذب نیرو',
        'ثبت نام آزمون',
    ],

    /*
    |--------------------------------------------------------------------------
    | Commercial board listing filters (iranestekhdam / e-estekhdam)
    |--------------------------------------------------------------------------
    */
    'board_listing' => [
        'max_items' => 80,
        'trust_keywords' => [
            'دولتی',
            'وزارت',
            'سازمان',
            'دستگاه اجرایی',
            'آموزش و پرورش',
            'آموزشوپرورش',
            'نیروی انتظامی',
            'ارتش',
            'سپاه',
            'شهرداری',
            'دانشگاه علوم پزشکی',
            'علوم پزشکی',
            'بانک',
            'بیمه',
            'تامین اجتماعی',
            'تأمین اجتماعی',
            'پتروشیمی',
            'نفت',
            'گاز',
            'فولاد',
            'صدا و سیما',
            'صداوسیما',
            'آب و فاضلاب',
            'منطقه آزاد',
            'قوه قضاییه',
            'دیوان',
            'بنیاد',
            'نهاد',
            'شرکت ملی',
            'ایران خودرو',
            'ایرانخودرو',
            'سایپا',
            'همراه اول',
            'ایرانسل',
            'مخابرات',
            'پست بانک',
            'بانک مرکزی',
            'سراسری',
            'آزمون استخدام',
            'دفتریاری',
            'ثبت احوال',
            'گمرک',
            'مالیات',
            'جهاد دانشگاهی',
            'سنجش',
        ],
        'skip_path_contains' => [
            '/login',
            '/register',
            '/cart',
            '/app',
            '/privacy',
            '/contact',
            '/tag/',
            '/author/',
            '/page/',
            '/search',
            '/category/technician',
        ],
    ],

];
