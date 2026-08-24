# Build production ZIP for cPanel installer: dist/jobazmoon-core.zip
param(
    [switch]$SkipDeps,
    [switch]$NoRestore,
    [string]$Output = ""
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

$argsList = @()
if ($SkipDeps) { $argsList += "--skip-deps" }
if ($NoRestore) { $argsList += "--no-restore" }
if ($Output -ne "") { $argsList += "--output=$Output" }

php "$Root\scripts\build-cpanel-package.php" @argsList
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}
