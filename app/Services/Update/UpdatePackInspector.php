<?php

declare(strict_types=1);

namespace App\Services\Update;

use RuntimeException;
use ZipArchive;

final class UpdatePackInspector
{
    public function __construct(
        private readonly UpdatePathGuard $paths,
        private readonly ManifestValidator $manifests,
    ) {}

    /**
     * Extract and validate an update ZIP into a work directory.
     *
     * @return array{
     *   work_dir: string,
     *   manifest: array<string, mixed>,
     *   checksums: array<string, string>,
     *   preflight: array<string, bool|string>,
     *   files: list<string>,
     *   deleted_files: list<string>,
     *   migrations: list<string>
     * }
     */
    public function inspect(string $zipPath, string $currentVersion): array
    {
        $preflight = [
            'package' => false,
            'manifest' => false,
            'version' => false,
            'php' => false,
            'laravel' => false,
            'permissions' => true,
            'files' => false,
            'checksum' => false,
            'backup' => true,
            'database' => true,
        ];

        if (! is_file($zipPath)) {
            throw new RuntimeException('فایل بسته به‌روزرسانی یافت نشد.');
        }

        $maxKb = (int) config('update.max_upload_kb', 102400);
        if (filesize($zipPath) > $maxKb * 1024) {
            throw new RuntimeException('حجم بسته به‌روزرسانی بیش از حد مجاز است.');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('فایل ZIP آسیب‌دیده یا قابل باز شدن نیست.');
        }

        $maxEntries = (int) config('update.max_zip_entries', 5000);
        if ($zip->numFiles > $maxEntries) {
            $zip->close();
            throw new RuntimeException('تعداد فایل‌های داخل ZIP بیش از حد مجاز است (ZIP Bomb؟).');
        }

        $maxExtracted = (int) config('update.max_extracted_bytes', 400 * 1024 * 1024);
        $uncompressed = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (! is_array($stat)) {
                continue;
            }
            $uncompressed += (int) ($stat['size'] ?? 0);
            if ($uncompressed > $maxExtracted) {
                $zip->close();
                throw new RuntimeException('حجم استخراج‌شده ZIP بیش از حد مجاز است.');
            }
            $name = $this->paths->normalize((string) ($stat['name'] ?? ''));
            if (! $this->paths->isSafeRelative($name) && ! str_ends_with($name, '/')) {
                $zip->close();
                throw new RuntimeException("مسیر ناامن داخل ZIP (ZIP Slip): {$name}");
            }
        }

        $preflight['package'] = true;

        $work = storage_path('app/updates/work-'.bin2hex(random_bytes(8)));
        if (! mkdir($work, 0755, true) && ! is_dir($work)) {
            $zip->close();
            throw new RuntimeException('امکان ایجاد پوشه کاری وجود ندارد.');
        }

        if (! $zip->extractTo($work)) {
            $zip->close();
            throw new RuntimeException('استخراج ZIP ناموفق بود.');
        }
        $zip->close();

        // Post-extract ZIP slip + symlink check
        $this->assertExtractedInside($work);
        $this->assertNoSymlinks($work);

        $manifestPath = $work.DIRECTORY_SEPARATOR.'manifest.json';
        if (! is_file($manifestPath)) {
            throw new RuntimeException('manifest.json در بسته وجود ندارد.');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            throw new RuntimeException('manifest.json نامعتبر است.');
        }

        $validation = $this->manifests->validate($manifest, $currentVersion);
        $preflight['manifest'] = $validation['checks']['manifest'] ?? false;
        $preflight['version'] = $validation['checks']['version'] ?? false;
        $preflight['php'] = $validation['checks']['php'] ?? false;
        $preflight['laravel'] = $validation['checks']['laravel'] ?? false;
        if (! $validation['ok']) {
            throw new RuntimeException(implode(' ', $validation['errors']));
        }

