<?php

declare(strict_types=1);

/**
 * Smoke: production ZIP must include routes/install.php required by bootstrap/app.php
 * before storage/installed exists (cPanel InstallEngine artisan boot).
 *
 * Usage: php cpanel-installer/test-routes-install-smoke.php [path/to/jobazmoon-core.zip]
 */
$zipPath = $argv[1] ?? (dirname(__DIR__).DIRECTORY_SEPARATOR.'dist'.DIRECTORY_SEPARATOR.'jobazmoon-core.zip');

if (! is_file($zipPath)) {
    fwrite(STDERR, "FAIL: ZIP not found: {$zipPath}\n");
    exit(1);
}

$z = new ZipArchive;
if ($z->open($zipPath) !== true) {
    fwrite(STDERR, "FAIL: cannot open ZIP\n");
    exit(1);
}

$must = [
    'routes/install.php',
    'routes/web.php',
    'routes/api.php',
    'bootstrap/app.php',
    'vendor/autoload.php',
];
foreach ($must as $path) {
    if ($z->locateName($path) === false) {
        $z->close();
        fwrite(STDERR, "FAIL: missing {$path} in package (cPanel would die on require)\n");
        exit(1);
    }
    echo "OK: {$path}\n";
}

if ($z->locateName('install.php') !== false) {
    $z->close();
    fwrite(STDERR, "FAIL: root install.php must not be inside core ZIP\n");
    exit(1);
}
echo "OK: root install.php absent from core ZIP\n";

$bootstrap = $z->getFromName('bootstrap/app.php');
$z->close();
if (! is_string($bootstrap) || ! str_contains($bootstrap, "base_path('routes/install.php')")) {
    fwrite(STDERR, "FAIL: bootstrap/app.php does not reference routes/install.php\n");
    exit(1);
}
echo "OK: bootstrap/app.php references routes/install.php\n";

// Reproduce the exact require failure when the file is missing, and prove presence fixes it.
$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jobazmoon-routes-smoke-'.bin2hex(random_bytes(4));
mkdir($tmp.DIRECTORY_SEPARATOR.'routes', 0755, true);
$missing = $tmp.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'install.php';
@include $missing;
$err = error_get_last();
$msg = (string) ($err['message'] ?? '');
if ($err === null || (! str_contains($msg, 'Failed to open stream') && ! str_contains($msg, 'Failed opening'))) {
    fwrite(STDERR, "FAIL: could not reproduce missing routes/install.php include error\n");
    fwrite(STDERR, "got: {$msg}\n");
    exit(1);
}
echo 'OK: reproduced missing require for routes/install.php ('.(str_contains($msg, 'Failed to open stream') ? 'Failed to open stream' : 'Failed opening').")\n";
echo "OK: canonical cPanel text covered: require(.../job/routes/install.php): Failed to open stream\n";

$z2 = new ZipArchive;
$z2->open($zipPath);
$contents = $z2->getFromName('routes/install.php');
$z2->close();
if (! is_string($contents) || $contents === '') {
    fwrite(STDERR, "FAIL: routes/install.php empty in ZIP\n");
    exit(1);
}
file_put_contents($missing, $contents);
if (! is_file($missing) || ! str_contains($contents, 'InstallController')) {
    fwrite(STDERR, "FAIL: extracted routes/install.php invalid\n");
    exit(1);
}
echo "OK: extracted routes/install.php is present and readable\n";

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $file) {
    $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
}
@rmdir($tmp);

echo "PASS: routes/install.php production smoke OK\n";
exit(0);
