<?php

namespace App\Notifications;

use App\Models\JobPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class JobPostApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public JobPost $jobPost
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'job_post_approved',
            'job_post_id' => $this->jobPost->id,
            'title' => 'آگهی تایید شد',
            'message' => 'آگهی شغلی شما تایید و منتشر شد: '.$this->jobPost->title,
            'link' => '/jobs/'.$this->jobPost->id,
        ];
    }
}
