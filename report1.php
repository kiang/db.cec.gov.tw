<?php

$fh = fopen(__DIR__ . '/elections.csv', 'r');
fgets($fh, 512);
$elections = array();
while ($line = fgetcsv($fh, 2048)) {
    $elections[$line[0]] = $line[1];
}
fclose($fh);
ksort($elections);

$candidates = array();
foreach ($elections AS $code => $election) {
    $cFh = fopen(__DIR__ . "/elections/{$code}.csv", 'r');
    $titles = fgets($cFh, 2048);
    if (false !== strpos($titles, '出生年次')) {
        while ($line = fgetcsv($cFh, 2048)) {
            if (false !== strpos($line[1], '|')) {
                $names = explode('|', $line[1]);
                $genders = explode('|', $line[3]);
                $birth_years = explode('|', $line[4]);
                foreach ($names AS $k => $v) {
                    $key = md5($names[$k] . $genders[$k] . $birth_years[$k]);
                    if (!isset($candidates[$key])) {
                        $candidates[$key] = array(
                            'name' => $names[$k],
                            'gender' => $genders[$k],
                            'birth_year' => $birth_years[$k],
                            'count' => 0,
                            'elections' => array(),
                        );
                    }
                    $candidates[$key]['count'] ++;
                    $candidates[$key]['elections'][] = array(
                        '選舉類型' => $election,
                        '區域' => $line[0],
                        '推薦政黨' => $line[5],
                        '得票數' => $line[6],
                        '得票率' => $line[7],
                        '當選註記' => $line[8],
                        '是否現任' => $line[9],
                    );
                }
            } else {
                $key = md5($line[1] . $line[3] . $line[4]);
                if (!isset($candidates[$key])) {
                    $candidates[$key] = array(
                        'name' => $line[1],
                        'gender' => $line[3],
                        'birth_year' => $line[4],
                        'count' => 0,
                        'elections' => array(),
                    );
                }
                $candidates[$key]['count'] ++;
                $candidates[$key]['elections'][] = array(
                    '選舉類型' => $election,
                    '區域' => $line[0],
                    '推薦政黨' => $line[5],
                    '得票數' => $line[6],
                    '得票率' => $line[7],
                    '當選註記' => $line[8],
                    '是否現任' => $line[9],
                );
            }
        }
    }
    fclose($cFh);
}

$fh = fopen(__DIR__ . '/report1.csv', 'w');
fputcsv($fh, array(
    '姓名', '性別', '出生年次', '選舉類型', '區域', '推薦政黨', '得票數', '得票率', '當選註記', '是否現任',
));
foreach ($candidates AS $key => $data) {
    if ($data['count'] > 1) {
        $firstLine = true;
        foreach ($data['elections'] AS $election) {
            if (false === $firstLine) {
                fputcsv($fh, array(
                    '-',
                    '-',
                    '-',
                    $election['選舉類型'],
                    $election['區域'],
                    $election['推薦政黨'],
                    $election['得票數'],
                    $election['得票率'],
                    $election['當選註記'],
                    $election['是否現任'],
                ));
            } else {
                $firstLine = false;
                fputcsv($fh, array(
                    $data['name'],
                    $data['gender'],
                    $data['birth_year'],
                    $election['選舉類型'],
                    $election['區域'],
                    $election['推薦政黨'],
                    $election['得票數'],
                    $election['得票率'],
                    $election['當選註記'],
                    $election['是否現任'],
                ));
            }
        }
    }
}
fclose($fh);