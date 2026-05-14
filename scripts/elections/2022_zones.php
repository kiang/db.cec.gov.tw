<?php

$basePath = dirname(dirname(__DIR__));
$voteDataPath = $basePath . '/voteData/2022-111年地方公職人員選舉';

$pool = [];

$electionTypes = ['R1', 'R2', 'R3', 'T1', 'T2', 'T3'];

foreach ($electionTypes as $type) {
    $csvFiles = glob($voteDataPath . '/' . $type . '/elbase.csv');
    if (empty($csvFiles)) {
        $csvFiles = array_merge(
            glob($voteDataPath . '/' . $type . '/city/elbase.csv'),
            glob($voteDataPath . '/' . $type . '/prv/elbase.csv')
        );
    }

    $isRType = in_array($type, ['R1', 'R2', 'R3']);
    $codes = [];
    foreach ($csvFiles as $csvFile) {
        $fh = fopen($csvFile, 'r');
        while ($line = fgetcsv($fh, 2048)) {
            if ($line[4] !== '0000') {
                $parts = explode('、', $line[5]);
                $code = $line[0] . $line[1] . $line[3];
                if ($isRType) {
                    $zoneCode = $type . '-' . $line[0] . $line[1] . $line[3] . '-' . $line[2];
                } else {
                    $zoneCode = $type . '-' . $line[0] . $line[1] . '-' . $line[2];
                }
                $pool[$codes[$code]][] = $zoneCode;
                foreach ($parts as $part) {
                    $pool[$codes[$code] . $part][] = $zoneCode;
                }
            } else {
                if ($line[3] === '000') {
                    $codes[$line[0] . $line[1]] = $line[5];
                } else {
                    $codes[$line[0] . $line[1] . $line[3]] = $codes[$line[0] . $line[1]] . $line[5];
                }
            }
        }
        fclose($fh);
    }
}

$json = json_decode(file_get_contents('/home/kiang/public_html/taiwan_basecode/cunli/geo/20221118.json'), true);

$map = [
    '南投縣名間鄉廍下村' => true,
    '南投縣竹山鎮硘[磘]里' => true,
    '嘉義市西區磚[磘]里' => true,
    '屏東縣新園鄉瓦[磘]村' => true,
    '屏東縣東港鎮下廍里' => true,
    '屏東縣里港鄉三廍村' => true,
    '彰化縣埔鹽鄉廍子村' => true,
    '彰化縣埔鹽鄉瓦[磘]村' => true,
    '彰化縣彰化市下廍里' => true,
    '彰化縣彰化市寶廍里' => true,
    '彰化縣彰化市磚[磘]里' => true,
    '彰化縣芳苑鄉頂廍村' => true,
    '新北市中和區灰[磘]里' => true,
    '新北市中和區瓦[磘]里' => true,
    '新北市坪林區石[曹]里' => true,
    '新北市樹林區[獇]寮里' => true,
    '新北市永和區新廍里' => true,
    '新北市瑞芳區濂新里' => true,
    '新北市瑞芳區濂洞里' => true,
    '澎湖縣馬公市[嵵]裡里' => true,
    '臺中市北屯區廍子里' => true,
    '臺中市外埔區廍子里' => true,
    '臺中市大安區龜[壳]里' => true,
    '臺中市大肚區蔗廍里' => true,
    '臺北市萬華區糖廍里' => true,
    '臺南市安南區[塭]南里' => true,
    '臺南市安南區公[塭]里' => true,
    '臺南市官田區南廍里' => true,
    '臺南市新化區[那]拔里' => true,
    '臺南市西港區[檨]林里' => true,
    '臺南市麻豆區寮廍里' => true,
    '臺南市龍崎區石[曹]里' => true,
    '雲林縣元長鄉瓦[磘]村' => true,
    '雲林縣四湖鄉[萡]子村' => true,
    '雲林縣四湖鄉[萡]東村' => true,
    '雲林縣水林鄉[欍]埔村' => true,
    '雲林縣麥寮鄉瓦[磘]村' => true,
    '高雄市左營區廍北里' => true,
    '高雄市左營區廍南里' => true,
];

$zoneFeatures = [];
foreach ($json['features'] as $f) {
    if (!empty($f['properties']['VILLNAME'])) {
        $key = $f['properties']['COUNTYNAME'] . $f['properties']['TOWNNAME'] . $f['properties']['VILLNAME'];
    } else {
        $key = $f['properties']['COUNTYNAME'] . $f['properties']['TOWNNAME'];
    }

    if (!isset($pool[$key])) {
        if (!isset($map[$key])) {
            echo "missing: {$key}\n";
        }
        continue;
    }

    foreach ($pool[$key] as $zoneCode) {
        if (!isset($zoneFeatures[$zoneCode])) {
            $zoneFeatures[$zoneCode] = [];
        }
        $zoneFeatures[$zoneCode][] = $f;
    }
}

$outputPath = $basePath . '/data/elections/2022';
if (!file_exists($outputPath)) {
    mkdir($outputPath, 0777, true);
}

foreach ($zoneFeatures as $zoneCode => $features) {
    $fc = [
        'type' => 'FeatureCollection',
        'features' => $features,
    ];

    $filePath = $outputPath . '/' . $zoneCode . '.json';
    file_put_contents($filePath, json_encode($fc, JSON_UNESCAPED_UNICODE));
}

echo "Done. Generated " . count($zoneFeatures) . " zone files in {$outputPath}\n";
