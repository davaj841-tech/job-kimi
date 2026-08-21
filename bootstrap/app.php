<?php

use App\Http\Middleware\CacheResponse;
use App\Http\Middleware\CheckInstalled;
use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\EnsureAuth;
use App\Http\Middleware\EnsureUserActive;
use App\Http\Middleware\EnsureOperatorPermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\FeatureEnabled;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\PreventInstallAccess;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackPageView;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyAuthCaptcha;
use App\Http\Middleware\VerifyTurnstileToken;
use App\Services\SiteErrorLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
        then: function () {
            if (! is_file(storage_path('installed'))) {
                Route::middleware('web')->group(base_path('routes/install.php'));
            } else {
                Route::middleware('web')->any('/install/{any?}', function () {
                    return redirect('/');
                })->where('any', '.*');
            }
        },
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
        $middleware->prependToGroup('web', CheckInstalled::class);
        $middleware->prependToGroup('api', CheckInstalled::class);
        $middleware->throttleApi('api');
        $middleware->validateCsrfTokens(except: [
            'csp-report',
        ]);
        $middleware->alias([
            'auth.ensure' => EnsureAuth::class,
            'user.active' => EnsureUserActive::class,
            'role' => EnsureRole::class,
            'operator.perm' => EnsureOperatorPermission::class,
            'subscription.check' => CheckSubscription::class,
            'turnstile' => VerifyTurnstileToken::class,
            'auth.captcha' => VerifyAuthCaptcha::class,
            'track.page' => TrackPageView::class,
            'cache.response' => CacheResponse::class,
            'feature' => FeatureEnabled::class,
            'install.check' => CheckInstalled::class,
            'install.prevent' => PreventInstallAccess::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\SeoRedirect::class,
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

        $exceptions->render(function (Throwable $e, $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->validator->errors()->first() ?: 'اطلاعات واردشده معتبر نیست.',
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'احراز هویت الزامی است.',
                    'errors' => null,
                ], 401);
            }

            if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                return response()->json([
                    'success' => false,
                    'message' => 'تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.',
                    'errors' => null,
                ], 429);
            }

            return null;
        });

        $exceptions->reportable(function (Throwable $e) {
            if (app()->bound('sentry') && app()->environment('production')) {
                app('sentry')->captureException($e);
            }
        });
    })->create();
