<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Exam> */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    /** @var list<string> */
    private const TITLES = [
        'آزمون استخدامی بانک ملی',
        'آزمون کتبی نیروی انتظامی',
        'آزمون عمومی آموزش و پرورش',
        'آزمون تخصصی شهرداری تهران',
        'آزمون استخدامی وزارت نفت',
        'آزمون بهداشت و درمان',
        'آزمون ورودی ارتش جمهوری اسلامی',
        'آزمون کارشناسی دستگاه‌های اجرایی',
        'آزمون هوش و استعداد تحصیلی',
        'آزمون معلومات عمومی استخدامی',
    ];

    public function definition(): array
    {
        $title = fake()->randomElement(self::TITLES).' — '.fake()->numerify('نوبت ##');
        $isFree = fake()->boolean(40);
        $duration = fake()->randomElement([30, 45, 60, 90, 120]);

        return [
            'title' => $title,
            'slug' => 'exam-'.Str::lower(Str::random(10)),
            'category_id' => ExamCategory::factory(),
            'job_post_id' => null,
            'description' => 'آزمون شبیه‌سازی‌شده برای آمادگی استخدامی. شامل سوالات چهارگزینه‌ای با زمان‌بندی مشخص.',
            'duration_minutes' => $duration,
            'passing_score' => 50,
            'total_questions' => 10,
            'total_marks' => 100,
            'is_free' => $isFree,
            'price' => $isFree ? 0 : fake()->randomElement([150000, 250000, 500000]),
            'subscription_required' => $isFree ? 'free' : 'paid',
            'status' => 'published',
            'created_by' => User::factory(),
        ];
    }

    /**
     * رابطه Exam → Question با has() (پیش‌فرض ۱۰ سوال).
     */
    public function withQuestions(int $count = 10): static
    {
        return $this->state(fn () => [
            'total_questions' => $count,
        ])->has(Question::factory()->count($count), 'questions');
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
