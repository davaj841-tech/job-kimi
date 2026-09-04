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

Route::post('/auth/otp/send', [AuthController::class, 'sendOtp'])->middleware(['auth.captcha:otp-send', 'throttle:otp-send']);
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware(['auth.captcha:otp-verify', 'throttle:otp-verify']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware(['auth.captcha:login', 'throttle:login']);
Route::post('/auth/register', [AuthController::class, 'register'])->middleware(['auth.captcha:register', 'throttle:login']);
Route::post('/auth/forgot-password', [PasswordResetController::class, 'forgot'])->middleware(['auth.captcha:forgot-password', 'throttle:otp-send']);
Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:otp-send');
Route::post('/auth/forgot-password/verify-otp', [PasswordResetController::class, 'verifyOtpReset'])->middleware(['auth.captcha:forgot-password', 'throttle:otp-verify']);

Route::post('/admin/auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login');
Route::post('/admin/auth/forgot-password', [AdminAuthController::class, 'forgotPassword'])->middleware('throttle:admin-login');
