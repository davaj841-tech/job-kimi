<?php

namespace Database\Seeders;

use App\Models\JobPost;
use App\Models\User;
use Illuminate\Database\Seeder;

class JobPostSeeder extends Seeder
{
    public function run(): void
    {
        $creators = User::query()->pluck('id');
        if ($creators->isEmpty()) {
            $this->call(UserSeeder::class);
            $creators = User::query()->pluck('id');
        }

        JobPost::factory()
            ->count(50)
            ->state(fn () => [
                'created_by' => $creators->random(),
            ])
            ->create();
    }
}
