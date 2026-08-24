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
}

function fail(string $msg, int $code = 1): never
{
    fwrite(STDERR, 'ERROR: '.$msg.PHP_EOL);
    exit($code);
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
    'install.php',
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
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'sessions'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR,
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

    foreach ($parts as $part) {
        if (in_array($part, $denyNameExact, true)) {
            return true;
        }
        foreach ($denyBasenamePrefix as $prefix) {
            if (str_starts_with($part, $prefix)) {
                return true;
            }
        }
    }

    if (in_array($base, $denyBasenameExact, true)) {
        return true;
    }

    foreach ($denyBasenamePrefix as $prefix) {
        if (str_starts_with($base, $prefix)) {
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
        $added++;
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

$zip->close();

if ($added < 100) {
    fail('Package looks too small ('.$added.' files). Aborting.');
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
];
foreach ($mustHave as $path) {
    if ($check->locateName($path) === false) {
        $check->close();
        fail('ZIP missing required entry: '.$path);
    }
}
$forbiddenSamples = ['.env', 'tests/', 'node_modules/', '.git/', 'install.php', 'cpanel-installer/'];
for ($i = 0; $i < $check->numFiles; $i++) {
    $name = $check->getNameIndex($i);
    if ($name === false) {
        continue;
    }
    foreach ($forbiddenSamples as $bad) {
        if ($name === $bad || str_starts_with($name, $bad) || str_contains('/'.$name, '/'.$bad)) {
            if ($bad === '.env' && ($name === '.env.example' || $name === '.env.production.example')) {
                continue;
            }
            $check->close();
            fail('ZIP contains forbidden path: '.$name);
        }
    }
}
$check->close();
out('ZIP inspection: OK');

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
