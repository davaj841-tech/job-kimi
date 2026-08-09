<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        // قیمت‌ها به ریال — بعداً از پنل ادمین قابل تغییر هستند
        $plans = [
            [
                'name' => 'رایگان',
                'duration_days' => 0,
                'price' => 0,
                'features' => ['free_plan_exam_limit'],
                'is_active' => true,
            ],
            [
                'name' => 'یک‌ماهه',
                'duration_days' => 30,
                'price' => 990000,
                'features' => ['unlimited_exams', 'pdf_discount'],
                'is_active' => true,
            ],
            [
                'name' => 'سه‌ماهه',
                'duration_days' => 90,
                'price' => 2490000,
                'features' => ['unlimited_exams', 'pdf_discount', 'priority_support'],
                'is_active' => true,
            ],
            [
                'name' => 'شش‌ماهه',
                'duration_days' => 180,
                'price' => 4490000,
                'features' => ['unlimited_exams', 'pdf_discount', 'priority_support', 'ai_boost'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}
