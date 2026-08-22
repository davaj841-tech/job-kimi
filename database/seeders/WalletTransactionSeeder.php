<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Seeder;

/** تراکنش‌های کیف پول روی جدول transactions. */
class WalletTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->pluck('id');
        if ($users->isEmpty()) {
            $this->call(UserSeeder::class);
            $users = User::query()->pluck('id');
        }

        WalletTransactionFactory::new()
            ->count(50)
            ->state(fn () => [
                'user_id' => $users->random(),
            ])
            ->create();
    }
}
