<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;

$providers = [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
];

// Telescope is require-dev — never boot it after `composer install --no-dev`.
if (class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)) {
    $providers[] = TelescopeServiceProvider::class;
}

return $providers;
