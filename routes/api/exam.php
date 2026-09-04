<?php

use App\Http\Controllers\Api\AnswerSheetController;
use App\Http\Controllers\Api\ExamAttemptController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ExamRatingController;
use App\Http\Controllers\Api\QuestionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authenticated exam / attempt / question routes — prefix: /api/v1
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'user.active', 'subscription.check', 'throttle:exams'])->group(function () {
    Route::post('/exams/{id}/rate', [ExamRatingController::class, 'store'])->whereNumber('id');

    Route::post('/exams', [ExamController::class, 'store'])->middleware(['role:super_admin,admin,operator', 'operator.perm']);
    Route::put('/exams/{id}', [ExamController::class, 'update'])->middleware(['role:super_admin,admin,operator', 'operator.perm']);
    Route::delete('/exams/{id}', [ExamController::class, 'destroy'])->middleware(['role:super_admin,admin,operator', 'operator.perm']);

    Route::post('/exams/{id}/start', [ExamAttemptController::class, 'start']);
    Route::post('/exams/{id}/submit/{attemptId}', [ExamAttemptController::class, 'submit']);
    Route::post('/exams/{id}/feedback/{attemptId}', [ExamAttemptController::class, 'feedback'])->whereNumber(['id', 'attemptId']);
    Route::get('/exams/{id}/result/{attemptId}', [ExamAttemptController::class, 'result']);
    Route::post('/exams/{id}/retry', [ExamAttemptController::class, 'retry']);
    Route::post('/exams/{id}/retry-wrong/{attemptId}', [ExamAttemptController::class, 'retryWrong']);

    Route::post('/exams/{id}/autosave/{attemptId}', [AnswerSheetController::class, 'autosave'])->whereNumber(['id', 'attemptId']);
    Route::get('/exams/{id}/autosave/{attemptId}', [AnswerSheetController::class, 'autosaved'])->whereNumber(['id', 'attemptId']);
    Route::get('/exams/{id}/answer-sheet/{attemptId}', [AnswerSheetController::class, 'show'])->whereNumber(['id', 'attemptId']);
    Route::get('/exams/{id}/report-card/{attemptId}', [AnswerSheetController::class, 'reportCard'])->whereNumber(['id', 'attemptId']);

    Route::post('/questions/import', [QuestionController::class, 'import'])->middleware(['role:super_admin,admin,operator', 'operator.perm']);
    Route::get('/questions/export', [QuestionController::class, 'export'])->middleware(['role:super_admin,admin,operator', 'operator.perm']);
    Route::apiResource('/questions', QuestionController::class)->middleware(['role:super_admin,admin,operator', 'operator.perm']);
});
