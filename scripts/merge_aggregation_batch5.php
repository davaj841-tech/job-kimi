<?php
/**
 * One-shot merge helper — run: php scripts/merge_aggregation_batch5.php
 * Do NOT place under config/ (Laravel loads all config/*.php).
 */

$path = dirname(__DIR__).'/config/aggregation.php';
$content = file_get_contents($path);
$batch = file_get_contents(__DIR__.'/aggregation_batch5_sources.txt');

$marker = "        ['slug'=>'e-estekhdam-bank',";
if (! str_contains($content, $marker)) {
    fwrite(STDERR, "Marker not found\n");
    exit(1);
}

$content = preg_replace(
    '/(\[' . "'slug'=>'e-estekhdam-bank'.*?\]\],)\n\n    \],/s",
    '$1'."\n".$batch."\n    ],",
    $content,
    1,
    $count
);

if ($count !== 1) {
    fwrite(STDERR, "Merge failed\n");
    exit(1);
}

file_put_contents($path, $content);

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$sources = config('aggregation.official_sources');
$slugs = array_column($sources, 'slug');
echo 'OK: '.count($sources).' sources, '.count(array_unique($slugs)).' unique slugs'."\n";
