<?php

declare(strict_types=1);

namespace Tests\Feature\Exam;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ExamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ExamLifecycleFeatureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $examAttrs
     */
    private function createExamWithQuestions(array $examAttrs = [], int $questionCount = 4): Exam
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
            'subscription_required' => 'free',
        ], $examAttrs));

        $letters = ['a', 'b', 'c', 'd'];
        for ($i = 0; $i < $questionCount; $i++) {
            Question::factory()->create([
                'exam_id' => $exam->id,
                'correct_answer' => $letters[$i % 4],
                'subject' => $i % 2 === 0 ? 'math' : 'general',
            ]);
        }

        return $exam->fresh(['questions']);
    }

    private function userWithActiveSubscription(): User
    {
        $plan = SubscriptionPlan::factory()->paid()->create();

        return User::factory()->create([
            'role' => 'jobseeker',
            'status' => 'active',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDays(30),
        ]);
    }

    private function userWithoutSubscription(): User
    {
        return User::factory()->create([
            'role' => 'jobseeker',
            'status' => 'active',
            'subscription_plan_id' => null,
            'subscription_expires_at' => null,
        ]);
    }

    public function test_user_can_start_free_exam_without_subscription(): void
    {
        $user = $this->userWithoutSubscription();
        $exam = $this->createExamWithQuestions([
            'is_free' => true,
            'subscription_required' => 'free',
            'price' => 0,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/exams/{$exam->id}/start");

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'attempt_id',
                    'questions',
                    'end_time',
                    'duration_minutes',
                ],
            ]);

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $response->json('data.attempt_id'),
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_user_with_active_subscription_can_start_paid_exam(): void
    {
        $user = $this->userWithActiveSubscription();
        $exam = Exam::factory()->paid()->create([
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
            'status' => 'published',
            'duration_minutes' => 45,
            'total_questions' => 2,
            'total_marks' => 100,
        ]);
        Question::factory()->count(2)->create(['exam_id' => $exam->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/exams/{$exam->id}/start");

        $response->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $response->json('data.attempt_id'),
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'subscription_plan_id' => $user->subscription_plan_id,
        ]);
    }

    public function test_user_without_subscription_cannot_start_paid_exam(): void
    {
        $user = $this->userWithoutSubscription();
        $exam = Exam::factory()->paid()->create([
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
            'status' => 'published',
            'total_questions' => 1,
        ]);
        Question::factory()->create(['exam_id' => $exam->id]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/exams/{$exam->id}/start")
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'SUBSCRIPTION_REQUIRED');

        $this->assertDatabaseMissing('exam_attempts', [
            'user_id' => $user->id,
            'exam_id' => $exam->id,
        ]);
    }

    public function test_autosave_persists_temporary_answers_on_exam_attempts(): void
    {
        $user = $this->userWithoutSubscription();
        $exam = $this->createExamWithQuestions([], 3);

        Sanctum::actingAs($user);

        $start = $this->postJson("/api/v1/exams/{$exam->id}/start")->assertCreated();
        $attemptId = (int) $start->json('data.attempt_id');
        $questions = $start->json('data.questions');

        $answers = [
            (string) $questions[0]['id'] => 'a',
            (string) $questions[1]['id'] => 'b',
        ];

        $autosave = $this->postJson("/api/v1/exams/{$exam->id}/autosave/{$attemptId}", [
            'answers' => $answers,
        ]);

        $autosave->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attempt_id', $attemptId)
            ->assertJsonPath('data.answers_count', 2);

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $attemptId,
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'status' => 'in_progress',
        ]);

        $attempt = ExamAttempt::query()->findOrFail($attemptId);
        $this->assertSame('a', $attempt->answers[(string) $questions[0]['id']] ?? $attempt->answers[$questions[0]['id']] ?? null);
        $this->assertSame('b', $attempt->answers[(string) $questions[1]['id']] ?? $attempt->answers[$questions[1]['id']] ?? null);

        $this->getJson("/api/v1/exams/{$exam->id}/autosave/{$attemptId}")
            ->assertOk()
            ->assertJsonPath('data.attempt_id', $attemptId)
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_expired_attempt_is_auto_submitted_on_submit(): void
    {
        $user = $this->userWithoutSubscription();
        $exam = $this->createExamWithQuestions([
            'duration_minutes' => 20,
        ], 2);

        Sanctum::actingAs($user);

        $start = $this->postJson("/api/v1/exams/{$exam->id}/start")->assertCreated();
        $attemptId = (int) $start->json('data.attempt_id');
        $q0 = $start->json('data.questions.0.id');

        $this->postJson("/api/v1/exams/{$exam->id}/autosave/{$attemptId}", [
            'answers' => [(string) $q0 => 'a'],
        ])->assertOk();

        $this->travel(21)->minutes();

        $this->assertTrue(
            app(ExamService::class)->checkExpiry(ExamAttempt::query()->findOrFail($attemptId))
        );

        $submit = $this->postJson("/api/v1/exams/{$exam->id}/submit/{$attemptId}", [
            'answers' => [],
        ]);

        $submit->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $attemptId,
            'status' => 'completed',
            'user_id' => $user->id,
        ]);

        $attempt = ExamAttempt::query()->findOrFail($attemptId);
        $this->assertNotNull($attempt->finished_at);
        $this->assertNotEmpty($attempt->answers);
    }

    public function test_expired_in_progress_attempt_is_finalized_when_starting_again(): void
    {
        $user = $this->userWithoutSubscription();
        $exam = $this->createExamWithQuestions(['duration_minutes' => 10], 2);

        Sanctum::actingAs($user);

        $first = $this->postJson("/api/v1/exams/{$exam->id}/start")->assertCreated();
        $firstAttemptId = (int) $first->json('data.attempt_id');

        $this->travel(11)->minutes();

        $second = $this->postJson("/api/v1/exams/{$exam->id}/start")->assertCreated();

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $firstAttemptId,
            'status' => 'completed',
        ]);

        $this->assertNotSame($firstAttemptId, (int) $second->json('data.attempt_id'));
        $this->assertDatabaseHas('exam_attempts', [
            'id' => $second->json('data.attempt_id'),
            'status' => 'in_progress',
        ]);
    }

    public function test_scoring_calculates_correct_percentage(): void
    {
        $user = $this->userWithoutSubscription();
        $exam = $this->createExamWithQuestions([
            'total_marks' => 100,
            'total_questions' => 4,
        ], 4);

        Sanctum::actingAs($user);

        $start = $this->postJson("/api/v1/exams/{$exam->id}/start")->assertCreated();
        $attemptId = (int) $start->json('data.attempt_id');
        $questions = collect($start->json('data.questions'));

        // ۲ درست از ۴ = ۵۰٪
        $answers = [];
        foreach ($questions->values() as $index => $question) {
            $db = Question::query()->findOrFail($question['id']);
            $answers[(string) $question['id']] = $index < 2
                ? $db->correct_answer
                : ($db->correct_answer === 'a' ? 'b' : 'a');
        }

        $submit = $this->postJson("/api/v1/exams/{$exam->id}/submit/{$attemptId}", [
            'answers' => $answers,
        ]);

        $submit->assertOk()
            ->assertJsonPath('data.total_correct', 2)
            ->assertJsonPath('data.total_wrong', 2)
            ->assertJsonPath('data.percentage', 50);

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $attemptId,
            'status' => 'completed',
            'total_correct' => 2,
            'total_wrong' => 2,
            'score' => 50,
        ]);
    }

    public function test_leaderboard_orders_by_score_descending(): void
    {
        $exam = $this->createExamWithQuestions([], 2);

        $high = User::factory()->create(['name' => 'رتبه یک']);
        $mid = User::factory()->create(['name' => 'رتبه دو']);
        $low = User::factory()->create(['name' => 'رتبه سه']);

        ExamAttempt::factory()->completed(95)->create([
            'user_id' => $high->id,
            'exam_id' => $exam->id,
            'finished_at' => now()->subMinutes(5),
        ]);
        ExamAttempt::factory()->completed(70)->create([
            'user_id' => $mid->id,
            'exam_id' => $exam->id,
            'finished_at' => now()->subMinutes(3),
        ]);
        ExamAttempt::factory()->completed(40)->create([
            'user_id' => $low->id,
            'exam_id' => $exam->id,
            'finished_at' => now()->subMinutes(1),
        ]);

        $response = $this->getJson('/api/v1/leaderboard');

        $response->assertOk()->assertJsonPath('success', true);

        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertGreaterThanOrEqual(3, count($rows));

        $this->assertSame($high->id, $rows[0]['user_id']);
        $this->assertSame(1, $rows[0]['rank']);
        $this->assertSame(95.0, (float) $rows[0]['total_score']);

        $this->assertSame($mid->id, $rows[1]['user_id']);
        $this->assertSame($low->id, $rows[2]['user_id']);

        $scores = array_column(array_slice($rows, 0, 3), 'total_score');
        $sorted = $scores;
        rsort($sorted, SORT_NUMERIC);
        $this->assertSame($sorted, $scores);
    }

    public function test_rank_uses_score_then_earlier_finish_time(): void
    {
        $exam = $this->createExamWithQuestions([], 1);
        $service = app(ExamService::class);

        $first = ExamAttempt::factory()->completed(90)->create([
            'user_id' => User::factory()->create()->id,
            'exam_id' => $exam->id,
            'finished_at' => now()->subMinutes(10),
        ]);
        $secondSameScore = ExamAttempt::factory()->completed(90)->create([
            'user_id' => User::factory()->create()->id,
            'exam_id' => $exam->id,
            'finished_at' => now()->subMinutes(5),
        ]);
        $lower = ExamAttempt::factory()->completed(60)->create([
            'user_id' => User::factory()->create()->id,
            'exam_id' => $exam->id,
            'finished_at' => now()->subMinutes(1),
        ]);

        $this->assertSame(1, $service->calculateRank($first));
        $this->assertSame(2, $service->calculateRank($secondSameScore));
        $this->assertSame(3, $service->calculateRank($lower));

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $first->id,
            'score' => 90,
            'status' => 'completed',
        ]);
    }

    public function test_cannot_retake_paid_exam_after_subscription_expires(): void
    {
        $plan = SubscriptionPlan::factory()->paid()->create();
        $user = User::factory()->create([
            'role' => 'jobseeker',
            'status' => 'active',
            'subscription_plan_id' => $plan->id,
            'subscription_expires_at' => now()->addDay(),
        ]);

        $exam = Exam::factory()->paid()->create([
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
            'status' => 'published',
            'total_questions' => 2,
            'duration_minutes' => 30,
        ]);
        Question::factory()->count(2)->create(['exam_id' => $exam->id]);

        Sanctum::actingAs($user);

        $first = $this->postJson("/api/v1/exams/{$exam->id}/start")->assertCreated();
        $attemptId = (int) $first->json('data.attempt_id');

        $this->postJson("/api/v1/exams/{$exam->id}/submit/{$attemptId}", [
            'answers' => [],
        ])->assertOk();

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $attemptId,
            'status' => 'completed',
        ]);

        // انقضای اشتراک
        $user->update([
            'subscription_expires_at' => now()->subMinute(),
        ]);

        $this->postJson("/api/v1/exams/{$exam->id}/start")
            ->assertForbidden()
            ->assertJsonPath('code', 'SUBSCRIPTION_REQUIRED');

        $this->postJson("/api/v1/exams/{$exam->id}/retry")
            ->assertForbidden()
            ->assertJsonPath('code', 'SUBSCRIPTION_REQUIRED');

        $this->assertSame(
            1,
            ExamAttempt::query()
                ->where('user_id', $user->id)
                ->where('exam_id', $exam->id)
                ->where('status', 'completed')
                ->count()
        );
    }

    public function test_autosave_rejected_after_time_limit(): void
    {
        $user = $this->userWithoutSubscription();
        $exam = $this->createExamWithQuestions(['duration_minutes' => 15], 2);

        Sanctum::actingAs($user);

        $start = $this->postJson("/api/v1/exams/{$exam->id}/start")->assertCreated();
        $attemptId = (int) $start->json('data.attempt_id');
        $qid = (string) $start->json('data.questions.0.id');

        $this->travel(16)->minutes();

        $this->postJson("/api/v1/exams/{$exam->id}/autosave/{$attemptId}", [
            'answers' => [$qid => 'a'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('exam_attempts', [
            'id' => $attemptId,
            'status' => 'in_progress',
        ]);
    }
}
