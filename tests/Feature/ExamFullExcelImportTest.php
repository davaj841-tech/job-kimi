<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\User;
use App\Services\Exam\ExamFullImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class ExamFullExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_download_returns_xlsx(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/v1/admin/questions/import-exam-sample');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $response->headers->get('content-type')
        );
    }

    public function test_full_exam_import_creates_exam_and_questions(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        ExamCategory::query()->firstOrCreate(
            ['slug' => 'general'],
            ['name' => 'عمومی', 'icon' => 'book']
        );

        $path = storage_path('app/testing-exam-full-import.xlsx');
        $writer = new Xlsx(app(ExamFullImportService::class)->buildSampleSpreadsheet());
        $writer->save($path);

        $response = $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/admin/questions/import-exam', [
                'file' => new UploadedFile(
                    $path,
                    'exam-full-import-sample.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.created', 3);

        $examId = (int) $response->json('data.exam.id');
        $this->assertGreaterThan(0, $examId);

        $exam = Exam::query()->find($examId);
        $this->assertNotNull($exam);
        $this->assertSame('آزمون نمونه ورود کامل', $exam->title);
        $this->assertSame(3, $exam->questions()->count());
        $this->assertSame(3, (int) $exam->total_questions);

        @unlink($path);
    }

    public function test_full_exam_import_resolves_classification_by_name_without_slug_column(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        ExamCategory::query()->firstOrCreate(
            ['slug' => 'general'],
            ['name' => 'عمومی', 'icon' => 'book']
        );

        $classification = \App\Models\JobClassification::query()->create([
            'name' => 'آزمون استخدامی',
            'parent_id' => null,
            'sort_order' => 1,
            'is_active' => true,
            'show_on_home' => true,
        ]);

        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('job_classifications', 'slug'),
            'Regression guard: job_classifications must not rely on a slug column'
        );

        $path = storage_path('app/testing-exam-full-import-class.xlsx');
        $spreadsheet = app(ExamFullImportService::class)->buildSampleSpreadsheet();
        $spreadsheet->getSheet(0)->setCellValue('K2', 'آزمون استخدامی');
        (new Xlsx($spreadsheet))->save($path);

        $response = $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/admin/questions/import-exam', [
                'file' => new UploadedFile(
                    $path,
                    'exam-with-classification.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $exam = Exam::query()->find((int) $response->json('data.exam.id'));
        $this->assertNotNull($exam);
        $this->assertSame($classification->id, (int) $exam->job_classification_id);

        @unlink($path);
    }

    public function test_existing_questions_import_still_requires_exam_id(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $response = $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/admin/questions/import', [
                'file' => UploadedFile::fake()->create('q.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ]);

        $response->assertStatus(422);
    }
}
