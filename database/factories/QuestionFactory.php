<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Question> */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'question_text' => fake()->sentence().'؟',
            'question_type' => 'multiple_choice',
            'option_a' => 'گزینه الف',
            'option_b' => 'گزینه ب',
            'option_c' => 'گزینه ج',
            'option_d' => 'گزینه د',
            'correct_answer' => 'a',
            'explanation' => fake()->sentence(),
            'difficulty' => 'medium',
            'subject' => 'general',
        ];
    }
}
