<?php

declare(strict_types=1);

/**
 * Build a JobAzmoon update pack with explicit file list (post-install fixes).
 *
 * Usage: php scripts/build-update-pack.php 1.0.1 1.0.0
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Update\UpdatePackBuilder;
use Illuminate\Support\Facades\File;

$target = $argv[1] ?? '1.0.1';
$from = $argv[2] ?? '1.0.0';
$description = $argv[3] ?? 'بهینه‌سازی موبایل و تم شب/روز، رفع تجمیع خودکار آگهی‌ها، بهبود ورود ادمین';

$roots = [
    'app/Console/Commands/AggregateJobsDispatchCommand.php',
    'app/Console/Commands/BootstrapAggregationCommand.php',
    'app/Http/Controllers/Api/Admin/AggregationScheduleAdminController.php',
    'app/Http/Controllers/Api/Admin/JobSourceAdminController.php',
    'app/Http/Controllers/Api/AdminAuthController.php',
    'app/Services/BackupService.php',
    'app/Services/Update/UpdateHealthChecker.php',
    'app/Services/Update/UpdateManager.php',
    'app/Services/Update/UpdatePackBuilder.php',
    'config/aggregation.php',
    'config/version.php',
    'routes/api/admin.php',
    'routes/console.php',
    'resources/css/app.css',
    'resources/views/admin.blade.php',
    'resources/views/spa.blade.php',
    'resources/js/app.ts',
    'resources/js/admin/main.ts',
    'resources/js/admin/stores/aggregationSchedule.ts',
    'resources/js/admin/stores/jobSources.ts',
    'resources/js/admin/views/JobSourcesView.vue',
    'resources/js/admin/views/LoginView.vue',
    'resources/js/composables/useDarkMode.ts',
    'resources/js/composables/useMobile.ts',
    'resources/js/stores/themeStore.ts',
    'resources/js/components/AppHeader.vue',
    'resources/js/components/BottomNav.vue',
    'resources/js/components/MobileFooter.vue',
    'resources/js/components/ThemeToggle.vue',
    'resources/js/components/dashboard/UserDashboard.vue',
    'resources/js/components/home/HomeHero.vue',
    'resources/js/components/layout/MainLayout.vue',
    'resources/js/components/layout/PageShell.vue',
    'resources/js/components/layout/BottomNav.vue',
    'resources/js/components/layout/MobileHeader.vue',
    'resources/js/components/resume/PreviewModal.vue',
    'resources/js/components/ui/PersianDatePicker.vue',
    'resources/js/layouts/UserLayout.vue',
    'resources/js/views/exams/ExamDetailView.vue',
    'resources/js/views/exams/ExamListView.vue',
    'resources/js/views/exams/ExamTakeView.vue',
    'resources/js/views/jobs/JobListView.vue',
    'resources/js/views/pdf/PdfListView.vue',
    'resources/js/views/resume/ResumeEditorView.vue',
    'resources/js/views/wallet/WalletView.vue',
    'vite.config.js',
];

$buildDir = base_path('public/build');
if (is_dir($buildDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($buildDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $rel = 'public/build/'.str_replace('\\', '/', substr($file->getPathname(), strlen($buildDir) + 1));
            $roots[] = $rel;
        }
    }
}

$files = [];
$missing = [];
foreach (array_unique($roots) as $rel) {
    $abs = base_path(str_replace('/', DIRECTORY_SEPARATOR, $rel));
    if (is_file($abs)) {
        $files[] = $rel;
    } else {
        $missing[] = $rel;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Missing files (run npm run build first):\n".implode("\n", $missing)."\n");
    exit(1);
}

// Ensure target version in config before packing
$versionFile = base_path('config/version.php');
$versionPhp = file_get_contents($versionFile);
if (is_string($versionPhp)) {
    $versionPhp = preg_replace(
        "/'current'\\s*=>\\s*'[^']*'/",
        "'current' => '{$target}'",
        $versionPhp,
        1
    );
    file_put_contents($versionFile, $versionPhp);
}

/** @var UpdatePackBuilder $builder */
$builder = app(UpdatePackBuilder::class);

$zipPath = $builder->build(
    targetVersion: $target,
    previousVersion: $from,
    files: $files,
    deleted: [],
    description: $description,
    releaseType: 'patch',
    maintenanceMode: true,
);

$distRoot = base_path('dist');
File::ensureDirectoryExists($distRoot);
$copyTo = $distRoot.DIRECTORY_SEPARATOR.basename($zipPath);
File::copy($zipPath, $copyTo);

echo "Built: {$zipPath}\n";
echo "Copy:  {$copyTo}\n";
echo 'Files: '.count($files)."\n";
