<?php

namespace Database\Factories;

use App\Models\ExamCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ExamCategory> */
class ExamCategoryFactory extends Factory
{
    protected $model = ExamCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'icon' => 'academic-cap',
        ];
    }
}
