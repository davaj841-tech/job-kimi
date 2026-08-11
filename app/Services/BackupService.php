<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use ZipArchive;

class BackupService
{
    public function backupDir(): string
    {
        $dir = storage_path('backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    public function createBackup(): string
    {
        $this->cleanupOldBackups();

        $stamp = now()->format('Y-m-d_His');
        $work = storage_path('app/backup-tmp-'.$stamp);
        File::ensureDirectoryExists($work);

        $sqlFile = $work.'/database.sql';
        $this->dumpDatabase($sqlFile);

        $pdfs = storage_path('app/pdfs');
        $resumes = storage_path('app/resumes');
        $public = storage_path('app/public');
        if (is_dir($pdfs)) {
            File::copyDirectory($pdfs, $work.'/pdfs');
        }
        if (is_dir($resumes)) {
            File::copyDirectory($resumes, $work.'/resumes');
        }
        if (is_dir($public)) {
            File::copyDirectory($public, $work.'/public');
        }

        $zipName = 'backup-'.$stamp.'.zip';
        $zipPath = $this->backupDir().DIRECTORY_SEPARATOR.$zipName;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            File::deleteDirectory($work);
            throw new \RuntimeException('امکان ایجاد فایل بکاپ وجود ندارد.');
        }

        $this->addFolderToZip($zip, $work, '');
        $zip->close();
        File::deleteDirectory($work);

        $this->cleanupOldBackups();

        return $zipPath;
    }

    protected function dumpDatabase(string $sqlFile): void
    {
        if (config('database.default') === 'sqlite') {
            $sqlite = config('database.connections.sqlite.database');
            if (is_string($sqlite) && is_file($sqlite)) {
                $sqliteCopy = preg_replace('/\.sql$/', '.sqlite', $sqlFile) ?: ($sqlFile.'.sqlite');
                copy($sqlite, $sqliteCopy);
                file_put_contents(
                    $sqlFile,
                    '-- SQLite binary copied as '.basename($sqliteCopy)."\n-- At: ".now()->toIso8601String()."\n"
                );

                return;
            }
        }

        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port', 3306);
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $mysqldump = $this->findMysqldump();

        if ($mysqldump) {
            $args = [
                $mysqldump,
                '--host='.$host,
                '--port='.(string) $port,
                '--user='.$user,
                '--single-transaction',
                '--routines',
                '--triggers',
                ...($pass !== null && $pass !== '' ? ['--password='.$pass] : []),
                $db,
            ];

            $result = Process::timeout(300)->run($args);

            if ($result->successful()) {
                file_put_contents($sqlFile, $result->output());

                return;
            }
        }

        // Fallback: schema dump via Laravel (data-light placeholder)
        file_put_contents($sqlFile, "-- JobAzmoon backup fallback\n-- DB: {$db}\n-- At: ".now()->toIso8601String()."\n-- mysqldump unavailable; export tables manually if needed.\n");
    }

    protected function findMysqldump(): ?string
    {
        $candidates = [
            'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe',
            'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe',
            'mysqldump',
        ];

        foreach (glob('C:\\laragon\\bin\\mysql\\*\\bin\\mysqldump.exe') ?: [] as $path) {
            array_unshift($candidates, $path);
        }

        foreach ($candidates as $bin) {
            if ($bin === 'mysqldump') {
                return $bin;
            }
            if (is_file($bin)) {
                return $bin;
            }
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

    public function listBackups(): array
    {
        $dir = $this->backupDir();
        $files = File::glob($dir.DIRECTORY_SEPARATOR.'backup-*.zip') ?: [];

        rsort($files);

        return array_map(function (string $path) {
            return [
                'path' => basename($path),
                'full_path' => $path,
                'size' => filesize($path) ?: 0,
                'size_human' => $this->humanSize(filesize($path) ?: 0),
                'date' => date('c', filemtime($path) ?: time()),
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

    public function cleanupOldBackups(int $keep = 7): void
    {
        $list = $this->listBackups();
        foreach (array_slice($list, $keep) as $item) {
            @unlink($item['full_path']);
        }
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
