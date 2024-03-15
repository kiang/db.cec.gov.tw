<?php
$rootPath = dirname(dirname(__DIR__));
$data2024 = json_decode(file_get_contents($rootPath . '/data/ly/2024_party_cunli.json'), true);
$basePath = $rootPath . '/voteData/2022-111年地方公職人員選舉';

/*
 * elbase.csv 行政區基本資料
  elcand.csv 候選人基本資料
  elpaty.csv 政黨基本資料
  elprof.csv 選舉概況檔
  elctks.csv 候選人得票檔
*/

$parties = array();
$fh = fopen($basePath . '/R1/elpaty.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $parties[$line[0]] = $line[1];
}

$header = array('省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '投開票所', '候選人號次', '得票數', '得票率', '當選註記');
$fh = fopen($basePath . '/R1/elctks.csv', 'r');
$voteCounts = array();
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $data = array_combine($header, $line);
    if ($data['投開票所'] != '0000' || $data['村里別'] != '0000') {
        continue;
    }
    $voteCounts["{$data['省市別']}{$data['縣市別']}{$data['鄉鎮市區']}{$data['選區別']}{$data['候選人號次']}"] = intval($data['得票數']);
}

$header = array('省市', '縣市', '選區', '鄉鎮市區', '村里', '名稱');
$cunliNames = $cunli2zone = array();
$fh = fopen($basePath . '/R1/elbase.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $data = array_combine($header, $line);
    if ($data['選區'] !== '00' && $data['村里'] !== '0000') {
        $cunli2zone["{$data['省市']}{$data['縣市']}{$data['鄉鎮市區']}{$data['村里']}"] = "{$data['省市']}{$data['縣市']}{$data['鄉鎮市區']}{$data['選區']}";
        $cunliNames["{$data['省市']}{$data['縣市']}{$data['鄉鎮市區']}{$data['村里']}"] = explode('、', $data['名稱']);
    } elseif ($data['縣市'] !== '000') {
        if ($data['鄉鎮市區'] !== '000') {
            $key = "{$data['省市']}{$data['縣市']}{$data['鄉鎮市區']}";
            $cunliNames[$key] = $cunliNames["{$data['省市']}{$data['縣市']}"] . $data['名稱'];
            $pos = strrpos($cunliNames[$key], '第');
            if (false !== $pos) {
                $cunliNames[$key] = substr($cunliNames[$key], 0, $pos);
            }
            $pos = strrpos($cunliNames[$key], '選舉區');
            if (false !== $pos) {
                $cunliNames[$key] = substr($cunliNames[$key], 0, $pos);
            }
        } else {
            $cunliNames["{$data['省市']}{$data['縣市']}"] = $data['名稱'];
        }
    }
}

$missing = array(
    '100080900A01' => '1000809001',
);
$zoneVotes = array();
$skipCounties = [
    '63000' => '臺北市',
    '64000' => '高雄市',
    '65000' => '新北市',
    '66000' => '臺中市',
    '67000' => '臺南市',
    '68000' => '桃園市',
    '10017' => '基隆市',
    '10018' => '新竹市',
    '10020' => '嘉義市',
];
foreach ($data2024 as $cunliCode => $cunliVote) {
    $countyKey = substr($cunliCode, 0, 5);
    if (isset($skipCounties[$countyKey])) {
        continue;
    }
    $zone = false;
    if (isset($cunli2zone[$cunliCode])) {
        $zone = $cunli2zone[$cunliCode];
    } elseif (isset($missing[$cunliCode])) {
        $zone = $missing[$cunliCode];
    }
    if (false !== $zone) {
        if (!isset($zoneVotes[$zone])) {
            $cityKey = substr($cunliCode, 0, 8);
            $zoneVotes[$zone] = array(
                'name' => $cunliNames[$cityKey] . substr($zone, -2),
                'total' => 0,
                'countCand' => 0,
                'voteBase' => 0,
                'votes' => array(),
                'match' => array(),
                '2022' => array(
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
$fh = fopen($basePath . '/R1/elcand.csv', 'r');
fgetcsv($fh, 2048);
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, '\'');
    }
    $data = array_combine($header, $line);
    $winnerMark = trim($data['當選註記']);
    $zone = "{$data['省市別']}{$data['縣市別']}{$data['鄉鎮市區']}{$data['選區別']}";
    if (!empty($winnerMark)) {
        ++$zoneVotes[$zone]['countCand'];
    }
    $voteCountsKey = "{$data['省市別']}{$data['縣市別']}{$data['鄉鎮市區']}{$data['選區別']}{$data['號次']}";
    $zoneVotes[$zone]['2022']['detail'][$voteCountsKey] = array(
        'name' => $data['名字'],
        'party' => $parties[$data['政黨代號']],
        'voteCount' => $voteCounts[$voteCountsKey],
        'elected' => !empty($winnerMark) ? true : false,
    );
    if (!isset($zoneVotes[$zone]['2022']['party'][$parties[$data['政黨代號']]])) {
        $zoneVotes[$zone]['2022']['party'][$parties[$data['政黨代號']]] = 0;
    }
    $zoneVotes[$zone]['2022']['party'][$parties[$data['政黨代號']]] += $voteCounts[$voteCountsKey];
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
    usort($zoneVotes[$zone]['2022']['detail'], 'cmp');
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

file_put_contents($rootPath . '/data/town_council/2022/2022_match_2024.json', json_encode($zoneVotes,  JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// foreach ($result as $key => $val) {
//     echo "\n\n" . $key . ': ' . implode(',', array_keys($val));
// }
