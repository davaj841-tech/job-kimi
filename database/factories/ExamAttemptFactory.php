<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExamAttempt> */
class ExamAttemptFactory extends Factory
{
    protected $model = ExamAttempt::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exam_id' => Exam::factory(),
            'started_at' => now()->subMinutes(10),
            'finished_at' => null,
            'score' => 0,
            'total_correct' => 0,
            'total_wrong' => 0,
            'status' => 'in_progress',
            'answers' => [],
        ];
    }

    public function completed(float $score = 80): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'score' => $score,
            'finished_at' => now(),
            'total_correct' => 8,
            'total_wrong' => 2,
        ]);
    }
}
