<?php

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

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
        'password' => 'admin1234',
    ],
    'user' => [
        'id' => $demo->id,
        'username' => $demo->username,
        'login' => $demo->username ?: $demo->email,
        'mobile' => $demo->mobile,
    ],
    'exam' => [
        'id' => $exam->id,
        'slug' => $exam->slug,
        'title' => $exam->title,
    ],
    'attempt' => $attempt ? [
        'id' => $attempt->id,
        'exam_id' => $attempt->exam_id,
        'user_id' => $attempt->user_id,
    ] : null,
];

file_put_contents(__DIR__.'/screenshot-context.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo json_encode($out, JSON_UNESCAPED_UNICODE).PHP_EOL;
