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
foreach (glob($basePath . '/voteData/2022-111年地方公職人員選舉/T1/*/elbase.csv') as $csvFile) {
    $fh = fopen($csvFile, 'r');
    while ($line = fgetcsv($fh, 2048)) {
        if ($line[4] !== '0000') {
            $parts = explode('、', $line[5]);
            $code = $line[0] . $line[1] . $line[3];
            $pool[$codes[$code]] = $line[0] . $line[1] . '-' . $line[2];
            foreach ($parts as $part) {
                $pool[$codes[$code] . $part] = $line[0] . $line[1] . '-' . $line[2];
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
    '南投縣名間鄉廍下村' => '10008-01',
    '南投縣竹山鎮硘[磘]里' => '10008-04',
    '嘉義市西區磚[磘]里' => '10020-02',
    '屏東縣新園鄉瓦[磘]村' => '10013-04',
    '屏東縣東港鎮下廍里' => '10013-04',
    '屏東縣里港鄉三廍村' => '10013-02',
    '彰化縣埔鹽鄉廍子村' => '10007-05',
    '彰化縣埔鹽鄉瓦[磘]村' => '10007-05',
    '彰化縣彰化市下廍里' => '10007-01',
    '彰化縣彰化市寶廍里' => '10007-01',
    '彰化縣彰化市磚[磘]里' => '10007-01',
    '彰化縣芳苑鄉頂廍村' => '10007-08',
    '新北市中和區灰[磘]里' => '65000-06',
    '新北市中和區瓦[磘]里' => '65000-06',
    '新北市坪林區石[曹]里' => '65000-09',
    '新北市樹林區[獇]寮里' => '65000-08',
    '新北市永和區新廍里' => '65000-07',
    '新北市瑞芳區濂新里' => '65000-10',
    '新北市瑞芳區濂洞里' => '65000-10',
    '澎湖縣馬公市[嵵]裡里' => '10016-01',
    '臺中市北屯區廍子里' => '66000-08',
    '臺中市外埔區廍子里' => '66000-01',
    '臺中市大安區龜[壳]里' => '66000-01',
    '臺中市大肚區蔗廍里' => '66000-03',
    '臺北市萬華區糖廍里' => '63000-05',
    '臺南市安南區[塭]南里' => '67000-06',
    '臺南市安南區公[塭]里' => '67000-06',
    '臺南市官田區南廍里' => '67000-03',
    '臺南市新化區[那]拔里' => '67000-05',
    '臺南市西港區[檨]林里' => '67000-02',
    '臺南市麻豆區寮廍里' => '67000-03',
    '臺南市龍崎區石[曹]里' => '67000-11',
    '雲林縣元長鄉瓦[磘]村' => '10009-03',
    '雲林縣四湖鄉[萡]子村' => '10009-05',
    '雲林縣四湖鄉[萡]東村' => '10009-05',
    '雲林縣水林鄉[欍]埔村' => '10009-06',
    '雲林縣麥寮鄉瓦[磘]村' => '10009-05',
    '高雄市左營區廍北里' => '64000-04',
    '高雄市左營區廍南里' => '64000-04',
];
foreach ($json['features'] as $k => $f) {
    if (!empty($f['properties']['VILLNAME'])) {
        $key = $f['properties']['COUNTYNAME'] . $f['properties']['TOWNNAME'] . $f['properties']['VILLNAME'];
        if (isset($pool[$key])) {
            $json['features'][$k]['properties']['ZONE'] = $pool[$key];
        } else {
            $json['features'][$k]['properties']['ZONE'] = $map[$key];
        }
    } else {
        $key = $f['properties']['COUNTYNAME'] . $f['properties']['TOWNNAME'];
        $json['features'][$k]['properties']['ZONE'] = $pool[$key];
    }
}

$councilPath = $basePath . '/data/council/2026';
if (!file_exists($councilPath)) {
    mkdir($councilPath, 0777, true);
}

$geoPHP = new geoPHP();

$result = array();
$pool = [];
$zoneAreas = $zoneName = [];
$city = [];
$cunliFh = fopen($councilPath . '/cunli.csv', 'w');
fputcsv($cunliFh, ['zone', 'city', 'area', 'village']);
foreach ($json['features'] as $f) {
    $zoneId = $f['properties']['ZONE'];
    $city[$zoneId] = $f['properties']['COUNTYNAME'];
    if (!isset($zoneAreas[$zoneId])) {
        $zoneAreas[$zoneId] = [];
    }
    $zoneAreas[$zoneId][$f['properties']['TOWNNAME']] = true;

    if (false !== $zoneId) {
        if (!empty($f['properties']['VILLNAME'])) {
            fputcsv($cunliFh, [$zoneId, $f['properties']['COUNTYNAME'], $f['properties']['TOWNNAME'], $f['properties']['VILLNAME']]);
        }

        $zoneName[$zoneId] = $f['properties']['COUNTYNAME'] . '第' . substr($zoneId, -2) . '選區';
        if (!isset($result[$zoneId])) {
            $result[$zoneId] = $geoPHP::load(json_encode($f['geometry']), 'json');
        } else {
            $result[$zoneId] = $result[$zoneId]->union($geoPHP::load(json_encode($f['geometry']), 'json'));
        }
    }
}

ksort($zoneAreas,  SORT_NATURAL);

$fh = fopen($councilPath . '/zones.csv', 'w');
fputcsv($fh, ['city', 'zone', 'code', 'areas', 'name', 'party']);
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
