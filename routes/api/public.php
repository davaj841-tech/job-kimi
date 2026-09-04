<?php

use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BlogPostController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\FeatureController;
use App\Http\Controllers\Api\GeneratedContentPublicController;
use App\Http\Controllers\Api\HomeFeedController;
use App\Http\Controllers\Api\JobPostCommentController;
use App\Http\Controllers\Api\JobPostController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PageViewController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\PDFProductController;
use App\Http\Controllers\Api\PublicSettingsController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SearchSuggestionController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\HealthController;
use App\Models\ExamSubject;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public catalog / content routes — prefix: /api/v1
|--------------------------------------------------------------------------
*/

Route::post('/coupons/validate', [CouponController::class, 'validateCode'])->middleware('throttle:coupon');
Route::post('/page-views', [PageViewController::class, 'store'])->middleware('throttle:30,1');

// Health check for uptime monitoring (no auth required).
Route::get('/health', HealthController::class)->name('api.health');

Route::get('/features', [FeatureController::class, 'index']);
Route::get('/search', SearchController::class);
Route::get('/search/suggestions', SearchSuggestionController::class);
Route::get('/payment-gateways', [PaymentGatewayController::class, 'index']);

Route::get('/exam-subjects', function () {
    return response()->json([
        'success' => true,
        'message' => 'عملیات موفق',
        'data' => ExamSubject::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon']),
    ]);
})->middleware('cache.response:600');

Route::get('/job-posts', [JobPostController::class, 'index'])->middleware('cache.response:120');
Route::get('/job-posts/filters', [JobPostController::class, 'filters']);
Route::get('/job-posts/{id}', [JobPostController::class, 'show'])->whereNumber('id');
Route::get('/job-posts/{id}/comments', [JobPostCommentController::class, 'index'])->whereNumber('id');

Route::get('/blog-posts', [BlogPostController::class, 'index']);
Route::get('/blog-posts/{slug}', [BlogPostController::class, 'show']);

Route::get('/articles', [GeneratedContentPublicController::class, 'index']);
Route::get('/articles/{slug}', [GeneratedContentPublicController::class, 'show']);

Route::get('/subscription-plans', [SubscriptionController::class, 'plans']);
Route::get('/pdf-products', [PDFProductController::class, 'index']);
Route::get('/pdf-products/{id}', [PDFProductController::class, 'show'])->whereNumber('id');

Route::get('/exams', [ExamController::class, 'index'])->middleware(['throttle:exams', 'cache.response:300']);
Route::get('/exams/{slug}', [ExamController::class, 'show'])->middleware('throttle:exams');

Route::post('/contact', [ContactController::class, 'store'])->middleware(['auth.captcha:contact', 'throttle:contact']);
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:newsletter');
Route::get('/home-feed', HomeFeedController::class);
// No cache.response: theme/font changes must appear on the public site immediately.
// Controller still uses a short Laravel cache that Setting::set / ThemeBootstrap::forget clear.
Route::get('/settings/public', [PublicSettingsController::class, 'index']);
Route::get('/banners', [BannerController::class, 'index'])->middleware('cache.response:300');
Route::get('/pages/{slug}', [PageController::class, 'show']);
Route::get('/leaderboard', [LeaderboardController::class, 'index']);
Route::get('/blog-posts/{id}/comments', [CommentController::class, 'index'])->whereNumber('id');
