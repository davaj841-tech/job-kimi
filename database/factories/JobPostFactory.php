<?php

namespace Database\Factories;

use App\Models\JobPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JobPost> */
class JobPostFactory extends Factory
{
    protected $model = JobPost::class;

    /** @var list<string> */
    private const TITLES = [
        'استخدام کارشناس بانک',
        'فراخوان نیروی انتظامی',
        'جذب معلم آموزش و پرورش',
        'استخدام کارشناس شهرداری',
        'فرصت شغلی وزارت نفت',
        'استخدام پرستار و بهیار',
    ];

    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(self::TITLES).' — '.fake()->numerify('##'),
            'company_name' => fake()->randomElement(['بانک ملی', 'نیروی انتظامی', 'آموزش و پرورش', 'شهرداری تهران']),
            'description' => 'شرح کامل آگهی استخدامی و شرایط ثبت‌نام.',
            'requirements' => 'مدرک تحصیلی مرتبط و کارت پایان خدمت (آقایان).',
            'education' => fake()->randomElement(['کارشناسی', 'کاردانی', 'دیپلم']),
            'employment_type' => fake()->randomElement(['full_time', 'contract']),
            'province' => fake()->randomElement(['تهران', 'اصفهان', 'فارس', 'خراسان رضوی']),
            'provinces' => ['تهران'],
            'city' => 'مرکز استان',
            'job_category' => fake()->randomElement(['bank', 'police', 'education', 'municipality']),
            'registration_deadline' => now()->addDays(fake()->numberBetween(7, 60)),
            'published_at' => now()->subDays(fake()->numberBetween(0, 10)),
            'status' => 'approved',
            'is_featured' => fake()->boolean(20),
            'view_count' => fake()->numberBetween(0, 5000),
            'created_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved', 'published_at' => now()]);
    }
}
