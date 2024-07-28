<?php
$rootPath = dirname(dirname(__DIR__));
$json = json_decode(file_get_contents($rootPath . '/data/ly/2024_party_cunli_president.json'), true);
$pool = [];
foreach ($json as $k => $cunli) {
    $kpVote = $cunli['votes']['柯文哲'];
    if (!isset($pool[$kpVote])) {
        $pool[$kpVote] = [];
    }
    $pool[$kpVote][] = $cunli;
}
krsort($pool);

$fh = fopen($rootPath . '/data/report/2024_president_base.csv', 'w');
fputcsv($fh, ['村里', '總統票總數', '柯文哲', '賴清德', '侯友宜']);
foreach ($pool as $v => $cunlis) {
    foreach ($cunlis as $cunli) {
        fputcsv($fh, [$cunli['name'], $cunli['total'], $cunli['votes']['柯文哲'], $cunli['votes']['賴清德'], $cunli['votes']['侯友宜']]);
    }
}
