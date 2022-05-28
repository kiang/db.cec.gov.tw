<?php
$data2020 = json_decode(file_get_contents(dirname(__DIR__) . '/data/2020_party_cunli.json'), true);
$basePath = dirname(__DIR__) . '/voteData/2018-107年地方公職人員選舉';

/*
 * elbase.csv 行政區基本資料
  elcand.csv 候選人基本資料
  elpaty.csv 政黨基本資料
  elprof.csv 選舉概況檔
  elctks.csv 候選人得票檔
*/

$parties = array();
$fh = fopen($basePath . '/直轄市區域議員/elpaty.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $parties[$line[0]] = $line[1];
}
$fh = fopen($basePath . '/縣市區域議員/elctks.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $parties[$line[0]] = $line[1];
}

$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '投開票所', '候選人號次', '得票數', '得票率', '當選註記');
$fh = fopen($basePath . '/直轄市區域議員/elctks.csv', 'r');
$voteCounts = array();
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $data = array_combine($header, $line);
    if ($data['投開票所'] != 0 || $data['村里別'] != '0000' || $data['鄉鎮市區'] != '000') {
        continue;
    }
    $voteCounts["{$data['省市別']}{$data['縣市別']}{$data['選區別']}{$data['候選人號次']}"] = intval($data['得票數']);
}
$fh = fopen($basePath . '/縣市區域議員/elctks.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $data = array_combine($header, $line);
    if ($data['投開票所'] != 0 || $data['村里別'] != '0000' || $data['鄉鎮市區'] != '000') {
        continue;
    }
    $voteCounts["{$data['省市別']}{$data['縣市別']}{$data['選區別']}{$data['候選人號次']}"] = intval($data['得票數']);
}

$header = array('省市', '縣市', '選區', '鄉鎮市區', '村里', '名稱');
$cunliNames = array();
$cunli2zone = array();
$fh = fopen($basePath . '/直轄市區域議員/elbase.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $data = array_combine($header, $line);
    if ($data['選區'] !== '00' && $data['村里'] !== '0000') {
        $cunli2zone["{$data['省市']}{$data['縣市']}{$data['鄉鎮市區']}{$data['村里']}"] = "{$data['省市']}{$data['縣市']}{$data['選區']}";
        $cunliNames["{$data['省市']}{$data['縣市']}{$data['鄉鎮市區']}{$data['村里']}"] = $data['名稱'];
    }
}

$fh = fopen($basePath . '/縣市區域議員/elbase.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $data = array_combine($header, $line);
    if ($data['選區'] !== '00' && $data['村里'] !== '0000') {
        $cunli2zone["{$data['省市']}{$data['縣市']}{$data['鄉鎮市區']}{$data['村里']}"] = "{$data['省市']}{$data['縣市']}{$data['選區']}";
        $cunliNames["{$data['省市']}{$data['縣市']}{$data['鄉鎮市區']}{$data['村里']}"] = $data['名稱'];
    }
}

$missing = array(
    '10005070A005' => '1000501',
    '10008090A004' => '1000803',
    '10013010A001' => '1001301',
    '10013010A002' => '1001301',
    '10013010A003' => '1001301',
    '10013010A008' => '1001301',
    '10013010A010' => '1001301',
    '10013010A013' => '1001301',
    '10013010A015' => '1001301',
    '10013010A018' => '1001301',
    '10013010A047' => '1001301',
    '10013010A057' => '1001301',
    '10018010A006' => '1001801',
    '10018010A008' => '1001802',
    '10018020A001' => '1001803',
    '10018020A006' => '1001803',
    '10018020A034' => '1001803',
    '09007010A002' => '0900701',
    '09007010A004' => '0900701',
    '09007010A005' => '0900701',
    '09007020A001' => '0900702',
    '09007020A003' => '0900702',
    '09007030A001' => '0900703',
    '09007030A004' => '0900703',
    '09007040A001' => '0900704',
);
$zoneVotes = array();
foreach ($data2020 as $cunliCode => $cunliVote) {
    $zone = false;
    if (isset($cunli2zone[$cunliCode])) {
        $zone = $cunli2zone[$cunliCode];
    } elseif (isset($missing[$cunliCode])) {
        $zone = $missing[$cunliCode];
    }
    if (false !== $zone) {
        if (!isset($zoneVotes[$zone])) {
            $zoneVotes[$zone] = array(
                'name' => mb_substr($cunliVote['name'], 0, 3, 'utf-8') . substr($zone, -2),
                'total' => 0,
                'countCand' => 0,
                'voteBase' => 0,
                'votes' => array(),
                'match' => array(),
                '2018' => array(
                    'party' => array(),
                    'detail' => array(),
                ),
            );
        }
        $zoneVotes[$zone]['total'] += $cunliVote['total'];
        foreach ($cunliVote['votes'] as $k => $v) {
            if (!isset($zoneVotes[$zone]['votes'][$k])) {
                $zoneVotes[$zone]['votes'][$k] = 0;
            }
            $zoneVotes[$zone]['votes'][$k] += $v;
        }
    }
}
ksort($zoneVotes);

