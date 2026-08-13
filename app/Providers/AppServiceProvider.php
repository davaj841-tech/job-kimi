<?php

namespace App\Providers;

use App\Contracts\Aggregation\DuplicateDetectorInterface;
use App\Contracts\Aggregation\JobNormalizerInterface;
use App\Contracts\Aggregation\JobPublisherInterface;
use App\Contracts\Aggregation\JobValidatorInterface;
use App\Events\ExamCompleted;
use App\Events\JobPostApproved;
use App\Events\PaymentSuccessful;
use App\Events\SubscriptionExpired;
use App\Events\UserRegistered;
use App\Helpers\IpHelper;
use App\Listeners\GenerateInvoice;
use App\Listeners\NotifyUserOfApproval;
use App\Listeners\SendExpiryReminder;
use App\Listeners\SendWelcomeNotification;
use App\Listeners\UpdateUserStats;
use App\Models\Feature;
use App\Observers\FeatureObserver;
use App\Services\Aggregation\CrawlerResolver;
use App\Services\Aggregation\DuplicateDetector;
use App\Services\Aggregation\JobNormalizer;
use App\Services\Aggregation\JobPublisher;
use App\Services\Aggregation\JobSourceManager;
use App\Services\Aggregation\JobValidator;
use App\Services\Aggregation\Parsers\BoardListingHtmlParser;
use App\Services\Aggregation\Parsers\EmploymentKeywordRssParser;
use App\Services\Aggregation\Parsers\OfficialAnnouncementHtmlParser;
use App\Services\Aggregation\Parsers\SourceParserRegistry;
use App\Services\Aggregation\SafeHttpFetcher;
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
        $this->app->singleton(IpHelper::class);

        $this->app->singleton(JobNormalizerInterface::class, JobNormalizer::class);
        $this->app->singleton(JobValidatorInterface::class, JobValidator::class);
        $this->app->singleton(DuplicateDetectorInterface::class, DuplicateDetector::class);
        $this->app->singleton(JobPublisherInterface::class, JobPublisher::class);

        $this->app->singleton(SourceParserRegistry::class, function ($app) {
            return new SourceParserRegistry([
                $app->make(EmploymentKeywordRssParser::class),
                $app->make(OfficialAnnouncementHtmlParser::class),
                $app->make(BoardListingHtmlParser::class),
            ]);
        });

        $this->app->singleton(SafeHttpFetcher::class, function ($app) {
            $http = config('aggregation.http', []);

            return new SafeHttpFetcher(
                $app->make(JobSourceManager::class),
                (int) ($http['timeout_seconds'] ?? 30),
                (int) ($http['max_bytes'] ?? 2_000_000),
                (int) ($http['max_redirects'] ?? 3),
            );
        });

        $this->app->singleton(CrawlerResolver::class, function ($app) {
            return CrawlerResolver::makeDefault(
                $app->make(SafeHttpFetcher::class),
                $app->make(SourceParserRegistry::class),
            );
        });
    }

    public function boot(): void
    {
        // Ensure API validation / auth messages stay Persian
        app()->setLocale(config('app.locale', 'fa'));

        if ($this->app->environment('production') && config('app.debug')) {
            report(new \RuntimeException('APP_DEBUG is enabled in production — disable it immediately.'));
        }

        Feature::observe(FeatureObserver::class);

        Request::macro('trustedIp', function (): ?string {
            /** @var Request $this */
            return app(IpHelper::class)->getClientIp($this);
        });

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
            return Limit::perMinute(3)->by(($request->input('mobile') ?: '').'|'.$request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(($request->input('mobile') ?: $request->input('login') ?: '').'|'.$request->ip());
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by(($request->input('username') ?: '').'|'.$request->ip());
        });
    }
}
