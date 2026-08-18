# JobAzmoon cPanel Installer
# Run from the Laravel project root (Laragon / Windows).
# Does NOT run composer update. Uses the current vendor + composer.lock.

$ErrorActionPreference = "Stop"

$ProjectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not (Test-Path (Join-Path $ProjectRoot "artisan"))) {
    throw "prepare-installer.ps1 must live in the Laravel project root."
}

$PhpCandidates = @(
    "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe",
    "C:\laragon\bin\php\php-8.2.27-Win32-vs16-x64\php.exe",
    "php"
)
$Php = $null
foreach ($c in $PhpCandidates) {
    if ($c -eq "php") {
        $Php = "php"
        break
    }
    if (Test-Path $c) {
        $Php = $c
        break
    }
}
if (-not $Php) { throw "PHP executable not found." }

$Composer = $null
foreach ($c in @(
    "C:\laragon\bin\composer\composer.bat",
    "C:\laragon\bin\composer\composer.phar",
    "composer"
)) {
    if ($c -eq "composer") { $Composer = "composer"; break }
    if (Test-Path $c) { $Composer = $c; break }
}

$OutRoot = Join-Path ([Environment]::GetFolderPath("Desktop")) "JobAzmoon-Installer"
$PkgDir = Join-Path $OutRoot "package"
$ZipPath = Join-Path $PkgDir "jobazmoon-core.zip"
$CheckPath = Join-Path $OutRoot "INSTALL-CHECK.txt"

New-Item -ItemType Directory -Force -Path $PkgDir | Out-Null

Write-Host "==> composer.json validate"
if ($Composer) {
    if ($Composer -like "*.phar") {
        & $Php $Composer --working-dir=$ProjectRoot validate --no-check-publish
    } else {
        & $Composer --working-dir=$ProjectRoot validate --no-check-publish
    }
    if ($LASTEXITCODE -ne 0) { throw "composer.json is not valid." }
}

$Lock = Join-Path $ProjectRoot "composer.lock"
if (-not (Test-Path $Lock)) { throw "composer.lock is missing." }

$Vendor = Join-Path $ProjectRoot "vendor\autoload.php"
if (-not (Test-Path $Vendor)) { throw "vendor is missing. Do not run composer update; restore the existing vendor." }

$composerJson = Get-Content (Join-Path $ProjectRoot "composer.json") -Raw | ConvertFrom-Json
$PlatformPhp = [string] $composerJson.config.platform.php
if ($PlatformPhp -ne "8.2.0") {
    throw "Expected composer config.platform.php = 8.2.0, got: $PlatformPhp"
}

Write-Host "==> php artisan optimize:clear"
& $Php (Join-Path $ProjectRoot "artisan") optimize:clear

$Stage = Join-Path $env:TEMP ("jobazmoon-core-" + [guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Force -Path $Stage | Out-Null

$Include = @(
    "app", "bootstrap", "config", "database", "lang", "public", "resources",
    "routes", "storage", "vendor", "artisan", "composer.json", "composer.lock",
    ".env.example"
)

Write-Host "==> staging files (no .env / .git / node_modules)"
foreach ($item in $Include) {
    $src = Join-Path $ProjectRoot $item
    if (Test-Path $src) {
        Copy-Item -Recurse -Force $src (Join-Path $Stage $item)
    }
}

# Strip secrets / junk from the staged copy
$strip = @(
    (Join-Path $Stage ".env"),
    (Join-Path $Stage "storage\installed"),
    (Join-Path $Stage "public\hot")
)
foreach ($p in $strip) {
    if (Test-Path $p) { Remove-Item -Force -Recurse $p }
}
Get-ChildItem -Path (Join-Path $Stage "storage\logs") -File -ErrorAction SilentlyContinue | Remove-Item -Force
Get-ChildItem -Path (Join-Path $Stage "storage\framework\cache\data") -Recurse -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -ne ".gitignore" } | Remove-Item -Force -Recurse
Get-ChildItem -Path (Join-Path $Stage "storage\framework\sessions") -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -ne ".gitignore" } | Remove-Item -Force
Get-ChildItem -Path (Join-Path $Stage "storage\framework\views") -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Extension -eq ".php" } | Remove-Item -Force
if (Test-Path (Join-Path $Stage "bootstrap\cache\packages.php")) {
    # keep package discovery files; they help hosts without composer
}

