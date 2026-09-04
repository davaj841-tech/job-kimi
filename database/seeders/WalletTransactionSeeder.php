<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Seeder;

/** Demo transactions only — bypasses WalletService; do NOT run on production. */
class WalletTransactionSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('WalletTransactionSeeder skipped in production (would desync ledger).');

            return;
        }
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
