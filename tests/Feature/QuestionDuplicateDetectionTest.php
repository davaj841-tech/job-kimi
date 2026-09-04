<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use App\Support\QuestionTextNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionDuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_cross_exam_duplicate_questions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $examA = Exam::factory()->create(['title' => 'آزمون الف']);
        $examB = Exam::factory()->create(['title' => 'آزمون ب']);

        $text = 'کدام گزینه درست است؟';

        Question::factory()->create([
            'exam_id' => $examA->id,
            'question_text' => $text,
            'option_a' => '۱',
            'option_b' => '۲',
            'option_c' => '۳',
            'option_d' => '۴',
            'correct_answer' => 'a',
        ]);

        Question::factory()->create([
            'exam_id' => $examB->id,
            'question_text' => '<p>'.$text.'</p>',
            'option_a' => '۱',
            'option_b' => '۲',
            'option_c' => '۳',
            'option_d' => '۴',
            'correct_answer' => 'a',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/questions/duplicates');

        $response->assertOk()
            ->assertJsonPath('data.total_groups', 1)
            ->assertJsonPath('data.total_questions', 2)
            ->assertJsonPath('data.groups.0.exam_count', 2);
    }

    public function test_admin_can_copy_duplicate_question_to_target_exam(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $examA = Exam::factory()->create(['title' => 'آزمون الف', 'total_questions' => 1]);
        $examB = Exam::factory()->create(['title' => 'آزمون ب', 'total_questions' => 1]);
        $examC = Exam::factory()->create(['title' => 'آزمون ج', 'total_questions' => 0]);

        $text = 'سوال تکراری نمونه';

        $source = Question::factory()->create([
            'exam_id' => $examA->id,
            'question_text' => $text,
            'correct_answer' => 'a',
        ]);

        Question::factory()->create([
            'exam_id' => $examB->id,
            'question_text' => $text,
            'correct_answer' => 'a',
        ]);

        $fingerprint = QuestionTextNormalizer::fingerprint($text);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/questions/copy-to-exam', [
                'exam_id' => $examC->id,
                'fingerprints' => [$fingerprint],
                'source_question_ids' => [
                    $fingerprint => $source->id,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.skipped', 0);

        $this->assertDatabaseHas('questions', [
            'exam_id' => $examC->id,
            'question_text' => $text,
        ]);

        $examC->refresh();
        $this->assertSame(1, $examC->total_questions);
    }

    public function test_copy_skips_when_question_already_exists_in_target_exam(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $examA = Exam::factory()->create(['total_questions' => 1]);
        $examB = Exam::factory()->create(['total_questions' => 1]);

        $text = 'سوال تکراری موجود';

        Question::factory()->create([
            'exam_id' => $examA->id,
            'question_text' => $text,
        ]);

        Question::factory()->create([
            'exam_id' => $examB->id,
            'question_text' => $text,
        ]);

        $fingerprint = QuestionTextNormalizer::fingerprint($text);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/questions/copy-to-exam', [
                'exam_id' => $examB->id,
                'fingerprints' => [$fingerprint],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped', 1);

        $this->assertSame(1, Question::query()->where('exam_id', $examB->id)->count());
    }
}
