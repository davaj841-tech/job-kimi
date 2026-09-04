<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

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
                    'ADMIN_SEED_PASSWORD not set or shorter than 8 chars — generated a one-time password. Set ADMIN_SEED_PASSWORD before seeding production.'
                );
                $this->command->warn('Generated admin password (save now): '.$password);
            }
        }

        $mobile = trim((string) env('ADMIN_SEED_MOBILE', ''));
        $username = trim((string) env('ADMIN_SEED_USERNAME', ''));
        $email = trim((string) env('ADMIN_SEED_EMAIL', ''));

        if ($mobile === '' || $username === '' || $email === '') {
            $message = 'AdminUserSeeder requires ADMIN_SEED_MOBILE, ADMIN_SEED_USERNAME, and ADMIN_SEED_EMAIL in .env (ADMIN_SEED_PASSWORD optional, min 8).';
            if ($this->command) {
                $this->command->error($message);
            }

            throw new RuntimeException($message);
        }

        User::query()->updateOrCreate(
            ['mobile' => $mobile],
            [
                'username' => $username,
                'name' => 'مدیر سیستم',
                'email' => $email,
                // hashed via User cast
                'password' => $password,
                'role' => 'super_admin',
                'status' => 'active',
                'is_verified' => true,
                'subscription_plan_id' => $freePlan?->id,
            ]
        );
    }
}
