<?php

namespace Tests\Feature\Backup;

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class BackupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        File::ensureDirectoryExists(storage_path('app/private/pdfs'));
        File::ensureDirectoryExists(storage_path('app/public/avatars'));
        File::put(storage_path('app/private/pdfs/sample.pdf'), '%PDF-1.4 test');
        File::put(storage_path('app/public/avatars/a.txt'), 'avatar');
        foreach (File::glob(storage_path('backups/backup-*.zip')) ?: [] as $file) {
            @unlink($file);
        }
    }

    public function test_create_backup_includes_database_and_private_files(): void
    {
        $path = app(BackupService::class)->createBackup();

        $this->assertFileExists($path);
        $this->assertTrue(str_starts_with(basename($path), 'backup-'));

        $verify = app(BackupService::class)->verifyBackup($path);
        $this->assertTrue($verify['ok'], $verify['message']);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->locateName('database.sql'));
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $this->assertNotFalse($zip->locateName('private/pdfs/sample.pdf'));
        $this->assertNotFalse($zip->locateName('public/avatars/a.txt'));
        $zip->close();
    }

    public function test_placeholder_sql_is_rejected_as_incomplete(): void
    {
        $zipPath = storage_path('backups/backup-incomplete-test.zip');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('database.sql', "-- JobAzmoon backup fallback\n-- mysqldump unavailable; export tables manually if needed.\n");
        $zip->close();

        $verify = app(BackupService::class)->verifyBackup($zipPath);
        $this->assertFalse($verify['ok']);
        $this->assertStringContainsString('ناقص', $verify['message']);
        @unlink($zipPath);
    }

    public function test_list_backups_does_not_expose_full_path(): void
    {
        app(BackupService::class)->createBackup();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/backups')->assertOk();
        $first = $response->json('data.0') ?? $response->json('data.data.0');
        $this->assertIsArray($first);
        $this->assertArrayNotHasKey('full_path', $first);
        $this->assertArrayHasKey('verified', $first);
        $this->assertTrue($first['verified']);
    }

    public function test_regular_admin_cannot_list_backups(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $this->getJson('/api/v1/admin/backups')->assertForbidden();
    }

    public function test_artisan_backup_run_succeeds_on_sqlite(): void
    {
        $this->artisan('backup:run')->assertSuccessful();
    }

    public function test_retention_keeps_only_configured_count(): void
    {
        $service = app(BackupService::class);
        $dir = $service->backupDir();
        for ($i = 0; $i < 3; $i++) {
            file_put_contents($dir.DIRECTORY_SEPARATOR.'backup-old-'.$i.'.zip', 'x');
            touch($dir.DIRECTORY_SEPARATOR.'backup-old-'.$i.'.zip', time() - (3 - $i) * 60);
        }

        $service->cleanupOldBackups(2);
        $left = File::glob($dir.DIRECTORY_SEPARATOR.'backup-old-*.zip') ?: [];
        $this->assertCount(2, $left);
    }
}
