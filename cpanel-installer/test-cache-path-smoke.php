<?php

declare(strict_types=1);
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Clean-environment smoke: missing framework dirs must not produce
 * "Please provide a valid cache path." after ensureFrameworkDirectories().
 *
 * Usage: php cpanel-installer/test-cache-path-smoke.php
 */
require_once __DIR__.'/lib/InstallEngine.php';

$root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jobazmoon-cache-smoke-'.bin2hex(random_bytes(4));
$public = $root.DIRECTORY_SEPARATOR.'public_html';
$job = $root.DIRECTORY_SEPARATOR.'job';
mkdir($public.DIRECTORY_SEPARATOR.'package', 0755, true);
mkdir($job.DIRECTORY_SEPARATOR.'storage', 0755, true); // incomplete extract

$engine = new InstallEngine(
    $public,
    $root,
    $job,
    $public.DIRECTORY_SEPARATOR.'package'.DIRECTORY_SEPARATOR.'jobazmoon-core.zip',
);

echo "Simulating incomplete extract (no framework/views)...\n";
$engine->ensureFrameworkDirectories();

$required = [
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];

foreach ($required as $rel) {
    $path = $engine->jobPath($rel);
    if (! is_dir($path) || ! is_writable($path)) {
        fwrite(STDERR, "FAIL: missing/unwritable {$rel}\n");
        exit(1);
    }
    echo "OK: {$rel}\n";
}

$autoload = dirname(__DIR__).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
    $fs = new Filesystem;
    try {
        new BladeCompiler($fs, '');
        fwrite(STDERR, "FAIL: empty path should throw\n");
        exit(1);
    } catch (InvalidArgumentException $e) {
        if (! str_contains($e->getMessage(), 'Please provide a valid cache path.')) {
            fwrite(STDERR, 'FAIL: unexpected message: '.$e->getMessage()."\n");
            exit(1);
        }
        echo "OK: Laravel error reproduced for empty path\n";
    }

    $compiled = $engine->jobPath('storage/framework/views');
    new BladeCompiler($fs, $compiled);
    echo "OK: BladeCompiler accepts ensured views path (no cache path error)\n";
}

// Cleanup
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $file) {
    $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
}
@rmdir($root);

echo "PASS: cache-path smoke OK\n";
exit(0);
