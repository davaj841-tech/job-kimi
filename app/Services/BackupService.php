<?php

namespace App\Services;

use App\Support\ThemeBootstrap;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class BackupService
{
    public const MANIFEST_NAME = 'manifest.json';

    public function backupDir(): string
    {
        $dir = storage_path('backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /**
     * Create a verified ZIP backup (database + private uploads + public files).
     *
     * @throws RuntimeException when the backup is incomplete or cannot be verified
     */
    public function createBackup(): string
    {
        $this->cleanupOldBackups();

        $stamp = now()->format('Y-m-d_His');
        $work = storage_path('app/backup-tmp-'.$stamp);
        File::ensureDirectoryExists($work);

        $warnings = [];
        $components = [
            'database' => false,
            'private' => false,
            'public' => false,
        ];

        try {
            $sqlFile = $work.'/database.sql';
            $this->dumpDatabase($sqlFile);
            $components['database'] = true;

            $privateRoot = storage_path('app/private');
            if (is_dir($privateRoot)) {
                File::copyDirectory($privateRoot, $work.'/private');
                $components['private'] = true;
            } else {
                $warnings[] = 'storage/app/private missing';
            }

            // Legacy paths (pre Laravel-11 private disk) — include if present.
            foreach (['pdfs', 'resumes'] as $legacy) {
                $src = storage_path('app/'.$legacy);
                if (is_dir($src) && ! is_dir($work.'/private/'.$legacy)) {
                    File::copyDirectory($src, $work.'/private/'.$legacy);
                    $components['private'] = true;
                    $warnings[] = "legacy storage/app/{$legacy} included under private/{$legacy}";
                }
            }

            $public = storage_path('app/public');
            if (is_dir($public)) {
                File::copyDirectory($public, $work.'/public');
                $components['public'] = true;
            } else {
                $warnings[] = 'storage/app/public missing';
            }

            if (! is_file($sqlFile)) {
                throw new RuntimeException('بکاپ ناقص: پایگاه داده ذخیره نشد.');
            }

            $manifest = [
                'version' => 2,
                'app' => 'JobAzmoon',
                'created_at' => now()->toIso8601String(),
                'driver' => (string) config('database.default'),
                'components' => $components,
                'warnings' => $warnings,
                'status' => $warnings === [] ? 'complete' : 'complete_with_warnings',
                'checksums' => $this->checksumTree($work),
            ];
            file_put_contents(
                $work.'/'.self::MANIFEST_NAME,
                json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );

            $zipName = 'backup-'.$stamp.'.zip';
            $zipPath = $this->backupDir().DIRECTORY_SEPARATOR.$zipName;

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('امکان ایجاد فایل بکاپ وجود ندارد.');
            }

            $this->addFolderToZip($zip, $work, '');
            $zip->close();

            $verify = $this->verifyBackup($zipPath);
            if (! ($verify['ok'] ?? false)) {
                @unlink($zipPath);
                throw new RuntimeException('بکاپ ایجاد شد اما تأیید اعتبار ناموفق بود: '.($verify['message'] ?? ''));
            }

            if ($warnings !== []) {
                Log::warning('Backup completed with warnings', [
                    'file' => basename($zipPath),
                    'warnings' => $warnings,
                ]);
            } else {
                Log::info('Backup created', ['file' => basename($zipPath)]);
            }

            $this->cleanupOldBackups();

            return $zipPath;
        } catch (\Throwable $e) {
            Log::error('Backup failed', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            if (is_dir($work)) {
                File::deleteDirectory($work);
            }
        }
    }

    /**
     * @return array{ok: bool, message: string, manifest?: array<string, mixed>|null}
     */
    public function verifyBackup(string $zipPath): array
    {
        if (! is_file($zipPath) || ! Str::endsWith(strtolower($zipPath), '.zip')) {
            return ['ok' => false, 'message' => 'فایل ZIP نامعتبر است.'];
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'message' => 'امکان باز کردن ZIP وجود ندارد.'];
        }

        $hasSql = $zip->locateName('database.sql') !== false;
        $hasSqlite = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_ends_with(strtolower($name), '.sqlite')) {
                $hasSqlite = true;
                break;
            }
        }

        $manifestRaw = $zip->getFromName(self::MANIFEST_NAME);
        $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;

        $sqlPreview = (string) ($zip->getFromName('database.sql') ?: '');
        $zip->close();

        if (! $hasSql && ! $hasSqlite) {
            return ['ok' => false, 'message' => 'بکاپ ناقص: فایل پایگاه داده یافت نشد.', 'manifest' => $manifest];
        }

        if ($sqlPreview !== '' && (
            str_contains($sqlPreview, 'mysqldump unavailable')
            || str_contains($sqlPreview, 'JobAzmoon backup fallback')
        )) {
            return ['ok' => false, 'message' => 'بکاپ ناقص: dump پایگاه داده واقعی نیست.', 'manifest' => $manifest];
        }

        if (is_array($manifest) && ($manifest['status'] ?? '') === 'incomplete') {
            return ['ok' => false, 'message' => 'مانیفست وضعیت incomplete دارد.', 'manifest' => $manifest];
        }

        $size = filesize($zipPath) ?: 0;
        if ($size < 64) {
            return ['ok' => false, 'message' => 'حجم فایل بکاپ غیرعادی کوچک است.', 'manifest' => $manifest];
        }

        return [
            'ok' => true,
            'message' => 'بکاپ معتبر است.',
            'manifest' => is_array($manifest) ? $manifest : null,
        ];
    }

    protected function dumpSqliteSql(string $sqlFile): void
    {
        $pdo = DB::connection()->getPdo();
        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $out = "-- JobAzmoon SQLite SQL dump\n-- At: ".now()->toIso8601String()."\nPRAGMA foreign_keys=OFF;\nBEGIN;\n";

        foreach ($tables as $table) {
            $table = (string) $table;
            $create = $pdo->query(
                "SELECT sql FROM sqlite_master WHERE type='table' AND name=".$pdo->quote($table)
            )->fetchColumn();
            if (! is_string($create) || $create === '') {
                continue;
            }
            $out .= 'DROP TABLE IF EXISTS "'.$table."\";\n".$create.";\n";
            $rows = $pdo->query('SELECT * FROM "'.$table.'"')->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = array_map(fn ($c) => '"'.$c.'"', array_keys($row));
                $vals = [];
                foreach ($row as $value) {
                    $vals[] = $value === null ? 'NULL' : $pdo->quote((string) $value);
                }
                $out .= 'INSERT INTO "'.$table.'" ('.implode(',', $cols).') VALUES ('.implode(',', $vals).");\n";
            }
        }

        $out .= "COMMIT;\n";
        if (strlen($out) < 32) {
            throw new RuntimeException('dump پایگاه SQLite خالی است.');
        }
        file_put_contents($sqlFile, $out);
    }

    protected function dumpDatabase(string $sqlFile): void
    {
        if (config('database.default') === 'sqlite') {
            $sqlite = config('database.connections.sqlite.database');
            if (is_string($sqlite) && is_file($sqlite)) {
                $sqliteCopy = preg_replace('/\.sql$/', '.sqlite', $sqlFile) ?: ($sqlFile.'.sqlite');
                if (! @copy($sqlite, $sqliteCopy)) {
                    throw new RuntimeException('کپی پایگاه SQLite ناموفق بود.');
                }
                file_put_contents(
                    $sqlFile,
                    '-- SQLite binary copied as '.basename($sqliteCopy)."\n-- At: ".now()->toIso8601String()."\n"
                );

                return;
            }

            $this->dumpSqliteSql($sqlFile);

            return;
        }

        if (config('database.default') !== 'mysql' && config('database.default') !== 'mariadb') {
            throw new RuntimeException('درایور پایگاه داده برای بکاپ پشتیبانی نمی‌شود: '.config('database.default'));
        }

        $host = (string) config('database.connections.mysql.host');
        $port = (string) config('database.connections.mysql.port', 3306);
        $db = (string) config('database.connections.mysql.database');
        $user = (string) config('database.connections.mysql.username');
        $pass = (string) config('database.connections.mysql.password');

        $mysqldump = $this->findMysqldump();
        if (! $mysqldump) {
            throw new RuntimeException('mysqldump یافت نشد. بکاپ پایگاه داده انجام نشد.');
        }

        $args = [
            $mysqldump,
            '--host='.$host,
            '--port='.$port,
            '--user='.$user,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--result-file='.$sqlFile,
            $db,
        ];

        $result = Process::timeout(600)
            ->env($pass !== '' ? ['MYSQL_PWD' => $pass] : [])
            ->run($args);

        if (! $result->successful() || ! is_file($sqlFile) || filesize($sqlFile) < 32) {
            $stderr = trim($result->errorOutput());
            // Never log passwords; only truncate stderr.
            Log::error('mysqldump failed', [
                'exit' => $result->exitCode(),
                'stderr' => Str::limit($stderr, 500),
            ]);
            throw new RuntimeException('dump پایگاه داده ناموفق بود.'.($stderr !== '' ? ' جزئیات در لاگ سرور.' : ''));
        }
    }

    protected function findMysqldump(): ?string
    {
        $candidates = [
            'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe',
        ];

        foreach (glob('C:\\laragon\\bin\\mysql\\*\\bin\\mysqldump.exe') ?: [] as $path) {
            array_unshift($candidates, $path);
        }

        foreach ($candidates as $bin) {
            if (is_file($bin)) {
                return $bin;
            }
        }

        $which = Process::run(['mysqldump', '--version']);
        if ($which->successful()) {
            return 'mysqldump';
        }

        return null;
    }

    protected function addFolderToZip(ZipArchive $zip, string $folder, string $relative): void
    {
        $items = scandir($folder) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $folder.DIRECTORY_SEPARATOR.$item;
            $local = ltrim($relative.'/'.$item, '/');
            if (is_dir($full)) {
                $zip->addEmptyDir($local);
                $this->addFolderToZip($zip, $full, $local);
            } else {
                $zip->addFile($full, $local);
            }
        }
    }

    /**
     * @return list<array{path: string, size: int, size_human: string, date: string, verified: bool|null, status: string|null}>
     */
    public function listBackups(): array
    {
        $dir = $this->backupDir();
        $files = File::glob($dir.DIRECTORY_SEPARATOR.'backup-*.zip') ?: [];

        rsort($files);

        return array_map(function (string $path) {
            $verify = null;
            try {
                $verify = $this->verifyBackup($path);
            } catch (\Throwable) {
                $verify = ['ok' => false, 'message' => 'verify error', 'manifest' => null];
            }

            return [
                'path' => basename($path),
                // Do not expose absolute filesystem paths to the API client.
                'size' => filesize($path) ?: 0,
                'size_human' => $this->humanSize(filesize($path) ?: 0),
                'date' => date('c', filemtime($path) ?: time()),
                'verified' => $verify['ok'] ?? null,
                'status' => is_array($verify['manifest'] ?? null)
                    ? ($verify['manifest']['status'] ?? null)
                    : null,
            ];
        }, $files);
    }

    public function resolvePath(string $path): string
    {
        $base = basename($path);
        if (! Str::startsWith($base, 'backup-') || ! Str::endsWith($base, '.zip')) {
            throw new \InvalidArgumentException('مسیر بکاپ نامعتبر است.');
        }
        $full = $this->backupDir().DIRECTORY_SEPARATOR.$base;
        if (! is_file($full)) {
            throw new \InvalidArgumentException('فایل بکاپ یافت نشد.');
        }

        return $full;
    }

    public function deleteBackup(string $path): void
    {
        $full = $this->resolvePath($path);
        @unlink($full);
    }

    public function storeUploaded(UploadedFile $file): string
    {
        $stamp = now()->format('Y-m-d_His');
        $name = 'backup-'.$stamp.'.zip';
        $file->move($this->backupDir(), $name);

        return $this->backupDir().DIRECTORY_SEPARATOR.$name;
    }

    public function restoreFromUpload(UploadedFile $file): void
    {
        $path = $this->storeUploaded($file);
        $this->restore($path);
    }

    public function restore(string $zipPath): void
    {
        if (! is_file($zipPath) || ! Str::endsWith(strtolower($zipPath), '.zip')) {
            throw new \InvalidArgumentException('فایل بکاپ نامعتبر است.');
        }

        $verify = $this->verifyBackup($zipPath);
        if (! ($verify['ok'] ?? false)) {
            throw new RuntimeException('بازگردانی رد شد: '.($verify['message'] ?? 'بکاپ نامعتبر'));
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('امکان خواندن فایل بکاپ وجود ندارد.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, ':')) {
                $zip->close();
                throw new \InvalidArgumentException('ساختار فایل بکاپ نامعتبر است.');
            }
        }

        $work = storage_path('app/restore-tmp-'.now()->format('YmdHis'));
        File::ensureDirectoryExists($work);
        $zip->extractTo($work);
        $zip->close();

        try {
            // Safety snapshot of current state before overwrite.
            $this->createBackup();
            $this->restoreDatabase($work);

            if (is_dir($work.'/private')) {
                $this->restoreDir($work.'/private', storage_path('app/private'));
            } else {
                // Legacy ZIP layout
                $this->restoreDir($work.'/pdfs', storage_path('app/private/pdfs'));
                $this->restoreDir($work.'/resumes', storage_path('app/private/resumes'));
            }

            $this->restoreDir($work.'/public', storage_path('app/public'));
            ThemeBootstrap::forget();
            try {
                Artisan::call('cache:clear');
            } catch (\Throwable) {
                // ignore cache driver issues
            }
        } finally {
            File::deleteDirectory($work);
        }
    }

    protected function restoreDatabase(string $work): void
    {
        $sqliteFiles = File::glob($work.DIRECTORY_SEPARATOR.'*.sqlite') ?: [];
        if (config('database.default') === 'sqlite' && $sqliteFiles !== []) {
            $target = config('database.connections.sqlite.database');
            if (! is_string($target) || $target === '' || $target === ':memory:') {
                throw new RuntimeException('مسیر پایگاه SQLite برای بازگردانی مشخص نیست.');
            }
            DB::purge();
            File::ensureDirectoryExists(dirname($target));
            if (! @copy($sqliteFiles[0], $target)) {
                throw new RuntimeException('کپی پایگاه SQLite انجام نشد. احتمالاً فایل در حال استفاده است.');
            }
            DB::reconnect();

            return;
        }

        $sqlFile = $work.DIRECTORY_SEPARATOR.'database.sql';

        if (config('database.default') === 'sqlite') {
            $sql = is_file($sqlFile) ? (string) file_get_contents($sqlFile) : '';
            if ($sql === '' || str_contains($sql, 'mysqldump unavailable') || str_contains($sql, 'JobAzmoon backup fallback')) {
                throw new RuntimeException('فایل SQL بکاپ ناقص است و قابل بازگردانی نیست.');
            }
            if (str_contains($sql, 'SQLite binary copied')) {
                throw new RuntimeException('بازگردانی باینری SQLite روی این محیط پشتیبانی نمی‌شود.');
            }
            DB::unprepared($sql);

            return;
        }

        if (! is_file($sqlFile) || ! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('فایل SQL برای بازگردانی یافت نشد یا درایور MySQL فعال نیست.');
        }

        $sql = (string) file_get_contents($sqlFile);
        if ($sql === '' || str_contains($sql, 'mysqldump unavailable') || str_contains($sql, 'JobAzmoon backup fallback')) {
            throw new RuntimeException('فایل SQL بکاپ ناقص است و قابل بازگردانی نیست.');
        }
        if (str_contains($sql, 'SQLite binary copied')) {
            throw new RuntimeException('این بکاپ مخصوص SQLite است و با MySQL سازگار نیست.');
        }

        $mysql = $this->findMysqlClient();
        if (! $mysql) {
            throw new RuntimeException('برنامه mysql برای بازگردانی یافت نشد.');
        }

        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port', 3306);
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = (string) config('database.connections.mysql.password');

        $args = [
            $mysql,
            '--host='.$host,
            '--port='.(string) $port,
            '--user='.$user,
            $db,
        ];

        $result = Process::timeout(600)
            ->env($pass !== '' ? ['MYSQL_PWD' => $pass] : [])
            ->input($sql)
            ->run($args);

        if (! $result->successful()) {
            Log::error('mysql restore failed', [
                'exit' => $result->exitCode(),
                'stderr' => Str::limit(trim($result->errorOutput()), 500),
            ]);
            throw new RuntimeException('بازگردانی پایگاه داده ناموفق بود.');
        }
    }

    protected function restoreDir(string $from, string $to): void
    {
        if (! is_dir($from)) {
            return;
        }
        File::ensureDirectoryExists($to);
        File::copyDirectory($from, $to);
    }

    protected function findMysqlClient(): ?string
    {
        $candidates = [
            'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysql.exe',
        ];
        foreach (glob('C:\\laragon\\bin\\mysql\\*\\bin\\mysql.exe') ?: [] as $path) {
            array_unshift($candidates, $path);
        }
        foreach ($candidates as $bin) {
            if (is_file($bin)) {
                return $bin;
            }
        }

        $which = Process::run(['mysql', '--version']);
        if ($which->successful()) {
            return 'mysql';
        }

        return null;
    }

    public function cleanupOldBackups(?int $keep = null): void
    {
        $keep ??= max(1, (int) config('backup.cleanup.default_keep', 7));
        $list = File::glob($this->backupDir().DIRECTORY_SEPARATOR.'backup-*.zip') ?: [];
        rsort($list);
        foreach (array_slice($list, $keep) as $path) {
            @unlink($path);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function checksumTree(string $folder): array
    {
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($folder) + 1));
            if ($rel === self::MANIFEST_NAME) {
                continue;
            }
            $out[$rel] = hash_file('sha256', $file->getPathname()) ?: '';
        }
        ksort($out);

        return $out;
    }

    protected function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }

        return round($n, 2).' '.$units[$i];
    }
}
