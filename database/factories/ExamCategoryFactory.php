<?php

namespace Database\Factories;

use App\Models\ExamCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExamCategory> */
class ExamCategoryFactory extends Factory
{
    protected $model = ExamCategory::class;

    /** @var array<string, string> slug => نام فارسی */
    public const CATEGORIES = [
        'bank' => 'بانک',
        'police' => 'نیروی انتظامی',
        'military' => 'ارتش / سپاه',
        'education' => 'آموزش و پرورش',
        'municipality' => 'شهرداری',
        'ministry' => 'وزارتخانه‌ها',
        'oil' => 'نفت و گاز',
        'healthcare' => 'بهداشت و درمان',
    ];

    public function definition(): array
    {
        $slug = fake()->randomElement(array_keys(self::CATEGORIES));

        return [
            'name' => self::CATEGORIES[$slug],
            'slug' => $slug.'-'.fake()->unique()->numerify('###'),
            'icon' => 'academic-cap',
        ];
    }

    public function bank(): static
    {
        return $this->state(fn () => [
            'name' => self::CATEGORIES['bank'],
            'slug' => 'bank',
            'icon' => 'building-library',
        ]);
    }

    public function police(): static
    {
        return $this->state(fn () => [
            'name' => self::CATEGORIES['police'],
            'slug' => 'police',
            'icon' => 'shield-check',
        ]);
    }

    /**
     * ساخت یا بازیابی دسته‌های استاندارد (bank, police, ...).
     *
     * @return list<ExamCategory>
     */
    public static function ensureStandardCategories(): array
    {
        $rows = [];
        foreach (self::CATEGORIES as $slug => $name) {
            $rows[] = ExamCategory::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'icon' => 'academic-cap']
            );
        }

        return $rows;
    }
}
