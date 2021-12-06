<?php

/*
 * elbase.csv 行政區基本資料
  elcand.csv 候選人基本資料
  elpaty.csv 政黨基本資料
  elprof.csv 選舉概況檔
  elctks.csv 候選人得票檔
 */
$basePath = dirname(dirname(__DIR__));
$councilPath = $basePath . '/data/council/2014';
if(!file_exists($councilPath)) {
    mkdir($councilPath, 0777, true);
}

$header = array('政黨代號', '政黨名稱');
$fh = fopen($basePath . '/voteData/2014-103年地方公職人員選舉/直轄市區域議員/elpaty.csv', 'r');
$party = array();
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line AS $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $line = array_combine($header, $line);
    $party[$line['政黨代號']] = $line['政黨名稱'];
}

$vcode = include $councilPath . '/vcode.php';

$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '號次', '名字', '政黨代號', '性別', '出生日期', '年齡', '出生地', '學歷', '現任', '當選註記', '副手');
$fh = fopen($basePath . '/voteData/2014-103年地方公職人員選舉/直轄市區域議員/elcand.csv', 'r');
$ref = array();
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line AS $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $line = array_combine($header, $line);
    $cityCode = "{$line['省市別']}{$line['縣市別']}-{$line['選區別']}";

    if (!isset($ref[$cityCode])) {
        $ref[$cityCode] = array();
    }
    $ref[$cityCode][$line['號次']] = array(
        'code' => $cityCode,
        'name' => $line['名字'],
        'party' => $party[$line['政黨代號']],
        'current' => $line['現任'],
        'win' => $line['當選註記'],
    );
}

$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '投開票所', '候選人號次', '得票數', '得票率', '當選註記');
$fh = fopen($basePath . '/voteData/2014-103年地方公職人員選舉/直轄市區域議員/elctks.csv', 'r');
$result = array();
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line AS $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $line = array_combine($header, $line);
    if ($line['投開票所'] != 0) {
        continue;
    }

    $cityCode = "{$line['省市別']}{$line['縣市別']}-{$line['選區別']}";
    $code = "{$line['省市別']}{$line['縣市別']}{$line['鄉鎮市區']}{$line['村里別']}";
    if (isset($vcode[$code])) {
        if (is_string($vcode[$code])) {
            $cunliCode = $vcode[$code];

            if (!isset($result[$cunliCode])) {
                $result[$cunliCode] = array();
            }

            if (!isset($result[$cunliCode][$line['候選人號次']])) {
                $result[$cunliCode][$line['候選人號次']] = $ref[$cityCode][$line['候選人號次']];
            }

            if (!isset($result[$cunliCode][$line['候選人號次']]['vote'])) {
                $result[$cunliCode][$line['候選人號次']]['vote'] = 0;
            }
            $result[$cunliCode][$line['候選人號次']]['vote'] += $line['得票數'];
        } else {
            $cunliCode = array_shift($vcode[$code]);
            if (!isset($result[$cunliCode])) {
                $result[$cunliCode] = array();
            }

            if (!isset($result[$cunliCode][$line['候選人號次']])) {
                $result[$cunliCode][$line['候選人號次']] = $ref[$cityCode][$line['候選人號次']];
            }

            if (!isset($result[$cunliCode][$line['候選人號次']]['vote'])) {
                $result[$cunliCode][$line['候選人號次']]['vote'] = 0;
            }
            $result[$cunliCode][$line['候選人號次']]['vote'] += $line['得票數'];
        }
    }
}

$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '號次', '名字', '政黨代號', '性別', '出生日期', '年齡', '出生地', '學歷', '現任', '當選註記', '副手');
$fh = fopen($basePath . '/voteData/2014-103年地方公職人員選舉/縣市區域議員/elcand.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line AS $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $line = array_combine($header, $line);
    $cityCode = "{$line['省市別']}{$line['縣市別']}-{$line['選區別']}";

    if (!isset($ref[$cityCode])) {
        $ref[$cityCode] = array();
    }
    $ref[$cityCode][$line['號次']] = array(
        'code' => $cityCode,
        'name' => $line['名字'],
        'party' => $party[$line['政黨代號']],
        'current' => $line['現任'],
        'win' => $line['當選註記'],
    );
}

$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '投開票所', '候選人號次', '得票數', '得票率', '當選註記');
$fh = fopen($basePath . '/voteData/2014-103年地方公職人員選舉/縣市區域議員/elctks.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line AS $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $line = array_combine($header, $line);
    if ($line['投開票所'] != 0) {
        continue;
    }

    $cityCode = "{$line['省市別']}{$line['縣市別']}-{$line['選區別']}";
    $code = "{$line['省市別']}{$line['縣市別']}{$line['鄉鎮市區']}{$line['村里別']}";
    if (isset($vcode[$code])) {
        if (is_string($vcode[$code])) {
            $cunliCode = $vcode[$code];

            if (!isset($result[$cunliCode])) {
                $result[$cunliCode] = array();
            }

            if (!isset($result[$cunliCode][$line['候選人號次']])) {
                $result[$cunliCode][$line['候選人號次']] = $ref[$cityCode][$line['候選人號次']];
            }

            if (!isset($result[$cunliCode][$line['候選人號次']]['vote'])) {
                $result[$cunliCode][$line['候選人號次']]['vote'] = 0;
            }
            $result[$cunliCode][$line['候選人號次']]['vote'] += $line['得票數'];
        } else {
            $cunliCode = array_shift($vcode[$code]);
            if (!isset($result[$cunliCode])) {
                $result[$cunliCode] = array();
            }

            if (!isset($result[$cunliCode][$line['候選人號次']])) {
                $result[$cunliCode][$line['候選人號次']] = $ref[$cityCode][$line['候選人號次']];
            }

            if (!isset($result[$cunliCode][$line['候選人號次']]['vote'])) {
                $result[$cunliCode][$line['候選人號次']]['vote'] = 0;
            }
            $result[$cunliCode][$line['候選人號次']]['vote'] += $line['得票數'];
        }
    }
}

file_put_contents($councilPath . '/cunli.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
