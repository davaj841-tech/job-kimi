<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\JobPost;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Services\MailConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Morilog\Jalali\Jalalian;

class SendExamReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MailConfigService $mail): void
    {
        $posts = JobPost::query()
            ->where('status', 'approved')
            ->whereDate('exam_date', now()->addDay()->toDateString())
            ->get();

        foreach ($posts as $post) {
            $examIds = Exam::query()->where('job_post_id', $post->id)->pluck('id');
            $userIds = ExamAttempt::query()
                ->whereIn('exam_id', $examIds)
                ->distinct()
                ->pluck('user_id');

            if ($userIds->isEmpty()) {
                $userIds = User::query()
                    ->whereNotNull('subscription_expires_at')
                    ->where('subscription_expires_at', '>', now())
                    ->limit(200)
                    ->pluck('id');
            }

            $users = User::query()->whereIn('id', $userIds->unique()->filter())->get();
            $jalali = $post->exam_date ? Jalalian::fromCarbon($post->exam_date)->format('Y/m/d') : null;
            $url = rtrim(config('app.url'), '/').'/jobs/'.$post->id;

            foreach ($users as $user) {
                $user->notify(new GenericDatabaseNotification(
                    'exam_reminder',
                    'یادآوری آزمون',
                    "آزمون مرتبط با «{$post->title}» فردا برگزار می‌شود.",
                    $url
                ));
                $mail->sendExamReminder($user, $post->title, $jalali, $url);
            }
        }
    }
}
