<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\User;
use Database\Factories\ExamCategoryFactory;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    public function run(): void
    {
        ExamCategoryFactory::ensureStandardCategories();

        $creator = User::query()->firstOrCreate(
            ['mobile' => '09120000099'],
            [
                'name' => 'سازنده آزمون',
                'email' => 'exam-seeder@jobazmoon.test',
                'password' => 'password',
                'role' => 'admin',
                'status' => 'active',
                'is_verified' => true,
            ]
        );

        $categories = ExamCategory::query()
            ->whereIn('slug', array_keys(ExamCategoryFactory::CATEGORIES))
            ->get();

        // ۵۰ آزمون؛ هر کدام ۱۰ سوال از طریق has()
        Exam::factory()
            ->count(50)
            ->withQuestions(10)
            ->state(fn () => [
                'created_by' => $creator->id,
                'category_id' => $categories->random()->id,
            ])
            ->create();

        // همگام‌سازی تعداد سوالات
        Exam::query()->each(function (Exam $exam) {
            $exam->update(['total_questions' => $exam->questions()->count()]);
        });
    }
}
