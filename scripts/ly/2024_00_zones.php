<?php

$basePath = dirname(dirname(__DIR__));
include $basePath . '/scripts/vendor/autoload.php';

$cunliBasedAreas = ['1000401', '1000402', '6300001', '6300002', '6300003', '6300005', '6300007', '6300008',
'6400005', '6400006', '6500002', '6500003', '6500004', '6500005', '6500006', '6500007', '6500008', '6500009',
'6700005', '6700006', '6800001', '6800003', '6800004', '6800006'];
$fh = fopen($basePath . '/voteData/2020總統立委/區域立委/elbase.csv', 'r');
$pool = $map = $zones = $areas = [];
while ($line = fgetcsv($fh, 2048)) {
    if ($line[2] === '00') {
        continue;
    }
    $line[4] = substr($line[4], 1);
    $key = $line[0] . $line[1] . $line[2]; // election zone
    $key2 = $line[0] . $line[1] . $line[3] . $line[4]; // cunli code
    $key3 = $line[3] . $line[4]; // cunli part only
    if (!isset($pool[$key])) {
        $pool[$key] = [];
    }
    $pool[$key][$key3] = $line[5];
    $map[$key2] = $key;
}

foreach ($pool as $key => $cunlis) {
    $zones[$key] = [
        'name' => $cunlis['000000'],
        'areas' => [],
    ];
    foreach ($cunlis as $k => $v) {
        if ($k !== '000000' && substr($k, -3) === '000') {
            $v = mb_substr($zones[$key]['name'], 0, 3, 'utf-8') . $v;
            $areas[$v] = $key;
            $zones[$key]['areas'][$v] = true;
        }
    }
}

$json = json_decode(file_get_contents('/home/kiang/public_html/taiwan_basecode/cunli/geo/20230317.json'), true);

$lyPath = $basePath . '/data/ly/2024';
if (!file_exists($lyPath)) {
    mkdir($lyPath, 0777, true);
}

$geoPHP = new geoPHP();

$result = [];
$toSkip = [];
foreach ($json['features'] as $f) {
    if (empty($f['geometry'])) {
        continue;
    }
    if (isset($map[$f['properties']['VILLCODE']])) {
        $zoneId = $map[$f['properties']['VILLCODE']];
    } else {
        $key = $f['properties']['COUNTYNAME'] . $f['properties']['TOWNNAME'];
        $zoneId = $areas[$key];
    }
    if (!in_array(strval($zoneId), $cunliBasedAreas)) {
        continue;
    }
    $toSkip[$zoneId] = true;
    if (!isset($result[$zoneId])) {
        $result[$zoneId] = $geoPHP::load(json_encode($f['geometry']), 'json');
    } else {
        $result[$zoneId] = $result[$zoneId]->union($geoPHP::load(json_encode($f['geometry']), 'json'));
    }
}

$json = json_decode(file_get_contents('/home/kiang/public_html/taiwan_basecode/city/geo/20230317.json'), true);

foreach ($json['features'] as $f) {
    if (empty($f['geometry'])) {
        continue;
    }
    $key = $f['properties']['COUNTYNAME'] . $f['properties']['TOWNNAME'];
    $zoneId = $areas[$key];
    if (isset($toSkip[$zoneId])) {
        continue;
    }
    if (!isset($result[$zoneId])) {
        $result[$zoneId] = $geoPHP::load(json_encode($f['geometry']), 'json');
    } else {
        $result[$zoneId] = $result[$zoneId]->union($geoPHP::load(json_encode($f['geometry']), 'json'));
    }
}

$fc = new stdClass();
$fc->type = 'FeatureCollection';
$fc->features = array();
foreach ($result as $zoneId => $geo) {
    $f = new stdClass();
    $f->type = 'Feature';
    $f->properties = new stdClass();
    $f->properties->id = $zoneId;
    $f->properties->name = $zones[$zoneId]['name'];
    $f->properties->areas = implode(',', array_keys($zones[$zoneId]['areas']));
    $f->geometry = json_decode($geo->simplify(0.001, true)->out('json'));
    $fc->features[] = $f;
}

file_put_contents($lyPath . '/zones.json', json_encode($fc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));