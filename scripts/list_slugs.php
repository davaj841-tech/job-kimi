<?php
$c = file_get_contents(dirname(__DIR__).'/config/aggregation.php');
preg_match_all("/'slug'\s*=>\s*'([^']+)'/", $c, $m1);
preg_match_all("/'slug'=>'([^']+)'/", $c, $m2);
$slugs = array_unique(array_merge($m1[1], $m2[1]));
sort($slugs);
echo count($slugs)." slugs\n";
foreach ($slugs as $s) {
    echo $s."\n";
}
