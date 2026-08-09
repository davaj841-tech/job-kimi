<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Exam> */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(5),
            'category_id' => ExamCategory::factory(),
            'job_post_id' => null,
            'description' => fake()->paragraph(),
            'duration_minutes' => 60,
            'passing_score' => 50,
            'total_questions' => 0,
            'total_marks' => 100,
            'is_free' => true,
            'price' => 0,
            'subscription_required' => 'any',
            'status' => 'published',
            'created_by' => User::factory(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'is_free' => false,
            'price' => 500000,
            'subscription_required' => 'paid',
        ]);
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'is_free' => true,
            'price' => 0,
            'subscription_required' => 'free',
        ]);
    }
}
