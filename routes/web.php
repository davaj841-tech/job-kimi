<?php

use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/health', HealthController::class)->name('health');
Route::get('/api/documentation', [ApiDocumentationController::class, 'ui'])->name('api.docs');
Route::get('/api/documentation.json', [ApiDocumentationController::class, 'spec'])->name('api.docs.spec');

// پنل عملیاتی اصلی: Vue SPA — Filament به /admin هدایت می‌شود
Route::redirect('/filament', '/admin');
Route::redirect('/filament/{any}', '/admin')->where('any', '.*');

Route::view('/admin/{any?}', 'admin')
    ->where('any', '.*')
    ->name('admin.spa');

Route::get('/{any?}', SpaController::class)
    ->where('any', '^(?!api|admin|filament|horizon|sanctum|health|robots\.txt|sitemap\.xml).*$')
    ->name('spa');
