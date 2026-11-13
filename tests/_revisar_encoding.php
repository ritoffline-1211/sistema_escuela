<?php

$s = file_get_contents(dirname(__DIR__) . '/docs/openapi_data.php');
preg_match_all('/[^\x00-\x7F]/u', $s, $m);
$c = array_count_values($m[0]);
arsort($c);

foreach ($c as $ch => $n) {
    printf("%s U+%04X %d\n", $ch, mb_ord($ch, 'UTF-8'), $n);
}
