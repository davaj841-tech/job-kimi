<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

/**
 * LOCAL ONLY — resets admin password. Never run on production hosts.
 *
 * Usage:
 *   ADMIN_RESET_PASSWORD='YourNewPass1' php scripts/reset-admin-password.php
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('local', 'testing')) {
    fwrite(STDERR, "Refused: only allowed when APP_ENV=local|testing\n");
    exit(1);
}

$password = (string) env('ADMIN_RESET_PASSWORD', '');
if (strlen($password) < 8) {
    fwrite(STDERR, "Set ADMIN_RESET_PASSWORD (min 8 chars) in the environment.\n");
    exit(1);
}

$user = User::query()->where('username', 'admin')->first();
if (! $user) {
    fwrite(STDERR, "admin not found\n");
    exit(1);
}

$user->password = $password;
$user->status = 'active';
$user->role = 'admin';
$user->save();

$ok = Hash::check($password, $user->fresh()->password);
echo 'reset admin id='.$user->id.' hash_ok='.($ok ? 'yes' : 'no').PHP_EOL;