if (Test-Path $ZipPath) { Remove-Item $ZipPath -Force }

Write-Host "==> creating jobazmoon-core.zip"
Push-Location $Stage
try {
    tar.exe -a -c -f $ZipPath *
} finally {
    Pop-Location
}
Remove-Item -Recurse -Force $Stage

Copy-Item (Join-Path $ProjectRoot "cpanel-installer\install.php") (Join-Path $OutRoot "install.php") -Force
Copy-Item $MyInvocation.MyCommand.Path (Join-Path $OutRoot "prepare-installer.ps1") -Force

$ZipSize = [math]::Round((Get-Item $ZipPath).Length / 1MB, 1)
$MigCount = (Get-ChildItem (Join-Path $ProjectRoot "database\migrations\*.php")).Count
$HasHtaccess = Test-Path (Join-Path $ProjectRoot "public\.htaccess")
$HasIndex = Test-Path (Join-Path $ProjectRoot "public\index.php")
$HasVendor = Test-Path $Vendor
$Laravel = "11.x"
try {
    $lockJson = Get-Content $Lock -Raw
    if ($lockJson -match '"name": "laravel/framework",[\s\S]*?"version": "v?([^"]+)"') {
        $Laravel = $Matches[1]
    }
} catch {}

$readme = @"
JobAzmoon — راهنمای نصب cPanel (بدون SSH / بدون Composer)

1) در cPanel یک دیتابیس MySQL و کاربر بسازید و کاربر را به دیتابیس وصل کنید.
2) PHP را روی 8.2 یا 8.3 بگذارید و افزونه‌های PDO, pdo_mysql, openssl, mbstring, xml, ctype, json, fileinfo, gd, zip, dom را فعال کنید.
3) کل پوشه JobAzmoon-Installer را داخل public_html آپلود کنید:
     public_html/install.php
     public_html/package/jobazmoon-core.zip
4) مرورگر: https://دامنه-شما/install.php
5) مراحل را کامل کنید. سیستم پوشه job را کنار public_html می‌سازد:
     /home/USER/job          ← هسته Laravel + vendor
     /home/USER/public_html  ← index.php و دارایی‌ها
6) بعد از نصب موفق، install.php باید حذف شود. اگر حذف خودکار نشد، خودتان پاک کنید.

Document Root همان public_html است. پوشه job را به عنوان دامنه تنظیم نکنید.
"@
Set-Content -Path (Join-Path $OutRoot "README-INSTALL.txt") -Value $readme -Encoding UTF8

$check = @"
JobAzmoon installer package check
Generated: $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")

jobazmoon-core.zip size_mb=$ZipSize
vendor_present=$HasVendor
composer_lock_present=True
laravel_version=$Laravel
php_minimum=^8.2
composer_platform_php=$PlatformPhp
migration_files=$MigCount
public_index_php=$HasIndex
public_htaccess=$HasHtaccess
storage_dir=True
horizon_package=kept (pcntl/posix not required for web install)
composer_update=NOT run
env_file_in_zip=NO

cPanel manual settings:
- PHP 8.2/8.3 + extensions listed in README-INSTALL.txt
- MySQL database + user
- public_html document root (default)
- SSL / HTTPS in cPanel
- Cron (optional): * * * * * php /home/USER/job/artisan schedule:run
- Horizon needs pcntl+posix; without them the site still works, queues use database driver
"@
Set-Content -Path $CheckPath -Value $check -Encoding UTF8

Write-Host ""
Write-Host "DONE: $OutRoot"
Write-Host "ZIP : $ZipPath  ($ZipSize MB)"
Write-Host $check
