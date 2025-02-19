<?php
$rootPath = dirname(dirname(__DIR__));
$basePath = $rootPath . '/voteData/2024總統立委/區域立委/';

$fh = fopen($basePath . '/elpaty.csv', 'r');
$partyNames = array();
while ($line = fgetcsv($fh, 2048)) {
    $partyNames[$line[0]] = $line[1];
}

$header = array('省市', '縣市', '選區', '鄉鎮市區', '村里', '名稱');
$fh = fopen($basePath . '/elbese.csv', 'r');
$cunliNames = array();
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($header, $line);
    if($data['村里'] != '0000') {
        continue;
    }
    if ($data['選區'] === '00') {
        $cunliNames[$data['省市'] . $data['縣市']] = $data['名稱'];
    } elseif($data['鄉鎮市區'] === '000') {
        $cunliNames[$data['省市'] . $data['縣市'] . $data['選區']] = $data['名稱'];
    } else {
        $cunliNames[$data['省市'] . $data['縣市'] . $data['鄉鎮市區']] = $cunliNames[$data['省市'] . $data['縣市']] . $data['名稱'];
    }
}
rewind($fh);
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($header, $line);
    if($data['村里'] == '0000') {
        continue;
    }

    $cunliNames[$data['省市'] . $data['縣市'] . $data['鄉鎮市區'] . $data['村里']] = $cunliNames[$data['省市'] . $data['縣市'] . $data['鄉鎮市區']] . $data['名稱'];
}

$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '號次', '名字', '政黨代號', '性別', '出生日期', '年齡', '出生地', '學歷', '現任', '當選註記', '副手');
$fh = fopen($basePath . '/elcand.csv', 'r');
$cand = $party = [];
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($header, $line);
    $candKey = $data['省市別'] . $data['縣市別'] . $data['選區別'] . $data['號次'];
    $cand[$candKey] = $data['名字'];
    $party[$candKey] = $partyNames[$data['政黨代號']];
}
$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '投開票所', '號次', '得票數', '得票率', '當選註記');
$fh = fopen($basePath . '/elctks.csv', 'r');
$result = array();
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($header, $line);
    if ($data['投開票所'] != 0) {
        $cunliCode = "{$data['省市別']}{$data['縣市別']}{$data['鄉鎮市區']}{$data['村里別']}";
        $zoneCode = "{$data['省市別']}{$data['縣市別']}{$data['選區別']}";
        if (!isset($result[$cunliCode])) {
            $result[$cunliCode] = array(
                'name' => $cunliNames[$cunliCode],
                'zone' => $cunliNames[$zoneCode],
                'zoneCode' => $zoneCode,
                'total' => 0,
                'votes' => array(),
            );
        }
        $candKey = $data['省市別'] . $data['縣市別'] . $data['選區別'] . $data['號次'];
        if (!isset($result[$cunliCode]['votes'][$cand[$candKey]])) {
            $result[$cunliCode]['votes'][$cand[$candKey]] = [
                'no' => $data['號次'],
                'name' => $cand[$candKey],
                'party' => $party[$candKey],
                'votes' => 0,
            ];
        }
        $result[$cunliCode]['total'] += $data['得票數'];
        $result[$cunliCode]['votes'][$cand[$candKey]]['votes'] += $data['得票數'];
    }
}
file_put_contents($rootPath . '/data/ly/2024_zone_cunli.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));