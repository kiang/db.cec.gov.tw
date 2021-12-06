<?php

$basePath = dirname(dirname(__DIR__));
include $basePath . '/scripts/vendor/autoload.php';

$councilPath = $basePath . '/data/council/2018';

$cunli = json_decode(file_get_contents($councilPath . '/cunli.json'), true);
$map = array();
foreach ($cunli AS $code => $items) {
    $item = array_shift($items);
    $map[$code] = $item['code'];
}

$json = json_decode(file_get_contents('/home/kiang/public_html/taiwan_basecode/cunli/geo/20180330.json'), true);

$geoPHP = new geoPHP();

$result = array();
$zoneAreas = [];
foreach ($json['features'] AS $f) {
    $zoneId = false;
    if (isset($map[$f['properties']['VILLCODE']])) {
        $zoneId = $map[$f['properties']['VILLCODE']];
    }
    $zoneAreas[$zoneId][$f['properties']['TOWNNAME']] = true;
    if (false !== $zoneId) {
        if (!isset($result[$zoneId])) {
            $result[$zoneId] = $geoPHP::load(json_encode($f['geometry']), 'json');
        } else {
            $result[$zoneId] = $result[$zoneId]->union($geoPHP::load(json_encode($f['geometry']), 'json'));
        }
    }
}

$fc = new stdClass();
$fc->type = 'FeatureCollection';
$fc->features = array();
foreach ($result AS $zoneId => $geo) {
    $f = new stdClass();
    $f->type = 'Feature';
    $f->properties = new stdClass();
    $f->properties->id = $zoneId;
    $f->properties->areas = implode(',', array_keys($zoneAreas[$zoneId]));
    $f->geometry = json_decode($geo->out('json'));
    $fc->features[] = $f;
}
file_put_contents($councilPath . '/zones.json', json_encode($fc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));