<?php

declare(strict_types=1);

/**
 * Build jobazmoon-core.zip for shared cPanel installer (no Composer/Node on server).
 *
 * Usage:
 *   php scripts/build-cpanel-package.php
 *   php scripts/build-cpanel-package.php --skip-deps
 *   php scripts/build-cpanel-package.php --output=dist/jobazmoon-core.zip
 *
 * Prerequisites when not using --skip-deps:
 *   composer install --no-dev --optimize-autoloader
 *   npm ci && npm run build
 */
$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$skipDeps = in_array('--skip-deps', $args, true);
$noRestore = in_array('--no-restore', $args, true);
$output = $root.DIRECTORY_SEPARATOR.'dist'.DIRECTORY_SEPARATOR.'jobazmoon-core.zip';

foreach ($args as $arg) {
    if (str_starts_with($arg, '--output=')) {
        $output = substr($arg, 9);
        if (! str_contains($output, DIRECTORY_SEPARATOR) && ! str_starts_with($output, '/') && ! preg_match('#^[A-Za-z]:#', $output)) {
            $output = $root.DIRECTORY_SEPARATOR.$output;
        }
    }
}

function out(string $msg): void
{
    fwrite(STDOUT, $msg.PHP_EOL);
    if (function_exists('fflush')) {
        fflush(STDOUT);
    }
}

function fail(string $msg, int $code = 1): never
{
    fwrite(STDERR, 'ERROR: '.$msg.PHP_EOL);
    exit($code);
}

