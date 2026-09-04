<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Services\MailConfigService;
use App\Services\SMSService;
use App\Support\SmsMobileMask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
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
                    ->orWhereDate('subscription_expires_at', now()->addDay()->toDateString())
                    ->orWhereDate('subscription_expires_at', now()->subDay()->toDateString());
            })
            ->get();

        foreach ($targets as $user) {
            $expiresDate = $user->subscription_expires_at->toDateString();
            $isExpired = $user->subscription_expires_at->isPast();
            $dedupeKey = 'mail:subscription_expiry:'.$user->id.':'.$expiresDate.':'.now()->toDateString().':'.($isExpired ? 'expired' : 'soon');
            if (! Cache::add($dedupeKey, 1, now()->addDays(2))) {
                continue;
            }

            $date = Jalalian::fromCarbon($user->subscription_expires_at)->format('Y/m/d');
            $template = (string) Setting::getFilled(
                $isExpired ? 'sms_subscription_expired_template' : 'sms_subscription_reminder_template',
                $isExpired
                    ? 'اشتراک شما در جاب‌آزمون در {date} منقضی شده است. برای تمدید وارد سایت شوید.'
                    : 'اشتراک شما در جاب‌آزمون تا {date} منقضی می‌شود. برای تمدید وارد سایت شوید.'
            );
            $message = str_replace(['{date}', ':date'], $date, $template);
            $link = rtrim(config('app.url'), '/').'/subscription';

            if ($user->mobile) {
                $queued = $smsService->queue($user->mobile, $message, 'transactional');
                Log::info('Subscription expiry SMS', [
                    'user_id' => $user->id,
                    'mobile' => SmsMobileMask::mask($user->mobile),
                    'queued' => $queued,
                ]);
            }

            $user->notify(new GenericDatabaseNotification(
                $isExpired ? 'subscription_expired' : 'subscription_expiry',
                $isExpired ? 'اشتراک منقضی شد' : 'اشتراک رو به انقضا',
                $message,
                $link
            ));

            $mail->sendSubscriptionExpiry($user, $date);
        }
    }
}
