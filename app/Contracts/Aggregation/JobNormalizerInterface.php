<?php

namespace App\Contracts\Aggregation;

/**
 * Maps raw crawl items into the project's JobPost field shape.
 */
interface JobNormalizerInterface
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalize(array $raw): array;
}