/** @param list<string> $files */
function lintPhpFiles(array $files): void
{
    foreach ($files as $file) {
        if (! is_file($file)) {
            fail('PHP lint target missing: '.$file);
        }
        $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open(
            [PHP_BINARY, '-l', $file],
            $descriptor,
            $pipes,
            dirname($file)
        );
        if (! is_resource($proc)) {
            fail('Failed to start php -l for: '.$file);
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($proc);
        if ($status !== 0) {
            fail(trim($stderr !== '' ? $stderr : (string) $stdout));
        }
        out('PHP lint OK: '.$file);
    }
}

function run(string $cmd, string $cwd): void
{
    out('> '.$cmd);
    $descriptor = [0 => STDIN, 1 => STDOUT, 2 => STDERR];
    $proc = proc_open($cmd, $descriptor, $pipes, $cwd, null, ['bypass_shell' => false]);
    if (! is_resource($proc)) {
        fail('Failed to start: '.$cmd);
    }
    $status = proc_close($proc);
    if ($status !== 0) {
        fail('Command failed ('.$status.'): '.$cmd);
    }
}

lintPhpFiles([
    $root.DIRECTORY_SEPARATOR.'cpanel-installer'.DIRECTORY_SEPARATOR.'install.php',
    $root.DIRECTORY_SEPARATOR.'cpanel-installer'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'InstallEngine.php',
    $root.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'build-cpanel-package.php',
]);

if (! $skipDeps) {
    // Horizon declares ext-pcntl/posix; Windows (and many shared hosts) lack them.
    // Package still includes Horizon for VPS; shared installs use database queue + cron.
    $composerCmd = 'composer install --no-dev --optimize-autoloader --no-interaction'
        .' --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix';
    run($composerCmd, $root);
    run('npm ci', $root);
    run('npm run build', $root);
}

$required = [
    'artisan',
    'bootstrap'.DIRECTORY_SEPARATOR.'app.php',
    'composer.json',
    'composer.lock',
    'vendor'.DIRECTORY_SEPARATOR.'autoload.php',
    'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'manifest.json',
];

foreach ($required as $rel) {
    if (! is_file($root.DIRECTORY_SEPARATOR.$rel)) {
        fail('Missing required file before packaging: '.$rel);
    }
}

$includeRoots = [
    'app',
    'bootstrap',
    'config',
    'database',
    'lang',
    'public',
    'resources',
    'routes',
    'storage',
    'vendor',
];

$includeFiles = [
    'artisan',
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    '.env.example',
    '.env.production.example',
];

/** Path segments / basenames that must never enter the ZIP. */
$denyNameExact = [
    '.env',
    '.env.backup',
    '.env.production',
    '.env.local',
    '.env.testing',
    '.git',
    '.github',
    '.gitattributes',
    '.gitignore',
    '.githooks',
    '.editorconfig',
    '.vscode',
    '.idea',
    '.fleet',
    '.nova',
    '.zed',
    '.vagrant',
    '.scribe',
    '.phpunit.cache',
    '.phpunit.result.cache',
    'node_modules',
    'tests',
    'docs',
    'docker',
    'deploy',
    'coverage',
    'dist',
    'cpanel-installer',
    // Do NOT deny bare "install.php" here — routes/install.php is required by
    // bootstrap/app.php whenever storage/installed is missing (cPanel migrate boot).
    // Root cPanel installer lives under cpanel-installer/ (already denied).
    'phpunit.xml',
    'phpstan.neon',
    'phpstan-baseline.neon',
    'docker-compose.yml',
    'docker-compose.yaml',
    'Homestead.json',
    'Homestead.yaml',
    'auth.json',
    'hot',
];

$denyPathContains = [
    DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'pail'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR,
    // Entire file-cache tree (phpstan dumps, compiled views leftovers, etc.)
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'sessions'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR,
    // Never ship uploaded/private runtime artifacts
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'private'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'hot',
    DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR,
];

$denyBasenamePrefix = ['.env.'];
$denyBasenameExact = [
    'installed',
    '.DS_Store',
    'Thumbs.db',
    'npm-debug.log',
    'yarn-error.log',
];

$denyExtension = ['.zip', '.log', '.sql', '.bak', '.key', '.pem', '.p12'];

function shouldExclude(string $absolute, string $relative, array $denyNameExact, array $denyPathContains, array $denyBasenamePrefix, array $denyBasenameExact, array $denyExtension): bool
{
    $norm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    $parts = array_values(array_filter(explode(DIRECTORY_SEPARATOR, $norm), static fn ($p) => $p !== '' && $p !== '.'));
    $base = $parts === [] ? basename($absolute) : $parts[array_key_last($parts)];

    $envExampleAllow = ['.env.example', '.env.production.example'];

    foreach ($parts as $part) {
        if (in_array($part, $denyNameExact, true)) {
            return true;
        }
        foreach ($denyBasenamePrefix as $prefix) {
            if (str_starts_with($part, $prefix) && ! in_array($part, $envExampleAllow, true)) {
                return true;
            }
        }
    }

    if (in_array($base, $denyBasenameExact, true)) {
        return true;
    }

    foreach ($denyBasenamePrefix as $prefix) {
        if (str_starts_with($base, $prefix) && ! in_array($base, $envExampleAllow, true)) {
            return true;
        }
    }

    $hay = DIRECTORY_SEPARATOR.$norm.DIRECTORY_SEPARATOR;
    foreach ($denyPathContains as $needle) {
        if (str_contains($hay, $needle)) {
            // Keep .gitignore placeholders inside empty storage dirs.
            if (str_ends_with($base, '.gitignore') && str_contains($norm, 'storage'.DIRECTORY_SEPARATOR)) {
                continue;
            }
            if (str_ends_with($base, '.gitignore') && str_contains($norm, 'bootstrap'.DIRECTORY_SEPARATOR.'cache')) {
                continue;
            }

            return true;
        }
    }

    foreach ($denyExtension as $ext) {
        if (str_ends_with(strtolower($base), $ext)) {
            return true;
        }
    }

    // Never ship compiled bootstrap cache files.
    if (str_contains($norm, 'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR) && $base !== '.gitignore') {
        return true;
    }

    return false;
}

$outDir = dirname($output);
if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
    fail('Cannot create output directory: '.$outDir);
}

if (is_file($output)) {
    $newestSource = 0;
    $watch = [
        'app/Support/EnamadBadge.php',
        'config/enamad.php',
        'resources/js/components/TrustBadges.vue',
        'public/build/manifest.json',
    ];
    foreach ($watch as $rel) {
        $abs = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($abs)) {
            $newestSource = max($newestSource, (int) filemtime($abs));
        }
    }
    $zipMtime = (int) filemtime($output);
    if ($newestSource > $zipMtime) {
        out('Note: existing '.$output.' is older than current source — rebuilding fresh package.');
    }
    @unlink($output);
}

