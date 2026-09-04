<?php

declare(strict_types=1);

namespace App\Services\Update;

use App\Models\SystemUpdate;
use App\Services\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

final class UpdateManager
{
    public function __construct(
        private readonly UpdatePackInspector $inspector,
        private readonly UpdateLock $lock,
        private readonly UpdatePathGuard $paths,
        private readonly BackupService $backups,
        private readonly UpdateHealthChecker $health,
    ) {}

    public function currentVersion(): string
    {
        return SemVer::current();
    }

    /**
     * Validate pack without installing.
     *
     * @return array<string, mixed>
     */
    public function validatePack(string $zipPath): array
    {
        $inspected = $this->inspector->inspect($zipPath, $this->currentVersion());
        File::deleteDirectory($inspected['work_dir']);

        return [
            'ok' => true,
            'manifest' => $inspected['manifest'],
            'preflight' => $inspected['preflight'],
            'files_count' => count($inspected['files']),
            'deleted_count' => count($inspected['deleted_files']),
            'migrations_count' => count($inspected['migrations']),
            'current_version' => $this->currentVersion(),
            'target_version' => $inspected['manifest']['version'] ?? null,
        ];
    }

    public function installFromZip(string $zipPath, ?int $userId = null, bool $forceMaintenance = false): SystemUpdate
    {
        $current = $this->currentVersion();
        $update = SystemUpdate::query()->create([
            'uuid' => (string) Str::uuid(),
            'version' => '0.0.0',
            'previous_version' => $current,
            'status' => SystemUpdate::PENDING,
            'user_id' => $userId,
            'pack_path' => $zipPath,
            'started_at' => now(),
            'log' => [],
        ]);

        $maintenanceOn = false;
        $workDir = null;

        try {
            $this->lock->acquire($update->uuid);
            $update->appendLog('قفل به‌روزرسانی گرفته شد.');

            $update->status = SystemUpdate::VALIDATING;
            $update->save();
            $update->appendLog('شروع اعتبارسنجی بسته.');

            $inspected = $this->inspector->inspect($zipPath, $current);
            $workDir = $inspected['work_dir'];
            $manifest = $inspected['manifest'];
            $version = (string) $manifest['version'];

            if (SystemUpdate::query()->where('version', $version)->where('status', SystemUpdate::COMPLETED)->exists()) {
                throw new RuntimeException("نسخه {$version} قبلاً نصب شده است.");
            }

            $update->fill([
                'version' => $version,
                'release_type' => $manifest['release_type'] ?? null,
                'description' => $manifest['description'] ?? null,
                'manifest' => $manifest,
                'preflight' => $inspected['preflight'],
            ])->save();
            $update->appendLog("بسته معتبر است. هدف: {$version}");

            $wantMaintenance = $forceMaintenance || ! empty($manifest['maintenance_mode']);
            if ($wantMaintenance) {
                $secret = (string) (config('update.maintenance_secret') ?: Str::random(32));
                Artisan::call('down', ['--secret' => $secret, '--render' => 'errors::503']);
                $maintenanceOn = true;
                $update->appendLog('حالت تعمیرات فعال شد.');
            }

            $update->status = SystemUpdate::BACKING_UP;
            $update->save();
            $backupId = 'upd-'.$update->uuid;
            $update->backup_id = $backupId;

            // Files backup first — rollback must work even when full DB backup fails (common on cPanel).
            $filesBackup = $this->backupTouchedFiles(
                $backupId,
                $inspected['files'],
                $inspected['deleted_files']
            );
            $update->files_backup_path = $filesBackup;
            $update->save();
            $update->appendLog('بکاپ فایل‌های هدف ایجاد شد.');

            $migrationNeeded = ! empty($manifest['migration_required']) || $inspected['migrations'] !== [];

            try {
                $fullBackup = $this->backups->createBackup();
                $update->full_backup_path = $fullBackup;
                $update->save();
                $update->appendLog('بکاپ کامل ایجاد شد: '.basename($fullBackup));
            } catch (Throwable $backupError) {
                if ($migrationNeeded) {
                    throw $backupError;
                }
                $update->appendLog(
                    'هشدار: بکاپ کامل DB انجام نشد (به‌روزرسانی فقط فایل ادامه می‌یابد): '.$backupError->getMessage(),
                    'warning'
                );
            }

            if ($migrationNeeded && $update->full_backup_path) {
                $dbBackup = $this->extractDatabaseFromFullBackup($update->full_backup_path, $backupId);
                $update->database_backup_path = $dbBackup;
                $update->save();
                $update->appendLog('بکاپ پایگاه‌داده از بکاپ کامل استخراج شد.');
            }

            $update->status = SystemUpdate::INSTALLING;
            $update->save();
            $this->installFiles($workDir, $inspected['files']);
            $this->deleteFiles($inspected['deleted_files']);
            $update->appendLog('فایل‌ها نصب/حذف شدند.');

            if ($inspected['migrations'] !== []) {
                $update->status = SystemUpdate::RUNNING_MIGRATIONS;
                $update->save();
                $this->runPackMigrations($workDir, $inspected['migrations']);
                $update->migrations_ran = true;
                $update->migrations_reversible = is_file((string) $update->full_backup_path)
                    || is_file((string) $update->database_backup_path);
                $update->save();
                $update->appendLog(
                    'مهاجرت‌های بسته اجرا شدند. بازگردانی اسکیما با migrate:rollback انجام نمی‌شود؛ '
                    .'در Failure از Restore بکاپ کامل/SQL استفاده می‌شود'
                    .($update->migrations_reversible ? ' (مسیر بکاپ DB آماده است).' : ' (هشدار: مسیر بکاپ DB ناقص است).')
                );
            }

            $update->status = SystemUpdate::CLEARING_CACHE;
            $update->save();
            $this->refreshCaches();
            $update->appendLog('کش‌ها تازه شدند.');

            $update->status = SystemUpdate::VERIFYING;
            $update->save();
            $health = $this->health->check();
            if (! ($health['ok'] ?? false)) {
                throw new RuntimeException('Health Check پس از به‌روزرسانی ناموفق بود: '.json_encode($health['checks'] ?? []));
            }
            $update->appendLog('Health Check موفق بود.');

            SemVer::writeCurrent($version);

            $update->status = SystemUpdate::COMPLETED;
            $update->finished_at = now();
            $update->duration_ms = (int) $update->started_at?->diffInMilliseconds(now());
            $update->save();
            $update->appendLog("به‌روزرسانی به {$version} کامل شد.");

            return $update->fresh() ?? $update;
        } catch (Throwable $e) {
            $update->status = SystemUpdate::FAILED;
            $update->error = $e->getMessage();
            $update->save();
            $update->appendLog('شکست: '.$e->getMessage(), 'error');

            try {
                $this->rollback($update);
            } catch (Throwable $rollbackError) {
                $update->appendLog('Rollback ناقص: '.$rollbackError->getMessage(), 'error');
                $update->rollback_complete = false;
                $update->save();
            }

            throw $e;
        } finally {
            if ($workDir && is_dir($workDir)) {
                File::deleteDirectory($workDir);
            }
            if ($maintenanceOn) {
                try {
                    Artisan::call('up');
                } catch (Throwable) {
                    //
                }
            }
            $this->lock->release();
        }
    }

