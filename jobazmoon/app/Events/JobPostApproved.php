<?php

namespace App\Events;

use App\Models\JobPost;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobPostApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(public JobPost $jobPost) {}
}
