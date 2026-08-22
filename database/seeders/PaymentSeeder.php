<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Seeder;

/** پرداخت‌ها روی جدول transactions (مدل Transaction). */
class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->pluck('id');
        if ($users->isEmpty()) {
            $this->call(UserSeeder::class);
            $users = User::query()->pluck('id');
        }

        PaymentFactory::new()
            ->count(50)
            ->state(fn () => [
                'user_id' => $users->random(),
            ])
            ->create();
    }
}
