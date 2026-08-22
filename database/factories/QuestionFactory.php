<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * گزینه‌ها در option_a…d (معادل options JSON).
 * correct_option ۱–۴ → correct_answer a–d
 * score در explanation ذکر می‌شود (ستون جدا در اسکیما نیست).
 *
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    /** @var list<string> */
    private const STEMS = [
        'کدام گزینه درباره قانون اساسی صحیح است؟',
        'نتیجه عبارت ریاضی زیر کدام است؟',
        'معنی واژه مشخص‌شده در جمله چیست؟',
        'کدام مورد از وظایف نیروی انتظامی است؟',
        'در رایانه، واحد اصلی پردازش کدام است؟',
        'کدام گزینه تعریف درستی از تورم است؟',
        'پایتخت کشور ایران کدام شهر است؟',
        'کدام گزینه جزء مهارت‌های نرم محسوب می‌شود؟',
    ];

    public function definition(): array
    {
        $correctOption = fake()->numberBetween(1, 4);
        $score = fake()->randomElement([1, 1, 2, 3]);
        $map = [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd'];

        return [
            'exam_id' => Exam::factory(),
            'question_text' => fake()->randomElement(self::STEMS),
            'question_type' => 'multiple_choice',
            'option_a' => 'گزینه ۱',
            'option_b' => 'گزینه ۲',
            'option_c' => 'گزینه ۳',
            'option_d' => 'گزینه ۴',
            'correct_answer' => $map[$correctOption],
            'explanation' => "correct_option={$correctOption}; score={$score}",
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
            'subject' => fake()->randomElement(['general', 'math', 'iq', 'computer', 'law']),
        ];
    }

    /**
     * @param  array{1?: string, 2?: string, 3?: string, 4?: string}  $options
     */
    public function withOptions(array $options, int $correctOption = 1, int $score = 1): static
    {
        $correctOption = max(1, min(4, $correctOption));
        $map = [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd'];

        return $this->state(fn () => [
            'option_a' => $options[1] ?? 'گزینه ۱',
            'option_b' => $options[2] ?? 'گزینه ۲',
            'option_c' => $options[3] ?? 'گزینه ۳',
            'option_d' => $options[4] ?? 'گزینه ۴',
            'correct_answer' => $map[$correctOption],
            'explanation' => "correct_option={$correctOption}; score={$score}",
        ]);
    }
}
