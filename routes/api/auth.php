<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\MathCaptchaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth routes (public) — prefix: /api/v1
|--------------------------------------------------------------------------
*/

Route::get('/auth/captcha', [MathCaptchaController::class, 'challenge'])
    ->middleware('throttle:20,1');

Route::post('/auth/otp/send', [AuthController::class, 'sendOtp'])->middleware(['auth.captcha', 'throttle:otp']);
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware(['auth.captcha', 'throttle:login']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware(['auth.captcha', 'throttle:login']);
Route::post('/auth/register', [AuthController::class, 'register'])->middleware(['auth.captcha', 'throttle:login']);
Route::post('/auth/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:otp');
Route::post('/auth/reset-password', [PasswordResetController::class, 'reset']);
Route::post('/auth/forgot-password/verify-otp', [PasswordResetController::class, 'verifyOtpReset'])->middleware('throttle:login');

Route::post('/admin/auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login');
Route::post('/admin/auth/forgot-password', [AdminAuthController::class, 'forgotPassword'])->middleware('throttle:admin-login');
