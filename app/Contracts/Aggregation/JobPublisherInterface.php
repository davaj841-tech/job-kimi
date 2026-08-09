<?php

namespace App\Contracts\Aggregation;

use App\Models\JobPost;
use App\Models\JobSource;

/**
 * Promotes verified aggregated data into the canonical job_posts table.
 */
interface JobPublisherInterface
{
    /**
     * @param  array<string, mixed>  $normalized
     */
    public function publish(array $normalized, JobSource $source, bool $autoApprove = false): JobPost;
}
