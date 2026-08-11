<?php

use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PDFProductController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment callbacks (public) + authenticated wallet/subscription/PDF
|--------------------------------------------------------------------------
*/

// ZarinPal callbacks (public — gateway redirects without Bearer token)
Route::match(['get', 'post'], '/wallet/verify', [WalletController::class, 'verify']);
Route::match(['get', 'post'], '/subscription/verify', [SubscriptionController::class, 'verifySubscription']);
Route::match(['get', 'post'], '/pdf-products/{id}/verify', [PDFProductController::class, 'verifyPurchase'])->whereNumber('id');

Route::middleware(['auth:sanctum', 'subscription.check'])->group(function () {
    Route::get('/wallet', [WalletController::class, 'index'])->middleware('feature:wallet');
    Route::post('/wallet/charge', [WalletController::class, 'charge'])->middleware('feature:wallet');

    Route::post('/subscription/subscribe', [SubscriptionController::class, 'subscribe'])
        ->middleware('feature:subscription');

    Route::post('/pdf-products/{id}/purchase', [PDFProductController::class, 'purchase'])
        ->middleware('feature:pdf-store')
        ->whereNumber('id');
    Route::get('/pdf-products/{id}/download', [PDFProductController::class, 'download'])
        ->middleware('feature:pdf-store')
        ->whereNumber('id');
    Route::get('/my-purchases', [PDFProductController::class, 'myPurchases'])
        ->middleware('feature:pdf-store');

    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}/invoice', [InvoiceController::class, 'download'])->whereNumber('id');
});
