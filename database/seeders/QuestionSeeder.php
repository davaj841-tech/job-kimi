<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Question;
use Illuminate\Database\Seeder;

/**
 * سوالات مستقل اضافه (علاوه بر سوالات has()-شده در ExamSeeder).
 */
class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $examIds = Exam::query()->pluck('id');

        if ($examIds->isEmpty()) {
            $this->call(ExamSeeder::class);
            $examIds = Exam::query()->pluck('id');
        }

        Question::factory()
            ->count(50)
            ->state(fn () => [
                'exam_id' => $examIds->random(),
            ])
            ->create();
    }
}
