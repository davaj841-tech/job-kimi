<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$bundleDir = $root . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'JobAzmoon-Installer';
$bundleZip = $root . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'JobAzmoon-Installer.zip';
$coreZip = $root . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'jobazmoon-core.zip';

if (!is_dir($bundleDir)) { fwrite(STDERR, "missing bundle dir\n"); exit(1); }

$core = new ZipArchive;
if ($core->open($coreZip) !== true) { fwrite(STDERR, "cannot open core zip\n"); exit(1); }
$must = ['vendor/autoload.php','public/build/manifest.json','artisan','composer.lock','routes/install.php','storage/framework/views/.gitignore','bootstrap/cache/.gitignore'];
foreach ($must as $p) { echo (($core->locateName($p) !== false) ? 'OK' : 'MISS') . " $p\n"; }
$manifest = $core->getFromName('public/build/manifest.json');
$data = json_decode((string)$manifest, true);
echo 'manifest_entries=' . (is_array($data) ? count($data) : 0) . "\n";
$forbidden = false;
for ($i = 0; $i < $core->numFiles; $i++) {
  $n = (string)$core->getNameIndex($i);
  if ($n === '.env' || $n === 'install.php' || str_starts_with($n, 'tests/') || str_starts_with($n, 'node_modules/') || str_starts_with($n, '.git/')) {
    echo "FORBIDDEN $n\n"; $forbidden = true; break;
  }
}
if (!$forbidden) echo "forbidden_scan=OK\n";
echo 'core_mb=' . round(((int)filesize($coreZip)) / 1048576, 2) . "\n";
$core->close();

if (is_file($bundleZip)) unlink($bundleZip);
$za = new ZipArchive;
if ($za->open($bundleZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { fwrite(STDERR, "cannot create installer zip\n"); exit(1); }
$base = realpath($bundleDir);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($it as $item) {
  $abs = $item->getPathname();
  $rel = str_replace('\\', '/', substr($abs, strlen($base) + 1));
  if ($rel === '' || $rel === false) continue;
  if ($item->isDir()) $za->addEmptyDir($rel);
  elseif ($item->isFile()) $za->addFile($abs, $rel);
}
$za->close();
echo 'installer_mb=' . round(((int)filesize($bundleZip)) / 1048576, 2) . PHP_EOL;
$check = new ZipArchive; $check->open($bundleZip);
foreach (['install.php','lib/InstallEngine.php','package/jobazmoon-core.zip','INSTALL.md','README-INSTALL.txt'] as $p) {
  echo (($check->locateName($p) !== false) ? 'OK' : 'MISS') . " $p\n";
}
if ($check->locateName('JobAzmoon-Installer/install.php') !== false) { echo "FAIL nested\n"; exit(1); }
$check->close();
echo "PASS rebuild installer zip\n";