<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->pluck('id');
        if ($users->isEmpty()) {
            $this->call(UserSeeder::class);
            $users = User::query()->pluck('id');
        }

        Ticket::factory()
            ->count(50)
            ->state(fn () => [
                'user_id' => $users->random(),
            ])
            ->create();
    }
}
