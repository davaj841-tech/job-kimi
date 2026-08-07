<?php

namespace App\Listeners;

use App\Events\JobPostApproved;
use App\Notifications\JobPostApprovedNotification;

class NotifyUserOfApproval
{
    public function handle(JobPostApproved $event): void
    {
        $job = $event->jobPost->loadMissing('creator');
        if ($job->creator) {
            $job->creator->notify(new JobPostApprovedNotification($job));
        }
    }
}
