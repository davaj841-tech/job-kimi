<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\JobClassification;
use App\Models\JobPost;
use App\Models\User;
use App\Services\HomeFeedCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

final class JobPostsExcelImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_jalali_deadline_imports_and_approves_job_for_homepage(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $classification = JobClassification::query()->firstOrCreate(
            ['name' => 'بانک‌ها'],
            ['icon' => 'briefcase', 'is_active' => true, 'sort_order' => 1]
        );

        $path = storage_path('app/testing-jobs-jalali.xlsx');
        $this->writeImportSheet($path, [
            'title' => 'استخدام بانک نمونه',
            'classification' => 'بانک‌ها',
            'description' => 'شرح آگهی نمونه',
            'registration_deadline' => '1405/06/15',
            'exam_date' => '1405/07/20',
            'registration_link' => 'https://example.com/job-1',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/admin/job-posts/import', [
                'file' => new UploadedFile(
                    $path,
                    'jobs-jalali.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.created', 1);

        $job = JobPost::query()->where('title', 'استخدام بانک نمونه')->first();
        $this->assertNotNull($job);
        $this->assertSame('approved', $job->status);
        $this->assertSame($classification->id, $job->job_classification_id);
        $this->assertNotNull($job->registration_deadline);

        $this->getJson('/api/v1/home-feed')
            ->assertOk()
            ->assertJsonFragment(['title' => 'استخدام بانک نمونه']);

        $this->getJson("/api/v1/job-posts/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'استخدام بانک نمونه');

        @unlink($path);
    }

    public function test_duplicate_import_is_skipped(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $classification = JobClassification::query()->firstOrCreate(
            ['name' => 'بانک‌ها'],
            ['icon' => 'briefcase', 'is_active' => true, 'sort_order' => 1]
        );

        JobPost::factory()->create([
            'title' => 'استخدام تکراری',
            'company_name' => 'بانک‌ها',
            'job_classification_id' => $classification->id,
            'registration_deadline' => '2026-09-06',
            'registration_link' => 'https://example.com/dup',
            'status' => 'approved',
        ]);

        $path = storage_path('app/testing-jobs-dup.xlsx');
        $this->writeImportSheet($path, [
            'title' => 'استخدام تکراری',
            'classification' => 'بانک‌ها',
            'description' => 'شرح',
            'registration_deadline' => '1405/06/15',
            'registration_link' => 'https://example.com/dup',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/admin/job-posts/import', [
                'file' => new UploadedFile(
                    $path,
                    'jobs-dup.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.created', 0);
        $response->assertJsonPath('data.duplicates', 1);
        $this->assertSame(1, JobPost::query()->where('title', 'استخدام تکراری')->count());

        @unlink($path);
    }

    public function test_import_clears_home_feed_cache(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Cache::put(HomeFeedCache::KEY, ['jobs' => []], 60);

        $path = storage_path('app/testing-jobs-cache.xlsx');
        $this->writeImportSheet($path, [
            'title' => 'آگهی کش',
            'classification' => 'بانک‌ها',
            'description' => 'شرح',
            'registration_deadline' => '1405/06/15',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/admin/job-posts/import', [
                'file' => new UploadedFile(
                    $path,
                    'jobs-cache.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ])
            ->assertOk();

        $this->assertFalse(Cache::has(HomeFeedCache::KEY));

        @unlink($path);
    }

    public function test_persian_header_sample_file_imports(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $path = storage_path('app/testing-jobs-persian-headers.xlsx');
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [
            'عنوان', 'برچسب_سئو', 'طبقه_بندی', 'شرح', 'استان‌ها', 'شهر',
            'مهلت_ثبت_نام', 'تاریخ_آزمون', 'لینک_ثبت_نام', 'ویژه',
        ];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 1], $header);
        }
        $sheet->fromArray([
            [
                'استخدام بنیاد مسکن ۱۴۰۵',
                'استخدام بنیاد مسکن ۱۴۰۵',
                'استخدام دولتی',
                'شرح آگهی بنیاد مسکن',
                'سراسری',
                'سراسری',
                '۱۴۰۵/۰۶/۰۷ تا ۱۴۰۵/۰۸/۰۶',
                '',
                'https://iranestekhdam.ir/category/استخدام-بنیاد-مسکن',
                'بله',
            ],
            [
                'استخدام کتابخانه عمومی',
                'استخدام کتابخانه عمومی',
                'استخدام دولتی',
                'شرح کتابخانه',
                'چند استان',
                'چند شهر',
                'در حال ثبت‌نام',
                '',
                'https://example.com/lib',
                'بله',
            ],
        ], null, 'A2');
        (new Xlsx($spreadsheet))->save($path);

        $response = $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/admin/job-posts/import', [
                'file' => new UploadedFile(
                    $path,
                    'jobs-fa.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true
                ),
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.created', 2);
        $response->assertJsonPath('data.skipped', 0);
        $job = JobPost::query()->where('title', 'استخدام بنیاد مسکن ۱۴۰۵')->first();
        $this->assertNotNull($job);
        $this->assertSame(
            'https://iranestekhdam.ir/category/استخدام-بنیاد-مسکن',
            $job->registration_link
        );

        @unlink($path);
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function writeImportSheet(string $path, array $row): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [
            'title', 'classification', 'description', 'registration_deadline',
            'exam_date', 'registration_link',
        ];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 1], $header);
        }
        $sheet->fromArray([[
            $row['title'] ?? '',
            $row['classification'] ?? '',
            $row['description'] ?? '',
            $row['registration_deadline'] ?? '',
            $row['exam_date'] ?? '',
            $row['registration_link'] ?? '',
        ]], null, 'A2');

        (new Xlsx($spreadsheet))->save($path);
    }
}
