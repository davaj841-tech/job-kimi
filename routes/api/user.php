<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\JobPostController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileExtrasController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserPanelController;
use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authenticated user routes — prefix: /api/v1
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'user.active', 'subscription.check'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [UserPanelController::class, 'updatePassword']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/settings', [ProfileExtrasController::class, 'updateNotificationPreferences']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:login');
    Route::post('/admin/auth/logout', [AdminAuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/user/dashboard-stats', [UserPanelController::class, 'dashboardStats']);
    Route::get('/user/recent-exams', [UserPanelController::class, 'recentExams']);
    Route::get('/user/skills-analysis', [UserPanelController::class, 'skillsAnalysis']);
    Route::get('/user/exam-history', [UserPanelController::class, 'examHistory']);
    Route::get('/user/activity', [UserPanelController::class, 'activity']);
    Route::get('/user/notifications', [NotificationController::class, 'index']);

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
    Route::post('/resumes/{id}/ai-suggest', [ResumeController::class, 'aiSuggest'])
        ->middleware('feature:ai-resume')
        ->whereNumber('id');
    Route::post('/resumes/{id}/ai/summary', [ResumeController::class, 'aiWriteSummary'])
        ->middleware('feature:ai-resume')
        ->whereNumber('id');
    Route::post('/resumes/{id}/ai/enhance-experience', [ResumeController::class, 'aiEnhanceExperience'])
        ->middleware('feature:ai-resume')
        ->whereNumber('id');
    Route::post('/resumes/{id}/ai/suggest-skills', [ResumeController::class, 'aiSuggestSkills'])
        ->middleware('feature:ai-resume')
        ->whereNumber('id');
    Route::post('/resumes/{id}/ai/draft', [ResumeController::class, 'aiDraft'])
        ->middleware('feature:ai-resume')
        ->whereNumber('id');
    Route::apiResource('/resumes', ResumeController::class)->parameters(['resumes' => 'id']);
});
