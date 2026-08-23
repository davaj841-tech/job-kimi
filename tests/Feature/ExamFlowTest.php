<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\ExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function createExamWithQuestions(array $examAttrs = [], int $questionCount = 4): Exam
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $exam = Exam::factory()->create(array_merge([
            'created_by' => $creator->id,
            'status' => 'published',
            'duration_minutes' => 60,
            'total_marks' => 100,
            'passing_score' => 50,
            'total_questions' => $questionCount,
            'is_free' => true,
            'price' => 0,
            'subscription_required' => 'free',
        ], $examAttrs));

        $answers = ['a', 'b', 'c', 'd'];
        for ($i = 0; $i < $questionCount; $i++) {
            Question::factory()->create([
                'exam_id' => $exam->id,
                'correct_answer' => $answers[$i % 4],
                'subject' => $i % 2 === 0 ? 'math' : 'general',
            ]);
        }

        return $exam->fresh();
    }

    public function test_free_user_can_take_free_exam(): void
    {
        $user = User::factory()->create(['role' => 'jobseeker']);
        $exam = $this->createExamWithQuestions(['is_free' => true, 'subscription_required' => 'free']);

        Sanctum::actingAs($user);

        $start = $this->postJson("/api/v1/exams/{$exam->id}/start");
        $start->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['attempt_id', 'questions', 'end_time', 'duration_minutes']]);

        $attemptId = $start->json('data.attempt_id');
        $questions = $start->json('data.questions');

        $answers = [];
        foreach ($questions as $question) {
            $answers[$question['id']] = 'a';
        }

        $submit = $this->postJson("/api/v1/exams/{$exam->id}/submit/{$attemptId}", [
            'answers' => $answers,
        ]);

        $submit->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['score', 'total_correct', 'total_wrong', 'percentage']]);

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $attemptId,
            'status' => 'completed',
            'user_id' => $user->id,
        ]);
    }

    public function test_free_user_cannot_take_paid_exam(): void
    {
        $user = User::factory()->create([
            'role' => 'jobseeker',
            'subscription_expires_at' => null,
        ]);
        $exam = $this->createExamWithQuestions([
            'is_free' => false,
            'subscription_required' => 'paid',
            'price' => 900000,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/exams/{$exam->id}/start")
            ->assertForbidden()
            ->assertJsonPath('code', 'SUBSCRIPTION_REQUIRED');
    }

    public function test_exam_auto_submits_after_timeout(): void
    {
        $user = User::factory()->create();
        $exam = $this->createExamWithQuestions([
            'duration_minutes' => 30,
            'is_free' => true,
            'subscription_required' => 'free',
        ]);

        Sanctum::actingAs($user);

        $start = $this->postJson("/api/v1/exams/{$exam->id}/start")->assertCreated();
        $attemptId = $start->json('data.attempt_id');

        $this->travel(31)->minutes();

        $this->postJson("/api/v1/exams/{$exam->id}/submit/{$attemptId}", [
            'answers' => [],
        ])->assertOk();

        $attempt = ExamAttempt::query()->find($attemptId);
        $this->assertSame('completed', $attempt->status);
        $this->assertNotNull($attempt->finished_at);
        $this->assertTrue(app(ExamService::class)->checkExpiry(
            ExamAttempt::factory()->create([
                'user_id' => $user->id,
                'exam_id' => $exam->id,
                'started_at' => now()->subMinutes(40),
                'status' => 'in_progress',
            ])
        ));
    }

    public function test_retry_wrong_answers_only_includes_wrong_questions(): void
    {
        $user = User::factory()->create();
        $exam = $this->createExamWithQuestions([], 4);
        $questions = $exam->questions()->orderBy('id')->get();

        Sanctum::actingAs($user);

        $answers = [];
        foreach ($questions as $index => $question) {
            // دو تا غلط، دو تا درست
            $answers[$question->id] = $index < 2 ? 'z' : $question->correct_answer;
            // 'z' invalid - use wrong letter
            if ($index < 2) {
                $answers[$question->id] = $question->correct_answer === 'a' ? 'b' : 'a';
            }
        }

        $attempt = ExamAttempt::factory()->completed(50)->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'answers' => $answers,
            'total_correct' => 2,
            'total_wrong' => 2,
        ]);

        $response = $this->postJson("/api/v1/exams/{$exam->id}/retry-wrong/{$attempt->id}");
        $response->assertCreated();

        $returnedIds = collect($response->json('data.questions'))->pluck('id')->sort()->values()->all();
        $expectedWrongIds = $questions->take(2)->pluck('id')->sort()->values()->all();

        $this->assertSame($expectedWrongIds, $returnedIds);
        $this->assertCount(2, $returnedIds);
    }

    public function test_rank_calculation_is_accurate(): void
    {
        $exam = $this->createExamWithQuestions();
        $users = User::factory()->count(3)->create();

        $attempts = [];
        $scores = [90, 70, 90];
        foreach ($users as $index => $user) {
            $attempts[] = ExamAttempt::factory()->completed($scores[$index])->create([
                'user_id' => $user->id,
                'exam_id' => $exam->id,
                'finished_at' => now()->addMinutes($index),
            ]);
        }

        $service = app(ExamService::class);

        // بالاترین نمره + زودتر تمام‌شده = رتبه ۱
        $this->assertSame(1, $service->calculateRank($attempts[0]));
        $this->assertSame(3, $service->calculateRank($attempts[1]));
        $this->assertSame(2, $service->calculateRank($attempts[2]));
    }

    public function test_dashboard_returns_correct_stats(): void
    {
        $user = User::factory()->create([
            'name' => 'کاربر تست',
            'wallet_balance' => 1000,
        ]);
        $exam = $this->createExamWithQuestions([
            'passing_score' => 50,
            'total_marks' => 100,
        ]);

        ExamAttempt::factory()->completed(80)->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'total_correct' => 8,
            'total_wrong' => 2,
            'finished_at' => now(),
        ]);
        ExamAttempt::factory()->completed(40)->create([
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'total_correct' => 4,
            'total_wrong' => 6,
            'finished_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.total_exams_taken', 2)
            ->assertJsonPath('data.stats.total_exams_passed', 1)
            ->assertJsonPath('data.stats.total_correct_answers', 12)
            ->assertJsonPath('data.stats.total_wrong_answers', 8)
            ->assertJsonPath('data.user.name', 'کاربر تست')
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'stats',
                    'progress_chart',
                    'recent_attempts',
                    'available_exams',
                ],
            ]);
    }
}
