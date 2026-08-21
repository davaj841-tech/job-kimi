<?php

$raw = env('CORS_ALLOWED_ORIGINS');
if ($raw === null || trim((string) $raw) === '') {
    $origins = env('APP_ENV') === 'production'
        ? array_values(array_filter([rtrim((string) env('APP_URL', ''), '/')]))
        : ['*'];
} else {
    $origins = array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $origins !== [] ? $origins : ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
