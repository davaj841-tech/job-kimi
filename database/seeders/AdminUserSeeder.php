<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $freePlan = SubscriptionPlan::query()->where('name', 'رایگان')->first();

        // Prefer explicit seed password; never ship a known default to production hosts.
        $password = (string) env('ADMIN_SEED_PASSWORD', '');
        if ($password === '' || strlen($password) < 8) {
            $password = Str::password(16);
            if ($this->command) {
                $this->command->warn(
                    'ADMIN_SEED_PASSWORD not set — generated a one-time password. Set ADMIN_SEED_PASSWORD before seeding production.'
                );
                $this->command->warn('Generated admin password (save now): '.$password);
            }
        }

        User::query()->updateOrCreate(
            ['mobile' => '09120000000'],
            [
                'username' => 'admin',
                'name' => 'مدیر سیستم',
                'email' => 'admin@jobazmoon.ir',
                // hashed via User cast
                'password' => $password,
                'role' => 'admin',
                'is_verified' => true,
                'subscription_plan_id' => $freePlan?->id,
            ]
        );
    }
}
