<?php

namespace App\Contracts\Aggregation;

use App\Models\JobSource;
use App\Models\JobSourceEndpoint;

/**
 * Future crawler contract. Implementations arrive in later phases.
 * Phase 2 only defines the boundary — no network I/O here.
 */
interface JobSourceCrawlerInterface
{
    public function supports(JobSource $source): bool;

    /**
     * @return array<int, array<string, mixed>> Normalized-ish raw items (future).
     */
    public function crawl(JobSource $source, ?JobSourceEndpoint $endpoint = null): array;
}
