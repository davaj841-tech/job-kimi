<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null) {
            if (! $user) {
                return false;
            }

            if (in_array($user->role, ['admin', 'operator'], true)) {
                return true;
            }

            $emails = config('horizon.allowed_emails', []);

            return is_array($emails) && in_array($user->email, $emails, true);
        });
    }
}
