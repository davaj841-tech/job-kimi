<?php

namespace App\Listeners;

use App\Events\SubscriptionExpired;
use App\Notifications\GenericDatabaseNotification;

class SendExpiryReminder
{
    public function handle(SubscriptionExpired $event): void
    {
        $user = $event->user;
        $user->notify(new GenericDatabaseNotification(
            'subscription_expired',
            'اشتراک منقضی شد',
            'اشتراک شما به پایان رسیده است. برای ادامه، پلن جدید تهیه کنید.',
            '/subscription'
        ));
    }
}
