<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class AdminCreateCommand extends Command
{
    protected $signature = 'admin:create
                            {--username= : Admin username}
                            {--password= : Admin password}
                            {--name= : Display name}
                            {--mobile= : Optional mobile for OTP backup}
                            {--email= : Optional email for password reset}
                            {--role=super_admin : super_admin, admin, or operator}';

    protected $description = 'Create or update an admin/operator user with username/password login';

    public function handle(): int
    {
        $username = $this->option('username') ?: $this->ask('Username', 'admin');
        $password = $this->option('password') ?: $this->secret('Password');
        $name = $this->option('name') ?: $this->ask('Name', 'Administrator');
        $mobile = $this->option('mobile') ?: $this->ask('Mobile (optional, for OTP backup)', '09120000000');
        $email = $this->option('email') ?: $this->ask('Email (optional, for password reset)', 'admin@jobazmoon.ir');
        $role = $this->option('role') ?: $this->choice('Role', ['super_admin', 'admin', 'operator'], 0);

        $validator = Validator::make([
            'username' => $username,
            'password' => $password,
            'mobile' => $mobile,
            'email' => $email ?: null,
            'role' => $role,
        ], [
            'username' => ['required', 'regex:/^[a-z0-9_]{3,20}$/'],
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'],
            'mobile' => ['required', 'regex:/^09\d{9}$/'],
            'email' => ['nullable', 'email'],
            'role' => ['required', 'in:super_admin,admin,operator'],
        ], [
            'username.regex' => 'Username must be 3-20 chars: a-z, 0-9, underscore.',
            'password.regex' => 'Password must contain at least one letter and one number.',
            'mobile.regex' => 'Mobile must be a valid Iranian number (09xxxxxxxxx).',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $freePlan = SubscriptionPlan::query()->where('price', 0)->first()
            ?? SubscriptionPlan::query()->where('name', 'رایگان')->first();

        $existingByUsername = User::query()->where('username', $username)->first();
        $existingByMobile = User::query()->where('mobile', $mobile)->first();

        if ($existingByUsername && $existingByMobile && $existingByUsername->id !== $existingByMobile->id) {
            $this->error('Username and mobile belong to different users.');

            return self::FAILURE;
        }

        $user = $existingByUsername ?: $existingByMobile;

        $payload = [
            'username' => $username,
            'password' => $password,
            'name' => $name,
            'mobile' => $mobile,
            'email' => $email ?: null,
            'role' => $role,
            'is_verified' => true,
            'status' => 'active',
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ];

        if ($freePlan && (! $user || $user->subscription_plan_id === null)) {
            $payload['subscription_plan_id'] = $freePlan->id;
        }

        if ($user) {
            $user->update($payload);
            $this->info("Updated {$role} user #{$user->id} ({$username}).");
        } else {
            $user = User::query()->create($payload);
            $this->info("Created {$role} user #{$user->id} ({$username}).");
        }

        $this->line('Login at /admin/login with username/password.');

        return self::SUCCESS;
    }
}
