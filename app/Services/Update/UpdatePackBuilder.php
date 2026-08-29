<?php

declare(strict_types=1);

namespace App\Services\Update;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

final class UpdatePackBuilder
{
    public function __construct(
        private readonly UpdatePathGuard $paths,
    ) {}

    /**
     * Build an update pack ZIP from git diff against previous version tag/path list,
     * or from an explicit file list.
     *
     * @param  list<string>|null  $files  Relative paths; null = changed files since previous version via git
     * @param  list<string>  $deleted
     * @return string Absolute path to generated ZIP
     */
    public function build(
        string $targetVersion,
        ?string $previousVersion = null,
        ?array $files = null,
        array $deleted = [],
        string $description = '',
        string $releaseType = 'patch',
        bool $migrationRequired = false,
        bool $maintenanceMode = true,
        ?string $outputDir = null,
    ): string {
        if (! SemVer::isValid($targetVersion)) {
            throw new RuntimeException('نسخه هدف نامعتبر است.');
        }

        $previousVersion = $previousVersion ?: SemVer::current();
        $files = $files ?? $this->detectChangedFiles($previousVersion);
        $files = array_values(array_unique(array_map(fn ($f) => $this->paths->normalize($f), $files)));
        $deleted = array_values(array_unique(array_map(fn ($f) => $this->paths->normalize($f), $deleted)));

        foreach ($files as $rel) {
            $this->paths->assertWritableTarget($rel);
            $abs = base_path(str_replace('/', DIRECTORY_SEPARATOR, $rel));
            if (! is_file($abs)) {
                throw new RuntimeException("فایل برای بسته وجود ندارد: {$rel}");
            }
        }
        foreach ($deleted as $rel) {
            $this->paths->assertWritableTarget($rel);
        }

        $migrations = [];
        foreach ($files as $rel) {
            if (str_starts_with($rel, 'database/migrations/') && str_ends_with($rel, '.php')) {
                $migrations[] = substr($rel, strlen('database/migrations/'));
                $migrationRequired = true;
            }
        }

        $checksums = [];
        foreach ($files as $rel) {
            $abs = base_path(str_replace('/', DIRECTORY_SEPARATOR, $rel));
            $checksums[$rel] = hash_file('sha256', $abs) ?: '';
        }

        $manifest = [
            'application' => config('version.name', 'JobAzmoon'),
            'version' => $targetVersion,
            'previous_version' => $previousVersion,
            'minimum_version' => $previousVersion,
            'release_date' => now()->toDateString(),
            'release_type' => $releaseType,
            'description' => $description !== '' ? $description : "Update to {$targetVersion}",
            'php' => '8.2',
            'laravel' => (string) (explode('.', Application::VERSION)[0] ?? '12'),
            'backup_required' => true,
            'migration_required' => $migrationRequired,
            'maintenance_mode' => $maintenanceMode,
            'composer_required' => false,
            'files' => $files,
            'deleted_files' => $deleted,
            'checksums' => $checksums,
        ];

        $outputDir = $outputDir ?: storage_path('app/updates/dist');
        File::ensureDirectoryExists($outputDir);
        $zipName = 'jobazmoon-update-v'.$targetVersion.'.zip';
        $zipPath = $outputDir.DIRECTORY_SEPARATOR.$zipName;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('امکان ایجاد ZIP وجود ندارد.');
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');
        $zip->addFromString('checksums.json', json_encode($checksums, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}');

        foreach ($files as $rel) {
            $abs = base_path(str_replace('/', DIRECTORY_SEPARATOR, $rel));
            if (str_starts_with($rel, 'database/migrations/')) {
                $zip->addFile($abs, 'database/migrations/'.basename($rel));
                // Also keep under files/ so installer can copy into project tree
                $zip->addFile($abs, 'files/'.$rel);
            } else {
                $zip->addFile($abs, 'files/'.$rel);
            }
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * @return list<string>
     */
    private function detectChangedFiles(string $sinceVersion): array
    {
        $tag = 'v'.$sinceVersion;
        $cmd = 'git diff --name-only --diff-filter=ACMRT '.$tag.' HEAD 2>&1';
        exec($cmd, $output, $code);
        if ($code !== 0) {
            // Fallback: uncommitted + last commit
            exec('git diff --name-only HEAD~1 HEAD 2>&1', $output, $code);
            if ($code !== 0) {
                throw new RuntimeException(
                    'تشخیص فایل‌ها از Git ممکن نیست. لیست فایل را صریح بدهید یا تگ v'.$sinceVersion.' را بسازید.'
                );
            }
        }

        $files = [];
        foreach ($output as $line) {
            $rel = $this->paths->normalize(trim($line));
            if ($rel === '' || ! $this->paths->isSafeRelative($rel)) {
                continue;
            }
            if ($this->paths->isProtected($rel)) {
                continue;
            }
            if (! $this->paths->isAllowedRoot($rel)) {
                continue;
            }
            if (! is_file(base_path(str_replace('/', DIRECTORY_SEPARATOR, $rel)))) {
                continue;
            }
            $files[] = $rel;
        }

        return array_values(array_unique($files));
    }
}
