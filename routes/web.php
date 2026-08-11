<?php

use App\Http\Controllers\CspReportController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/health', HealthController::class)->name('health');
Route::post('/csp-report', [CspReportController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('csp.report');

// Scribe UI: /api/documentation — OpenAPI: /api/documentation.openapi
Route::redirect('/api/documentation.json', '/api/documentation.openapi');

// Filament PHP admin: /filament — Vue ops SPA: /admin
Route::view('/admin/{any?}', 'admin')
    ->where('any', '.*')
    ->name('admin.spa');

Route::get('/{any?}', SpaController::class)
    ->where('any', '^(?!api|admin|filament|horizon|telescope|sanctum|health|csp-report|robots\.txt|sitemap\.xml).*$')
    ->name('spa');