$zip = new ZipArchive;
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fail('Cannot create ZIP: '.$output);
}

$added = 0;

$addFile = static function (string $absolute, string $relative) use ($zip, &$added, $denyNameExact, $denyPathContains, $denyBasenamePrefix, $denyBasenameExact, $denyExtension): void {
    if (shouldExclude($absolute, $relative, $denyNameExact, $denyPathContains, $denyBasenamePrefix, $denyBasenameExact, $denyExtension)) {
        return;
    }
    $zipPath = str_replace('\\', '/', $relative);
    if ($zip->addFile($absolute, $zipPath)) {
        // Store vendor binaries uncompressed — much faster close() on Windows; size still acceptable.
        $normRel = str_replace('\\', '/', $relative);
        if (str_starts_with($normRel, 'vendor/') && method_exists($zip, 'setCompressionName')) {
            $zip->setCompressionName($zipPath, ZipArchive::CM_STORE);
        }
        $added++;
        if ($added % 500 === 0) {
            out('  … '.$added.' files queued');
        }
    }
};

foreach ($includeFiles as $file) {
    $abs = $root.DIRECTORY_SEPARATOR.$file;
    if (is_file($abs)) {
        $addFile($abs, $file);
    }
}

foreach ($includeRoots as $dir) {
    $absDir = $root.DIRECTORY_SEPARATOR.$dir;
    if (! is_dir($absDir)) {
        if ($dir === 'lang') {
            continue;
        }
        fail('Missing required directory: '.$dir);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    /** @var SplFileInfo $item */
    foreach ($iterator as $item) {
        $abs = $item->getPathname();
        $rel = substr($abs, strlen($root) + 1);
        if ($item->isDir()) {
            if (shouldExclude($abs, $rel, $denyNameExact, $denyPathContains, $denyBasenamePrefix, $denyBasenameExact, $denyExtension)) {
                continue;
            }
            $zip->addEmptyDir(str_replace('\\', '/', $rel));

            continue;
        }
        if ($item->isFile()) {
            $addFile($abs, $rel);
        }
    }
}

// Force Laravel runtime directory placeholders (empty dirs are often omitted from ZIP).
$storagePlaceholders = [
    'storage/framework/cache/.gitignore' => "*\n!.gitignore\n",
    'storage/framework/cache/data/.gitignore' => "*\n!.gitignore\n",
    'storage/framework/sessions/.gitignore' => "*\n!.gitignore\n",
    'storage/framework/views/.gitignore' => "*\n!.gitignore\n",
    'storage/logs/.gitignore' => "*\n!.gitignore\n",
    'storage/app/.gitignore' => "*\n!public/\n!.gitignore\n",
    'storage/app/public/.gitignore' => "*\n!.gitignore\n",
    'storage/app/private/.gitignore' => "*\n!.gitignore\n",
    'bootstrap/cache/.gitignore' => "*\n!.gitignore\n",
];
foreach ($storagePlaceholders as $zipPath => $contents) {
    $local = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $zipPath);
    if ($zip->locateName($zipPath) !== false) {
        continue;
    }
    if (is_file($local)) {
        if ($zip->addFile($local, $zipPath)) {
            $added++;
        } else {
            $zip->addFromString($zipPath, (string) file_get_contents($local));
            $added++;
        }
    } else {
        $zip->addFromString($zipPath, $contents);
        $added++;
    }
}

out('Closing ZIP (finalizing '.$added.' entries)…');

