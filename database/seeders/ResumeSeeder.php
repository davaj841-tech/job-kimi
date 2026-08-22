<?php

namespace Database\Seeders;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Database\Seeder;

class ResumeSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->pluck('id');
        if ($users->isEmpty()) {
            $this->call(UserSeeder::class);
            $users = User::query()->pluck('id');
        }

        Resume::factory()
            ->count(50)
            ->state(fn () => [
                'user_id' => $users->random(),
            ])
            ->create();
    }
}
