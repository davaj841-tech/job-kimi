<?php

use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BlogPostController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\FeatureController;
use App\Http\Controllers\Api\JobPostController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PageViewController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\PDFProductController;
use App\Http\Controllers\Api\PublicSettingsController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SearchSuggestionController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Models\ExamSubject;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public catalog / content routes — prefix: /api/v1
|--------------------------------------------------------------------------
*/

Route::post('/coupons/validate', [CouponController::class, 'validateCode']);
Route::post('/page-views', [PageViewController::class, 'store']);
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

Route::get('/blog-posts', [BlogPostController::class, 'index']);
Route::get('/blog-posts/{slug}', [BlogPostController::class, 'show']);

Route::get('/articles', [\App\Http\Controllers\Api\GeneratedContentPublicController::class, 'index']);
Route::get('/articles/{slug}', [\App\Http\Controllers\Api\GeneratedContentPublicController::class, 'show']);

Route::get('/subscription-plans', [SubscriptionController::class, 'plans']);
Route::get('/pdf-products', [PDFProductController::class, 'index']);
Route::get('/pdf-products/{id}', [PDFProductController::class, 'show'])->whereNumber('id');

Route::get('/exams', [ExamController::class, 'index'])->middleware('cache.response:300');
Route::get('/exams/{slug}', [ExamController::class, 'show']);

Route::post('/contact', [ContactController::class, 'store']);
Route::get('/home-feed', \App\Http\Controllers\Api\HomeFeedController::class)
    ->middleware('cache.response:120');
Route::get('/settings/public', [PublicSettingsController::class, 'index'])->middleware('cache.response:300');
Route::get('/banners', [BannerController::class, 'index'])->middleware('cache.response:300');
Route::get('/pages/{slug}', [PageController::class, 'show']);
Route::get('/leaderboard', [LeaderboardController::class, 'index']);
Route::get('/blog-posts/{id}/comments', [CommentController::class, 'index'])->whereNumber('id');
