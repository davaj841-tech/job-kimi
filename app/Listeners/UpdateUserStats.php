<?php

namespace App\Listeners;

use App\Events\ExamCompleted;
use App\Services\AchievementService;
use Illuminate\Support\Facades\Log;

class UpdateUserStats
{
    public function __construct(protected AchievementService $achievements) {}

    public function handle(ExamCompleted $event): void
    {
        $attempt = $event->attempt->loadMissing('user');
        $user = $attempt->user;
        if (! $user) {
            return;
        }

        try {
            $this->achievements->evaluateAfterExam($user, $attempt);
        } catch (\Throwable $e) {
            Log::warning('UpdateUserStats failed: '.$e->getMessage());
        }
    }
}