// Stamp package so hosts/admins can verify they got a fresh build (not a stale dist/ artifact).
$gitHead = trim((string) shell_exec('git rev-parse --short HEAD 2>NUL') ?: shell_exec('git rev-parse --short HEAD 2>/dev/null') ?: '');
$buildInfo = [
    'product' => 'JobAzmoon',
    'built_at' => gmdate('c'),
    'built_at_local' => date('Y-m-d H:i:s T'),
    'git_commit' => $gitHead !== '' ? $gitHead : null,
    'php' => PHP_VERSION,
    'files_added' => $added,
];
$buildInfoJson = json_encode($buildInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (! is_string($buildInfoJson)) {
    fail('Failed to encode build-info.json');
}
$zip->addFromString('build-info.json', $buildInfoJson);
$added++;

if (! $zip->close()) {
    fail('ZipArchive::close failed while writing '.$output);
}

if ($added < 100) {
    fail('Package looks too small ('.$added.' files). Aborting.');
}

if (! is_file($output)) {
    fail('ZIP missing after close: '.$output);
}

$size = filesize($output) ?: 0;
out('');
out('Package built: '.$output);
out('Files added: '.$added);
out('Size: '.round($size / (1024 * 1024), 2).' MB');

// Smoke-inspect critical paths inside the ZIP.
$check = new ZipArchive;
if ($check->open($output) !== true) {
    fail('Cannot reopen ZIP for inspection.');
}
$mustHave = [
    'vendor/autoload.php',
    'public/build/manifest.json',
    'artisan',
    'bootstrap/app.php',
    'composer.lock',
    'routes/web.php',
    'routes/api.php',
    'routes/console.php',
    // Required by bootstrap/app.php when storage/installed is absent (every cPanel artisan boot).
    'routes/install.php',
    'build-info.json',
    'storage/framework/cache/.gitignore',
    'storage/framework/sessions/.gitignore',
    'storage/framework/views/.gitignore',
    'storage/logs/.gitignore',
    'bootstrap/cache/.gitignore',
];
foreach ($mustHave as $path) {
    if ($check->locateName($path) === false) {
        $check->close();
        fail('ZIP missing required entry: '.$path);
    }
}
// Forbid root-level install.php only — routes/install.php must remain in the package.
$forbiddenExact = ['install.php', '.env'];
$forbiddenPrefixes = ['tests/', 'node_modules/', '.git/', 'cpanel-installer/', 'storage/framework/cache/phpstan'];
for ($i = 0; $i < $check->numFiles; $i++) {
    $name = $check->getNameIndex($i);
    if ($name === false) {
        continue;
    }
    if (in_array($name, $forbiddenExact, true)) {
        $check->close();
        fail('ZIP contains forbidden path: '.$name);
    }
    foreach ($forbiddenPrefixes as $bad) {
        if ($name === $bad || str_starts_with($name, $bad)) {
            $check->close();
            fail('ZIP contains forbidden path: '.$name);
        }
    }
}
$check->close();
out('ZIP inspection: OK');

// Assemble drop-in JobAzmoon-Installer/ for public_html upload (WordPress-like).
$bundleDir = $root.DIRECTORY_SEPARATOR.'dist'.DIRECTORY_SEPARATOR.'JobAzmoon-Installer';
$bundlePkg = $bundleDir.DIRECTORY_SEPARATOR.'package';
$bundleZip = $root.DIRECTORY_SEPARATOR.'dist'.DIRECTORY_SEPARATOR.'JobAzmoon-Installer.zip';

out('');
out('Assembling JobAzmoon-Installer bundle...');

if (is_dir($bundleDir)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($bundleDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($bundleDir);
}

if (! mkdir($bundlePkg, 0755, true) && ! is_dir($bundlePkg)) {
    fail('Cannot create bundle directory: '.$bundlePkg);
}

$installerSrc = $root.DIRECTORY_SEPARATOR.'cpanel-installer';
if (! copy($installerSrc.DIRECTORY_SEPARATOR.'install.php', $bundleDir.DIRECTORY_SEPARATOR.'install.php')) {
    fail('Cannot copy install.php into bundle.');
}
if (! is_dir($bundleDir.DIRECTORY_SEPARATOR.'lib') && ! mkdir($bundleDir.DIRECTORY_SEPARATOR.'lib', 0755, true)) {
    fail('Cannot create bundle lib/.');
}
if (! copy($installerSrc.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'InstallEngine.php', $bundleDir.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'InstallEngine.php')) {
    fail('Cannot copy InstallEngine.php into bundle.');
}
if (! copy($output, $bundlePkg.DIRECTORY_SEPARATOR.'jobazmoon-core.zip')) {
    fail('Cannot copy jobazmoon-core.zip into bundle package/.');
}

$installMdSrc = $root.DIRECTORY_SEPARATOR.'INSTALL.md';
if (is_file($installMdSrc)) {
    copy($installMdSrc, $bundleDir.DIRECTORY_SEPARATOR.'INSTALL.md');
}

$readme = <<<'TXT'
JobAzmoon — نصب روی cPanel (بدون Composer / بدون npm / بدون SSH)

1) در cPanel دیتابیس MySQL و کاربر بسازید و کاربر را به دیتابیس وصل کنید.
2) PHP را روی 8.2 یا 8.3 بگذارید و افزونه‌های لازم را فعال کنید
   (pdo, pdo_mysql, openssl, mbstring, tokenizer, xml, ctype, json, fileinfo, gd, zip, dom).
