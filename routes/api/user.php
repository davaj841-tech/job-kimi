<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\JobPostController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileExtrasController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authenticated user routes — prefix: /api/v1
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'subscription.check'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/admin/auth/logout', [AdminAuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::post('/job-posts/submit', [JobPostController::class, 'submit']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{id}', [TicketController::class, 'show'])->whereNumber('id');
    Route::post('/tickets/{id}/reply', [TicketController::class, 'reply'])->whereNumber('id');

    Route::post('/blog-posts/{id}/comments', [CommentController::class, 'store'])->whereNumber('id');

    Route::get('/achievements', [ProfileExtrasController::class, 'achievements']);
    Route::get('/notification-preferences', [ProfileExtrasController::class, 'notificationPreferences']);
    Route::put('/notification-preferences', [ProfileExtrasController::class, 'updateNotificationPreferences']);

    Route::get('/resumes/{id}/pdf', [ResumeController::class, 'downloadPDF'])->whereNumber('id');
    Route::get('/resumes/{id}/preview', [ResumeController::class, 'preview'])->whereNumber('id');
    Route::put('/resumes/{id}/template', [ResumeController::class, 'updateTemplate'])->whereNumber('id');
    Route::post('/resumes/{id}/ai-suggest', [ResumeController::class, 'aiSuggest'])->whereNumber('id');
    Route::apiResource('/resumes', ResumeController::class)->parameters(['resumes' => 'id']);
});
