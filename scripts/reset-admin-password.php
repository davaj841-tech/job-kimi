<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::query()->where('username', 'admin')->first();
if (! $user) {
    fwrite(STDERR, "admin not found\n");
    exit(1);
}

$user->password = 'admin1234';
$user->status = 'active';
$user->role = 'admin';
$user->save();

$ok = Hash::check('admin1234', $user->fresh()->password);
echo "reset admin id={$user->id} hash_ok=".($ok ? 'yes' : 'no').PHP_EOL;
