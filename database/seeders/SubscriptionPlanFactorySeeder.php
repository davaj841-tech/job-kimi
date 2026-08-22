<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanFactorySeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::factory()->count(50)->create();
    }
}