        $checksumsPath = $work.DIRECTORY_SEPARATOR.'checksums.json';
        $checksums = [];
        if (is_file($checksumsPath)) {
            $decoded = json_decode((string) file_get_contents($checksumsPath), true);
            if (! is_array($decoded)) {
                throw new RuntimeException('checksums.json نامعتبر است.');
            }
            /** @var array<string, string> $checksums */
            $checksums = $decoded;
        } elseif (isset($manifest['checksums']) && is_array($manifest['checksums'])) {
            /** @var array<string, string> $checksums */
            $checksums = $manifest['checksums'];
        } else {
            throw new RuntimeException('checksums.json یا manifest.checksums الزامی است.');
        }

        $filesRoot = $work.DIRECTORY_SEPARATOR.'files';
        $fileList = [];
        if (is_dir($filesRoot)) {
            $fileList = $this->listRelativeFiles($filesRoot);
        }

        /** @var list<string> $declaredFiles */
        $declaredFiles = array_values(array_map(
            fn ($f) => $this->paths->normalize((string) $f),
            is_array($manifest['files'] ?? null) ? $manifest['files'] : []
        ));

        /** @var list<string> $deleted */
        $deleted = array_values(array_map(
            fn ($f) => $this->paths->normalize((string) $f),
            is_array($manifest['deleted_files'] ?? null) ? $manifest['deleted_files'] : []
        ));

        foreach ($declaredFiles as $rel) {
            $this->paths->assertWritableTarget($rel);
            $disk = $filesRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (! is_file($disk)) {
                throw new RuntimeException("فایل اعلام‌شده در بسته موجود نیست: {$rel}");
            }
        }

        foreach ($fileList as $rel) {
            $this->paths->assertWritableTarget($rel);
            if ($declaredFiles !== [] && ! in_array($rel, $declaredFiles, true)) {
                throw new RuntimeException("فایل اعلام‌نشده در بسته: {$rel}");
            }
        }

        foreach ($deleted as $rel) {
            $this->paths->assertWritableTarget($rel);
        }

        // Verify checksums for all files under files/
        foreach ($fileList as $rel) {
            $disk = $filesRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $expected = $checksums[$rel] ?? $checksums['files/'.$rel] ?? null;
            if ($expected === null) {
                throw new RuntimeException("checksum برای فایل وجود ندارد: {$rel}");
            }
            $actual = hash_file('sha256', $disk);
            if (! hash_equals(strtolower((string) $expected), strtolower((string) $actual))) {
                throw new RuntimeException("checksum نادرست: {$rel}");
            }
        }

        $preflight['files'] = true;
        $preflight['checksum'] = true;

        $migrations = [];
        $migDir = $work.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';
        if (is_dir($migDir)) {
            foreach ($this->listRelativeFiles($migDir) as $rel) {
                if (str_ends_with($rel, '.php')) {
                    $migrations[] = $rel;
                }
            }
        }

        if (! empty($manifest['migration_required']) && $migrations === [] && empty($manifest['files'])) {
            // migration_required but no migration files — warn via exception only if strictly required files missing
        }

        return [
            'work_dir' => $work,
            'manifest' => $manifest,
            'checksums' => $checksums,
            'preflight' => $preflight,
            'files' => $declaredFiles !== [] ? $declaredFiles : $fileList,
            'deleted_files' => $deleted,
            'migrations' => $migrations,
        ];
    }

    private function assertExtractedInside(string $work): void
    {
        $realWork = realpath($work);
        if ($realWork === false) {
            throw new RuntimeException('پوشه کاری نامعتبر است.');
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($work, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $real = realpath($file->getPathname());
            if ($real === false || ! str_starts_with($real, $realWork)) {
                throw new RuntimeException('تشخیص ZIP Slip پس از استخراج.');
            }
        }
    }

    private function assertNoSymlinks(string $work): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($work, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isLink()) {
                throw new RuntimeException('بسته حاوی symlink است و رد شد: '.$file->getFilename());
            }
        }
    }

    /**
     * @return list<string>
     */
    private function listRelativeFiles(string $root): array
    {
        $rootReal = realpath($root);
        if ($rootReal === false) {
            return [];
        }
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootReal, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }
            $full = $file->getPathname();
            $rel = substr($full, strlen($rootReal) + 1);
            $out[] = $this->paths->normalize($rel);
        }
        sort($out);

        return $out;
    }
}
