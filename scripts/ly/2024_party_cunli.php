<?php
$rootPath = dirname(dirname(__DIR__));
$basePath = $rootPath . '/voteData/2024總統立委/不分區政黨/';

$header = array('省市', '縣市', '選區', '鄉鎮市區', '村里', '名稱');
$fh = fopen($basePath . '/elbase.csv', 'r');
$cunliNames = array();
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($header, $line);
    if ($data['選區'] !== '00') {
        continue;
    }
    if ($data['鄉鎮市區'] === '000') {
        $cunliNames[$data['省市'] . $data['縣市']] = $data['名稱'];
    } elseif ($data['村里'] === '0000') {
        $cunliNames[$data['省市'] . $data['縣市'] . $data['鄉鎮市區']] = $cunliNames[$data['省市'] . $data['縣市']] . $data['名稱'];
    } else {
        $cunliNames[$data['省市'] . $data['縣市'] . $data['鄉鎮市區'] . $data['村里']] = $cunliNames[$data['省市'] . $data['縣市'] . $data['鄉鎮市區']] . $data['名稱'];
    }
}

$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '號次', '名字', '政黨代號', '性別', '出生日期', '年齡', '出生地', '學歷', '現任', '當選註記', '副手');
$fh = fopen($basePath . '/elcand.csv', 'r');
$cand = array();
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($header, $line);
    $cand[$data['號次']] = $data['名字'];
}
$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '投開票所', '號次', '得票數', '得票率', '當選註記');
$fh = fopen($basePath . '/elctks.csv', 'r');
$result = array();
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($header, $line);
    if ($data['投開票所'] != 0) {
        $cunliCode = "{$data['省市別']}{$data['縣市別']}{$data['鄉鎮市區']}{$data['村里別']}";
        if (!isset($result[$cunliCode])) {
            $result[$cunliCode] = array(
                'name' => $cunliNames[$cunliCode],
                'total' => 0,
                'votes' => array(),
            );
        }
        if (!isset($result[$cunliCode]['votes'][$cand[$data['號次']]])) {
            $result[$cunliCode]['votes'][$cand[$data['號次']]] = 0;
        }
        $result[$cunliCode]['total'] += $data['得票數'];
        $result[$cunliCode]['votes'][$cand[$data['號次']]] += $data['得票數'];
    }
}
file_put_contents($rootPath . '/data/ly/2024_party_cunli.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$basePath = $rootPath . '/voteData/2024總統立委/總統/';

$header = array('省市', '縣市', '選區', '鄉鎮市區', '村里', '名稱');
$fh = fopen($basePath . '/elbase.csv', 'r');
$cunliNames = array();
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($header, $line);
    if ($data['選區'] !== '00') {
        continue;
    }
    if ($data['鄉鎮市區'] === '000') {
        $cunliNames[$data['省市'] . $data['縣市']] = $data['名稱'];
    } elseif ($data['村里'] === '0000') {
        $cunliNames[$data['省市'] . $data['縣市'] . $data['鄉鎮市區']] = $cunliNames[$data['省市'] . $data['縣市']] . $data['名稱'];
    } else {
        $cunliNames[$data['省市'] . $data['縣市'] . $data['鄉鎮市區'] . $data['村里']] = $cunliNames[$data['省市'] . $data['縣市'] . $data['鄉鎮市區']] . $data['名稱'];
    }
}

$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '號次', '名字', '政黨代號', '性別', '出生日期', '年齡', '出生地', '學歷', '現任', '當選註記', '副手');
$fh = fopen($basePath . '/elcand.csv', 'r');
$cand = array();
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($header, $line);
    if (!isset($cand[$data['號次']])) {
        $cand[$data['號次']] = $data['名字'];
    }
}
$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '投開票所', '號次', '得票數', '得票率', '當選註記');
$fh = fopen($basePath . '/elctks.csv', 'r');
$result = array();
while ($line = fgetcsv($fh, 2048)) {
    $data = array_combine($header, $line);
    if ($data['投開票所'] != 0) {
        $cunliCode = "{$data['省市別']}{$data['縣市別']}{$data['鄉鎮市區']}{$data['村里別']}";
        if (!isset($result[$cunliCode])) {
            $result[$cunliCode] = array(
                'name' => $cunliNames[$cunliCode],
                'total' => 0,
                'votes' => array(),
            );
        }
        if (!isset($result[$cunliCode]['votes'][$cand[$data['號次']]])) {
            $result[$cunliCode]['votes'][$cand[$data['號次']]] = 0;
        }
        $result[$cunliCode]['total'] += $data['得票數'];
        $result[$cunliCode]['votes'][$cand[$data['號次']]] += $data['得票數'];
    }
}
file_put_contents($rootPath . '/data/ly/2024_party_cunli_president.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
