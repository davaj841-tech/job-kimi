<?php

declare(strict_types=1);

return [
    'max_upload_kb' => (int) env('UPDATE_MAX_UPLOAD_KB', 102400), // 100 MB
    'max_extracted_bytes' => (int) env('UPDATE_MAX_EXTRACTED_BYTES', 400 * 1024 * 1024),
    'max_zip_entries' => (int) env('UPDATE_MAX_ZIP_ENTRIES', 5000),
    'lock_ttl_seconds' => (int) env('UPDATE_LOCK_TTL', 3600),
    'maintenance_secret' => env('UPDATE_MAINTENANCE_SECRET'),

    /*
    | Relative paths (posix) that update packs must never write/delete.
    */
    'protected_paths' => [
        '.env',
        '.env.example',
        '.env.testing',
        'vendor',
        'node_modules',
        '.git',
        'bootstrap/cache',
        'composer.lock',
        'storage',
        'public/uploads',
        'public/storage',
    ],

    /*
    | Top-level directories / files that may appear under files/ in a pack.
    */
    'allowed_roots' => [
        'app',
        'bootstrap',
        'config',
        'database',
        'lang',
        'public',
        'resources',
        'routes',
        'composer.json',
        'package.json',
        'vite.config.js',
        'vite.config.ts',
        'tsconfig.json',
        'phpunit.xml',
        'artisan',
    ],

    'require_composer_blocks_install' => true,
];
