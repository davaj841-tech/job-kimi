<?php

$raw = file_get_contents('phpstan-full.json');
// Strip BOM if present
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
$j = json_decode($raw, true);
if (! is_array($j)) {
    fwrite(STDERR, "JSON decode failed: ".json_last_error_msg()."\n");
    // try raw format fallback
    exit(1);
}

$files = $j['files'] ?? [];
$all = [];
foreach ($files as $path => $info) {
    foreach ($info['messages'] ?? [] as $m) {
        $all[] = [
            'file' => str_replace('\\', '/', (string) $path),
            'line' => (int) ($m['line'] ?? 0),
            'id' => (string) ($m['identifier'] ?? 'unknown'),
            'msg' => (string) ($m['message'] ?? ''),
        ];
    }
}

echo 'TOTAL='.count($all).PHP_EOL;
$byId = [];
foreach ($all as $e) {
    $byId[$e['id']] = ($byId[$e['id']] ?? 0) + 1;
}
arsort($byId);
foreach ($byId as $k => $v) {
    echo "ID\t{$v}\t{$k}\n";
}

$tsv = [];
foreach ($all as $e) {
    $msg = str_replace(["\r", "\n", "\t"], ' ', $e['msg']);
    $tsv[] = "{$e['id']}\t{$e['line']}\t{$e['file']}\t{$msg}";
}
file_put_contents('phpstan-classified.tsv', implode("\n", $tsv));

// Group property.notFound by property name
$props = [];
foreach ($all as $e) {
    if ($e['id'] !== 'property.notFound') {
        continue;
    }
    if (preg_match('/\$([A-Za-z_][A-Za-z0-9_]*)/', $e['msg'], $m)) {
        $props[$m[1]] = ($props[$m[1]] ?? 0) + 1;
    }
}
arsort($props);
echo "PROPS\n";
foreach (array_slice($props, 0, 40, true) as $k => $v) {
    echo "PROP\t{$v}\t{$k}\n";
}

// Top files
$byFile = [];
foreach ($all as $e) {
    $rel = preg_replace('#^.*/(app/.*)$#', '$1', $e['file']) ?: $e['file'];
    $byFile[$rel] = ($byFile[$rel] ?? 0) + 1;
}
arsort($byFile);
echo "FILES\n";
foreach (array_slice($byFile, 0, 30, true) as $k => $v) {
    echo "FILE\t{$v}\t{$k}\n";
}
