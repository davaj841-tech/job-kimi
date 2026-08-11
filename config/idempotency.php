<?php

declare(strict_types=1);

return [
    'lock_timeout' => (int) env('IDEMPOTENCY_LOCK_TIMEOUT', 10),
    'cache_ttl' => (int) env('IDEMPOTENCY_CACHE_TTL', 3600),
];
