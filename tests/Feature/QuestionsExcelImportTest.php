<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\User;
use App\Services\QuestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class QuestionsExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_persian_header_xlsx_imports_questions_when_exam_selected(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $category = ExamCategory::query()->create([
            'name' => 'عمومی',
            'slug' => 'general-'.uniqid(),
        ]);
        $exam = Exam::factory()->create([
            'title' => 'آزمون تست ورود اکسل',
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'total_questions' => 0,
        ]);

        $path = storage_path('app/testing-questions-import.xlsx');
        $this->writePersianSample($path);

        $file = new UploadedFile(
            $path,
            '30-question-jobazmoon.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $summary = app(QuestionService::class)->importFromExcel($file, (int) $exam->id);

        $this->assertSame(2, $summary['created'], json_encode($summary['errors'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $summary['skipped'], json_encode($summary['errors'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(2, $exam->fresh()->questions()->count());
        $this->assertSame(2, (int) $exam->fresh()->total_questions);

        @unlink($path);
    }

    public function test_admin_api_accepts_persian_header_xlsx(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $category = ExamCategory::query()->create([
            'name' => 'عمومی',
            'slug' => 'general-'.uniqid(),
        ]);
        $exam = Exam::factory()->create([
            'title' => 'آزمون API ورود',
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'total_questions' => 0,
        ]);

        $path = storage_path('app/testing-questions-import-api.xlsx');
        $this->writePersianSample($path);

        $response = $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/admin/questions/import', [
                'exam_id' => $exam->id,
                'file' => new UploadedFile(
                    $path,
                    'sample.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.created', 2);
        $this->assertSame(2, $exam->fresh()->questions()->count());

        @unlink($path);
    }

    protected function writePersianSample(string $path): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['نام_آزمون', 'درس', 'متن_سوال', 'گزینه_الف', 'گزینه_ب', 'گزینه_ج', 'گزینه_د', 'پاسخ_صحیح', 'توضیحات', 'سطح', 'سال', 'منبع'],
            ['', 'ریاضی', 'اگر ۱۵ درصد عددی برابر ۳۰ باشد، آن عدد چند است؟', '۱۵۰', '۱۸۰', '۲۰۰', '۲۲۵', 'ج', 'توضیح', 'متوسط', '1403', 'تألیفی'],
            ['', 'ریاضی', 'حاصل ۲×۳ کدام است؟', '۵', '۶', '۷', '۸', 'ب', '۲×۳=۶', 'آسان', '1402', 'تألیفی'],
        ]);
        (new Xlsx($spreadsheet))->save($path);
    }
}
