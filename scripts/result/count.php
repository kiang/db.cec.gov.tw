<?php
$basePath = dirname(dirname(__DIR__));
$dataPath = $basePath . '/data/2014';

$pool = [];
foreach (glob($basePath . '/data/*/*.csv') as $csvFile) {
    $p = pathinfo($csvFile);
    $p2 = pathinfo($p['dirname']);
    $y = $p2['filename'];
    $type = $p['filename'];
    switch ($type) {
        case '直轄市山地原住民區民代表':
            $type = '鄉鎮市民代表';
            break;
        case '直轄市山地原住民區長':
            $type = '鄉鎮市長';
            break;
        case '直轄市議員':
        case '縣市議員':
            $type = '議員';
            break;
        case '直轄市長':
            $type = '縣市長';
            break;
    }
    $typeKey = $type . $y;
    $fh = fopen($csvFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        if (!isset($pool[$data['party']])) {
            $pool[$data['party']] = [];
        }
        if (!isset($pool[$data['party']][$typeKey])) {
            $pool[$data['party']][$typeKey] = 0;
        }
        $pool[$data['party']][$typeKey] += $data['ticket_num'];
        // if ('Y' === $data['is_victor']) {
        //     $pool[$type][$y][$data['party']] += 1;
        // }
    }
}

ksort($pool['無黨籍及未經政黨推薦']);
$fullKeys = array_keys($pool['無黨籍及未經政黨推薦']);
$oFh = fopen($basePath . '/data/count.csv', 'w');
fputcsv($oFh, array_merge(['party'], $fullKeys));
foreach($pool AS $party => $l1) {
    $line = [$party];
    foreach($fullKeys AS $key) {
        if(isset($l1[$key])) {
            $line[] = $l1[$key];
        } else {
            $line[] = '';
        }
    }
    fputcsv($oFh, $line);
}