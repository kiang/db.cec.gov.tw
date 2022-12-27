<?php
$basePath = dirname(dirname(__DIR__));
$dataPath = $basePath . '/data/2014';

$pool = $cityPool = $meta = [];
$years = ['2014', '2018', '2022'];
foreach ($years as $y) {
    $meta[$y] = [
        'candidates' => 0,
        'victors' => 0,
        're-elected' => 0,
        'current' => 0,
        'cunli' => 0,
    ];
    foreach (glob($basePath . '/data/' . $y . '/*.csv') as $csvFile) {
        $p = pathinfo($csvFile);
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
            ++$meta[$y]['candidates'];
            if($data['is_current'] === 'Y') {
                ++$meta[$y]['current'];
                if($data['is_victor'] === 'Y') {
                    ++$meta[$y]['re-elected'];
                }
            }
            if($data['is_victor'] === 'Y') {
                ++$meta[$y]['victors'];
                if($type === '村里長' && $data['is_current'] === 'N') {
                    ++$meta[$y]['cunli'];
                }
            }
            $city = mb_substr($data['area'], 0, 3, 'utf-8');
            if (!isset($cityPool[$city])) {
                $cityPool[$city] = [];
            }
            if (!isset($pool[$data['party']])) {
                $pool[$data['party']] = [];
            }
            if (!isset($cityPool[$city][$data['party']])) {
                $cityPool[$city][$data['party']] = [];
            }
            if (!isset($pool[$data['party']][$typeKey])) {
                $pool[$data['party']][$typeKey] = 0;
            }
            if (!isset($cityPool[$city][$data['party']][$typeKey])) {
                $cityPool[$city][$data['party']][$typeKey] = 0;
            }
            $pool[$data['party']][$typeKey] += $data['ticket_num'];
            $cityPool[$city][$data['party']][$typeKey] += $data['ticket_num'];
            // if ('Y' === $data['is_victor']) {
            //     $pool[$type][$y][$data['party']] += 1;
            // }
        }
    }
}

ksort($pool['無黨籍及未經政黨推薦']);
$fullKeys = array_keys($pool['無黨籍及未經政黨推薦']);
$oFh = fopen($basePath . '/data/count/all.csv', 'w');
fputcsv($oFh, array_merge(['party'], $fullKeys));
foreach ($pool as $party => $l1) {
    $line = [$party];
    foreach ($fullKeys as $key) {
        if (isset($l1[$key])) {
            $line[] = $l1[$key];
        } else {
            $line[] = '';
        }
    }
    fputcsv($oFh, $line);
}

foreach ($cityPool as $city => $pool) {
    $oFh = fopen($basePath . '/data/count/' . $city . '.csv', 'w');
    fputcsv($oFh, array_merge(['party'], $fullKeys));
    foreach ($pool as $party => $l1) {
        $line = [$party];
        foreach ($fullKeys as $key) {
            if (isset($l1[$key])) {
                $line[] = $l1[$key];
            } else {
                $line[] = '';
            }
        }
        fputcsv($oFh, $line);
    }
}

print_r($meta);