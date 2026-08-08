<?php

namespace App\Contracts\Aggregation;

use App\Models\JobPost;

interface DuplicateDetectorInterface
{
    /**
     * @param  array<string, mixed>  $normalized
     * @return array{is_duplicate: bool, original: ?JobPost, score: ?float, reason: ?string}
     */
    public function findDuplicate(array $normalized): array;
}
