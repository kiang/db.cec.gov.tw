<?php
$json = json_decode(file_get_contents(dirname(dirname(__DIR__)) . '/data/ly/2024_zone_cunli.json'), true);
$pool = [];

foreach($json AS $cunli) {
    if(!isset($pool[$cunli['zoneCode']])) {
        $pool[$cunli['zoneCode']] = [
            'name' => $cunli['zone'],
            'total' => 0,
            'votes_all' => 0,
            'votes' => []
        ];
    }
    $pool[$cunli['zoneCode']]['total'] += $cunli['total'];
    $pool[$cunli['zoneCode']]['votes_all'] += $cunli['votes_all'];
    foreach($cunli['votes'] AS $name => $vote) {
        if(!isset($pool[$cunli['zoneCode']]['votes'][$name])) {
            $pool[$cunli['zoneCode']]['votes'][$name] = $vote;
        } else {
            $pool[$cunli['zoneCode']]['votes'][$name]['votes'] += $vote['votes'];
        }
    }
}
function cmp($a, $b)
{
    if ($a['votes'] == $b['votes']) {
        return 0;
    }
    return ($a['votes'] > $b['votes']) ? -1 : 1;
}

$fh = fopen(dirname(dirname(__DIR__)) . '/data/report/2024_zone.csv', 'w');
fputcsv($fh, ['選區', '總票數', '選舉人數', '第一高票', '得票', '政黨', '第二高票', '得票', '政黨']);
foreach($pool AS $zoneCode => $zone) {
    usort($zone['votes'], "cmp");
    fputcsv($fh, [
        $zone['name'],
        $zone['total'],
        $zone['votes_all'],
        $zone['votes'][0]['name'],
        $zone['votes'][0]['votes'],
        $zone['votes'][0]['party'],
        $zone['votes'][1]['name'],
        $zone['votes'][1]['votes'],
        $zone['votes'][1]['party']
    ]);
}