    public function rollback(SystemUpdate $update): SystemUpdate
    {
        $update->status = SystemUpdate::ROLLING_BACK;
        $update->save();
        $update->appendLog('شروع Rollback.');

        $complete = true;

        if ($update->files_backup_path && is_file($update->files_backup_path)) {
            $this->restoreFilesBackup($update->files_backup_path);
            $update->appendLog('فایل‌ها از بکاپ بازگردانی شدند.');
        } else {
            $complete = false;
            $update->appendLog('بکاپ فایل برای Rollback در دسترس نیست.', 'error');
        }

        if ($update->migrations_ran) {
            if ($update->full_backup_path && is_file($update->full_backup_path)) {
                try {
                    $this->backups->restore($update->full_backup_path);
                    $update->appendLog('پایگاه‌داده/فایل‌ها از بکاپ کامل بازگردانی شدند.');
                } catch (Throwable $e) {
                    try {
                        $this->restoreDatabaseSql((string) $update->database_backup_path);
                        $update->appendLog('پایگاه‌داده از SQL بکاپ بازگردانی شد.');
                    } catch (Throwable $e2) {
                        $complete = false;
                        $update->appendLog('بازگردانی DB ناموفق: '.$e->getMessage().' / '.$e2->getMessage(), 'error');
                    }
                }
            } elseif ($update->database_backup_path && is_file($update->database_backup_path)) {
                try {
                    $this->restoreDatabaseSql($update->database_backup_path);
                    $update->appendLog('پایگاه‌داده از SQL بکاپ بازگردانی شد.');
                } catch (Throwable $e) {
                    $complete = false;
                    $update->appendLog('بازگردانی DB ناموفق: '.$e->getMessage(), 'error');
                }
            } else {
                $complete = false;
                $update->appendLog('مهاجرت اجرا شده ولی بکاپ DB موجود نیست — Rollback دیتابیس ممکن نیست.', 'error');
            }
            $update->migrations_reversible = $complete;
        }

        if ($update->previous_version && SemVer::isValid($update->previous_version)) {
            SemVer::writeCurrent($update->previous_version);
            $update->appendLog('نسخه به '.$update->previous_version.' بازگردانده شد.');
        }

        try {
            $this->refreshCaches();
        } catch (Throwable) {
            //
        }

        $health = $this->health->check();
        $update->appendLog('Health پس از Rollback: '.(($health['ok'] ?? false) ? 'ok' : 'fail'));

        $update->rollback_complete = $complete;
        $update->status = SystemUpdate::ROLLED_BACK;
        $update->finished_at = now();
        $update->duration_ms = (int) $update->started_at?->diffInMilliseconds(now());
        $update->save();

        return $update->fresh() ?? $update;
    }

