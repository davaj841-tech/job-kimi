<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Services\MailConfigService;
use App\Services\SMSService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\Jalalian;

class SendSubscriptionExpiryNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SMSService $smsService, MailConfigService $mail): void
    {
        $targets = User::query()
            ->whereNotNull('subscription_expires_at')
            ->where(function ($q) {
                $q->whereDate('subscription_expires_at', now()->addDays(3)->toDateString())
                    ->orWhereDate('subscription_expires_at', now()->addDay()->toDateString());
            })
            ->get();

        foreach ($targets as $user) {
            $date = Jalalian::fromCarbon($user->subscription_expires_at)->format('Y/m/d');
            $message = "اشتراک شما در JobAzmoon تا {$date} منقضی می‌شود. برای تمدید وارد سایت شوید.";
            $link = rtrim(config('app.url'), '/').'/subscription';

            if ($user->mobile) {
                $sent = $smsService->sendSMS($user->mobile, $message);
                Log::info('Subscription expiry SMS', [
                    'user_id' => $user->id,
                    'mobile' => $user->mobile,
                    'sent' => $sent,
                ]);
            }

            $user->notify(new GenericDatabaseNotification(
                'subscription_expiry',
                'اشتراک رو به انقضا',
                $message,
                $link
            ));

            $mail->sendSubscriptionExpiry($user, $date);
        }
    }
}
