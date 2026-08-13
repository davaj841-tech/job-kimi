<?php

use App\Http\Middleware\CacheResponse;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\EnsureAuth;
use App\Http\Middleware\EnsureOperatorPermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\FeatureEnabled;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackPageView;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyTurnstileToken;
use App\Services\SiteErrorLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $trustedProxies = collect([
            env('TRUSTED_PROXIES', ''),
            env('TRUSTED_PROXIES_V6', ''),
        ])
            ->flatMap(fn (string $ips) => array_filter(explode(',', $ips)))
            ->map(fn (string $ip) => trim($ip))
            ->filter()
            ->values()
            ->all();

        if (! empty($trustedProxies)) {
            $middleware->trustProxies(at: $trustedProxies);
        }

        $middleware->prepend(TrustProxies::class);
        $middleware->throttleApi('api');
        $middleware->validateCsrfTokens(except: [
            'csp-report',
        ]);
        $middleware->alias([
            'auth.ensure' => EnsureAuth::class,
            'role' => EnsureRole::class,
            'operator.perm' => EnsureOperatorPermission::class,
            'subscription.check' => CheckSubscription::class,
            'turnstile' => VerifyTurnstileToken::class,
            'track.page' => TrackPageView::class,
            'cache.response' => CacheResponse::class,
            'feature' => FeatureEnabled::class,
        ]);
        $middleware->web(append: [
            ForceHttps::class,
            SecurityHeaders::class,
            TrackPageView::class,
        ]);
        $middleware->api(append: [
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {
            try {
                $request = request();
                app(SiteErrorLogger::class)->report($e, [
                    'url' => $request?->fullUrl(),
                    'method' => $request?->method(),
                    'user_id' => $request?->user()?->id,
                ]);
            } catch (Throwable) {
                // ignore
            }
        });

        $exceptions->reportable(function (Throwable $e) {
            if (app()->bound('sentry') && app()->environment('production')) {
                app('sentry')->captureException($e);
            }
        });
    })->create();
