<?php

$basePath = dirname(dirname(__DIR__));
include $basePath . '/scripts/vendor/autoload.php';

/**
 *  從 2022 選舉結果取出個別議員選區對應的村里資料
 *     [0] => 00 //省市
    [1] => 000 //縣市
    [2] => 00 //選區
    [3] => 000 //鄉鎮市區
    [4] => 0000 //村里
    [5] => 全國 //名稱
 */
$codes = $pool = [];
$csvFiles = [
    $basePath . '/voteData/2022-111年地方公職人員選舉/R1/elbase.csv',
    $basePath . '/voteData/2022-111年地方公職人員選舉/R3/elbase.csv'
];
foreach ($csvFiles as $csvFile) {
    $fh = fopen($csvFile, 'r');
    while ($line = fgetcsv($fh, 2048)) {
        if (false !== strpos($line[5], '選舉區')) {
            continue;
        }
        if ($line[4] !== '0000') {
            $parts = explode('、', $line[5]);
            $code = $line[0] . $line[1] . $line[3];
            foreach ($parts as $part) {
                $pool[$codes[$code] . $part] = $line[0] . $line[1] . $line[3] . '-' . $line[2];
            }
        } else {
            if ($line[3] === '000') {
                $codes[$line[0] . $line[1]] = $line[5];
            } else {
                $codes[$line[0] . $line[1] . $line[3]] = $codes[$line[0] . $line[1]] . $line[5];
            }
        }
    }
    fclose($fh);
}


$json = json_decode(file_get_contents('/home/kiang/public_html/taiwan_basecode/cunli/geo/20221118.json'), true);

$map = [
    '南投縣名間鄉廍下村' => '10008060-02',
    '南投縣竹山鎮硘[磘]里' => '10008040-03',
    '屏東縣新園鄉瓦[磘]村' => '10013170-01',
    '屏東縣東港鎮下廍里' => '10013030-04',
    '屏東縣里港鄉三廍村' => '10013090-04',
    '彰化縣埔鹽鄉廍子村' => '10007140-01',
    '彰化縣埔鹽鄉瓦[磘]村' => '10007140-02',
    '彰化縣彰化市下廍里' => '10007010-03',
    '彰化縣彰化市寶廍里' => '10007010-04',
    '彰化縣彰化市磚[磘]里' => '10007010-02',
    '彰化縣芳苑鄉頂廍村' => '10007230-02',
    '澎湖縣馬公市[嵵]裡里' => '10016010-04',
    '雲林縣元長鄉瓦[磘]村' => '10009170-03',
    '雲林縣四湖鄉[萡]子村' => '10009180-03',
    '雲林縣四湖鄉[萡]東村' => '10009180-03',
    '雲林縣水林鄉[欍]埔村' => '10009200-03',
    '雲林縣麥寮鄉瓦[磘]村' => '10009130-01',
];
$skip = [
    '嘉義市' => true,
    '基隆市' => true,
    '新竹市' => true,
    '新北市' => true,
    '桃園市' => true,
    '臺中市' => true,
    '臺北市' => true,
    '臺南市' => true,
    '高雄市' => true,
    '高雄市茂林區' => true,
    '高雄市桃源區' => true,
    '高雄市那瑪夏區' => true,
    '新北市烏來區' => true,
    '臺中市和平區' => true,
    '桃園市復興區' => true,
];
foreach ($json['features'] as $k => $f) {
    $key = $f['properties']['COUNTYNAME'] . $f['properties']['TOWNNAME'];
    if (isset($skip[$f['properties']['COUNTYNAME']]) && !isset($skip[$key])) {
        continue;
    }
    if (!empty($f['properties']['VILLNAME'])) {
        $key = $key . $f['properties']['VILLNAME'];
        if (isset($pool[$key])) {
            $json['features'][$k]['properties']['ZONE'] = $pool[$key];
            unset($pool[$key]);
        } else {
            $json['features'][$k]['properties']['ZONE'] = $map[$key];
        }
    }
}

$councilPath = $basePath . '/data/town_council/2026';
if (!file_exists($councilPath)) {
    mkdir($councilPath, 0777, true);
}

file_put_contents($councilPath . '/cunli.json', json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
exec('/usr/bin/gzip ' . $councilPath . '/cunli.json');

$geoPHP = new geoPHP();

$result = array();
$pool = [];
$zoneAreas = $zoneName = [];
$city = [];
$cunliFh = fopen($councilPath . '/cunli.csv', 'w');
fputcsv($cunliFh, ['zone', 'city', 'area', 'village', 'village_code']);
foreach ($json['features'] as $f) {
    if (empty($f['properties']['ZONE'])) {
        continue;
    }
    $zoneId = $f['properties']['ZONE'];
    $city[$zoneId] = $f['properties']['COUNTYNAME'] . $f['properties']['TOWNNAME'];
    if (!isset($zoneAreas[$zoneId])) {
        $zoneAreas[$zoneId] = [];
    }
    $zoneAreas[$zoneId][$f['properties']['VILLNAME']] = true;

    if (false !== $zoneId) {
        if (!empty($f['properties']['VILLNAME'])) {
            fputcsv($cunliFh, [$zoneId, $f['properties']['COUNTYNAME'], $f['properties']['TOWNNAME'], $f['properties']['VILLNAME'], $f['properties']['VILLCODE']]);
        }

        $zoneName[$zoneId] = $f['properties']['COUNTYNAME'] . $f['properties']['TOWNNAME'] . '第' . substr($zoneId, -2) . '選區';
        if (!isset($result[$zoneId])) {
            $result[$zoneId] = $geoPHP::load(json_encode($f['geometry']), 'json');
        } else {
            $result[$zoneId] = $result[$zoneId]->union($geoPHP::load(json_encode($f['geometry']), 'json'));
        }
    }
}

ksort($zoneAreas,  SORT_NATURAL);

$fh = fopen($councilPath . '/zones.csv', 'w');
fputcsv($fh, ['city-town', 'zone', 'code', 'areas', 'name', 'party']);
foreach ($zoneAreas as $k => $v) {
    if (!empty($k)) {
        fputcsv($fh, [$city[$k], $zoneName[$k], $k, implode('/', array_keys($v)), '', '']);
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
    $f->properties->name = $zoneName[$zoneId];
    $f->properties->areas = implode(',', array_keys($zoneAreas[$zoneId]));
    $f->geometry = json_decode($geo->out('json'));
    $fc->features[] = $f;
}

file_put_contents($councilPath . '/zones.json', json_encode($fc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
exec('/usr/bin/gzip ' . $councilPath . '/zones.json');
