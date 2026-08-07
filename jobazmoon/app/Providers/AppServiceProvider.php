<?php

namespace App\Providers;

use App\Events\ExamCompleted;
use App\Events\JobPostApproved;
use App\Events\PaymentSuccessful;
use App\Events\SubscriptionExpired;
use App\Events\UserRegistered;
use App\Listeners\GenerateInvoice;
use App\Listeners\NotifyUserOfApproval;
use App\Listeners\SendExpiryReminder;
use App\Listeners\SendWelcomeNotification;
use App\Listeners\UpdateUserStats;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(UserRegistered::class, SendWelcomeNotification::class);
        Event::listen(ExamCompleted::class, UpdateUserStats::class);
        Event::listen(PaymentSuccessful::class, GenerateInvoice::class);
        Event::listen(SubscriptionExpired::class, SendExpiryReminder::class);
        Event::listen(JobPostApproved::class, NotifyUserOfApproval::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)->by(($request->input('mobile') ?: '').'|'.$request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by(($request->input('mobile') ?: '').'|'.$request->ip());
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by(($request->input('username') ?: '').'|'.$request->ip());
        });
    }
}
