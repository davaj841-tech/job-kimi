<?php

$raw = file_get_contents(__DIR__ . '/phpstan-now.txt');
if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
    $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
}
$lines = preg_split('/\r\n|\n|\r/', $raw) ?: [];
$byFile = [];
$byIdFile = [];
foreach ($lines as $l) {
    $l = trim($l);
    if (! preg_match('/^(.*):(\d+):(.*) \[identifier=([^\]]+)\]/', $l, $m)) {
        continue;
    }
    $rel = str_replace('\\', '/', $m[1]);
    if (preg_match('#(app/.*)$#', $rel, $rm)) {
        $rel = $rm[1];
    }
    $byFile[$rel] = ($byFile[$rel] ?? 0) + 1;
    $byIdFile[$m[4]][$rel] = ($byIdFile[$m[4]][$rel] ?? 0) + 1;
}
arsort($byFile);
echo "---BY FILE (top 40)---\n";
foreach (array_slice($byFile, 0, 40, true) as $f => $c) {
    echo "$c\t$f\n";
}
echo "---property.notFound by file---\n";
$pn = $byIdFile['property.notFound'] ?? [];
arsort($pn);
foreach (array_slice($pn, 0, 30, true) as $f => $c) {
    echo "$c\t$f\n";
}
