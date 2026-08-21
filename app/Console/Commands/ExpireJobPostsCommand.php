<?php

namespace App\Console\Commands;

use App\Models\JobPost;
use Illuminate\Console\Command;

class ExpireJobPostsCommand extends Command
{
    protected $signature = 'jobs:expire';

    protected $description = 'Mark approved job posts as expired when registration_deadline has passed';

    public function handle(): int
    {
        $count = JobPost::query()
            ->where('status', 'approved')
            ->whereNotNull('registration_deadline')
            ->whereDate('registration_deadline', '<', now()->toDateString())
            ->update(['status' => 'expired']);

        if ($count > 0) {
            $this->info("Expired {$count} job post(s).");
        }

        return self::SUCCESS;
    }
}
