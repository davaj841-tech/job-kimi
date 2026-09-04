# JobAzmoon pre-deploy check (Windows). Prefer bash when available.
# Usage: powershell -ExecutionPolicy Bypass -File scripts/pre-deploy-check.ps1

$ErrorActionPreference = "Continue"
$Root = Split-Path -Parent $PSScriptRoot
if (-not $Root) { $Root = (Get-Location).Path }
Set-Location $Root

$bashCmd = Get-Command bash -ErrorAction SilentlyContinue
if ($null -ne $bashCmd) {
    & bash "$Root/scripts/pre-deploy-check.sh"
    exit $LASTEXITCODE
}

$script:PassCount = 0
$script:FailCount = 0
$script:WarnCount = 0

function Write-Pass([string]$Message) {
    Write-Host "PASS  $Message"
    $script:PassCount++
}
function Write-Fail([string]$Message) {
    Write-Host "FAIL  $Message"
    $script:FailCount++
}
function Write-WarnMsg([string]$Message) {
    Write-Host "WARN  $Message"
    $script:WarnCount++
}

Write-Host "=== JobAzmoon pre-deploy check (PowerShell) ==="
Write-Host "Root: $Root"
Write-Host ""

try {
    $ver = & php -r "echo PHP_VERSION;"
    & php -r "exit(version_compare(PHP_VERSION,'8.2.0','>=')?0:1);"
    if ($LASTEXITCODE -eq 0) { Write-Pass "PHP >= 8.2 ($ver)" } else { Write-Fail "PHP >= 8.2 (found $ver)" }
} catch {
    Write-Fail "PHP binary missing"
}

$modules = (& php -m 2>$null) -join "`n"
foreach ($ext in @("openssl", "pdo", "mbstring", "tokenizer", "xml", "curl", "fileinfo", "zip")) {
    if ($modules -match "(?im)^$ext$") { Write-Pass "PHP extension: $ext" } else { Write-Fail "PHP extension missing: $ext" }
}

if (Test-Path "artisan") { Write-Pass "artisan present" } else { Write-Fail "artisan missing" }
if (Test-Path "bootstrap/app.php") { Write-Pass "bootstrap/app.php present" } else { Write-Fail "bootstrap/app.php missing" }
if (Test-Path "routes/install.php") { Write-Pass "routes/install.php present (cPanel boot)" } else { Write-Fail "routes/install.php missing" }

if (Test-Path ".env.example") {
    Write-Pass ".env.example present"
    $ex = Get-Content ".env.example" -Raw
    if ($ex -match "(?m)^APP_KEY=") { Write-Pass ".env.example has APP_KEY" } else { Write-Fail ".env.example missing APP_KEY" }
    if ($ex -match "(?m)^APP_URL=") { Write-Pass ".env.example has APP_URL" } else { Write-Fail ".env.example missing APP_URL" }
    if ($ex -match "(?m)^QUEUE_CONNECTION=") { Write-Pass ".env.example has QUEUE_CONNECTION" } else { Write-Fail ".env.example missing QUEUE_CONNECTION" }
    if ($ex -match "(?m)^CACHE_STORE=") { Write-Pass ".env.example has CACHE_STORE" } else { Write-Fail ".env.example missing CACHE_STORE" }
    if ($ex -match "(?m)^SESSION_DRIVER=") { Write-Pass ".env.example has SESSION_DRIVER" } else { Write-Fail ".env.example missing SESSION_DRIVER" }
    if ($ex -match "(?m)^\s*MAIL_HOST=.*smtp\.example\.com") { Write-Fail ".env.example still contains smtp.example.com" } else { Write-Pass ".env.example has no smtp.example.com" }
} else {
    Write-Fail ".env.example missing"
}

if (Test-Path ".env.production.example") {
    $pex = Get-Content ".env.production.example" -Raw
    if ($pex -match "(?m)^\s*MAIL_HOST=.*smtp\.example\.com") { Write-Fail ".env.production.example still contains smtp.example.com" } else { Write-Pass ".env.production.example has no smtp.example.com" }
} else {
    Write-WarnMsg ".env.production.example missing"
}

foreach ($d in @("storage", "bootstrap/cache", "storage/framework/cache", "storage/framework/sessions", "storage/framework/views", "storage/logs")) {
    if (-not (Test-Path $d)) { New-Item -ItemType Directory -Force -Path $d | Out-Null }
    if (Test-Path $d) { Write-Pass "writable: $d" } else { Write-Fail "cannot create: $d" }
}

if (Test-Path "vendor/autoload.php") { Write-Pass "vendor/autoload.php present" } else { Write-Fail "vendor/autoload.php missing - run composer install" }

if (Test-Path "public/build/manifest.json") {
    & php -r "exit((is_array(json_decode(@file_get_contents('public/build/manifest.json'), true)) && json_decode(@file_get_contents('public/build/manifest.json'), true) !== []) ? 0 : 1);"
    if ($LASTEXITCODE -eq 0) { Write-Pass "public/build/manifest.json valid non-empty" } else { Write-Fail "public/build/manifest.json empty or invalid JSON" }
} else {
    Write-Fail "public/build/manifest.json missing - run npm run build"
}

if ((Test-Path "cpanel-installer/install.php") -and (Test-Path "cpanel-installer/lib/InstallEngine.php")) {
    Write-Pass "cPanel installer sources present"
    & php -l "cpanel-installer/install.php" | Out-Null
    if ($LASTEXITCODE -eq 0) { Write-Pass "install.php PHP syntax" } else { Write-Fail "install.php PHP syntax error" }
    & php -l "cpanel-installer/lib/InstallEngine.php" | Out-Null
    if ($LASTEXITCODE -eq 0) { Write-Pass "InstallEngine.php PHP syntax" } else { Write-Fail "InstallEngine.php PHP syntax error" }
} else {
    Write-Fail "cPanel installer sources missing"
}

if (Test-Path "vendor/autoload.php") {
    $list = (& php artisan list --raw 2>$null) -join "`n"
    if ($list -match "(?m)^mail:test") { Write-Pass "artisan mail:test registered" } else { Write-Fail "artisan mail:test not listed" }
}

Write-Host ""
Write-Host ("=== Summary: PASS={0}  FAIL={1}  WARN={2} ===" -f $script:PassCount, $script:FailCount, $script:WarnCount)
if ($script:FailCount -gt 0) {
    Write-Host "RESULT: FAIL - do not create production ZIP until failures are fixed."
    exit 1
}
Write-Host "RESULT: PASS - safe to run: php scripts/build-cpanel-package.php"
exit 0
