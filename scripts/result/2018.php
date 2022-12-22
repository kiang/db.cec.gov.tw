<?php
$basePath = dirname(dirname(__DIR__));
$voteDataPath = $basePath . '/voteData/2018-107年地方公職人員選舉';
$dataPath = $basePath . '/data/2018';
if (!file_exists($dataPath)) {
    mkdir($dataPath, 0777, true);
}
$candidateFiles = [
    $voteDataPath . '/直轄市市長/elcand.csv' => '直轄市長',
    $voteDataPath . '/直轄市區域議員/elcand.csv' => '直轄市議員',
    $voteDataPath . '/直轄市平原議員/elcand.csv' => '直轄市議員',
    $voteDataPath . '/直轄市山原議員/elcand.csv' => '直轄市議員',
    $voteDataPath . '/縣市區域議員/elcand.csv' => '縣市議員',
    $voteDataPath . '/縣市平原議員/elcand.csv' => '縣市議員',
    $voteDataPath . '/縣市山原議員/elcand.csv' => '縣市議員',
    $voteDataPath . '/縣市市長/elcand.csv' => '縣市長',
    $voteDataPath . '/縣市鄉鎮市長/elcand.csv' => '鄉鎮市長',
    $voteDataPath . '/直轄市區長/elcand.csv' => '直轄市山地原住民區長',
    $voteDataPath . '/縣市鄉鎮市民代表/elcand.csv' => '鄉鎮市民代表',
    $voteDataPath . '/縣市鄉鎮市民平原代表/elcand.csv' => '鄉鎮市民代表',
    $voteDataPath . '/直轄市區民代表/elcand.csv' => '直轄市山地原住民區民代表',
    $voteDataPath . '/縣市村里長/elcand.csv' => '村里長',
    $voteDataPath . '/直轄市村里長/elcand.csv' => '村里長',
];

foreach ($candidateFiles as $candidateFile => $election) {
    $electionFile = $dataPath . '/' . $election . '.csv';
    if (file_exists($electionFile)) {
        unlink($electionFile);
    }
}
/*
Array
(
    [prv_code] => 63
    [city_code] => 000
    [area_code] => 00
    [dept_code] => 000
    [li_code] => 0000
    [cand_no] => 1
    [cand_name] => 張家豪
    [party_code] => 306
    [cand_sex] => 1
    [cand_birthday] => 0740111
    [cand_age] => 37
    [cand_bornplace] => 臺灣省
    [cand_edu] => 大學
    [is_current] => N
    [is_victor] =>  
    [is_vice] =>  
)
*/
$parties = [];
foreach ($candidateFiles as $candidateFile => $election) {
    $electionFile = $dataPath . '/' . $election . '.csv';
    if (!file_exists($electionFile)) {
        $oFh = fopen($electionFile, 'w');
        fputcsv($oFh, [
            'area', 'cand_no', 'cand_name', 'party', 'cand_sex', 'cand_birthday', 'cand_age', 'cand_bornplace', 'cand_edu',
            'is_current', 'is_victor', 'ticket_num', 'prv_code', 'city_code', 'area_code', 'dept_code', 'li_code',
        ]);
    } else {
        $oFh = fopen($electionFile, 'a');
    }

    $p = pathinfo($candidateFile);
    $fh = fopen($p['dirname'] . '/elbase.csv', 'r');
    $area = [];
    while ($line = fgetcsv($fh, 2048)) {
        foreach ($line as $k => $v) {
            $line[$k] = str_replace('\'', '', $v);
        }
        $code = $line[0] . $line[1] . $line[2] . $line[3] . $line[4];
        $area[$code] = $line[5];
    }

    $fh = fopen($p['dirname'] . '/elctks.csv', 'r');
    $votes = [];
    while ($line = fgetcsv($fh, 2048)) {
        foreach ($line as $k => $v) {
            $line[$k] = str_replace('\'', '', $v);
        }
        $code = $line[0] . $line[1] . $line[2] . $line[3] . $line[4] . $line[5] . $line[6];
        $votes[$code] = $line[7];
    }

    $fh = fopen($candidateFile, 'r');
    $head = [
        'prv_code', 'city_code', 'area_code', 'dept_code', 'li_code',
        'cand_no', 'cand_name', 'party_code', 'cand_sex', 'cand_birthday', 'cand_age', 'cand_bornplace', 'cand_edu',
        'is_current', 'is_victor', 'is_vice'
    ];
    while ($line = fgetcsv($fh, 2048)) {
        foreach ($line as $k => $v) {
            $line[$k] = str_replace('\'', '', $v);
        }
        $data = array_combine($head, $line);
        $code = $line[0] . $line[1] . $line[2] . $line[3] . $line[4];
        $vcode = $line[0] . $line[1] . $line[2] . $line[3] . $line[4] . '0' . $data['cand_no'];
        switch ($election) {
            case '直轄市山地原住民區民代表':
            case '直轄市山地原住民區長':
            case '鄉鎮市長':
            case '鄉鎮市民代表':
                $areaCode = $line[0] . $line[1] . '000000000';
                $data['area'] = $area[$areaCode];
                $data['area'] .= $area[$code];
                break;
            case '村里長':
                $areaCode = $line[0] . $line[1] . '000000000';
                $data['area'] = $area[$areaCode];
                $areaCode = $line[0] . $line[1] . '00' . $line[3] . '0000';
                $data['area'] .= $area[$areaCode];
                $data['area'] .= $area[$code];
                break;
            default:
                $data['area'] = $area[$code];
        }
        if (!isset($parties[$data['party_code']])) {
            $pFh = fopen($p['dirname'] . '/elpaty.csv', 'r');
            while ($pLine = fgetcsv($pFh, 2048)) {
                foreach ($pLine as $k => $v) {
                    $pLine[$k] = str_replace('\'', '', $v);
                }
                $parties[$pLine[0]] = $pLine[1];
            }
            fclose($pFh);
        }
        if ('*' === $data['is_victor']) {
            $data['is_victor'] = 'Y';
        } else {
            $data['is_victor'] = 'N';
        }
        if ('1' == $data['cand_sex']) {
            $data['cand_sex'] = 'm';
        } else {
            $data['cand_sex'] = 'f';
        }

        fputcsv($oFh, [
            $data['area'], $data['cand_no'], $data['cand_name'], $parties[$data['party_code']], $data['cand_sex'], $data['cand_birthday'],
            $data['cand_age'], $data['cand_bornplace'], $data['cand_edu'], $data['is_current'], $data['is_victor'], $votes[$vcode],
            $data['prv_code'], $data['city_code'], $data['area_code'], $data['dept_code'], $data['li_code']
        ]);
    }
}
