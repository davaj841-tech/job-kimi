<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        // فقط local/staging — در production خاموش
        if (! $this->app->environment(['local', 'staging'])) {
            return;
        }

        Telescope::filter(function (IncomingEntry $entry) {
            return true;
        });
    }

    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token', 'password', 'otp_code', 'code']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'authorization',
        ]);
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', function (?User $user = null) {
            if (! $user) {
                return false;
            }

            return in_array($user->role, ['admin', 'operator'], true)
                || in_array($user->email, [
                    'admin@jobazmoon.ir',
                ], true);
        });
    }
}
