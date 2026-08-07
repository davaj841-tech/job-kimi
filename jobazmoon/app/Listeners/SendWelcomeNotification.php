<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Notifications\GenericDatabaseNotification;
use App\Services\MailConfigService;

class SendWelcomeNotification
{
    public function __construct(protected MailConfigService $mail) {}

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;
        $this->mail->sendWelcome($user);
        $user->notify(new GenericDatabaseNotification(
            'welcome',
            'خوش آمدید',
            'به جاب‌آزمون خوش آمدید. آماده‌اید برای شروع؟',
            '/dashboard'
        ));
    }
}
