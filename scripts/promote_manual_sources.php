<?php
/**
 * Promote manual_only sources to limited for automatic crawl (pending import).
 * Run: php scripts/promote_manual_sources.php
 */

$path = dirname(__DIR__).'/config/aggregation.php';
$content = file_get_contents($path);

$before = substr_count($content, "'quality_status'=>'manual_only'")
    + substr_count($content, "'quality_status' => 'manual_only'");

$content = str_replace("'quality_status'=>'manual_only'", "'quality_status'=>'limited'", $content);
$content = str_replace("'quality_status' => 'manual_only'", "'quality_status' => 'limited'", $content);

$after = substr_count($content, "'quality_status'=>'manual_only'")
    + substr_count($content, "'quality_status' => 'manual_only'");

file_put_contents($path, $content);

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$sources = config('aggregation.official_sources');
$limited = collect($sources)->where('quality_status', 'limited')->count();
$manual = collect($sources)->where('quality_status', 'manual_only')->count();
$unavail = collect($sources)->where('quality_status', 'temporarily_unavailable')->count();

echo "Promoted {$before} manual_only entries\n";
echo 'Config: '.count($sources)." sources — limited={$limited}, manual={$manual}, unavailable={$unavail}\n";
