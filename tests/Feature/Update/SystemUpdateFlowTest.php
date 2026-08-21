<?php

declare(strict_types=1);

namespace Tests\Feature\Update;

use App\Models\SystemUpdate;
use App\Models\User;
use App\Services\Update\SemVer;
use App\Services\Update\UpdateManager;
use App\Services\Update\UpdatePackBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

final class SystemUpdateFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = storage_path('app/updates/test-fixtures');
        File::ensureDirectoryExists($this->fixtureDir);
        SemVer::writeCurrent('1.0.0');
    }

    protected function tearDown(): void
    {
        if (is_dir($this->fixtureDir)) {
            File::deleteDirectory($this->fixtureDir);
        }
        parent::tearDown();
    }

    public function test_super_admin_can_view_status_operator_cannot(): void
    {
        $super = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($super);
        $this->getJson('/api/v1/admin/system-updates/status')->assertSuccessful();

        $op = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['exams', 'system_update'],
        ]);
        Sanctum::actingAs($op);
        $this->getJson('/api/v1/admin/system-updates/status')->assertForbidden();
    }

    public function test_validate_and_install_pack(): void
    {
        $target = app_path('Support/_UpdateProbe.php');
        @unlink($target);
        file_put_contents($target, "<?php\nnamespace App\\Support;\nclass _UpdateProbe { public const V = '1.0.1'; }\n");

        $zip = app(UpdatePackBuilder::class)->build(
            targetVersion: '1.0.1',
            previousVersion: '1.0.0',
            files: ['app/Support/_UpdateProbe.php'],
            description: 'probe',
            releaseType: 'patch',
            migrationRequired: false,
            maintenanceMode: false,
            outputDir: $this->fixtureDir,
        );

        // Reset file to old content to simulate production before update
        file_put_contents($target, "<?php\nnamespace App\\Support;\nclass _UpdateProbe { public const V = '1.0.0'; }\n");
        SemVer::writeCurrent('1.0.0');

        $manager = app(UpdateManager::class);
        $validated = $manager->validatePack($zip);
        $this->assertTrue($validated['ok']);
        $this->assertSame('1.0.1', $validated['target_version']);

        $update = $manager->installFromZip($zip, null);
        $this->assertSame(SystemUpdate::COMPLETED, $update->status);
        $this->assertSame('1.0.1', SemVer::current());
        $this->assertStringContainsString('1.0.1', (string) file_get_contents($target));

        @unlink($target);
    }

    public function test_rejects_path_traversal_in_zip(): void
    {
        $zipPath = $this->fixtureDir.DIRECTORY_SEPARATOR.'evil.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $manifest = [
            'application' => 'JobAzmoon',
            'version' => '1.0.2',
            'minimum_version' => '1.0.0',
            'release_date' => '2026-08-21',
            'release_type' => 'patch',
            'description' => 'evil',
            'php' => '8.2',
            'laravel' => '11',
            'backup_required' => true,
            'migration_required' => false,
            'maintenance_mode' => false,
            'files' => ['app/Evil.php'],
            'deleted_files' => [],
            'checksums' => ['app/Evil.php' => hash('sha256', 'x')],
        ];
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->addFromString('checksums.json', json_encode(['app/Evil.php' => hash('sha256', 'x')]));
        $zip->addFromString('../.env', "APP_KEY=hacked\n");
        $zip->close();

        $this->expectException(\RuntimeException::class);
        app(UpdateManager::class)->validatePack($zipPath);
    }

    public function test_rejects_env_overwrite_declared_file(): void
    {
        $zipPath = $this->fixtureDir.DIRECTORY_SEPARATOR.'env.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $body = "APP_KEY=hacked\n";
        $manifest = [
            'application' => 'JobAzmoon',
            'version' => '1.0.3',
            'minimum_version' => '1.0.0',
            'release_date' => '2026-08-21',
            'release_type' => 'patch',
            'description' => 'env',
            'php' => '8.2',
            'laravel' => '11',
            'backup_required' => true,
            'migration_required' => false,
            'maintenance_mode' => false,
            'files' => ['.env'],
            'deleted_files' => [],
            'checksums' => ['.env' => hash('sha256', $body)],
        ];
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->addFromString('checksums.json', json_encode(['.env' => hash('sha256', $body)]));
        $zip->addFromString('files/.env', $body);
        $zip->close();

        $this->expectException(\RuntimeException::class);
        app(UpdateManager::class)->validatePack($zipPath);
    }

    public function test_duplicate_completed_version_blocked(): void
    {
        SystemUpdate::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'version' => '1.0.9',
            'previous_version' => '1.0.0',
            'status' => SystemUpdate::COMPLETED,
        ]);

        $target = app_path('Support/_UpdateProbe2.php');
        file_put_contents($target, "<?php\nnamespace App\\Support;\nclass _UpdateProbe2 {}\n");
        $zip = app(UpdatePackBuilder::class)->build(
            '1.0.9',
            '1.0.0',
            ['app/Support/_UpdateProbe2.php'],
            [],
            'dup',
            'patch',
            false,
            false,
            $this->fixtureDir,
        );
        SemVer::writeCurrent('1.0.0');

        try {
            $this->expectException(\RuntimeException::class);
            app(UpdateManager::class)->installFromZip($zip, null);
        } finally {
            @unlink($target);
        }
    }

    public function test_admin_upload_validate_endpoint(): void
    {
        $target = app_path('Support/_UpdateProbe3.php');
        file_put_contents($target, "<?php\nnamespace App\\Support;\nclass _UpdateProbe3 { public const V='x'; }\n");
        $zip = app(UpdatePackBuilder::class)->build(
            '1.0.4',
            '1.0.0',
            ['app/Support/_UpdateProbe3.php'],
            [],
            'api',
            'patch',
            false,
            false,
            $this->fixtureDir,
        );
        @unlink($target);

        $super = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Sanctum::actingAs($super);

        $upload = new UploadedFile($zip, basename($zip), 'application/zip', null, true);
        $this->post('/api/v1/admin/system-updates/validate', ['file' => $upload], [
            'Accept' => 'application/json',
        ])->assertSuccessful()
            ->assertJsonPath('data.target_version', '1.0.4');
    }

    public function test_concurrent_lock_blocks_second_install(): void
    {
        $lock = storage_path('app/updates/update.lock');
        File::ensureDirectoryExists(dirname($lock));
        file_put_contents($lock, json_encode(['uuid' => 'x', 'started_at' => now()->toIso8601String()]));

        $target = app_path('Support/_UpdateProbe4.php');
        file_put_contents($target, "<?php\nnamespace App\\Support;\nclass _UpdateProbe4 {}\n");
        $zip = app(UpdatePackBuilder::class)->build(
            '1.0.5',
            '1.0.0',
            ['app/Support/_UpdateProbe4.php'],
            [],
            'lock',
            'patch',
            false,
            false,
            $this->fixtureDir,
        );
        SemVer::writeCurrent('1.0.0');

        try {
            $this->expectException(\RuntimeException::class);
            app(UpdateManager::class)->installFromZip($zip, null);
        } finally {
            @unlink($target);
            @unlink($lock);
        }
    }
}