3) محتویات این پوشه را داخل public_html آپلود کنید تا این ساختار ساخته شود:
     public_html/install.php
     public_html/lib/InstallEngine.php
     public_html/package/jobazmoon-core.zip
4) مرورگر: https://YOUR-DOMAIN/install.php
5) Wizard را کامل کنید. هسته Laravel در ~/job و وب در public_html قرار می‌گیرد.
6) بعد از نصب موفق، install.php باید حذف شود. اگر خودکار حذف نشد، از File Manager پاک کنید.
7) Cronهای Scheduler و Queue را از صفحه پایان نصب در cPanel اضافه کنید.

جزئیات کامل: INSTALL.md
TXT;
file_put_contents($bundleDir.DIRECTORY_SEPARATOR.'README-INSTALL.txt', $readme);

if (is_file($bundleZip)) {
    @unlink($bundleZip);
}
$bundleArchive = new ZipArchive;
if ($bundleArchive->open($bundleZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fail('Cannot create JobAzmoon-Installer.zip');
}
$bundleIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($bundleDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
/** @var SplFileInfo $item */
foreach ($bundleIterator as $item) {
    $abs = $item->getPathname();
    // Flat ZIP root: extract directly into public_html (WordPress-style).
    // Nested JobAzmoon-Installer/ breaks install.php path resolution (~/job vs public_html/job).
    $rel = substr($abs, strlen($bundleDir) + 1);
    $rel = str_replace('\\', '/', $rel);
    if ($rel === '' || $rel === false) {
        continue;
    }
    if ($item->isDir()) {
        $bundleArchive->addEmptyDir($rel);
    } elseif ($item->isFile()) {
        $bundleArchive->addFile($abs, $rel);
    }
}
$bundleArchive->close();

$bundleVerify = new ZipArchive;
if ($bundleVerify->open($bundleZip) !== true) {
    fail('Cannot verify JobAzmoon-Installer.zip');
}
$flatMust = ['install.php', 'lib/InstallEngine.php', 'package/jobazmoon-core.zip'];
foreach ($flatMust as $path) {
    if ($bundleVerify->locateName($path) === false) {
        $bundleVerify->close();
        fail('Installer ZIP missing flat path: '.$path);
    }
}
if ($bundleVerify->locateName('JobAzmoon-Installer/install.php') !== false) {
    $bundleVerify->close();
    fail('Installer ZIP must not nest files under JobAzmoon-Installer/ — extract goes to public_html root.');
}
$bundleVerify->close();

out('Installer folder: '.$bundleDir);
out('Installer ZIP: '.$bundleZip.' ('.round(((int) filesize($bundleZip)) / (1024 * 1024), 2).' MB)');

out('');
out('Running installer regression tests...');
run('php '.escapeshellarg($root.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'phpunit').' --filter=Install', $root);

out('');
out('Running installer smoke tests...');
run('php '.escapeshellarg($root.DIRECTORY_SEPARATOR.'cpanel-installer'.DIRECTORY_SEPARATOR.'test-routes-install-smoke.php'), $root);
run('php '.escapeshellarg($root.DIRECTORY_SEPARATOR.'cpanel-installer'.DIRECTORY_SEPARATOR.'test-cache-path-smoke.php'), $root);

if (is_file($root.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'verify-package-contents.php')) {
    run('php '.escapeshellarg($root.DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'verify-package-contents.php').' '.escapeshellarg($output), $root);
}

if (! $skipDeps && ! $noRestore) {
    out('');
    out('Restoring development Composer dependencies...');
    run(
        'composer install --no-interaction --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix',
        $root
    );
}

out('Done.');
exit(0);
