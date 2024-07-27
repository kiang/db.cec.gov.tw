<?php
$rootPath = dirname(dirname(__DIR__));
$json = json_decode(file_get_contents($rootPath . '/data/ly/2024_party_cunli_president.json'), true);
$pool = [];
foreach ($json as $k => $cunli) {
    if ($cunli['votes']['柯文哲'] > $cunli['votes']['賴清德'] && $cunli['votes']['柯文哲'] > $cunli['votes']['侯友宜']) {
        $pool[$k] = $cunli;
    }
}

$json2 = json_decode(file_get_contents($rootPath . '/data/ly/2024_party_cunli.json'), true);

$fh = fopen($rootPath . '/data/report/2024_president_cunli.csv', 'w');
$header = ['村里', '總統票總數', '柯文哲', '賴清德', '侯友宜', '立委票總數'];
$headerDone = false;
$parties = [];
foreach ($pool as $k => $cunli) {
    $pool[$k]['lyTotal'] = $json2[$k]['total'];
    $pool[$k]['ly'] = $json2[$k]['votes'];
    if (false === $headerDone) {
        $headerDone = true;
        $parties = array_keys($pool[$k]['ly']);
        $header = array_merge($header, $parties);
        fputcsv($fh, $header);
    }
    $line = [$cunli['name'], $cunli['total'], $cunli['votes']['柯文哲'], $cunli['votes']['賴清德'], $cunli['votes']['侯友宜'], $pool[$k]['lyTotal']];

    foreach ($parties as $party) {
        if (isset($pool[$k]['ly'][$party])) {
            $line[] = $pool[$k]['ly'][$party];
        } else {
            $line[] = '';
        }
    }
    fputcsv($fh, $line);
}
