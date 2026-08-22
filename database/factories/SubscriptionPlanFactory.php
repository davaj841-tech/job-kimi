<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SubscriptionPlan> */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        $days = fake()->randomElement([30, 90, 180, 365]);
        $prices = [
            30 => 990_000,
            90 => 2_490_000,
            180 => 4_490_000,
            365 => 7_990_000,
        ];

        return [
            'name' => match ($days) {
                30 => 'یک‌ماهه',
                90 => 'سه‌ماهه',
                180 => 'شش‌ماهه',
                default => 'یک‌ساله',
            }.' '.fake()->unique()->numerify('##'),
            'duration_days' => $days,
            'price' => $prices[$days],
            'features' => fake()->randomElements(
                ['unlimited_exams', 'pdf_discount', 'priority_support', 'ai_boost', 'resume_builder'],
                fake()->numberBetween(1, 4)
            ),
            'is_active' => true,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'name' => 'رایگان',
            'duration_days' => 0,
            'price' => 0,
            'features' => ['free_plan_exam_limit'],
        ]);
    }

    public function paid(int $days = 30, int $price = 990000): static
    {
        return $this->state(fn () => [
            'name' => 'اشتراک پولی '.$days.'روزه',
            'duration_days' => $days,
            'price' => $price,
            'features' => ['unlimited_exams', 'pdf_discount'],
            'is_active' => true,
        ]);
    }
}
