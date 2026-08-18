<?php

use App\Http\Controllers\Install\InstallController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')->name('install.')->middleware('install.prevent')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::post('/requirements', [InstallController::class, 'storeRequirements'])->name('requirements');

    Route::get('/database', [InstallController::class, 'database'])->name('database');
    Route::post('/database/test', [InstallController::class, 'testDatabase'])->name('database.test');
    Route::post('/database', [InstallController::class, 'storeDatabase'])->name('database.store');

    Route::get('/migrate', [InstallController::class, 'migrate'])->name('migrate');
    Route::post('/migrate', [InstallController::class, 'runMigrate'])->name('migrate.run');

    Route::get('/admin', [InstallController::class, 'admin'])->name('admin');
    Route::post('/admin', [InstallController::class, 'storeAdmin'])->name('admin.store');

    Route::get('/finish', [InstallController::class, 'finish'])->name('finish');
});