    /**
     * @param  list<string>  $files
     * @param  list<string>  $deleted
     */
    private function backupTouchedFiles(string $backupId, array $files, array $deleted): string
    {
        $dir = storage_path('app/updates/backups/'.$backupId);
        File::ensureDirectoryExists($dir);
        $zipPath = $dir.DIRECTORY_SEPARATOR.'files.zip';
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('امکان ایجاد بکاپ فایل‌ها نیست.');
        }

        $meta = ['files' => [], 'deleted_targets' => [], 'new_files' => []];

        foreach (array_unique(array_merge($files, $deleted)) as $rel) {
            $abs = base_path(str_replace('/', DIRECTORY_SEPARATOR, $rel));
            if (is_file($abs)) {
                $zip->addFile($abs, 'files/'.$rel);
                $meta['files'][] = $rel;
            } elseif (in_array($rel, $files, true)) {
                // Will be created by this update — must be removed on rollback.
                $meta['new_files'][] = $rel;
            } elseif (in_array($rel, $deleted, true)) {
                $meta['deleted_targets'][] = $rel;
            }
        }

        $zip->addFromString('meta.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');
        $zip->close();

        return $zipPath;
    }

    private function extractDatabaseFromFullBackup(string $fullBackupZip, string $backupId): string
    {
        $dir = storage_path('app/updates/backups/'.$backupId);
        File::ensureDirectoryExists($dir);
        $sqlPath = $dir.DIRECTORY_SEPARATOR.'database.sql';

        $zip = new ZipArchive;
        if ($zip->open($fullBackupZip) !== true) {
            throw new RuntimeException('باز کردن بکاپ کامل برای استخراج DB ناموفق بود.');
        }

        $sql = $zip->getFromName('database.sql');
        if (is_string($sql) && $sql !== '') {
            file_put_contents($sqlPath, $sql);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_ends_with(strtolower($name), '.sqlite')) {
                $bin = $zip->getFromIndex($i);
                if (is_string($bin)) {
                    file_put_contents($dir.DIRECTORY_SEPARATOR.'database.sqlite', $bin);
                }
            }
        }
        $zip->close();

        if (! is_file($sqlPath) && ! is_file($dir.DIRECTORY_SEPARATOR.'database.sqlite')) {
            throw new RuntimeException('استخراج پایگاه‌داده از بکاپ کامل ناموفق بود.');
        }

        if (! is_file($sqlPath)) {
            file_put_contents($sqlPath, "-- sqlite binary extracted as database.sqlite\n");
        }

        return $sqlPath;
    }

    /**
     * @param  list<string>  $files
     */
    private function installFiles(string $workDir, array $files): void
    {
        $filesRoot = $workDir.DIRECTORY_SEPARATOR.'files';
        foreach ($files as $rel) {
            $this->paths->assertWritableTarget($rel);
            $src = $filesRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (is_link($src)) {
                throw new RuntimeException("فایل symlink در بسته مجاز نیست: {$rel}");
            }
            $dest = base_path(str_replace('/', DIRECTORY_SEPARATOR, $rel));
            if (is_link($dest)) {
                throw new RuntimeException("مقصد symlink است و قابل overwrite نیست: {$rel}");
            }
            File::ensureDirectoryExists(dirname($dest));
            if (! copy($src, $dest)) {
                throw new RuntimeException("کپی فایل ناموفق: {$rel}");
            }
        }
    }

    /**
     * @param  list<string>  $files
     */
    private function deleteFiles(array $files): void
    {
        foreach ($files as $rel) {
            $this->paths->assertWritableTarget($rel);
            $dest = base_path(str_replace('/', DIRECTORY_SEPARATOR, $rel));
            if (is_file($dest)) {
                @unlink($dest);
            }
        }
    }

    /**
     * @param  list<string>  $migrations
     */
    private function runPackMigrations(string $workDir, array $migrations): void
    {
        $src = $workDir.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        $dest = database_path('migrations');
        File::ensureDirectoryExists($dest);

        $copied = [];
        foreach ($migrations as $rel) {
            $from = $src.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $name = basename($rel);
            $to = $dest.DIRECTORY_SEPARATOR.$name;
            if (! is_file($to)) {
                copy($from, $to);
                $copied[] = $to;
            }
        }

        $exit = Artisan::call('migrate', ['--force' => true]);
        if ($exit !== 0) {
            throw new RuntimeException('اجرای migrate ناموفق بود: '.Artisan::output());
        }
    }

    private function restoreFilesBackup(string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('باز کردن بکاپ فایل ناموفق بود.');
        }
        $tmp = storage_path('app/updates/restore-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($tmp);
        $zip->extractTo($tmp);
        $zip->close();

        $metaFile = $tmp.DIRECTORY_SEPARATOR.'meta.json';
        $meta = is_file($metaFile) ? json_decode((string) file_get_contents($metaFile), true) : [];
        $filesRoot = $tmp.DIRECTORY_SEPARATOR.'files';

        if (is_dir($filesRoot)) {
            $rootReal = realpath($filesRoot);
            if ($rootReal === false) {
                throw new RuntimeException('پوشه بکاپ فایل نامعتبر است.');
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($filesRoot, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (! $file->isFile()) {
                    continue;
                }
                $rel = $this->paths->normalize(substr($file->getPathname(), strlen($rootReal) + 1));
                $this->paths->assertWritableTarget($rel);
                $dest = base_path(str_replace('/', DIRECTORY_SEPARATOR, $rel));
                File::ensureDirectoryExists(dirname($dest));
                copy($file->getPathname(), $dest);
            }
        }

        /** @var list<string> $newFiles */
        $newFiles = is_array($meta['new_files'] ?? null) ? $meta['new_files'] : [];
        foreach ($newFiles as $rel) {
            $rel = $this->paths->normalize((string) $rel);
            $this->paths->assertWritableTarget($rel);
            $dest = base_path(str_replace('/', DIRECTORY_SEPARATOR, $rel));
            if (is_file($dest)) {
                @unlink($dest);
            }
        }

        File::deleteDirectory($tmp);
    }

    private function restoreDatabaseSql(string $sqlPath): void
    {
        $sqliteCopy = dirname($sqlPath).DIRECTORY_SEPARATOR.'database.sqlite';
        if (is_file($sqliteCopy) && config('database.default') === 'sqlite') {
            $db = config('database.connections.sqlite.database');
            if (is_string($db)) {
                copy($sqliteCopy, $db);

                return;
            }
        }

        $content = (string) file_get_contents($sqlPath);
        if (str_contains($content, 'see full_backup_path')) {
            throw new RuntimeException('برای Rollback دیتابیس MySQL از بکاپ کامل (full_backup_path) استفاده کنید.');
        }

        // Best-effort: restore via full backup sibling if present on the update record is handled by caller.
        if (strlen($content) > 50 && config('database.default') === 'mysql') {
            DB::unprepared($content);
        }
    }

    private function refreshCaches(): void
    {
        try {
            Artisan::call('optimize:clear');
        } catch (Throwable $e) {
            Log::warning('Update optimize:clear failed', ['error' => $e->getMessage()]);
        }

        if (! app()->environment('production')) {
            return;
        }

        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            try {
                $exit = Artisan::call($command);
                if ($exit !== 0) {
                    Log::warning("Update {$command} returned non-zero", ['exit' => $exit, 'output' => Artisan::output()]);
                }
            } catch (Throwable $e) {
                Log::warning("Update {$command} failed", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * @return list<SystemUpdate>
     */
    public function history(int $limit = 50): array
    {
        return SystemUpdate::query()->latest('id')->limit($limit)->get()->all();
    }

    /**
     * @return array{
     *   current_version: string,
     *   locked: bool,
     *   lock: array<string, mixed>|null,
     *   last: SystemUpdate|null,
     *   health: array{ok: bool, checks: array<string, string>, version: string}
     * }
     */
    public function status(): array
    {
        return [
            'current_version' => $this->currentVersion(),
            'locked' => $this->lock->isLocked(),
            'lock' => $this->lock->info(),
            'last' => SystemUpdate::query()->latest('id')->first(),
            'health' => $this->health->check(),
        ];
    }
}
