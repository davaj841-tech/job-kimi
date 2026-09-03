<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\JobClassification;
use App\Models\JobPost;
use App\Models\Question;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Critical business flows not covered by existing test suites.
 * Covers: Autosave (#7), Excel import (#16), IDOR prevention (#20).
 */
final class CriticalBusinessFlowsTest extends TestCase
{
    use RefreshDatabase;

    // ─── #7: Exam Autosave ───

    public function test_autosave_persists_answers_during_exam(): void
    {
        $user = User::factory()->create(['role' => 'jobseeker']);
        $exam = $this->createExamWithQuestions(['is_free' => true, 'subscription_required' => 'free']);

        Sanctum::actingAs($user);

        $start = $this->postJson("/api/v1/exams/{$exam->id}/start");
        $start->assertCreated();

        $attemptId = $start->json('data.attempt_id');
        $questions = $start->json('data.questions');

        $partialAnswers = [$questions[0]['id'] => 'a', $questions[1]['id'] => 'b'];

        $save = $this->postJson("/api/v1/exams/{$exam->id}/autosave/{$attemptId}", [
            'answers' => $partialAnswers,
        ]);
        $save->assertOk();

        $get = $this->getJson("/api/v1/exams/{$exam->id}/autosave/{$attemptId}");
        $get->assertOk();

        $saved = $get->json('data.answers');
        $this->assertEquals('a', $saved[(string) $questions[0]['id']] ?? $saved[$questions[0]['id']] ?? null);
    }

    public function test_autosave_rejected_after_timeout(): void
    {
        $user = User::factory()->create(['role' => 'jobseeker']);
        $exam = $this->createExamWithQuestions(['is_free' => true, 'subscription_required' => 'free', 'duration_minutes' => 10]);

        Sanctum::actingAs($user);

        $start = $this->postJson("/api/v1/exams/{$exam->id}/start")->assertCreated();
        $attemptId = $start->json('data.attempt_id');

        $this->travel(11)->minutes();

        $this->postJson("/api/v1/exams/{$exam->id}/autosave/{$attemptId}", [
            'answers' => [1 => 'a'],
        ])->assertStatus(422);
    }

    // ─── #16: Excel Import ───

    public function test_excel_import_creates_job_posts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        JobClassification::firstOrCreate(['name' => 'بانک‌ها'], ['is_active' => true, 'sort_order' => 1]);

        $file = $this->createExcelFile();

        $response = $this->actingAs($admin)->post('/api/v1/admin/job-posts/import', [
            'file' => $file,
        ]);

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, $response->json('data.created'));
        $this->assertDatabaseHas('job_posts', ['status' => 'approved']);
    }

    // ─── #20: IDOR Prevention ───

    public function test_idor_exam_attempt_other_user(): void
    {
        $user1 = User::factory()->create(['role' => 'jobseeker']);
        $user2 = User::factory()->create(['role' => 'jobseeker']);
        $exam = $this->createExamWithQuestions(['is_free' => true, 'subscription_required' => 'free']);

        Sanctum::actingAs($user1);
        $start = $this->postJson("/api/v1/exams/{$exam->id}/start")->assertCreated();
        $attemptId = $start->json('data.attempt_id');

        Sanctum::actingAs($user2);
        $this->postJson("/api/v1/exams/{$exam->id}/submit/{$attemptId}", [
            'answers' => [],
        ])->assertNotFound();
    }

    public function test_idor_resume_download_other_user(): void
    {
        $user1 = User::factory()->create(['role' => 'jobseeker']);
        $user2 = User::factory()->create(['role' => 'jobseeker']);

        $resume = Resume::create([
            'user_id' => $user1->id,
            'template_id' => 1,
            'title' => 'Private',
            'data' => ['personal' => []],
        ]);

        Sanctum::actingAs($user2);
        $this->getJson("/api/v1/resumes/{$resume->id}")->assertNotFound();
        $this->getJson("/api/v1/resumes/{$resume->id}/pdf")->assertNotFound();
        $this->putJson("/api/v1/resumes/{$resume->id}", ['title' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/v1/resumes/{$resume->id}")->assertNotFound();
    }

    public function test_idor_autosave_other_users_attempt(): void
    {
        $user1 = User::factory()->create(['role' => 'jobseeker']);
        $user2 = User::factory()->create(['role' => 'jobseeker']);
        $exam = $this->createExamWithQuestions(['is_free' => true, 'subscription_required' => 'free']);

        Sanctum::actingAs($user1);
        $start = $this->postJson("/api/v1/exams/{$exam->id}/start")->assertCreated();
        $attemptId = $start->json('data.attempt_id');

        Sanctum::actingAs($user2);
        $this->postJson("/api/v1/exams/{$exam->id}/autosave/{$attemptId}", [
            'answers' => [1 => 'a'],
        ])->assertNotFound();
    }

    public function test_idor_job_post_other_user_cannot_edit(): void
    {
        $user1 = User::factory()->create(['role' => 'jobseeker']);
        $user2 = User::factory()->create(['role' => 'jobseeker']);

        $classification = JobClassification::firstOrCreate(['name' => 'تست'], ['is_active' => true, 'sort_order' => 1]);
        $job = JobPost::create([
            'title' => 'Private Job',
            'company_name' => 'C',
            'job_classification_id' => $classification->id,
            'description' => 'x',
            'status' => 'pending',
            'created_by' => $user1->id,
        ]);

        // Non-admin user cannot access admin endpoints
        Sanctum::actingAs($user2);
        $this->putJson("/api/v1/admin/job-posts/{$job->id}", [
            'title' => 'Hacked',
        ])->assertStatus(403);
    }

    // ─── Helpers ───

    private function createExamWithQuestions(array $attrs = [], int $count = 4): Exam
    {
        $creator = User::factory()->create(['role' => 'admin']);
        $exam = Exam::factory()->create(array_merge([
            'created_by' => $creator->id,
            'status' => 'published',
            'duration_minutes' => 60,
            'total_marks' => 100,
            'passing_score' => 50,
            'total_questions' => $count,
        ], $attrs));

        for ($i = 0; $i < $count; $i++) {
            Question::factory()->create([
                'exam_id' => $exam->id,
                'correct_answer' => ['a', 'b', 'c', 'd'][$i % 4],
            ]);
        }

        return $exam->fresh();
    }

    private function createExcelFile(): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'title');
        $sheet->setCellValue('B1', 'classification');
        $sheet->setCellValue('C1', 'description');
        $sheet->setCellValue('D1', 'registration_deadline');
        $sheet->setCellValue('A2', 'استخدام تست');
        $sheet->setCellValue('B2', 'بانک‌ها');
        $sheet->setCellValue('C2', 'توضیحات تست');
        $sheet->setCellValue('D2', now()->addMonth()->format('Y-m-d'));

        $path = storage_path('app/test_import.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'jobs.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
