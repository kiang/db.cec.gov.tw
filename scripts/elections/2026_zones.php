<?php

$basePath = dirname(dirname(__DIR__));
$voteDataPath = $basePath . '/voteData/2022-111年地方公職人員選舉';

$typeNames = [
    'R1' => '鄉鎮市民代表(區域)',
    'R2' => '鄉鎮市民代表(平原原住民)',
    'R3' => '直轄市原住民區民代表',
    'T1' => '議員(區域)',
    'T2' => '議員(平地原住民)',
    'T3' => '議員(山地原住民)',
];

$pool = [];
$zoneNames = [];

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
                if (!isset($zoneNames[$zoneCode])) {
                    if ($isRType) {
                        $zoneNames[$zoneCode] = $codes[$code] . '第' . $line[2] . '選區';
                    } else {
                        $zoneNames[$zoneCode] = $codes[$line[0] . $line[1]] . '第' . $line[2] . '選區';
                    }
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

$json = json_decode(file_get_contents('/home/kiang/public_html/taiwan_basecode/cunli/geo/20260407.json'), true);

// 2026 T1 zone redistricting override for 5 counties:
// 新竹縣(10004), 彰化縣(10007), 雲林縣(10009), 基隆市(10017), 新竹市(10018)
// Source: CEC gazette for 2026 local elections
$overrideCounties = ['10004', '10007', '10009', '10017', '10018'];
$overrideCountyNames = [
    '10004' => '新竹縣',
    '10007' => '彰化縣',
    '10009' => '雲林縣',
    '10017' => '基隆市',
    '10018' => '新竹市',
];

// 新竹縣 T2/T3 renumbering: T1 zones grew from 10 to 11, shifting T2/T3 by +1
$t2t3Renumber = [
    'T2-10004-11' => 'T2-10004-12',
    'T3-10004-12' => 'T3-10004-13',
    'T3-10004-13' => 'T3-10004-14',
];

// Remove old T1 zone entries (and renumber T2/T3) for the 5 counties from $pool and $zoneNames
foreach ($pool as $key => $zoneCodes) {
    $newCodes = [];
    foreach ($zoneCodes as $zc) {
        if (isset($t2t3Renumber[$zc])) {
            $newCodes[] = $t2t3Renumber[$zc];
        } elseif (substr($zc, 0, 2) !== 'T1') {
            $newCodes[] = $zc;
        } else {
            $countyCode = substr($zc, 3, 5);
            if (!in_array($countyCode, $overrideCounties)) {
                $newCodes[] = $zc;
            }
        }
    }
    if (empty($newCodes)) {
        unset($pool[$key]);
    } else {
        $pool[$key] = $newCodes;
    }
}
$renamedZoneNames = [];
foreach (array_keys($zoneNames) as $zc) {
    if (isset($t2t3Renumber[$zc])) {
        $newZc = $t2t3Renumber[$zc];
        $renamedZoneNames[$newZc] = $overrideCountyNames['10004'] . '第' . substr($newZc, -2) . '選區';
        unset($zoneNames[$zc]);
    } elseif (substr($zc, 0, 2) === 'T1') {
        $countyCode = substr($zc, 3, 5);
        if (in_array($countyCode, $overrideCounties)) {
            unset($zoneNames[$zc]);
        }
    }
}
$zoneNames += $renamedZoneNames;

// Build GeoJSON village lookup: TOWNCODE => [VILLNAME => true]
$geoVillages = [];
foreach ($json['features'] as $f) {
    $p = $f['properties'];
    if (!empty($p['VILLNAME'])) {
        $tc = $p['TOWNCODE'];
        if (!isset($geoVillages[$tc])) {
            $geoVillages[$tc] = [];
        }
        $geoVillages[$tc][$p['VILLNAME']] = true;
    }
}

// Define 2026 T1 zones
$t1Overrides = [];

// Helper: add a town-based zone (all villages in the town)
function addTownZone(&$t1Overrides, $countyCode, $countyName, $zone, $townCode, $townName, &$geoVillages) {
    $zoneCode = 'T1-' . $countyCode . '-' . sprintf('%02d', $zone);
    if (!isset($t1Overrides[$zoneCode])) {
        $t1Overrides[$zoneCode] = [];
    }
    $t1Overrides[$zoneCode][] = $countyName . $townName;
    if (isset($geoVillages[$townCode])) {
        foreach (array_keys($geoVillages[$townCode]) as $villName) {
            $t1Overrides[$zoneCode][] = $countyName . $townName . $villName;
        }
    }
}

// Helper: add a village-based zone
function addVillageZone(&$t1Overrides, $countyCode, $countyName, $zone, $townCode, $townName, $villages, &$geoVillages) {
    $zoneCode = 'T1-' . $countyCode . '-' . sprintf('%02d', $zone);
    if (!isset($t1Overrides[$zoneCode])) {
        $t1Overrides[$zoneCode] = [];
    }
    foreach ($villages as $villName) {
        $t1Overrides[$zoneCode][] = $countyName . $townName . $villName;
    }
}

// --- 新竹縣 (10004): 11 T1 zones ---
// Zone 1: 竹北市 15 villages
addVillageZone($t1Overrides, '10004', '新竹縣', 1, '10004010', '竹北市',
    ['尚義里','崇義里','新港里','大義里','白地里','大眉里','麻園里','溪州里','新庄里','聯興里','泰和里','新社里','新國里','竹義里','新崙里'], $geoVillages);
// Zone 2: 竹北市 16 villages
addVillageZone($t1Overrides, '10004', '新竹縣', 2, '10004010', '竹北市',
    ['竹北里','竹仁里','福德里','北崙里','中崙里','文化里','興安里','斗崙里','北興里','十興里','鹿場里','東興里','中興里','東平里','東海里','隘口里'], $geoVillages);
// Zone 3: 湖口鄉
addTownZone($t1Overrides, '10004', '新竹縣', 3, '10004050', '湖口鄉', $geoVillages);
// Zone 4: 新豐鄉
addTownZone($t1Overrides, '10004', '新竹縣', 4, '10004060', '新豐鄉', $geoVillages);
// Zone 5: 關西鎮
addTownZone($t1Overrides, '10004', '新竹縣', 5, '10004040', '關西鎮', $geoVillages);
// Zone 6: 新埔鎮
addTownZone($t1Overrides, '10004', '新竹縣', 6, '10004030', '新埔鎮', $geoVillages);
// Zone 7: 橫山鄉、尖石鄉
addTownZone($t1Overrides, '10004', '新竹縣', 7, '10004080', '橫山鄉', $geoVillages);
addTownZone($t1Overrides, '10004', '新竹縣', 7, '10004120', '尖石鄉', $geoVillages);
// Zone 8: 芎林鄉
addTownZone($t1Overrides, '10004', '新竹縣', 8, '10004070', '芎林鄉', $geoVillages);
// Zone 9: 竹東鎮、五峰鄉
addTownZone($t1Overrides, '10004', '新竹縣', 9, '10004020', '竹東鎮', $geoVillages);
addTownZone($t1Overrides, '10004', '新竹縣', 9, '10004130', '五峰鄉', $geoVillages);
// Zone 10: 寶山鄉
addTownZone($t1Overrides, '10004', '新竹縣', 10, '10004100', '寶山鄉', $geoVillages);
// Zone 11: 北埔鄉、峨眉鄉
addTownZone($t1Overrides, '10004', '新竹縣', 11, '10004090', '北埔鄉', $geoVillages);
addTownZone($t1Overrides, '10004', '新竹縣', 11, '10004110', '峨眉鄉', $geoVillages);

// --- 彰化縣 (10007): 8 T1 zones ---
addTownZone($t1Overrides, '10007', '彰化縣', 1, '10007010', '彰化市', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 1, '10007080', '花壇鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 1, '10007090', '芬園鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 2, '10007020', '鹿港鎮', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 2, '10007060', '福興鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 2, '10007070', '秀水鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 3, '10007030', '和美鎮', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 3, '10007050', '伸港鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 3, '10007040', '線西鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 4, '10007100', '員林市', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 4, '10007130', '大村鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 4, '10007160', '永靖鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 5, '10007110', '溪湖鎮', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 5, '10007140', '埔鹽鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 5, '10007150', '埔心鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 6, '10007120', '田中鎮', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 6, '10007170', '社頭鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 6, '10007180', '二水鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 7, '10007190', '北斗鎮', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 7, '10007210', '田尾鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 7, '10007220', '埤頭鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 7, '10007260', '溪州鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 8, '10007200', '二林鎮', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 8, '10007240', '大城鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 8, '10007230', '芳苑鄉', $geoVillages);
addTownZone($t1Overrides, '10007', '彰化縣', 8, '10007250', '竹塘鄉', $geoVillages);

// --- 雲林縣 (10009): 6 T1 zones ---
addTownZone($t1Overrides, '10009', '雲林縣', 1, '10009010', '斗六市', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 1, '10009090', '莿桐鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 1, '10009100', '林內鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 2, '10009020', '斗南鎮', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 2, '10009070', '古坑鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 2, '10009080', '大埤鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 3, '10009030', '虎尾鎮', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 3, '10009050', '土庫鎮', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 3, '10009150', '褒忠鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 3, '10009170', '元長鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 4, '10009040', '西螺鎮', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 4, '10009110', '二崙鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 4, '10009120', '崙背鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 5, '10009160', '臺西鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 5, '10009130', '麥寮鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 5, '10009140', '東勢鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 5, '10009180', '四湖鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 6, '10009060', '北港鎮', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 6, '10009190', '口湖鄉', $geoVillages);
addTownZone($t1Overrides, '10009', '雲林縣', 6, '10009200', '水林鄉', $geoVillages);

// --- 基隆市 (10017): 7 T1 zones ---
addTownZone($t1Overrides, '10017', '基隆市', 1, '10017010', '中正區', $geoVillages);
addTownZone($t1Overrides, '10017', '基隆市', 2, '10017070', '信義區', $geoVillages);
addTownZone($t1Overrides, '10017', '基隆市', 3, '10017040', '仁愛區', $geoVillages);
addTownZone($t1Overrides, '10017', '基隆市', 4, '10017050', '中山區', $geoVillages);
addTownZone($t1Overrides, '10017', '基隆市', 5, '10017060', '安樂區', $geoVillages);
addTownZone($t1Overrides, '10017', '基隆市', 6, '10017030', '暖暖區', $geoVillages);
addTownZone($t1Overrides, '10017', '基隆市', 7, '10017020', '七堵區', $geoVillages);

// --- 新竹市 (10018): 5 T1 zones (village-level) ---
// Zone 1: 東區 36 villages
addVillageZone($t1Overrides, '10018', '新竹市', 1, '10018010', '東區',
    ['東門里','榮光里','成功里','育賢里','中正里','親仁里','文華里','復中里','三民里','公園里','東園里','東山里','東勢里','光復里','前溪里','水源里','千甲里','綠水里','埔頂里','仙宮里','龍山里','新莊里','關新里','仙水里','金山里','建功里','光明里','立功里','軍功里','武功里','豐功里','科園里','關東里','建華里','錦華里','復興里'], $geoVillages);
// Zone 2: 東區 18 villages (新竹里 not in current GeoJSON, included for completeness)
addVillageZone($t1Overrides, '10018', '新竹市', 2, '10018010', '東區',
    ['南門里','關帝里','南市里','福德里','振興里','新興里','竹蓮里','南大里','寺前里','下竹里','頂竹里','光鎮里','高峰里','柴橋里','新光里','新竹里','湖濱里','明湖里'], $geoVillages);
// Zone 3: 北區 15 villages
addVillageZone($t1Overrides, '10018', '新竹市', 3, '10018020', '北區',
    ['客雅里','中雅里','育英里','曲溪里','西雅里','南勢里','大鵬里','西門里','仁德里','潛園里','中央里','崇禮里','石坊里','興南里','台溪里'], $geoVillages);
// Zone 4: 北區 30 villages (gazette 浦雅里 = GeoJSON 湳雅里, both included)
addVillageZone($t1Overrides, '10018', '新竹市', 4, '10018020', '北區',
    ['北門里','中興里','大同里','中山里','長和里','新民里','民富里','水田里','文雅里','光田里','士林里','福林里','古賢里','浦雅里','湳雅里','舊社里','武陵里','南寮里','舊港里','康樂里','港北里','中寮里','海濱里','磐石里','新雅里','光華里','金華里','境福里','金竹里','湳中里','金雅里'], $geoVillages);
// Zone 5: 香山區 24 villages
addVillageZone($t1Overrides, '10018', '新竹市', 5, '10018030', '香山區',
    ['頂埔里','中埔里','埔前里','牛埔里','樹下里','浸水里','虎林里','虎山里','港南里','大庄里','美山里','朝山里','東香里','香山里','香村里','海山里','鹽水里','內湖里','南港里','中隘里','南隘里','大湖里','茄苳里','頂福里'], $geoVillages);

// Apply T1 overrides to $pool and $zoneNames
foreach ($t1Overrides as $zoneCode => $keys) {
    $countyCode = substr($zoneCode, 3, 5);
    $zoneNum = substr($zoneCode, -2);
    $zoneNames[$zoneCode] = $overrideCountyNames[$countyCode] . '第' . $zoneNum . '選區';
    foreach ($keys as $key) {
        if (!isset($pool[$key])) {
            $pool[$key] = [];
        }
        $pool[$key][] = $zoneCode;
    }
}

// 2026 new county-wide indigenous zones (山地原住民) not present in 2022 data
// These zones cover the entire county — all villages map to this zone.
$countyWideT3 = [
    'T3-10007-10' => '10007', // 彰化縣第10選區 山地原住民
];

foreach ($countyWideT3 as $zoneCode => $countyCode) {
    $zoneNum = substr($zoneCode, -2);
    $zoneNames[$zoneCode] = $overrideCountyNames[$countyCode] ?? '';
    // Try to get county name from existing zone names
    if (empty($zoneNames[$zoneCode])) {
        foreach ($zoneNames as $zn => $name) {
            if (strpos($zn, $countyCode) !== false) {
                $zoneNames[$zoneCode] = mb_ereg_replace('第\d+選區$', '', $name) . '第' . $zoneNum . '選區';
                break;
            }
        }
    } else {
        $zoneNames[$zoneCode] = $zoneNames[$zoneCode] . '第' . $zoneNum . '選區';
    }
    // Map all villages in this county to this zone
    foreach ($json['features'] as $f) {
        $p = $f['properties'];
        if (substr($p['TOWNCODE'] ?? '', 0, 5) === $countyCode) {
            if (!empty($p['VILLNAME'])) {
                $key = $p['COUNTYNAME'] . $p['TOWNNAME'] . $p['VILLNAME'];
            } else {
                $key = $p['COUNTYNAME'] . $p['TOWNNAME'];
            }
            if (!isset($pool[$key])) {
                $pool[$key] = [];
            }
            if (!in_array($zoneCode, $pool[$key])) {
                $pool[$key][] = $zoneCode;
            }
        }
    }
}

// Rare-character villages not matched by elbase name lookup.
// R1 zone format: R1-{省市2}{縣市3}{鄉鎮3}-{選區2}, e.g. 'R1-10007010-03'
// R1 only applies to 縣 (non-直轄市). 直轄市 (臺北/新北/桃園/臺中/臺南/高雄) and
// 省轄市 (嘉義市) have no R1/R2/R3 zones.
// To find the correct R1 zone, look up the village in the R1 elbase.csv.
$map = [
    // rare characters
    '南投縣名間鄉廍下村' => ['T1-10008-01', 'T2-10008-06', 'T3-10008-08'],
    '南投縣竹山鎮硘[磘]里' => ['T1-10008-04', 'T2-10008-06', 'T3-10008-07'],
    '嘉義市西區磚[磘]里' => ['T1-10020-02'],
    '屏東縣新園鄉瓦[磘]村' => ['T1-10013-04', 'T2-10013-08', 'T3-10013-13'],
    '屏東縣東港鎮下廍里' => ['T1-10013-04', 'T2-10013-08', 'T3-10013-09'],
    '屏東縣里港鄉三廍村' => ['T1-10013-02', 'T2-10013-08', 'T3-10013-10'],
    '彰化縣埔鹽鄉廍子村' => ['T1-10007-05', 'T2-10007-09', 'T3-10007-10'],
    '彰化縣埔鹽鄉瓦[磘]村' => ['T1-10007-05', 'T2-10007-09', 'T3-10007-10'],
    '彰化縣彰化市下廍里' => ['T1-10007-01', 'T2-10007-09', 'T3-10007-10'],
    '彰化縣彰化市寶廍里' => ['T1-10007-01', 'T2-10007-09', 'T3-10007-10'],
    '彰化縣彰化市磚[磘]里' => ['T1-10007-01', 'T2-10007-09', 'T3-10007-10'],
    '彰化縣芳苑鄉頂廍村' => ['T1-10007-08', 'T2-10007-09', 'T3-10007-10'],
    '新北市中和區灰[磘]里' => ['T1-65000-06', 'T2-65000-12', 'T3-65000-13'],
    '新北市中和區瓦[磘]里' => ['T1-65000-06', 'T2-65000-12', 'T3-65000-13'],
    '新北市坪林區石[曹]里' => ['T1-65000-09', 'T2-65000-12', 'T3-65000-13'],
    '新北市樹林區[獇]寮里' => ['T1-65000-08', 'T2-65000-12', 'T3-65000-13'],
    '新北市永和區新廍里' => ['T1-65000-07', 'T2-65000-12', 'T3-65000-13'],
    '新北市瑞芳區濂新里' => ['T1-65000-10', 'T2-65000-12', 'T3-65000-13'],
    '新北市瑞芳區濂洞里' => ['T1-65000-10', 'T2-65000-12', 'T3-65000-13'],
    '澎湖縣馬公市[嵵]裡里' => ['T1-10016-01'],
    '臺中市北屯區廍子里' => ['T1-66000-08', 'T2-66000-15', 'T3-66000-17'],
    '臺中市外埔區廍子里' => ['T1-66000-01', 'T2-66000-15', 'T3-66000-16'],
    '臺中市大安區龜[壳]里' => ['T1-66000-01', 'T2-66000-15', 'T3-66000-16'],
    '臺中市大肚區蔗廍里' => ['T1-66000-03', 'T2-66000-15', 'T3-66000-16'],
    '臺北市萬華區糖廍里' => ['T1-63000-05', 'T2-63000-07', 'T3-63000-08'],
    '臺南市安南區[塭]南里' => ['T1-67000-06', 'T2-67000-12', 'T3-67000-13'],
    '臺南市安南區公[塭]里' => ['T1-67000-06', 'T2-67000-12', 'T3-67000-13'],
    '臺南市官田區南廍里' => ['T1-67000-03', 'T2-67000-12', 'T3-67000-13'],
    '臺南市新化區[那]拔里' => ['T1-67000-05', 'T2-67000-12', 'T3-67000-13'],
    '臺南市西港區[檨]林里' => ['T1-67000-02', 'T2-67000-12', 'T3-67000-13'],
    '臺南市麻豆區寮廍里' => ['T1-67000-03', 'T2-67000-12', 'T3-67000-13'],
    '臺南市龍崎區石[曹]里' => ['T1-67000-11', 'T2-67000-12', 'T3-67000-13'],
    '雲林縣元長鄉瓦[磘]村' => ['T1-10009-03'],
    '雲林縣四湖鄉[萡]子村' => ['T1-10009-05'],
    '雲林縣四湖鄉[萡]東村' => ['T1-10009-05'],
    '雲林縣水林鄉[欍]埔村' => ['T1-10009-06'],
    '雲林縣麥寮鄉瓦[磘]村' => ['T1-10009-05'],
    '高雄市左營區廍北里' => ['T1-64000-04', 'T2-64000-12', 'T3-64000-13'],
    '高雄市左營區廍南里' => ['T1-64000-04', 'T2-64000-12', 'T3-64000-13'],
    // 2026 new villages (splits from 2022, need manual zone mapping)
    // 縣 villages need T1 + R1 (+ T2/T3 if applicable)
    // 直轄市 villages need T1 (+ T2/T3 if applicable), no R1
    // e.g. '宜蘭縣員山鄉金古村' => ['T1-10002-03', 'R1-10002070-02'],
    '宜蘭縣員山鄉金古村' => ['T1-10002-04', 'T2-10002-11', 'T3-10002-12', 'R1-10002070-01'],
    '宜蘭縣員山鄉金泰村' => ['T1-10002-04', 'T2-10002-11', 'T3-10002-12', 'R1-10002070-01'],
    '宜蘭縣壯圍鄉壯六村' => ['T1-10002-05', 'T2-10002-11', 'T3-10002-12', 'R1-10002060-03'],
    '宜蘭縣壯圍鄉美間村' => ['T1-10002-05', 'T2-10002-11', 'T3-10002-12', 'R1-10002060-04'],
    '宜蘭縣壯圍鄉順和村' => ['T1-10002-05', 'T2-10002-11', 'T3-10002-12', 'R1-10002060-03'],
    // 新竹縣 2026: T1 zones 1-11, T2 zone 12, T3 zones 13-14
    // 新豐鄉 is T1 zone 4; T3 zone 13 (尖石鄉及竹北市、湖口鄉、新豐鄉、關西鎮、新埔鎮、橫山鄉)
    '新竹縣新豐鄉明新村' => ['T1-10004-04', 'T2-10004-12', 'T3-10004-13', 'R1-10004060-04'],
    '桃園市中壢區松嶺里' => ['T1-68000-07', 'T2-68000-13', 'T3-68000-14'],
    '桃園市中壢區青園里' => ['T1-68000-07', 'T2-68000-13', 'T3-68000-14'],
    '桃園市中壢區青航里' => ['T1-68000-07', 'T2-68000-13', 'T3-68000-14'],
    '桃園市八德區大盛里' => ['T1-68000-03', 'T2-68000-13', 'T3-68000-14'],
    '桃園市大園區仁德里' => ['T1-68000-05', 'T2-68000-13', 'T3-68000-14'],
    '桃園市大園區新南里' => ['T1-68000-05', 'T2-68000-13', 'T3-68000-14'],
    '桃園市桃園區幸福里' => ['T1-68000-01', 'T2-68000-13', 'T3-68000-14'],
    '桃園市桃園區民有里' => ['T1-68000-01', 'T2-68000-13', 'T3-68000-14'],
    '桃園市桃園區藝文里' => ['T1-68000-01', 'T2-68000-13', 'T3-68000-14'],
    '桃園市龜山區文桃里' => ['T1-68000-02', 'T2-68000-13', 'T3-68000-14'],
    '桃園市龜山區文樂里' => ['T1-68000-02', 'T2-68000-13', 'T3-68000-14'],
    '桃園市龜山區文藝里' => ['T1-68000-02', 'T2-68000-13', 'T3-68000-14'],
    '桃園市龜山區長樂里' => ['T1-68000-02', 'T2-68000-13', 'T3-68000-14'],
    '臺南市官田區東庄里' => ['T1-67000-03', 'T2-67000-12', 'T3-67000-13'],
    '臺南市官田區西庄里' => ['T1-67000-03', 'T2-67000-12', 'T3-67000-13'],
    '花蓮縣吉安鄉吉昌村' => ['T1-10015-03', 'T2-10015-05', 'T3-10015-08', 'R1-10015050-01'],
    '苗栗縣頭份市上庄里' => ['T1-10005-05', 'T2-10005-07', 'T3-10005-08', 'R1-10005050-03'],
    '苗栗縣頭份市興安里' => ['T1-10005-05', 'T2-10005-07', 'T3-10005-08', 'R1-10005050-01'],
];

$zoneFeatures = [];
foreach ($json['features'] as $f) {
    if (!empty($f['properties']['VILLNAME'])) {
        $key = $f['properties']['COUNTYNAME'] . $f['properties']['TOWNNAME'] . $f['properties']['VILLNAME'];
    } else {
        $key = $f['properties']['COUNTYNAME'] . $f['properties']['TOWNNAME'];
    }

    if (isset($pool[$key])) {
        $zoneCodes = $pool[$key];
    } elseif (isset($map[$key])) {
        $zoneCodes = $map[$key];
    } else {
        echo "missing: {$key}\n";
        continue;
    }

    foreach ($zoneCodes as $zoneCode) {
        if (!isset($zoneFeatures[$zoneCode])) {
            $zoneFeatures[$zoneCode] = [];
        }
        $zoneFeatures[$zoneCode][] = $f;
    }
}

$outputPath = $basePath . '/data/elections/2026';
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

ksort($zoneNames, SORT_NATURAL);
$fh = fopen($outputPath . '/list.csv', 'w');
fputcsv($fh, ['type', 'code', 'name', 'type_name']);
foreach ($zoneNames as $zoneCode => $name) {
    $type = substr($zoneCode, 0, 2);
    fputcsv($fh, [$type, $zoneCode, $name, $typeNames[$type]]);
}
fclose($fh);

echo "Done. Generated " . count($zoneFeatures) . " zone files and list.csv in {$outputPath}\n";
