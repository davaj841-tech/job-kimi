<?php

namespace Database\Factories;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Resume> */
class ResumeFactory extends Factory
{
    protected $model = Resume::class;

    public function definition(): array
    {
        $first = fake()->randomElement(['علی', 'زهرا', 'محمد', 'سارا']);
        $last = fake()->randomElement(['محمدی', 'حسینی', 'رضایی']);

        return [
            'user_id' => User::factory(),
            'template_id' => fake()->numberBetween(1, 5),
            'title' => 'رزومه '.$first.' '.$last,
            'data' => [
                'personal' => [
                    'full_name' => $first.' '.$last,
                    'mobile' => '09'.fake()->numerify('#########'),
                    'email' => fake()->safeEmail(),
                    'city' => 'تهران',
                ],
                'education' => [
                    ['degree' => 'کارشناسی', 'field' => 'مهندسی کامپیوتر', 'university' => 'دانشگاه تهران'],
                ],
                'experience' => [
                    ['title' => 'کارشناس', 'company' => 'شرکت نمونه', 'from' => '1400', 'to' => '1402'],
                ],
                'skills' => ['Excel', 'PowerPoint', 'زبان انگلیسی'],
            ],
            'pdf_path' => null,
            'is_active' => true,
        ];
    }
}
