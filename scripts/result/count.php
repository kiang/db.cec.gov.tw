<?php
$basePath = dirname(dirname(__DIR__));
$dataPath = $basePath . '/data/2014';

$pool = [];
foreach (glob($basePath . '/data/*/*.csv') as $csvFile) {
    $p = pathinfo($csvFile);
    $p2 = pathinfo($p['dirname']);
    $y = $p2['filename'];
    $type = $p['filename'];
    if (!isset($pool[$type])) {
        $pool[$type] = [];
    }
    if (!isset($pool[$type][$y])) {
        $pool[$type][$y] = [];
    }
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        if (!isset($pool[$type][$y][$data['party']])) {
            $pool[$type][$y][$data['party']] = 0;
        }
        $pool[$type][$y][$data['party']] += $data['ticket_num'];
        // if ('Y' === $data['is_victor']) {
        //     $pool[$type][$y][$data['party']] += 1;
        // }
    }
}

foreach ($pool as $type => $l1) {
    foreach ($l1 as $y => $l2) {
        arsort($pool[$type][$y]);
        $pool[$type][$y]['total'] = 0;
        foreach ($l2 as $v) {
            $pool[$type][$y]['total'] += $v;
        }
    }
}

print_r($pool);
