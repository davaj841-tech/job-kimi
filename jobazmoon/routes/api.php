<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AnswerSheetController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminExamController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AIContentController;
use App\Http\Controllers\Api\Admin\AnalyticsAdminController;
use App\Http\Controllers\Api\Admin\AuditLogAdminController;
use App\Http\Controllers\Api\Admin\BackupAdminController;
use App\Http\Controllers\Api\Admin\BannerAdminController;
use App\Http\Controllers\Api\Admin\BlogPostAdminController;
use App\Http\Controllers\Api\Admin\CouponAdminController;
use App\Http\Controllers\Api\Admin\AggregationQualityController;
use App\Http\Controllers\Api\Admin\AggregationScheduleAdminController;
use App\Http\Controllers\Api\Admin\CrawlerRunAdminController;
use App\Http\Controllers\Api\Admin\JobSourceAdminController;
use App\Http\Controllers\Api\Admin\JobClassificationAdminController;
use App\Http\Controllers\Api\Admin\JobPostAdminController;
use App\Http\Controllers\Api\Admin\PageAdminController;
use App\Http\Controllers\Api\Admin\PDFProductAdminController;
use App\Http\Controllers\Api\Admin\SettingsAdminController;
use App\Http\Controllers\Api\Admin\SubscriptionAdminController;
use App\Http\Controllers\Api\Admin\TicketAdminController;
use App\Http\Controllers\Api\Admin\TransactionAdminController;
use App\Http\Controllers\Api\Admin\WalletAdminController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BlogPostController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExamAttemptController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ExamRatingController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\JobPostController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PageViewController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\PDFProductController;
use App\Http\Controllers\Api\ProfileExtrasController;
use App\Http\Controllers\Api\PublicSettingsController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SearchSuggestionController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\Admin\SiteErrorAdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/otp/send', [AuthController::class, 'sendOtp'])->middleware(['turnstile', 'throttle:otp']);
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware(['turnstile', 'throttle:login']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware(['turnstile', 'throttle:login']);
Route::post('/auth/register', [AuthController::class, 'register'])->middleware(['turnstile', 'throttle:login']);
Route::post('/auth/forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:otp');
Route::post('/auth/reset-password', [PasswordResetController::class, 'reset']);
Route::post('/auth/forgot-password/verify-otp', [PasswordResetController::class, 'verifyOtpReset'])->middleware('throttle:login');

Route::post('/admin/auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login');
Route::post('/admin/auth/forgot-password', [AdminAuthController::class, 'forgotPassword'])->middleware('throttle:admin-login');
Route::post('/coupons/validate', [CouponController::class, 'validateCode']);
Route::post('/page-views', [PageViewController::class, 'store']);
Route::get('/search', SearchController::class);
Route::get('/search/suggestions', SearchSuggestionController::class);
Route::get('/payment-gateways', [PaymentGatewayController::class, 'index']);

// Public Job Posts
Route::get('/exam-subjects', function () {
    return response()->json([
        'success' => true,
        'message' => 'عملیات موفق',
        'data' => \App\Models\ExamSubject::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon']),
    ]);
});

Route::get('/job-posts', [JobPostController::class, 'index']);
Route::get('/job-posts/filters', [JobPostController::class, 'filters']);
Route::get('/job-posts/{id}', [JobPostController::class, 'show'])->whereNumber('id');

// Public Blog
Route::get('/blog-posts', [BlogPostController::class, 'index']);
Route::get('/blog-posts/{slug}', [BlogPostController::class, 'show']);

// Public Subscription Plans + PDF Store listing
Route::get('/subscription-plans', [SubscriptionController::class, 'plans']);
Route::get('/pdf-products', [PDFProductController::class, 'index']);
Route::get('/pdf-products/{id}', [PDFProductController::class, 'show'])->whereNumber('id');

// Public exams catalog (لیست/جزئیات؛ شروع آزمون نیاز به ورود دارد)
Route::get('/exams', [ExamController::class, 'index']);
Route::get('/exams/{slug}', [ExamController::class, 'show']);

// Public contact form
Route::post('/contact', [ContactController::class, 'store']);
Route::get('/settings/public', [PublicSettingsController::class, 'index']);
Route::get('/banners', [BannerController::class, 'index']);
Route::get('/pages/{slug}', [PageController::class, 'show']);
Route::get('/leaderboard', [LeaderboardController::class, 'index']);
Route::get('/blog-posts/{id}/comments', [CommentController::class, 'index'])->whereNumber('id');

// ZarinPal callbacks (public — gateway redirects without Bearer token)
Route::match(['get', 'post'], '/wallet/verify', [WalletController::class, 'verify']);
Route::match(['get', 'post'], '/subscription/verify', [SubscriptionController::class, 'verifySubscription']);
Route::match(['get', 'post'], '/pdf-products/{id}/verify', [PDFProductController::class, 'verifyPurchase'])->whereNumber('id');

Route::middleware(['auth:sanctum', 'subscription.check'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/admin/auth/logout', [AdminAuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::post('/job-posts/submit', [JobPostController::class, 'submit']);

    // Wallet
    Route::get('/wallet', [WalletController::class, 'index']);
    Route::post('/wallet/charge', [WalletController::class, 'charge']);

    // Subscription
    Route::post('/subscription/subscribe', [SubscriptionController::class, 'subscribe']);

    // PDF Store
    Route::post('/pdf-products/{id}/purchase', [PDFProductController::class, 'purchase'])->whereNumber('id');
    Route::get('/pdf-products/{id}/download', [PDFProductController::class, 'download'])->whereNumber('id');
    Route::get('/my-purchases', [PDFProductController::class, 'myPurchases']);

    // Transactions + invoices
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}/invoice', [InvoiceController::class, 'download'])->whereNumber('id');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{id}', [TicketController::class, 'show'])->whereNumber('id');
    Route::post('/tickets/{id}/reply', [TicketController::class, 'reply'])->whereNumber('id');

    Route::post('/blog-posts/{id}/comments', [CommentController::class, 'store'])->whereNumber('id');
    Route::post('/exams/{id}/rate', [ExamRatingController::class, 'store'])->whereNumber('id');

    Route::get('/achievements', [ProfileExtrasController::class, 'achievements']);
    Route::get('/notification-preferences', [ProfileExtrasController::class, 'notificationPreferences']);
    Route::put('/notification-preferences', [ProfileExtrasController::class, 'updateNotificationPreferences']);

    // Resumes — custom routes قبل از apiResource
    Route::get('/resumes/{id}/pdf', [ResumeController::class, 'downloadPDF'])->whereNumber('id');
    Route::get('/resumes/{id}/preview', [ResumeController::class, 'preview'])->whereNumber('id');
    Route::put('/resumes/{id}/template', [ResumeController::class, 'updateTemplate'])->whereNumber('id');
    Route::post('/resumes/{id}/ai-suggest', [ResumeController::class, 'aiSuggest'])->whereNumber('id');
    Route::apiResource('/resumes', ResumeController::class)->parameters(['resumes' => 'id']);

    Route::post('/exams', [ExamController::class, 'store'])->middleware('role:admin,operator');
    Route::put('/exams/{id}', [ExamController::class, 'update']);
    Route::delete('/exams/{id}', [ExamController::class, 'destroy'])->middleware('role:admin,operator');

    Route::post('/exams/{id}/start', [ExamAttemptController::class, 'start']);
    Route::post('/exams/{id}/submit/{attemptId}', [ExamAttemptController::class, 'submit']);
    Route::get('/exams/{id}/result/{attemptId}', [ExamAttemptController::class, 'result']);
    Route::post('/exams/{id}/retry', [ExamAttemptController::class, 'retry']);
    Route::post('/exams/{id}/retry-wrong/{attemptId}', [ExamAttemptController::class, 'retryWrong']);

    Route::post('/exams/{id}/autosave/{attemptId}', [AnswerSheetController::class, 'autosave'])->whereNumber(['id', 'attemptId']);
    Route::get('/exams/{id}/autosave/{attemptId}', [AnswerSheetController::class, 'autosaved'])->whereNumber(['id', 'attemptId']);
    Route::get('/exams/{id}/answer-sheet/{attemptId}', [AnswerSheetController::class, 'show'])->whereNumber(['id', 'attemptId']);
    Route::get('/exams/{id}/report-card/{attemptId}', [AnswerSheetController::class, 'reportCard'])->whereNumber(['id', 'attemptId']);

    Route::post('/questions/import', [QuestionController::class, 'import'])->middleware('role:admin,operator');
    Route::get('/questions/export', [QuestionController::class, 'export'])->middleware('role:admin,operator');
    Route::apiResource('/questions', QuestionController::class)->middleware('role:admin,operator');

    Route::middleware('role:admin,operator')->prefix('admin')->group(function () {
        Route::get('/dashboard-stats', [AdminDashboardController::class, 'stats']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::get('/users/{id}', [AdminUserController::class, 'show'])->whereNumber('id');
        Route::put('/users/{id}', [AdminUserController::class, 'update'])->whereNumber('id');
        Route::put('/users/{id}/role', [AdminUserController::class, 'updateRole'])->whereNumber('id');
        Route::put('/users/{id}/status', [AdminUserController::class, 'updateStatus'])->whereNumber('id');
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->whereNumber('id');

        Route::get('/exam-subjects', [\App\Http\Controllers\Api\Admin\ExamSubjectAdminController::class, 'index']);
        Route::post('/exam-subjects', [\App\Http\Controllers\Api\Admin\ExamSubjectAdminController::class, 'store']);
        Route::put('/exam-subjects/{id}', [\App\Http\Controllers\Api\Admin\ExamSubjectAdminController::class, 'update'])->whereNumber('id');
        Route::delete('/exam-subjects/{id}', [\App\Http\Controllers\Api\Admin\ExamSubjectAdminController::class, 'destroy'])->whereNumber('id');

        Route::get('/exam-categories', [AdminExamController::class, 'categories']);
        Route::get('/exam-job-posts', [AdminExamController::class, 'jobPosts']);
        Route::get('/exams/{id}/stats', [AdminExamController::class, 'stats'])->whereNumber('id');
        Route::get('/exams', [AdminExamController::class, 'index']);
        Route::get('/exams/{id}', [AdminExamController::class, 'show'])->whereNumber('id');
        Route::post('/exams', [AdminExamController::class, 'store']);
        Route::put('/exams/{id}', [AdminExamController::class, 'update'])->whereNumber('id');
        Route::delete('/exams/{id}', [AdminExamController::class, 'destroy'])->whereNumber('id');

        Route::get('/questions', [QuestionController::class, 'index']);
        Route::post('/questions', [QuestionController::class, 'store']);
        Route::get('/questions/export', [QuestionController::class, 'export']);
        Route::get('/questions/import-sample', [QuestionController::class, 'importSample']);
        Route::post('/questions/import', [QuestionController::class, 'import']);
        Route::get('/exams/{id}/preview', [AdminExamController::class, 'preview'])->whereNumber('id');
        Route::post('/exams/{id}/practice/start', [AdminExamController::class, 'practiceStart'])->whereNumber('id');
        Route::post('/exams/{id}/practice/submit/{attemptId}', [AdminExamController::class, 'practiceSubmit'])->whereNumber('id')->whereNumber('attemptId');
        Route::get('/exams/{id}/practice/result/{attemptId}', [AdminExamController::class, 'practiceResult'])->whereNumber('id')->whereNumber('attemptId');
        Route::get('/questions/{question}', [QuestionController::class, 'show'])->whereNumber('question');
        Route::put('/questions/{question}', [QuestionController::class, 'update'])->whereNumber('question');
        Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])->whereNumber('question');

        Route::post('/job-posts/import', [JobPostAdminController::class, 'import']);
        Route::get('/job-posts/import-sample', [JobPostAdminController::class, 'importSample']);
        Route::get('/job-posts/filter-options', [JobPostAdminController::class, 'filterOptions']);
        Route::post('/job-posts/{id}/approve', [JobPostAdminController::class, 'approve'])->whereNumber('id');
        Route::post('/job-posts/{id}/reject', [JobPostAdminController::class, 'reject'])->whereNumber('id');
        Route::apiResource('/job-posts', JobPostAdminController::class)->parameters(['job-posts' => 'id']);

        Route::get('/job-sources/options', [JobSourceAdminController::class, 'options']);
        Route::post('/job-sources/{id}/approve', [JobSourceAdminController::class, 'approve'])->whereNumber('id');
        Route::post('/job-sources/{id}/unapprove', [JobSourceAdminController::class, 'unapprove'])->whereNumber('id');
        Route::post('/job-sources/{id}/enable', [JobSourceAdminController::class, 'enable'])->whereNumber('id');
        Route::post('/job-sources/{id}/disable', [JobSourceAdminController::class, 'disable'])->whereNumber('id');
        Route::post('/job-sources/{id}/test-crawl', [JobSourceAdminController::class, 'testCrawl'])->whereNumber('id');
        Route::post('/job-sources/{id}/reset-health', [JobSourceAdminController::class, 'resetHealth'])->whereNumber('id');
        Route::post('/job-sources/{id}/endpoints', [JobSourceAdminController::class, 'storeEndpoint'])->whereNumber('id');
        Route::put('/job-sources/{id}/endpoints/{endpointId}', [JobSourceAdminController::class, 'updateEndpoint'])->whereNumber('id')->whereNumber('endpointId');
        Route::delete('/job-sources/{id}/endpoints/{endpointId}', [JobSourceAdminController::class, 'destroyEndpoint'])->whereNumber('id')->whereNumber('endpointId');
        Route::get('/job-sources', [JobSourceAdminController::class, 'index']);
        Route::post('/job-sources', [JobSourceAdminController::class, 'store']);
        Route::get('/job-sources/{id}', [JobSourceAdminController::class, 'show'])->whereNumber('id');
        Route::put('/job-sources/{id}', [JobSourceAdminController::class, 'update'])->whereNumber('id');
        Route::delete('/job-sources/{id}', [JobSourceAdminController::class, 'destroy'])->whereNumber('id');

        Route::get('/crawler-runs', [CrawlerRunAdminController::class, 'index']);
        Route::get('/crawler-runs/errors', [CrawlerRunAdminController::class, 'errors']);
        Route::get('/crawler-runs/{id}', [CrawlerRunAdminController::class, 'show'])->whereNumber('id');

        Route::get('/aggregation/quality-stats', [AggregationQualityController::class, 'stats']);
        Route::get('/aggregation/pending-jobs', [AggregationQualityController::class, 'pendingJobs']);
        Route::get('/aggregation/jobs/{id}', [AggregationQualityController::class, 'showJob'])->whereNumber('id');
        Route::put('/aggregation/jobs/{id}', [AggregationQualityController::class, 'updateJob'])->whereNumber('id');
        Route::post('/aggregation/jobs/{id}/approve', [AggregationQualityController::class, 'approveJob'])->whereNumber('id');
        Route::post('/aggregation/jobs/{id}/reject', [AggregationQualityController::class, 'rejectJob'])->whereNumber('id');

        Route::get('/aggregation-schedule', [AggregationScheduleAdminController::class, 'show']);
        Route::put('/aggregation-schedule', [AggregationScheduleAdminController::class, 'update']);
        Route::post('/aggregation-schedule/times', [AggregationScheduleAdminController::class, 'addTime']);
        Route::put('/aggregation-schedule/times/{id}', [AggregationScheduleAdminController::class, 'updateTime']);
        Route::delete('/aggregation-schedule/times/{id}', [AggregationScheduleAdminController::class, 'removeTime']);
        Route::post('/aggregation-schedule/dispatch-now', [AggregationScheduleAdminController::class, 'dispatchNow']);

        Route::post('/job-classifications/reorder', [JobClassificationAdminController::class, 'reorder']);
        Route::apiResource('/job-classifications', JobClassificationAdminController::class)
            ->parameters(['job-classifications' => 'id']);

        Route::post('/blog-posts/{id}/publish', [BlogPostAdminController::class, 'publish'])->whereNumber('id');
        Route::post('/blog-posts/{id}/draft', [BlogPostAdminController::class, 'draft'])->whereNumber('id');
        Route::apiResource('/blog-posts', BlogPostAdminController::class)->parameters(['blog-posts' => 'id']);

        Route::post('/ai/crawl-jobs', [AIContentController::class, 'crawlJobs']);
        Route::post('/ai/generate-blog', [AIContentController::class, 'generateBlog']);
        Route::post('/ai/generate-questions', [AIContentController::class, 'generateQuestions']);
        Route::get('/ai/stats', [AIContentController::class, 'stats']);
        Route::get('/ai/contents', [AIContentController::class, 'index']);
        Route::get('/ai/contents/{id}', [AIContentController::class, 'show'])->whereNumber('id');
        Route::post('/ai/contents/{id}/approve', [AIContentController::class, 'approve'])->whereNumber('id');
        Route::post('/ai/contents/{id}/reject', [AIContentController::class, 'reject'])->whereNumber('id');
        Route::delete('/ai/contents/{id}', [AIContentController::class, 'destroy'])->whereNumber('id');

        Route::apiResource('/pdf-products', PDFProductAdminController::class)->parameters(['pdf-products' => 'id']);

        Route::get('/transactions/stats', [TransactionAdminController::class, 'stats']);
        Route::get('/transactions', [TransactionAdminController::class, 'index']);
        Route::get('/transactions/{id}', [TransactionAdminController::class, 'show'])->whereNumber('id');
        Route::post('/transactions/{id}/regenerate-invoice', [InvoiceController::class, 'regenerate'])->whereNumber('id');

        Route::get('/coupons', [CouponAdminController::class, 'index']);
        Route::post('/coupons', [CouponAdminController::class, 'store']);
        Route::put('/coupons/{id}', [CouponAdminController::class, 'update'])->whereNumber('id');
        Route::delete('/coupons/{id}', [CouponAdminController::class, 'destroy'])->whereNumber('id');

        Route::get('/subscriptions/stats', [SubscriptionAdminController::class, 'stats']);
        Route::get('/subscriptions/plans', [SubscriptionAdminController::class, 'plans']);
        Route::post('/subscriptions/plans', [SubscriptionAdminController::class, 'storePlan']);
        Route::put('/subscriptions/plans/{id}', [SubscriptionAdminController::class, 'updatePlan'])->whereNumber('id');
        Route::delete('/subscriptions/plans/{id}', [SubscriptionAdminController::class, 'destroyPlan'])->whereNumber('id');
        Route::get('/subscriptions/subscribers', [SubscriptionAdminController::class, 'subscribers']);
        Route::post('/subscriptions/subscribers/{id}/renew', [SubscriptionAdminController::class, 'renew'])->whereNumber('id');
        Route::post('/subscriptions/subscribers/{id}/cancel', [SubscriptionAdminController::class, 'cancel'])->whereNumber('id');

        Route::get('/wallets/stats', [WalletAdminController::class, 'stats']);
        Route::get('/wallets', [WalletAdminController::class, 'index']);
        Route::get('/wallets/{id}/history', [WalletAdminController::class, 'history'])->whereNumber('id');
        Route::post('/wallets/{id}/charge', [WalletAdminController::class, 'charge'])->whereNumber('id');
        Route::post('/wallets/{id}/deduct', [WalletAdminController::class, 'deduct'])->whereNumber('id');

        Route::get('/settings', [SettingsAdminController::class, 'index']);
        Route::put('/settings', [SettingsAdminController::class, 'update']);
        Route::post('/settings/upload-logo', [SettingsAdminController::class, 'uploadLogo']);

        Route::get('/tickets', [TicketAdminController::class, 'index']);
        Route::put('/tickets/{id}/status', [TicketAdminController::class, 'updateStatus'])->whereNumber('id');

        Route::get('/banners', [BannerAdminController::class, 'index']);
        Route::post('/banners', [BannerAdminController::class, 'store']);
        Route::put('/banners/{id}', [BannerAdminController::class, 'update'])->whereNumber('id');
        Route::delete('/banners/{id}', [BannerAdminController::class, 'destroy'])->whereNumber('id');

        Route::get('/pages', [PageAdminController::class, 'index']);
        Route::post('/pages', [PageAdminController::class, 'store']);
        Route::put('/pages/{id}', [PageAdminController::class, 'update'])->whereNumber('id');
        Route::delete('/pages/{id}', [PageAdminController::class, 'destroy'])->whereNumber('id');

        Route::get('/analytics/visits', [AnalyticsAdminController::class, 'visits']);
        Route::get('/analytics/top-pages', [AnalyticsAdminController::class, 'topPages']);
        Route::get('/analytics/devices', [AnalyticsAdminController::class, 'devices']);

        Route::get('/backups', [BackupAdminController::class, 'index']);
        Route::post('/backups', [BackupAdminController::class, 'store']);
        Route::get('/backups/download', [BackupAdminController::class, 'download']);
        Route::delete('/backups', [BackupAdminController::class, 'destroy']);

        Route::get('/audit-logs', [AuditLogAdminController::class, 'index']);
        Route::get('/site-errors', [SiteErrorAdminController::class, 'index']);
        Route::get('/site-errors/{id}', [SiteErrorAdminController::class, 'show'])->whereNumber('id');
        Route::post('/site-errors/{id}/resolve', [SiteErrorAdminController::class, 'resolve'])->whereNumber('id');
        Route::delete('/site-errors/{id}', [SiteErrorAdminController::class, 'destroy'])->whereNumber('id');
        Route::delete('/site-errors', [SiteErrorAdminController::class, 'clearResolved']);
    });
});
