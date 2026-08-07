<?php

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
        $middleware->trustProxies(at: '*');
        $middleware->throttleApi('api');
        $middleware->alias([
            'auth.ensure' => \App\Http\Middleware\EnsureAuth::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
            'subscription.check' => \App\Http\Middleware\CheckSubscription::class,
            'turnstile' => \App\Http\Middleware\VerifyTurnstileToken::class,
            'track.page' => \App\Http\Middleware\TrackPageView::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\ForceHttps::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\TrackPageView::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
