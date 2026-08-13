<?php

/**
 * LOCAL ONLY — dumps IDs for screenshot tooling. Never deploy/run in production.
 */
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('local', 'testing')) {
    fwrite(STDERR, "Refused: only allowed when APP_ENV=local|testing\n");
    exit(1);
}

$admin = User::query()->where('username', 'admin')->firstOrFail();
$demo = User::query()->where('id', '!=', $admin->id)->whereNotNull('username')->first()
    ?? User::query()->where('id', '!=', $admin->id)->firstOrFail();

$exam = Exam::query()->where('status', 'published')->orderBy('id')->firstOrFail();
$attempt = ExamAttempt::query()
    ->where('exam_id', $exam->id)
    ->where('status', 'completed')
    ->orderByDesc('id')
    ->first();

$out = [
    'admin' => [
        'id' => $admin->id,
        'username' => $admin->username,
    ],
    'user' => [
        'id' => $demo->id,
        'username' => $demo->username,
    ],
    'exam' => [
        'id' => $exam->id,
        'slug' => $exam->slug,
    ],
    'attempt' => $attempt ? [
        'id' => $attempt->id,
        'exam_id' => $attempt->exam_id,
    ] : null,
];

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
