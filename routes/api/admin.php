<?php

use App\Http\Controllers\Api\Admin\GeneratedContentAdminController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminExamController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AggregationQualityController;
use App\Http\Controllers\Api\Admin\AggregationScheduleAdminController;
use App\Http\Controllers\Api\Admin\AIContentController;
use App\Http\Controllers\Api\Admin\AnalyticsAdminController;
use App\Http\Controllers\Api\Admin\AuditLogAdminController;
use App\Http\Controllers\Api\Admin\BackupAdminController;
use App\Http\Controllers\Api\Admin\BannerAdminController;
use App\Http\Controllers\Api\Admin\BlogPostAdminController;
use App\Http\Controllers\Api\Admin\CouponAdminController;
use App\Http\Controllers\Api\Admin\CrawlerRunAdminController;
use App\Http\Controllers\Api\Admin\ExamSubjectAdminController;
use App\Http\Controllers\Api\Admin\JobClassificationAdminController;
use App\Http\Controllers\Api\Admin\JobPostAdminController;
use App\Http\Controllers\Api\Admin\JobSourceAdminController;
use App\Http\Controllers\Api\Admin\PageAdminController;
use App\Http\Controllers\Api\Admin\PDFProductAdminController;
use App\Http\Controllers\Api\Admin\SettingsAdminController;
use App\Http\Controllers\Api\Admin\SiteErrorAdminController;
use App\Http\Controllers\Api\Admin\SubscriptionAdminController;
use App\Http\Controllers\Api\Admin\TicketAdminController;
use App\Http\Controllers\Api\Admin\TransactionAdminController;
use App\Http\Controllers\Api\Admin\WalletAdminController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\QuestionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin API routes — prefix: /api/v1/admin
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'subscription.check', 'role:admin,operator', 'operator.perm'])->prefix('admin')->group(function () {
    Route::get('/dashboard-stats', [AdminDashboardController::class, 'stats']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::get('/users/{id}', [AdminUserController::class, 'show'])->whereNumber('id');
    Route::put('/users/{id}', [AdminUserController::class, 'update'])->whereNumber('id');
    Route::put('/users/{id}/role', [AdminUserController::class, 'updateRole'])->whereNumber('id');
    Route::put('/users/{id}/status', [AdminUserController::class, 'updateStatus'])->whereNumber('id');
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->whereNumber('id');

    Route::get('/exam-subjects', [ExamSubjectAdminController::class, 'index']);
    Route::post('/exam-subjects', [ExamSubjectAdminController::class, 'store']);
    Route::put('/exam-subjects/{id}', [ExamSubjectAdminController::class, 'update'])->whereNumber('id');
    Route::delete('/exam-subjects/{id}', [ExamSubjectAdminController::class, 'destroy'])->whereNumber('id');

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
    Route::post('/crawler-runs/prune-failed', [CrawlerRunAdminController::class, 'pruneFailed']);
    Route::get('/crawler-runs/{id}', [CrawlerRunAdminController::class, 'show'])->whereNumber('id');
    Route::delete('/crawler-runs/{id}', [CrawlerRunAdminController::class, 'destroy'])->whereNumber('id');

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
    Route::get('/audit-logs/report', [AuditLogAdminController::class, 'report']);
    Route::delete('/audit-logs', [AuditLogAdminController::class, 'destroyRange']);

    Route::get('/site-errors', [SiteErrorAdminController::class, 'index']);
    Route::get('/site-errors/export', [SiteErrorAdminController::class, 'export']);
    Route::post('/site-errors/auto-heal', [SiteErrorAdminController::class, 'autoHeal']);
    Route::get('/site-errors/{id}', [SiteErrorAdminController::class, 'show'])->whereNumber('id');
    Route::post('/site-errors/{id}/resolve', [SiteErrorAdminController::class, 'resolve'])->whereNumber('id');
    Route::delete('/site-errors/{id}', [SiteErrorAdminController::class, 'destroy'])->whereNumber('id');
    Route::delete('/site-errors', [SiteErrorAdminController::class, 'clearResolved']);

    Route::get('/generated-contents/dashboard', [GeneratedContentAdminController::class, 'dashboard']);
    Route::get('/generated-contents/settings', [GeneratedContentAdminController::class, 'settings']);
    Route::get('/generated-contents/templates', [GeneratedContentAdminController::class, 'templates']);
    Route::put('/generated-contents/templates/{id}', [GeneratedContentAdminController::class, 'updateTemplate'])->whereNumber('id');
    Route::post('/generated-contents/generate-now', [GeneratedContentAdminController::class, 'generateNow']);
    Route::get('/generated-contents', [GeneratedContentAdminController::class, 'index']);
    Route::get('/generated-contents/{id}', [GeneratedContentAdminController::class, 'show'])->whereNumber('id');
    Route::put('/generated-contents/{id}', [GeneratedContentAdminController::class, 'update'])->whereNumber('id');
    Route::delete('/generated-contents/{id}', [GeneratedContentAdminController::class, 'destroy'])->whereNumber('id');
    Route::post('/generated-contents/{id}/regenerate', [GeneratedContentAdminController::class, 'regenerate'])->whereNumber('id');
    Route::post('/generated-contents/{id}/publish', [GeneratedContentAdminController::class, 'publish'])->whereNumber('id');
    Route::post('/generated-contents/{id}/unpublish', [GeneratedContentAdminController::class, 'unpublish'])->whereNumber('id');
});
