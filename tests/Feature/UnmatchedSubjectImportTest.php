<?php

namespace Tests\Feature;

use App\Imports\QuestionsImport;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\ExamSubject;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class UnmatchedSubjectImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_unmatched_subject_for_unknown_name(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $category = ExamCategory::query()->create(['name' => 'عمومی', 'slug' => 'g-'.uniqid()]);
        $exam = Exam::factory()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'total_questions' => 0,
        ]);

        ExamSubject::query()->where('slug', 'general')->firstOrCreate(
            ['slug' => 'general'],
            ['name' => 'عمومی', 'icon' => '📘', 'sort_order' => 1, 'is_active' => true]
        );

        $import = new QuestionsImport((int) $exam->id);
        $import->collection(new Collection([
            collect([
                'درس' => 'درس اکسل ناشناخته تست',
                'متن_سوال' => 'سوال نمونه؟',
                'گزینه_الف' => '۱',
                'گزینه_ب' => '۲',
                'گزینه_ج' => '۳',
                'گزینه_د' => '۴',
                'پاسخ_صحیح' => 'ب',
            ]),
        ]));

        $this->assertSame(1, $import->created);
        $subject = ExamSubject::query()->where('name', 'درس اکسل ناشناخته تست')->first();
        $this->assertNotNull($subject);
        $this->assertTrue((bool) $subject->is_unmatched);
        $this->assertSame($subject->slug, Question::query()->where('exam_id', $exam->id)->value('subject'));
    }

    public function test_merge_unmatched_subject_updates_questions(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $math = ExamSubject::query()->firstOrCreate(
            ['slug' => 'math'],
            [
                'name' => 'ریاضی',
                'icon' => '🧮',
                'sort_order' => 1,
                'is_active' => true,
                'is_unmatched' => false,
            ]
        );
        $orphan = ExamSubject::query()->create([
            'name' => 'ریاضیات اکسل '.uniqid(),
            'slug' => 'unmatched-'.uniqid(),
            'icon' => '❓',
            'sort_order' => 99,
            'is_active' => true,
            'is_unmatched' => true,
        ]);
        $category = ExamCategory::query()->create(['name' => 'عمومی', 'slug' => 'g-'.uniqid()]);
        $exam = Exam::factory()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
        ]);
        Question::query()->create([
            'exam_id' => $exam->id,
            'question_text' => 'تست؟',
            'question_type' => 'multiple_choice',
            'option_a' => 'ا',
            'option_b' => 'ب',
            'option_c' => 'ج',
            'option_d' => 'د',
            'correct_answer' => 'a',
            'difficulty' => 'medium',
            'subject' => $orphan->slug,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/admin/exam-subjects/{$orphan->id}", [
                'merge_into_id' => $math->id,
            ]);

        $response->assertOk();
        $this->assertDatabaseMissing('exam_subjects', ['id' => $orphan->id]);
        $this->assertSame($math->slug, Question::query()->where('exam_id', $exam->id)->value('subject'));
    }

    public function test_rename_slug_cascades_to_questions(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $subject = ExamSubject::query()->create([
            'name' => 'نامرتبط قدیم '.uniqid(),
            'slug' => 'old-slug-'.uniqid(),
            'icon' => '❓',
            'sort_order' => 1,
            'is_active' => true,
            'is_unmatched' => true,
        ]);
        $oldSlug = $subject->slug;
        $category = ExamCategory::query()->create(['name' => 'عمومی', 'slug' => 'g-'.uniqid()]);
        $exam = Exam::factory()->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
        ]);
        Question::query()->create([
            'exam_id' => $exam->id,
            'question_text' => 'تست؟',
            'question_type' => 'multiple_choice',
            'option_a' => 'ا',
            'option_b' => 'ب',
            'option_c' => 'ج',
            'option_d' => 'د',
            'correct_answer' => 'a',
            'difficulty' => 'medium',
            'subject' => $oldSlug,
        ]);

        $newSlug = 'fixed-slug-'.uniqid();
        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/admin/exam-subjects/{$subject->id}", [
                'name' => 'درس تصحیح‌شده '.uniqid(),
                'slug' => $newSlug,
            ]);

        $response->assertOk();
        $this->assertSame($newSlug, Question::query()->where('exam_id', $exam->id)->value('subject'));
        $this->assertFalse((bool) $subject->fresh()->is_unmatched);
    }
}