$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '號次', '名字', '政黨代號', '性別', '出生日期', '年齡', '出生地', '學歷', '現任', '當選註記', '副手');
$fh = fopen($basePath . '/直轄市區域議員/elcand.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $data = array_combine($header, $line);
    $winnerMark = trim($data['當選註記']);
    $zone = "{$data['省市別']}{$data['縣市別']}{$data['選區別']}";
    if (!empty($winnerMark)) {
        ++$zoneVotes[$zone]['countCand'];
    }
    $voteCountsKey = "{$data['省市別']}{$data['縣市別']}{$data['選區別']}{$data['號次']}";
    $zoneVotes[$zone]['2018']['detail'][$voteCountsKey] = array(
        'name' => $data['名字'],
        'party' => $parties[$data['政黨代號']],
        'voteCount' => $voteCounts[$voteCountsKey],
        'elected' => !empty($winnerMark) ? true : false,
    );
    if (!isset($zoneVotes[$zone]['2018']['party'][$parties[$data['政黨代號']]])) {
        $zoneVotes[$zone]['2018']['party'][$parties[$data['政黨代號']]] = 0;
    }
    $zoneVotes[$zone]['2018']['party'][$parties[$data['政黨代號']]] += $voteCounts[$voteCountsKey];
}
$fh = fopen($basePath . '/縣市區域議員/elcand.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $data = array_combine($header, $line);
    $winnerMark = trim($data['當選註記']);
    $zone = "{$data['省市別']}{$data['縣市別']}{$data['選區別']}";
    if (!empty($winnerMark)) {
        ++$zoneVotes[$zone]['countCand'];
    }
    $voteCountsKey = "{$data['省市別']}{$data['縣市別']}{$data['選區別']}{$data['號次']}";
    $zoneVotes[$zone]['2018']['detail'][$voteCountsKey] = array(
        'name' => $data['名字'],
        'party' => $parties[$data['政黨代號']],
        'voteCount' => $voteCounts[$voteCountsKey],
        'elected' => !empty($winnerMark) ? true : false,
    );
    if (!isset($zoneVotes[$zone]['2018']['party'][$parties[$data['政黨代號']]])) {
        $zoneVotes[$zone]['2018']['party'][$parties[$data['政黨代號']]] = 0;
    }
    $zoneVotes[$zone]['2018']['party'][$parties[$data['政黨代號']]] += $voteCounts[$voteCountsKey];
}

function cmp($a, $b)
{
    if ($a['voteCount'] == $b['voteCount']) {
        return 0;
    }
    return ($a['voteCount'] > $b['voteCount']) ? -1 : 1;
}

$result = array();
foreach ($zoneVotes as $zone => $meta) {
    $zoneVotes[$zone]['voteBase'] = ceil($meta['total'] / $meta['countCand']);
    usort($zoneVotes[$zone]['2018']['detail'], 'cmp');
    foreach ($meta['votes'] as $party => $vote) {
        if ($vote > $zoneVotes[$zone]['voteBase']) {
            $zoneVotes[$zone]['match'][$party] = floor($vote / $zoneVotes[$zone]['voteBase']);
            if (!isset($result[$party])) {
                $result[$party] = array();
            }
            $result[$party][$meta['name']] = $zoneVotes[$zone]['match'][$party];
        }
    }
}

file_put_contents(dirname(__DIR__) . '/data/2018_match_2020.json', json_encode($zoneVotes,  JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// foreach ($result as $key => $val) {
//     echo "\n\n" . $key . ': ' . implode(',', array_keys($val));
// }
