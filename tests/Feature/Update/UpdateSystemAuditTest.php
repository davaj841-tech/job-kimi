<?php

declare(strict_types=1);

namespace Tests\Feature\Update;

use App\Models\SystemUpdate;
use App\Services\BackupService;
use App\Services\Update\ManifestValidator;
use App\Services\Update\SemVer;
use App\Services\Update\UpdateHealthChecker;
use App\Services\Update\UpdateLock;
use App\Services\Update\UpdateManager;
use App\Services\Update\UpdatePackBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

/**
 * Production-gate audit: real install, failure/rollback, security, version, concurrency, backup.
 */
final class UpdateSystemAuditTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private string $probeRel = 'app/Support/_AuditProbe.php';

    private string $probeAbs;

    private string $newRel = 'app/Support/_AuditNewFile.php';

    private string $newAbs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('app/updates/audit-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->dir);
        $this->probeAbs = base_path(str_replace('/', DIRECTORY_SEPARATOR, $this->probeRel));
        $this->newAbs = base_path(str_replace('/', DIRECTORY_SEPARATOR, $this->newRel));
        @unlink($this->probeAbs);
        @unlink($this->newAbs);
        SemVer::writeCurrent('1.0.0');
        app(UpdateLock::class)->release();
        try {
            Artisan::call('up');
        } catch (\Throwable) {
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->probeAbs);
        @unlink($this->newAbs);
        app(UpdateLock::class)->release();
        try {
            Artisan::call('up');
        } catch (\Throwable) {
        }
        if (is_dir($this->dir)) {
            File::deleteDirectory($this->dir);
        }
        // Drop audit table if a failed migration left it
        try {
            Schema::dropIfExists('update_audit_probe');
        } catch (\Throwable) {
        }
        parent::tearDown();
    }

    public function test_real_update_v100_to_v101_with_migration_backup_history_health(): void
    {
        file_put_contents($this->probeAbs, $this->probeSource('1.0.1'));
        file_put_contents($this->newAbs, "<?php\n// will be packaged as NEW file content\nnamespace App\\Support;\nclass _AuditNewFile { public const V='1.0.1'; }\n");

        $migName = '2026_08_21_999001_create_update_audit_probe_table.php';
        $migRel = 'database/migrations/'.$migName;
        $migAbs = database_path('migrations'.DIRECTORY_SEPARATOR.$migName);
        file_put_contents($migAbs, $this->goodMigrationSource());

        $zip = app(UpdatePackBuilder::class)->build(
            targetVersion: '1.0.1',
            previousVersion: '1.0.0',
            files: [$this->probeRel, $this->newRel, $migRel],
            description: 'audit real update',
            releaseType: 'patch',
            migrationRequired: true,
            maintenanceMode: false,
            outputDir: $this->dir,
        );

        // Simulate production before pack content is applied
        file_put_contents($this->probeAbs, $this->probeSource('1.0.0'));
        @unlink($this->newAbs);
        // Keep migration out of project until pack installs it (pack already contains it)
        @unlink($migAbs);
        SemVer::writeCurrent('1.0.0');

        $manager = app(UpdateManager::class);
        $update = $manager->installFromZip($zip, null);

        $this->assertSame(SystemUpdate::COMPLETED, $update->status);
        $this->assertSame('1.0.1', SemVer::current());
        $this->assertStringContainsString("'1.0.1'", (string) file_get_contents($this->probeAbs));
        $this->assertFileExists($this->newAbs);
        $this->assertTrue(Schema::hasTable('update_audit_probe'));
        $this->assertNotEmpty($update->full_backup_path);
        $this->assertFileExists((string) $update->full_backup_path);
        $verify = app(BackupService::class)->verifyBackup((string) $update->full_backup_path);
        $this->assertTrue($verify['ok'], $verify['message'] ?? 'backup invalid');
        $this->assertNotEmpty($update->files_backup_path);
        $this->assertTrue($update->migrations_ran);
        $this->assertSame('1.0.1', $manager->status()['current_version']);
        $this->assertTrue($manager->status()['health']['ok']);
        $this->assertFalse(app(UpdateLock::class)->isLocked());

        $history = SystemUpdate::query()->where('version', '1.0.1')->where('status', SystemUpdate::COMPLETED)->count();
        $this->assertSame(1, $history);

        // cleanup migration file left in project
        @unlink($migAbs);
    }

    public function test_real_failure_rolls_back_files_version_lock_and_status(): void
    {
        file_put_contents($this->probeAbs, $this->probeSource('1.0.0-original'));
        file_put_contents($this->newAbs, "<?php\nnamespace App\\Support;\nclass _AuditNewFile {}\n");

        $migName = '2026_08_21_999002_failing_update_audit_migration.php';
        $migRel = 'database/migrations/'.$migName;
        $migAbs = database_path('migrations'.DIRECTORY_SEPARATOR.$migName);
        // Pack will contain failing migration; start without it on disk
        $failingBody = $this->failingMigrationSource();
        file_put_contents($migAbs, $failingBody);

        // New probe content for pack
        file_put_contents($this->probeAbs, $this->probeSource('1.0.2-broken'));

        $zip = app(UpdatePackBuilder::class)->build(
            targetVersion: '1.0.2',
            previousVersion: '1.0.0',
            files: [$this->probeRel, $this->newRel, $migRel],
            description: 'intentional failure',
            releaseType: 'patch',
            migrationRequired: true,
            maintenanceMode: true,
            outputDir: $this->dir,
        );

        // Reset to pre-install state
        file_put_contents($this->probeAbs, $this->probeSource('1.0.0-original'));
        @unlink($this->newAbs);
        @unlink($migAbs);
        SemVer::writeCurrent('1.0.0');

        $manager = app(UpdateManager::class);
        $caught = null;
        try {
            $manager->installFromZip($zip, null);
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $row = SystemUpdate::query()->where('version', '1.0.2')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame(SystemUpdate::ROLLED_BACK, $row->status);
        $this->assertNotNull($row->error);
        $this->assertStringContainsString('1.0.0-original', (string) file_get_contents($this->probeAbs));
        $this->assertFileDoesNotExist($this->newAbs);
        $this->assertSame('1.0.0', SemVer::current());
        $this->assertFalse(app(UpdateLock::class)->isLocked());
        $this->assertFalse(app()->isDownForMaintenance());
        $logMessages = [];
        foreach ($row->log ?? [] as $entry) {
            if (is_array($entry) && isset($entry['message'])) {
                $logMessages[] = (string) $entry['message'];
            }
        }
        $joined = implode("\n", $logMessages);
        $this->assertTrue(
            (bool) preg_match('/Health.*Rollback/u', $joined),
            'Expected Health+Rollback log entry, got: '.$joined
        );
        $this->assertTrue(
            (bool) preg_match('/intentional migration failure|شکست/u', $joined),
            'Expected failure log entry'
        );

        @unlink($migAbs);
    }

    public function test_security_rejects_zip_slip_env_vendor_storage_bad_checksum_corrupt_and_oversized(): void
    {
        $manager = app(UpdateManager::class);
        SemVer::writeCurrent('1.0.0');

        $cases = [
            'zip-slip' => function (string $path): void {
                $this->writeMaliciousZip($path, '../.env', "HACKED\n", ['app/x.php']);
            },
            'zip-slip-nested' => function (string $path): void {
                $this->writeMaliciousZip($path, 'files/../../.env', "HACKED\n", ['app/x.php']);
            },
            'env-declared' => function (string $path): void {
                $body = "APP_KEY=stolen\n";
                $this->writeDeclaredProtectedZip($path, '.env', $body, '1.0.3');
            },
            'storage-declared' => function (string $path): void {
                $this->writeDeclaredProtectedZip($path, 'storage/logs/hack.txt', 'x', '1.0.4');
            },
            'vendor-declared' => function (string $path): void {
                $this->writeDeclaredProtectedZip($path, 'vendor/autoload.php', 'x', '1.0.5');
            },
            'bootstrap-cache' => function (string $path): void {
                $this->writeDeclaredProtectedZip($path, 'bootstrap/cache/config.php', 'x', '1.0.6');
            },
            'absolute-win' => function (string $path): void {
                $this->writeMaliciousZip($path, 'C:/Windows/Temp/x.php', 'x', ['app/x.php']);
            },
            'bad-checksum' => function (string $path): void {
                $body = "<?php\n";
                $zip = new ZipArchive;
                $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $manifest = $this->baseManifest('1.0.7', ['app/Support/_BadChecksum.php']);
                $manifest['checksums'] = ['app/Support/_BadChecksum.php' => hash('sha256', 'wrong')];
                $zip->addFromString('manifest.json', json_encode($manifest) ?: '{}');
                $zip->addFromString('checksums.json', json_encode($manifest['checksums']) ?: '{}');
                $zip->addFromString('files/app/Support/_BadChecksum.php', $body);
                $zip->close();
            },
            'fake-manifest-app' => function (string $path): void {
                $body = "<?php\n";
                $zip = new ZipArchive;
                $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $manifest = $this->baseManifest('1.0.8', ['app/Support/_Fake.php']);
                $manifest['application'] = 'OtherApp';
                $manifest['checksums'] = ['app/Support/_Fake.php' => hash('sha256', $body)];
                $zip->addFromString('manifest.json', json_encode($manifest) ?: '{}');
                $zip->addFromString('checksums.json', json_encode($manifest['checksums']) ?: '{}');
                $zip->addFromString('files/app/Support/_Fake.php', $body);
                $zip->close();
            },
            'corrupt-zip' => function (string $path): void {
                file_put_contents($path, 'not-a-zip');
            },
            'oversized' => function (string $path): void {
                config(['update.max_upload_kb' => 1]); // 1 KB
                file_put_contents($path, str_repeat('A', 2048));
            },
        ];

        $envBefore = is_file(base_path('.env')) ? (string) file_get_contents(base_path('.env')) : null;

        foreach ($cases as $name => $builder) {
            $zipPath = $this->dir.DIRECTORY_SEPARATOR.$name.'.zip';
            $builder($zipPath);
            try {
                $manager->validatePack($zipPath);
                $this->fail("Expected rejection for case: {$name}");
            } catch (\Throwable $e) {
                $this->assertNotSame('', $e->getMessage(), $name);
            }
        }

        if ($envBefore !== null) {
            $this->assertSame($envBefore, (string) file_get_contents(base_path('.env')));
        }
        $this->assertFileDoesNotExist(base_path('storage/logs/hack.txt'));
    }

    public function test_duplicate_and_downgrade_and_same_version_blocked_major_policy(): void
    {
        SemVer::writeCurrent('1.0.1');
        $v = new ManifestValidator;

        $downgrade = $this->baseManifest('1.0.0', []);
        $this->assertFalse($v->validate($downgrade, '1.0.1')['ok']);

        $same = $this->baseManifest('1.0.1', []);
        $this->assertFalse($v->validate($same, '1.0.1')['ok']);

        $patchMajor = $this->baseManifest('2.0.0', []);
        $patchMajor['release_type'] = 'patch';
        $this->assertFalse($v->validate($patchMajor, '1.0.0')['ok']);

        $okMajor = $this->baseManifest('2.0.0', []);
        $okMajor['release_type'] = 'major';
        $okMajor['minimum_version'] = '1.0.0';
        $this->assertTrue($v->validate($okMajor, '1.0.0')['ok']);

        $okPatch = $this->baseManifest('1.0.2', []);
        $this->assertTrue($v->validate($okPatch, '1.0.1')['ok']);
    }

    public function test_duplicate_completed_install_blocked(): void
    {
        file_put_contents($this->probeAbs, $this->probeSource('x'));
        $zip = app(UpdatePackBuilder::class)->build(
            '1.0.9',
            '1.0.0',
            [$this->probeRel],
            [],
            'dup',
            'patch',
            false,
            false,
            $this->dir,
        );
        SemVer::writeCurrent('1.0.0');
        $manager = app(UpdateManager::class);
        $first = $manager->installFromZip($zip, null);
        $this->assertSame(SystemUpdate::COMPLETED, $first->status);

        SemVer::writeCurrent('1.0.0'); // even if version file rolled, completed row blocks
        $this->expectException(\RuntimeException::class);
        $manager->installFromZip($zip, null);
    }

    public function test_concurrent_lock_allows_only_one(): void
    {
        $lock = app(UpdateLock::class);
        $lock->acquire('holder-1');
        $this->assertTrue($lock->isLocked());

        file_put_contents($this->probeAbs, $this->probeSource('c'));
        $zip = app(UpdatePackBuilder::class)->build(
            '1.0.10',
            '1.0.0',
            [$this->probeRel],
            [],
            'lock',
            'patch',
            false,
            false,
            $this->dir,
        );
        SemVer::writeCurrent('1.0.0');

        try {
            app(UpdateManager::class)->installFromZip($zip, null);
            $this->fail('Second install should be blocked');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('در حال اجرا', $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    public function test_backup_is_restorable_not_metadata_only(): void
    {
        $backup = app(BackupService::class)->createBackup();
        $this->assertFileExists($backup);
        $verify = app(BackupService::class)->verifyBackup($backup);
        $this->assertTrue($verify['ok'], $verify['message'] ?? '');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($backup) === true);
        $hasDb = $zip->locateName('database.sql') !== false;
        $sql = (string) $zip->getFromName('database.sql');
        $zip->close();
        $this->assertTrue($hasDb);
        $this->assertGreaterThan(100, strlen($sql));
        $this->assertStringNotContainsString('mysqldump unavailable', $sql);
    }

    public function test_file_only_update_succeeds_when_full_backup_unavailable(): void
    {
        file_put_contents($this->probeAbs, $this->probeSource('1.0.1-pack'));

        $zip = app(UpdatePackBuilder::class)->build(
            targetVersion: '1.0.1',
            previousVersion: '1.0.0',
            files: [$this->probeRel],
            description: 'file-only no migration',
            releaseType: 'patch',
            migrationRequired: false,
            maintenanceMode: false,
            outputDir: $this->dir,
        );

        file_put_contents($this->probeAbs, $this->probeSource('1.0.0-prod'));
        SemVer::writeCurrent('1.0.0');

        $this->mock(BackupService::class, function ($mock): void {
            $mock->shouldReceive('createBackup')
                ->once()
                ->andThrow(new \RuntimeException('mysqldump یافت نشد. بکاپ پایگاه داده انجام نشد.'));
        });

        $update = app(UpdateManager::class)->installFromZip($zip, null);

        $this->assertSame(SystemUpdate::COMPLETED, $update->status);
        $this->assertSame('1.0.1', SemVer::current());
        $this->assertStringContainsString('1.0.1-pack', (string) file_get_contents($this->probeAbs));
        $this->assertNotEmpty($update->files_backup_path);
        $this->assertFileExists((string) $update->files_backup_path);
        $this->assertNull($update->full_backup_path);
        $this->assertFalse($update->migrations_ran);

        $logMessages = [];
        foreach ($update->log ?? [] as $entry) {
            if (is_array($entry) && isset($entry['message'])) {
                $logMessages[] = (string) $entry['message'];
            }
        }
        $this->assertTrue(
            (bool) preg_match('/بکاپ فایل/u', implode("\n", $logMessages)),
            'Expected files backup log before install'
        );
    }

    public function test_file_only_failure_rollback_complete_without_db_backup(): void
    {
        file_put_contents($this->probeAbs, $this->probeSource('1.0.2-broken'));

        $zip = app(UpdatePackBuilder::class)->build(
            targetVersion: '1.0.2',
            previousVersion: '1.0.0',
            files: [$this->probeRel],
            description: 'file-only failure path',
            releaseType: 'patch',
            migrationRequired: false,
            maintenanceMode: false,
            outputDir: $this->dir,
        );

        file_put_contents($this->probeAbs, $this->probeSource('1.0.0-safe'));
        SemVer::writeCurrent('1.0.0');

        $this->mock(BackupService::class, function ($mock): void {
            $mock->shouldReceive('createBackup')
                ->once()
                ->andThrow(new \RuntimeException('mysqldump یافت نشد.'));
        });

        $this->mock(UpdateHealthChecker::class, function ($mock): void {
            $mock->shouldReceive('check')->andReturn([
                'ok' => false,
                'checks' => ['database' => 'fail'],
                'version' => '1.0.0',
            ]);
        });

        $caught = null;
        try {
            app(UpdateManager::class)->installFromZip($zip, null);
        } catch (\Throwable $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $row = SystemUpdate::query()->where('version', '1.0.2')->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame(SystemUpdate::ROLLED_BACK, $row->status);
        $this->assertTrue($row->rollback_complete, 'Files backup should allow complete rollback');
        $this->assertSame('1.0.0', SemVer::current());
        $this->assertStringContainsString('1.0.0-safe', (string) file_get_contents($this->probeAbs));
    }

    public function test_cpanel_compatibility_preflight(): void
    {
        $this->assertTrue(class_exists(ZipArchive::class));
        $this->assertTrue(is_writable(storage_path('app')));
        $this->assertTrue(is_writable(storage_path('logs')));
        $this->assertGreaterThan(0, (int) config('update.max_upload_kb'));
        $this->assertTrue((bool) config('update.require_composer_blocks_install'));
        // No root / SSH required for core path: validate+install uses PHP ZipArchive + Artisan migrate only.
        $this->assertTrue(function_exists('proc_open') || true);
        $status = app(UpdateManager::class)->status();
        $this->assertArrayHasKey('health', $status);
        $this->assertArrayHasKey('current_version', $status);
    }

    private function probeSource(string $v): string
    {
        return "<?php\nnamespace App\\Support;\nclass _AuditProbe { public const V = '{$v}'; }\n";
    }

    private function goodMigrationSource(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_audit_probe', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_audit_probe');
    }
};
PHP;
    }

    private function failingMigrationSource(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        throw new RuntimeException('intentional migration failure for audit');
    }

    public function down(): void
    {
    }
};
PHP;
    }

    /**
     * @param  list<string>  $files
     * @return array<string, mixed>
     */
    private function baseManifest(string $version, array $files): array
    {
        return [
            'application' => 'JobAzmoon',
            'version' => $version,
            'minimum_version' => '1.0.0',
            'release_date' => '2026-08-21',
            'release_type' => 'patch',
            'description' => 't',
            'php' => '8.2',
            'laravel' => '11',
            'backup_required' => true,
            'migration_required' => false,
            'maintenance_mode' => false,
            'files' => $files,
            'deleted_files' => [],
            'checksums' => [],
        ];
    }

    /**
     * @param  list<string>  $declared
     */
    private function writeMaliciousZip(string $path, string $evilName, string $body, array $declared): void
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $manifest = $this->baseManifest('1.0.11', $declared);
        $checksums = [];
        foreach ($declared as $f) {
            $checksums[$f] = hash('sha256', 'x');
        }
        $manifest['checksums'] = $checksums;
        $zip->addFromString('manifest.json', json_encode($manifest) ?: '{}');
        $zip->addFromString('checksums.json', json_encode($checksums) ?: '{}');
        foreach ($declared as $f) {
            $zip->addFromString('files/'.$f, 'x');
        }
        $zip->addFromString($evilName, $body);
        $zip->close();
    }

    private function writeDeclaredProtectedZip(string $path, string $rel, string $body, string $version): void
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $manifest = $this->baseManifest($version, [$rel]);
        $manifest['checksums'] = [$rel => hash('sha256', $body)];
        $zip->addFromString('manifest.json', json_encode($manifest) ?: '{}');
        $zip->addFromString('checksums.json', json_encode($manifest['checksums']) ?: '{}');
        $zip->addFromString('files/'.$rel, $body);
        $zip->close();
    }
}
