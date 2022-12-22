<?php
$basePath = dirname(dirname(__DIR__));
$voteDataPath = $basePath . '/voteData/2022-111年地方公職人員選舉';
$dataPath = $basePath . '/data/2022';
if (!file_exists($dataPath)) {
    mkdir($dataPath, 0777, true);
}
$candidateFiles = [
    $voteDataPath . '/C1/prv/elcand.csv' => '直轄市長',
    $voteDataPath . '/T1/prv/elcand.csv' => '直轄市議員',
    $voteDataPath . '/T2/prv/elcand.csv' => '直轄市議員',
    $voteDataPath . '/T3/prv/elcand.csv' => '直轄市議員',
    $voteDataPath . '/T1/city/elcand.csv' => '縣市議員',
    $voteDataPath . '/T2/city/elcand.csv' => '縣市議員',
    $voteDataPath . '/T3/city/elcand.csv' => '縣市議員',
    $voteDataPath . '/C1/city/elcand.csv' => '縣市長',
    $voteDataPath . '/D1/elcand.csv' => '鄉鎮市長',
    $voteDataPath . '/D2/elcand.csv' => '直轄市山地原住民區長',
    $voteDataPath . '/R1/elcand.csv' => '鄉鎮市民代表',
    $voteDataPath . '/R2/elcand.csv' => '鄉鎮市民代表',
    $voteDataPath . '/R3/elcand.csv' => '直轄市山地原住民區民代表',
    $voteDataPath . '/V1/elcand.csv' => '村里長',
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
    $fh = fopen($p['dirname'] . '/elarea.csv', 'r');
    $head = fgetcsv($fh, 2048);
    $area = [];
    while ($line = fgetcsv($fh, 2048)) {
        $code = $line[0] . $line[1] . $line[2] . $line[3] . $line[4];
        $area[$code] = $line[5];
    }

    $fh = fopen($p['dirname'] . '/eltckt.csv', 'r');
    $head = fgetcsv($fh, 2048);
    $votes = [];
    while ($line = fgetcsv($fh, 2048)) {
        $code = $line[0] . $line[1] . $line[2] . $line[3] . $line[4] . $line[5] . $line[6];
        $votes[$code] = $line[7];
    }

    $fh = fopen($candidateFile, 'r');
    $head = fgetcsv($fh, 2048);
    while ($line = fgetcsv($fh, 2048)) {
        $data = array_combine($head, $line);
        $code = $line[0] . $line[1] . $line[2] . $line[3] . $line[4];
        $vcode = $line[0] . $line[1] . $line[2] . $line[3] . $line[4] . '0000' . $data['cand_no'];
        switch ($election) {
            case '直轄市山地原住民區民代表':
            case '直轄市山地原住民區長':
            case '直轄市議員':
            case '縣市議員':
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
            fgetcsv($pFh, 2048);
            while ($pLine = fgetcsv($pFh, 2048)) {
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
