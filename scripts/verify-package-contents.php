<?php

declare(strict_types=1);

$zipPath = $argv[1] ?? dirname(__DIR__).'/dist/jobazmoon-core.zip';
if (! is_file($zipPath)) {
    $alt = dirname(__DIR__).'/dist/JobAzmoon-Installer/package/jobazmoon-core.zip';
    $zipPath = is_file($alt) ? $alt : $zipPath;
}

if (! is_file($zipPath)) {
    fwrite(STDERR, "ZIP not found\n");
    exit(1);
}

$z = new ZipArchive;
if ($z->open($zipPath) !== true) {
    fwrite(STDERR, "Cannot open ZIP\n");
    exit(1);
}

echo 'ZIP: '.$zipPath."\n";
echo 'Modified: '.date('Y-m-d H:i:s', (int) filemtime($zipPath))."\n";
echo 'Size: '.round(filesize($zipPath) / (1024 * 1024), 2)." MB\n\n";

$checks = [
    'build-info.json',
    'app/Support/EnamadBadge.php',
    'config/enamad.php',
    'app/Mail/PaymentReceiptMail.php',
    'app/Mail/TicketReplyMail.php',
    'app/Services/Seo/SeoAutoOptimizer.php',
    'resources/views/emails/payment-receipt.blade.php',
    'resources/views/partials/analytics.blade.php',
    'resources/js/views/legal/RefundView.vue',
    'public/build/manifest.json',
];

$missing = 0;
foreach ($checks as $path) {
    $ok = $z->locateName($path) !== false;
    echo ($ok ? 'YES' : 'NO ').' '.$path."\n";
    if (! $ok) {
        $missing++;
    }
}

$manifest = $z->getFromName('public/build/manifest.json');
if (is_string($manifest)) {
    echo "\nmanifest.json snippet:\n";
    echo substr($manifest, 0, 200)."...\n";
}

$z->close();
exit($missing > 0 ? 1 : 0);
