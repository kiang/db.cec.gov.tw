<?php
$rootPath = dirname(dirname(__DIR__));
$json2018 = json_decode(file_get_contents($rootPath . '/data/council/2018/2018_match_2020.json'), true);
$json2022 = json_decode(file_get_contents($rootPath . '/data/council/2022/2022_match_2024.json'), true);

$parties = [];
$k1 = key($json2022);
foreach ($json2022[$k1]['votes'] as $party => $partyCount) {
    if (isset($json2018[$k1]['votes'][$party])) {
        $parties[] = $party;
    }
}

$reportPath = $rootPath . '/data/council/2022/2022_compare_2018';
if (!file_exists($reportPath)) {
    mkdir($reportPath, 0777, true);
}

foreach ($parties as $party) {
    $oFh = fopen($reportPath . '/' . $party . '.csv', 'w');
    fputcsv($oFh, ['zone', '2020', '2024', 'diff', 'rate', 'target']);
    $total = 0;
    foreach ($json2022 as $k => $v) {
        $partyCount = $v['votes'][$party];
        $elected = [];
        $targetLine = '';
        foreach ($v['2022']['detail'] as $c) {
            if ($c['elected'] == 1) {
                $elected[] = $c;
            }
        }
        while ($partyCount > 0 && !empty($elected)) {
            $target = array_pop($elected);
            if (!empty($target)) {
                $partyCount -= $target['voteCount'];
                if ($partyCount > 0) {
                    $total += 1;
                    $targetLine .= "{$target['name']}({$target['party']})-{$target['voteCount']}｜";
                }
            }
        }
        if (substr($k, 0, 2) !== '65') {
            $diff = $json2022[$k]['votes'][$party] - $json2018[$k]['votes'][$party];
            $rate = '0';
            if ($json2018[$k]['votes'][$party] != 0) {
                $rate = round($diff / $json2018[$k]['votes'][$party], 4) * 100;
            }

            fputcsv($oFh, [$v['name'], $json2018[$k]['votes'][$party], $json2022[$k]['votes'][$party], $diff, $rate, $targetLine]);
        } else {
            $zone = intval(substr($k, -2));
            switch ($zone) {
                case 1:
                    $diff = $json2022[$k]['votes'][$party] - $json2018[$k]['votes'][$party];
                    $rate = '0';
                    if ($json2018[$k]['votes'][$party] != 0) {
                        $rate = round($diff / $json2018[$k]['votes'][$party], 4) * 100;
                    }

                    fputcsv($oFh, [$v['name'], $json2018[$k]['votes'][$party], $json2022[$k]['votes'][$party], $diff, $rate, $targetLine]);
                    break;
                case 2:
                    // 新莊區 2022 獨立為第3選區，但 2018 為第2選區，所以要加上第2選區的票數
                    $new2022 = $json2022[$k]['votes'][$party] + $json2022[$k + 1]['votes'][$party];
                    $diff = $new2022 - $json2018[$k]['votes'][$party];
                    $rate = round($diff / $json2018[$k]['votes'][$party], 4) * 100;
                    fputcsv($oFh, [$v['name'], $json2018[$k]['votes'][$party], $new2022, $diff, $rate, $targetLine]);
                    break;
                case 3:
                    fputcsv($oFh, [$v['name'], 0, $json2022[$k]['votes'][$party], $json2022[$k]['votes'][$party], 0.0, $targetLine]);
                    break;
                default:
                    $diff = $json2022[$k]['votes'][$party] - $json2018[$k - 1]['votes'][$party];
                    $rate = round($diff / $json2018[$k - 1]['votes'][$party], 4) * 100;
                    fputcsv($oFh, [$v['name'], $json2018[$k - 1]['votes'][$party], $json2022[$k]['votes'][$party], $diff, $rate, $targetLine]);
            }
        }
    }
    echo "{$party}: {$total}\n";
}
