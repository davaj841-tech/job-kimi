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
use App\Models\BlogPost;
use App\Models\CmsPage;
use App\Models\Exam;
use App\Models\Feature;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\PdfProduct;
use App\Observers\FeatureObserver;
use App\Observers\SeoContentObserver;
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
use App\Support\IranMobile;
use App\Support\ProcOpen;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! is_file(storage_path('installed'))) {
            $this->app['config']->set('session.driver', 'file');
            $this->app['config']->set('cache.default', 'file');
            $this->app['config']->set('queue.default', 'sync');
        }

        $this->app->singleton(\App\Services\Sms\SmsLogger::class);
        $this->app->singleton(\App\Services\Sms\SmsManager::class);
        $this->app->singleton(\App\Services\Sms\SmsServiceInterface::class, \App\Services\Sms\SmsManager::class);
        $this->app->singleton(\App\Services\Sms\SmsService::class, fn ($app) => new \App\Services\Sms\SmsService($app->make(\App\Services\Sms\SmsManager::class)));

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

        // Shared hosting: Pulse Servers / some tooling needs shell helpers; avoid noise.
        if (! ProcOpen::available()) {
            config(['pulse.enabled' => false]);
        }

        if ($this->app->environment('production') && config('app.debug')) {
            report(new \RuntimeException('APP_DEBUG is enabled in production — disable it immediately.'));
        }

        // Legacy MAIL_SCHEME=tls|ssl must never reach Symfony (only smtp|smtps).
        try {
            app(\App\Services\MailConfigService::class)->normalizeConfiguredSmtpScheme();
        } catch (\Throwable) {
            // Installer / early boot may lack Settings; ignore.
        }

        Feature::observe(FeatureObserver::class);

        foreach ([Exam::class, JobPost::class, BlogPost::class, CmsPage::class, GeneratedContent::class, PdfProduct::class] as $model) {
            $model::observe(SeoContentObserver::class);
        }

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

        // OTP ارسال — ۵ درخواست در ۱۰ دقیقه (بر اساس IP)
        RateLimiter::for('otp-send', function (Request $request) {
            return Limit::perMinutes(10, 5)
                ->by('otp-send:'.$request->ip())
                ->response(fn (Request $request, array $headers) => $this->throttleJsonResponse($headers));
        });

        // سازگاری با نام قبلی throttle:otp
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinutes(10, 5)
                ->by('otp-send:'.$request->ip())
                ->response(fn (Request $request, array $headers) => $this->throttleJsonResponse($headers));
        });

        // OTP تأیید — ۱۰ درخواست در ۱۵ دقیقه (بر اساس IP)
        RateLimiter::for('otp-verify', function (Request $request) {
            return Limit::perMinutes(15, 10)
                ->by('otp-verify:'.$request->ip())
                ->response(fn (Request $request, array $headers) => $this->throttleJsonResponse($headers));
        });

        RateLimiter::for('login', function (Request $request) {
            $id = (string) ($request->input('mobile') ?: $request->input('login') ?: '');
            $id = IranMobile::normalize($id) ?: $id;

            return Limit::perMinute(5)
                ->by($id.'|'.$request->ip())
                ->response(fn (Request $request, array $headers) => $this->throttleJsonResponse($headers));
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)
                ->by(($request->input('username') ?: '').'|'.$request->ip())
                ->response(fn (Request $request, array $headers) => $this->throttleJsonResponse($headers));
        });

        // آزمون‌ها — ۶۰ درخواست در دقیقه (کاربر لاگین‌شده؛ در غیر این صورت IP)
        RateLimiter::for('exams', function (Request $request) {
            $key = $request->user()?->id
                ? 'exams:user:'.$request->user()->id
                : 'exams:ip:'.$request->ip();

            return Limit::perMinute(60)
                ->by($key)
                ->response(fn (Request $request, array $headers) => $this->throttleJsonResponse($headers));
        });

        // پرداخت — ۱۰ درخواست در ساعت برای هر کاربر
        RateLimiter::for('payment', function (Request $request) {
            $key = $request->user()?->id
                ? 'payment:user:'.$request->user()->id
                : 'payment:ip:'.$request->ip();

            return Limit::perHour(10)
                ->by($key)
                ->response(fn (Request $request, array $headers) => $this->throttleJsonResponse($headers));
        });

        // ادمین — ۱۰۰ درخواست در دقیقه برای هر ادمین
        RateLimiter::for('admin-api', function (Request $request) {
            $key = $request->user()?->id
                ? 'admin:user:'.$request->user()->id
                : 'admin:ip:'.$request->ip();

            return Limit::perMinute(100)
                ->by($key)
                ->response(fn (Request $request, array $headers) => $this->throttleJsonResponse($headers));
        });

        RateLimiter::for('contact', function (Request $request) {
            return Limit::perMinute(5)
                ->by((string) $request->ip())
                ->response(fn (Request $request, array $headers) => $this->throttleJsonResponse($headers));
        });

        RateLimiter::for('newsletter', function (Request $request) {
            return Limit::perMinute(5)
                ->by((string) $request->ip())
                ->response(fn (Request $request, array $headers) => $this->throttleJsonResponse($headers));
        });

        RateLimiter::for('coupon', function (Request $request) {
            return Limit::perMinute(10)
                ->by((string) $request->ip())
                ->response(fn (Request $request, array $headers) => $this->throttleJsonResponse($headers));
        });

        RateLimiter::for('payment-callback', function (Request $request) {
            return Limit::perMinute(30)
                ->by((string) $request->ip())
                ->response(fn (Request $request, array $headers) => $this->throttleJsonResponse($headers));
        });
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function throttleJsonResponse(array $headers): JsonResponse
    {
        $retryAfter = (int) ($headers['Retry-After'] ?? 60);
        $minutes = max(1, (int) ceil($retryAfter / 60));

        return response()->json([
            'success' => false,
            'message' => "تعداد درخواست‌های شما بیش از حد مجاز است. لطفاً {$minutes} دقیقه دیگر تلاش کنید.",
            'errors' => null,
        ], 429, $headers);
    }
}
