<?php

$basePath = dirname(dirname(__DIR__));
include $basePath . '/scripts/vendor/autoload.php';

$councilPath = $basePath . '/data/council/2014';

$cunli = json_decode(file_get_contents($councilPath . '/cunli.json'), true);
$map = array();
foreach ($cunli as $code => $items) {
    $item = array_shift($items);
    $map[$code] = $item['code'];
}

$json = json_decode(file_get_contents('/home/kiang/public_html/taiwan_basecode/cunli/geo/20150401.json'), true);

$geoPHP = new geoPHP();

$result = array();
$pool = [];
$zoneAreas = $zoneName = [];
$city = [];
foreach ($json['features'] as $f) {
    $zoneId = false;
    if (isset($map[$f['properties']['VILLAGE_ID']])) {
        $zoneId = $map[$f['properties']['VILLAGE_ID']];
    } else {
        switch ($f['properties']['VILLAGE_ID']) {
            case '6600600-020':
                $zoneId = '66000-06';
                break;
            case '1001802-S01': //新竹空軍基地
                $zoneId = '10018-04';
                break;
            case '6601400-S01': //清泉崗軍事基地
                $zoneId = '66000-02';
                break;
            case '0900701-S01':
            case '0900701-S02':
            case '0900701-S03':
            case '0900701-S04':
            case '0900701-S05':
            case '0900701-S06':
            case '0900701-S07':
            case '0900701-S08':
            case '0900701-S09':
                $zoneId = '09007-01';
                break;
            case '0900702-S01':
            case '0900702-S02':
            case '0900702-S03':
            case '0900702-S04':
            case '0900702-S05':
            case '0900702-S06':
            case '0900702-S07':
            case '0900702-S08':
            case '0900702-S09':
                $zoneId = '09007-02';
                break;
            case '0900703-S01':
            case '0900703-S02':
            case '0900703-S03':
            case '0900703-S04':
            case '0900703-S05':
            case '0900703-S06':
            case '0900703-S07':
            case '0900703-S08':
            case '0900703-S09':
                $zoneId = '09007-03';
                break;
            case '0900704-S01':
            case '0900704-S02':
            case '0900704-S03':
            case '0900704-S04':
            case '0900704-S05':
            case '0900704-S06':
            case '0900704-S07':
            case '0900704-S08':
            case '0900704-S09':
                $zoneId = '09007-04';
                break;
            case '1000204-S01':
            case '1000204-S02':
            case '1000204-S03':
            case '1000204-S04':
            case '1000204-S05':
            case '1000204-S06':
            case '1000204-S07':
            case '1000204-S08':
                $zoneId = '10002-02';
                break;
            case '1001602-S01':
            case '1001602-S02':
            case '1001602-S03':
            case '1001602-S04':
            case '1001602-S05':
            case '1001602-S06':
            case '1001602-S07':
            case '1001602-S08':
                $zoneId = '10016-02';
                break;
            case '1001603-S01':
                $zoneId = '10016-03';
                break;
            case '1001605-S01':
                $zoneId = '10016-05';
                break;
            case '1001701-S01':
            case '1001701-S02':
            case '1001701-S03':
            case '1001701-S04':
                $zoneId = '10017-01';
                break;
            case '1001802-S01':
                $zoneId = '10018-04';
                break;
            case '6400200-S01':
                $zoneId = '64000-06';
                break;
            case '6400900-S01':
                $zoneId = '64000-10';
                break;
            case '6401000-S01':
                $zoneId = '64000-06';
                break;
            case '6401100-S01':
                $zoneId = '64000-10';
                break;
            case '6401900-S01':
                $zoneId = '64000-03';
                break;
            case '6601200-S01':
                $zoneId = '66000-02';
                break;
            case '6601400-S01':
            case '6601400-S02':
                $zoneId = '66000-02';
                break;
            case '6602500-S01':
                $zoneId = '66000-03';
                break;
        }
    }
    switch ($f['properties']['C_Name']) {
        case '新北市':
            switch ($f['properties']['T_Name']) {
                case '三芝區':
                case '石門區':
                case '淡水區':
                case '八里區':
                    $zoneId = '65000-01';
                    break;
                case '林口區':
                case '五股區':
                case '泰山區':
                    $zoneId = '65000-02';
                    break;
                case '新莊區':
                    $zoneId = '65000-03';
                    break;
                case '蘆洲區':
                case '三重區':
                    $zoneId = '65000-04';
                    break;
                case '板橋區':
                    $zoneId = '65000-05';
                    break;
                case '中和區':
                    $zoneId = '65000-06';
                    break;
                case '永和區':
                    $zoneId = '65000-07';
                    break;
                case '樹林區':
                case '鶯歌區':
                case '土城區':
                case '三峽區':
                    $zoneId = '65000-08';
                    break;
                case '新店區':
                case '深坑區':
                case '石碇區':
                case '坪林區':
                case '烏來區':
                    $zoneId = '65000-09';
                    break;
                case '平溪區':
                case '瑞芳區':
                case '雙溪區':
                case '貢寮區':
                    $zoneId = '65000-10';
                    break;
                case '汐止區':
                case '金山區':
                case '萬里區':
                    $zoneId = '65000-11';
                    break;
            }
            break;
        case '臺南市':
            switch ($f['properties']['T_Name']) {
                case '新營區':
                case '鹽水區':
                case '柳營區':
                case '後壁區':
                case '白河區':
                case '東山區':
                    $zoneId = '67000-01';
                    break;
                case '佳里區':
                case '七股區':
                case '西港區':
                case '北門區':
                case '學甲區':
                case '將軍區':
                    $zoneId = '67000-02';
                    break;
                case '麻豆區':
                case '六甲區':
                case '下營區':
                case '官田區':
                case '大內區':
                    $zoneId = '67000-03';
                    break;
                case '玉井區':
                case '南化區':
                case '楠西區':
                case '左鎮區':
                    $zoneId = '67000-04';
                    break;
                case '善化區':
                case '安定區':
                case '山上區':
                case '新化區':
                case '新市區':
                    $zoneId = '67000-05';
                    break;
                case '安南區':
                    $zoneId = '67000-06';
                    break;
                case '永康區':
                    $zoneId = '67000-07';
                    break;
                case '北區':
                case '中西區':
                    $zoneId = '67000-08';
                    break;
                case '安平區':
                case '南區':
                    $zoneId = '67000-09';
                    break;
                case '東區':
                    $zoneId = '67000-10';
                    break;
                case '仁德區':
                case '歸仁區':
                case '關廟區':
                case '龍崎區':
                    $zoneId = '67000-11';
                    break;
            }
            break;
    }
    $city[$zoneId] = $f['properties']['C_Name'];
    if (!isset($zoneAreas[$zoneId])) {
        $zoneAreas[$zoneId] = [];
    }
    $zoneAreas[$zoneId][$f['properties']['T_Name']] = true;

    if (false !== $zoneId) {
        $zoneName[$zoneId] = $f['properties']['C_Name'] . '第' . substr($zoneId, -2) . '選區';
        if (!isset($result[$zoneId])) {
            $result[$zoneId] = $geoPHP::load(json_encode($f['geometry']), 'json');
        } else {
            $result[$zoneId] = $result[$zoneId]->union($geoPHP::load(json_encode($f['geometry']), 'json'));
        }
    }
}
$councilPath = $basePath . '/data/council/2022';
if (!file_exists($councilPath)) {
    mkdir($councilPath, 0777, true);
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
