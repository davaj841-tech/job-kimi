<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $freePlan = SubscriptionPlan::query()->where('name', 'رایگان')->first();

        User::query()->updateOrCreate(
            ['mobile' => '09120000000'],
            [
                'username' => 'admin',
                'name' => 'مدیر سیستم',
                'email' => 'admin@jobazmoon.ir',
                // hashed via User cast — password has letter + number (min 8)
                'password' => 'admin1234',
                'role' => 'admin',
                'is_verified' => true,
                'subscription_plan_id' => $freePlan?->id,
            ]
        );
    }
}
