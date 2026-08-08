<?php

namespace Database\Factories;

use App\Models\JobPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPost>
 */
class JobPostFactory extends Factory
{
    protected $model = JobPost::class;

    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'company_name' => fake()->company(),
            'description' => fake()->paragraph(),
            'province' => 'تهران',
            'provinces' => ['تهران'],
            'city' => 'تهران',
            'status' => 'pending',
            'is_featured' => false,
            'view_count' => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }
}
