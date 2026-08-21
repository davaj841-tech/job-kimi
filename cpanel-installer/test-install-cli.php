<?php

declare(strict_types=1);

/**
 * Simulated cPanel install test — run from project root:
 *   php cpanel-installer/test-install-cli.php
 *
 * Requires MySQL credentials in .env (DB_*). Creates an isolated temp public_html/job layout.
 */

$projectRoot = dirname(__DIR__);
require_once __DIR__.'/lib/InstallEngine.php';

@set_time_limit(0);

function loadEnv(string $path): array
{
    $vars = [];
    if (! is_file($path)) {
        return $vars;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $vars[trim($k)] = trim($v, " \t\"'");
    }

    return $vars;
}

function rrmdir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($dir);
}

function copyDir(string $src, string $dst): void
{
    if (! is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel = substr($item->getPathname(), strlen($src) + 1);
        $target = $dst.DIRECTORY_SEPARATOR.$rel;
        if ($item->isDir()) {
            if (! is_dir($target)) {
                mkdir($target, 0755, true);
            }
        } else {
            $parent = dirname($target);
            if (! is_dir($parent)) {
                mkdir($parent, 0755, true);
            }
            copy($item->getPathname(), $target);
        }
    }
}

function createZipFromDir(string $source, string $zipPath): void
{
    $zip = new ZipArchive;
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create zip');
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $rel = substr($file->getPathname(), strlen($source) + 1);
        $zip->addFile($file->getPathname(), str_replace('\\', '/', $rel));
    }
    $zip->close();
}

$env = loadEnv($projectRoot.'/.env');

$db = [
    'host' => $env['DB_HOST'] ?? '127.0.0.1',
    'port' => $env['DB_PORT'] ?? '3306',
    'user' => $env['DB_USERNAME'] ?? 'root',
    'pass' => $env['DB_PASSWORD'] ?? '',
];

$mysqlAvailable = false;
if (($env['DB_CONNECTION'] ?? '') === 'mysql') {
    $mysqlAvailable = true;
} else {
    try {
        $probe = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']),
            $db['user'],
            $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $probe->query('SELECT 1');
        $mysqlAvailable = true;
        fwrite(STDOUT, "==> Using MySQL probe (local .env is not mysql)\n");
    } catch (Throwable) {
        fwrite(STDERR, "SKIP: MySQL not available — set DB_CONNECTION=mysql in .env or start MySQL.\n");
        exit(0);
    }
}

if (! $mysqlAvailable) {
    fwrite(STDERR, "SKIP: MySQL not configured.\n");
    exit(0);
}

$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jobazmoon-install-'.bin2hex(random_bytes(4));
$home = $tmp;
$publicHtml = $home.DIRECTORY_SEPARATOR.'public_html';
$jobDir = $home.DIRECTORY_SEPARATOR.'job';
$packageDir = $publicHtml.DIRECTORY_SEPARATOR.'package';
$zipPath = $packageDir.DIRECTORY_SEPARATOR.InstallEngine::PACKAGE_FILE;
$stage = $tmp.DIRECTORY_SEPARATOR.'stage';

echo "==> Preparing staging copy\n";
mkdir($publicHtml, 0755, true);
mkdir($packageDir, 0755, true);
mkdir($stage, 0755, true);

$include = ['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes', 'storage', 'vendor', 'artisan', 'composer.json', 'composer.lock', '.env.example'];
foreach ($include as $item) {
    $src = $projectRoot.DIRECTORY_SEPARATOR.$item;
    if (is_dir($src) || is_file($src)) {
        if (is_dir($src)) {
            copyDir($src, $stage.DIRECTORY_SEPARATOR.$item);
        } else {
            copy($src, $stage.DIRECTORY_SEPARATOR.$item);
        }
    }
}

$manifestDir = $stage.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build';
if (! is_dir($manifestDir)) {
    mkdir($manifestDir, 0755, true);
}
if (! is_file($manifestDir.DIRECTORY_SEPARATOR.'manifest.json')) {
    file_put_contents($manifestDir.DIRECTORY_SEPARATOR.'manifest.json', json_encode(['resources/js/app.ts' => ['file' => 'assets/app.js']]));
    echo "==> Created dummy public/build/manifest.json for test\n";
}

createZipFromDir($stage, $zipPath);
rrmdir($stage);

copy(__DIR__.'/install.php', $publicHtml.'/install.php');
copyDir(__DIR__.'/lib', $publicHtml.'/lib');

$testDb = 'jobazmoon_install_test_'.bin2hex(random_bytes(3));
$db['name'] = $testDb;

$engine = new InstallEngine($publicHtml, $home, $jobDir, $zipPath, $publicHtml.'/install.php');

echo "==> Testing database connection\n";
$test = $engine->testDatabase($db);
if (! $test['ok']) {
    fwrite(STDERR, "FAIL: ".$test['message']."\n");
    rrmdir($tmp);
    exit(1);
}

$site = [
    'site_name' => 'JobAzmoon Test',
    'url' => 'http://localhost',
    'name' => 'Test Admin',
    'email' => 'install-test-'.bin2hex(random_bytes(3)).'@example.com',
    'mobile' => '09120000001',
    'password' => 'TestPass123!',
];

echo "==> Running full install into {$jobDir}\n";
try {
    $result = $engine->runInstall($site, $db, true);
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: ".$e->getMessage()."\n");
    rrmdir($tmp);
    exit(1);
}

if (! $result['ok']) {
    fwrite(STDERR, "FAIL: install returned not ok\n");
    rrmdir($tmp);
    exit(1);
}

if (! $engine->isLocked()) {
    fwrite(STDERR, "FAIL: install lock missing\n");
    rrmdir($tmp);
    exit(1);
}

$failedVerify = array_filter($result['verify'], static fn (array $c): bool => ! $c['ok'] && ! in_array($c['label'], ['HTTPS'], true));
if ($failedVerify !== []) {
    fwrite(STDERR, "WARN: verify checks failed: ".json_encode(array_column($failedVerify, 'label'), JSON_UNESCAPED_UNICODE)."\n");
}

echo "==> PASS — install completed\n";
foreach ($result['log'] as $line) {
    echo "  - {$line}\n";
}

// Cleanup test DB
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']),
        $db['user'],
        $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec('DROP DATABASE IF EXISTS `'.$testDb.'`');
    echo "==> Dropped test database {$testDb}\n";
} catch (Throwable $e) {
    echo "==> WARN: could not drop test database {$testDb}\n";
}

rrmdir($tmp);
echo "DONE\n";
