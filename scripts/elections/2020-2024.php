<?php
$rootPath = dirname(dirname(__DIR__));

$elections = [
    '2020不分區' => [
        'path' => $rootPath . '/voteData/2020總統立委/不分區政黨',
        'type' => 'party',
        'quoted' => true,
    ],
    '2022議員' => [
        'paths' => [
            $rootPath . '/voteData/2022-111年地方公職人員選舉/T1/city',
            $rootPath . '/voteData/2022-111年地方公職人員選舉/T1/prv',
        ],
        'type' => 'candidate',
        'quoted' => false,
    ],
    '2024不分區' => [
        'path' => $rootPath . '/voteData/2024總統立委/不分區政黨',
        'type' => 'party',
        'quoted' => true,
    ],
    '2024總統' => [
        'path' => $rootPath . '/voteData/2024總統立委/總統',
        'type' => 'president',
        'quoted' => true,
    ],
];

$elctkHeader = ['省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '投開票所', '候選人號次', '得票數', '得票率', '當選註記'];
$elbaseHeader = ['省市', '縣市', '選區', '鄉鎮市區', '村里', '名稱'];

// Build cunli names and name-to-code mapping from 2024 presidential elbase
$cunliNames = [];
$areaCodes = []; // area prefix => accumulated name (county+town)
$nameToCode = []; // full name (county+town+village) => election cunli code
$mergedNameToCode = []; // full name => merged election cunli code (0Axx)
$fh = fopen($rootPath . '/voteData/2024總統立委/總統/elbase.csv', 'r');
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, " \t\n\r\0\x0B'\"");
    }
    $data = array_combine($elbaseHeader, $line);
    if ($data['村里'] === '0000') {
        if ($data['鄉鎮市區'] === '000') {
            $areaCodes[$data['省市'] . $data['縣市']] = $data['名稱'];
        } else {
            $areaCodes[$data['省市'] . $data['縣市'] . $data['鄉鎮市區']] = $areaCodes[$data['省市'] . $data['縣市']] . $data['名稱'];
        }
    } else {
        $cunliCode = $data['省市'] . $data['縣市'] . $data['鄉鎮市區'] . $data['村里'];
        $cunliNames[$cunliCode] = $data['名稱'];
        $prefix = $areaCodes[$data['省市'] . $data['縣市'] . $data['鄉鎮市區']];
        $isMerged = strpos($data['村里'], '0A') === 0;
        $parts = explode('、', $data['名稱']);
        foreach ($parts as $part) {
            if ($isMerged) {
                // For merged entries, map individual names to merged code as fallback
                $mergedNameToCode[$prefix . $part] = $cunliCode;
            }
            // Don't overwrite individual village mappings with merged entry mappings
            if (!isset($nameToCode[$prefix . $part])) {
                $nameToCode[$prefix . $part] = $cunliCode;
            }
        }
    }
}
fclose($fh);

// Manual mapping for villages with Unicode character variants between geojson and elbase
$geoNameToElCode = [
    '新北市坪林區石[曹]里' => '650002000004',
    '臺中市大安區龜[壳]里' => '660002200004',
    '臺南市西港區[檨]林里' => '670001400004',
    '臺南市新化區[那]拔里' => '670001800018',
    '臺南市安南區[塭]南里' => '670003500003',
    '臺南市安南區公[塭]里' => '670003500024',
    '雲林縣水林鄉[欍]埔村' => '100092000021',
    '新北市瑞芳區濂新里' => '650001200027',
    '新北市瑞芳區濂洞里' => '650001200028',
    '臺南市龍崎區石[曹]里' => '670003000008',
];

// Load geojson and build VILLCODE mapping
echo "Loading geojson...\n";
$geoJson = json_decode(file_get_contents('/home/kiang/public_html/taiwan_basecode/cunli/geo/20221118.json'), true);

// Map: election cunli code => [county, town, [villcodes]]
$cunliGeoInfo = [];
$unmapped = [];
foreach ($geoJson['features'] as $f) {
    $p = $f['properties'];
    if (empty($p['VILLNAME'])) {
        continue;
    }
    $key = $p['COUNTYNAME'] . $p['TOWNNAME'] . $p['VILLNAME'];
    // Strip brackets from geojson special character names (e.g., 灰[磘]里 => 灰磘里)
    $cleanKey = preg_replace('/\[([^\]]*)\]/', '$1', $key);
    $elCode = null;
    if (isset($nameToCode[$key])) {
        $elCode = $nameToCode[$key];
    } elseif ($cleanKey !== $key && isset($nameToCode[$cleanKey])) {
        $elCode = $nameToCode[$cleanKey];
    } elseif (isset($geoNameToElCode[$key])) {
        $elCode = $geoNameToElCode[$key];
    }

    // Also find merged parent code
    $mergedCode = null;
    if (isset($mergedNameToCode[$key])) {
        $mergedCode = $mergedNameToCode[$key];
    } elseif ($cleanKey !== $key && isset($mergedNameToCode[$cleanKey])) {
        $mergedCode = $mergedNameToCode[$cleanKey];
    }

    if ($elCode) {
        if (!isset($cunliGeoInfo[$elCode])) {
            $cunliGeoInfo[$elCode] = [
                'county' => $p['COUNTYNAME'],
                'town' => $p['TOWNNAME'],
                'villcodes' => [],
                'merged' => $mergedCode,
            ];
        }
        $cunliGeoInfo[$elCode]['villcodes'][] = $p['VILLCODE'];
    } else {
        $unmapped[] = $key;
    }
}
unset($geoJson);

if (!empty($unmapped)) {
    echo "Warning: " . count($unmapped) . " geojson villages unmapped\n";
    foreach (array_slice($unmapped, 0, 10) as $u) {
        echo "  - $u\n";
    }
}

function trimFields($line)
{
    foreach ($line as $k => $v) {
        $line[$k] = trim($v, " \t\n\r\0\x0B'\"");
    }
    return $line;
}

function loadParties($path)
{
    $parties = [];
    $fh = fopen($path . '/elpaty.csv', 'r');
    while ($line = fgetcsv($fh, 2048)) {
        $line = trimFields($line);
        $parties[$line[0]] = $line[1];
    }
    fclose($fh);
    return $parties;
}

function loadPartyCandidates($path)
{
    $candidates = [];
    $header = ['省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '號次', '名字', '政黨代號', '性別', '出生日期', '年齡', '出生地', '學歷', '現任', '當選註記', '副手'];
    $fh = fopen($path . '/elcand.csv', 'r');
    while ($line = fgetcsv($fh, 2048)) {
        $line = trimFields($line);
        if (count($line) >= 16) {
            $data = array_combine($header, $line);
        } else {
            continue;
        }
        $candidates[$data['號次']] = $data['名字'];
    }
    fclose($fh);
    return $candidates;
}

function loadPresidentCandidates($path, $parties)
{
    $candidates = [];
    $header = ['省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '號次', '名字', '政黨代號', '性別', '出生日期', '年齡', '出生地', '學歷', '現任', '當選註記', '副手'];
    $fh = fopen($path . '/elcand.csv', 'r');
    while ($line = fgetcsv($fh, 2048)) {
        $line = trimFields($line);
        if (count($line) < 16) {
            continue;
        }
        $data = array_combine($header, $line);
        // Skip vice presidential candidates (副手=Y)
        if ($data['副手'] === 'Y') {
            continue;
        }
        $partyName = isset($parties[$data['政黨代號']]) ? $parties[$data['政黨代號']] : $data['政黨代號'];
        $candidates[$data['號次']] = [
            'name' => $data['名字'],
            'party' => $partyName,
        ];
    }
    fclose($fh);
    return $candidates;
}

function loadCouncilCandidates($path, $parties)
{
    $candidates = [];
    $header = ['省市別', '縣市別', '選區別', '鄉鎮市區', '村里別', '號次', '名字', '政黨代號', '性別', '出生日期', '年齡', '出生地', '學歷', '現任', '當選註記', '副手'];
    $fh = fopen($path . '/elcand.csv', 'r');
    while ($line = fgetcsv($fh, 2048)) {
        $line = trimFields($line);
        if (count($line) < 16) {
            continue;
        }
        $data = array_combine($header, $line);
        $zone = $data['省市別'] . $data['縣市別'] . $data['選區別'];
        $partyName = isset($parties[$data['政黨代號']]) ? $parties[$data['政黨代號']] : $data['政黨代號'];
        $candidates[$zone][$data['號次']] = [
            'name' => $data['名字'],
            'party' => $partyName,
            'elected' => trim($data['當選註記']) !== '' ? true : false,
        ];
    }
    fclose($fh);
    return $candidates;
}

function loadCouncilElbase($path)
{
    $elbaseHeader = ['省市', '縣市', '選區', '鄉鎮市區', '村里', '名稱'];
    $cunli2zone = [];
    $fh = fopen($path . '/elbase.csv', 'r');
    while ($line = fgetcsv($fh, 2048)) {
        $line = trimFields($line);
        $data = array_combine($elbaseHeader, $line);
        if ($data['選區'] !== '00' && $data['村里'] !== '0000' && $data['鄉鎮市區'] !== '000') {
            $cunliCode = $data['省市'] . $data['縣市'] . $data['鄉鎮市區'] . $data['村里'];
            $cunli2zone[$cunliCode] = $data['省市'] . $data['縣市'] . $data['選區'];
        }
    }
    fclose($fh);
    return $cunli2zone;
}

function loadCunliVotes($path, $elctkHeader)
{
    $votes = [];
    $fh = fopen($path . '/elctks.csv', 'r');
    while ($line = fgetcsv($fh, 2048)) {
        $line = trimFields($line);
        $data = array_combine($elctkHeader, $line);
        // Only cunli-level aggregated rows
        if ($data['投開票所'] !== '0000' && $data['投開票所'] !== '0') {
            continue;
        }
        if ($data['村里別'] === '0000' || $data['鄉鎮市區'] === '000') {
            continue;
        }
        $cunliCode = $data['省市別'] . $data['縣市別'] . $data['鄉鎮市區'] . $data['村里別'];
        $votes[$cunliCode][$data['候選人號次']] = intval($data['得票數']);
    }
    fclose($fh);
    return $votes;
}

// Result array: cunliCode => election data
$result = [];

// Initialize all cunli entries
foreach ($cunliNames as $code => $name) {
    $result[$code] = [
        'name' => $name,
    ];
}

// Process 2020不分區
echo "Processing 2020不分區...\n";
$path2020party = $elections['2020不分區']['path'];
$partyCands2020 = loadPartyCandidates($path2020party);
$votes2020party = loadCunliVotes($path2020party, $elctkHeader);
foreach ($votes2020party as $cunliCode => $candVotes) {
    if (!isset($result[$cunliCode])) {
        continue;
    }
    $partyVotes = [];
    foreach ($candVotes as $candNo => $voteCount) {
        $partyName = isset($partyCands2020[$candNo]) ? $partyCands2020[$candNo] : $candNo;
        $partyVotes[$partyName] = $voteCount;
    }
    $result[$cunliCode]['2020不分區'] = $partyVotes;
}

// Process 2022議員 (city + prv)
echo "Processing 2022議員...\n";
$cunli2zone = [];
$councilCandidates = [];
$councilVotes = [];
foreach ($elections['2022議員']['paths'] as $path) {
    $parties = loadParties($path);
    $cands = loadCouncilCandidates($path, $parties);
    $councilCandidates = $councilCandidates + $cands;
    $zoneMap = loadCouncilElbase($path);
    $cunli2zone = $cunli2zone + $zoneMap;
    $votes = loadCunliVotes($path, $elctkHeader);
    foreach ($votes as $code => $v) {
        $councilVotes[$code] = $v;
    }
}

foreach ($councilVotes as $cunliCode => $candVotes) {
    if (!isset($result[$cunliCode])) {
        continue;
    }
    $zone = isset($cunli2zone[$cunliCode]) ? $cunli2zone[$cunliCode] : null;
    if (!$zone) {
        continue;
    }
    $candidates = isset($councilCandidates[$zone]) ? $councilCandidates[$zone] : [];
    $detail = [];
    foreach ($candVotes as $candNo => $voteCount) {
        $candInfo = isset($candidates[$candNo]) ? $candidates[$candNo] : ['name' => $candNo, 'party' => '', 'elected' => false];
        $detail[] = [
            'no' => $candNo,
            'name' => $candInfo['name'],
            'party' => $candInfo['party'],
            'votes' => $voteCount,
            'elected' => $candInfo['elected'],
        ];
    }
    usort($detail, function ($a, $b) {
        return $b['votes'] - $a['votes'];
    });
    $result[$cunliCode]['2022議員'] = $detail;
}

// Process 2024不分區
echo "Processing 2024不分區...\n";
$path2024party = $elections['2024不分區']['path'];
$partyCands2024 = loadPartyCandidates($path2024party);
$votes2024party = loadCunliVotes($path2024party, $elctkHeader);
foreach ($votes2024party as $cunliCode => $candVotes) {
    if (!isset($result[$cunliCode])) {
        continue;
    }
    $partyVotes = [];
    foreach ($candVotes as $candNo => $voteCount) {
        $partyName = isset($partyCands2024[$candNo]) ? $partyCands2024[$candNo] : $candNo;
        $partyVotes[$partyName] = $voteCount;
    }
    $result[$cunliCode]['2024不分區'] = $partyVotes;
}

// Process 2024總統
echo "Processing 2024總統...\n";
$path2024pres = $elections['2024總統']['path'];
$parties2024 = loadParties($path2024pres);
$presCands = loadPresidentCandidates($path2024pres, $parties2024);
$votes2024pres = loadCunliVotes($path2024pres, $elctkHeader);
foreach ($votes2024pres as $cunliCode => $candVotes) {
    if (!isset($result[$cunliCode])) {
        continue;
    }
    $detail = [];
    foreach ($candVotes as $candNo => $voteCount) {
        $candInfo = isset($presCands[$candNo]) ? $presCands[$candNo] : ['name' => $candNo, 'party' => ''];
        $detail[$candInfo['name']] = [
            'party' => $candInfo['party'],
            'votes' => $voteCount,
        ];
    }
    $result[$cunliCode]['2024總統'] = $detail;
}

// Write per-cunli JSON files
$outputPath = $rootPath . '/data/elections/2020-2024';
if (!file_exists($outputPath)) {
    mkdir($outputPath, 0777, true);
}

// Collect all elCodes that have geo info (including those needing merged fallback)
$written = [];
$count = 0;
foreach ($cunliGeoInfo as $elCode => $geo) {
    // Find election data: use individual code first, fall back to merged parent
    $data = null;
    if (isset($result[$elCode]) && count($result[$elCode]) > 1) {
        $data = $result[$elCode];
    } elseif ($geo['merged'] && isset($result[$geo['merged']]) && count($result[$geo['merged']]) > 1) {
        $data = $result[$geo['merged']];
    }
    if (!$data) {
        continue;
    }

    $output = array_merge([
        'county' => $geo['county'],
        'town' => $geo['town'],
        'name' => $data['name'],
    ], array_diff_key($data, ['name' => true]));

    // Write one file per geojson VILLCODE
    foreach ($geo['villcodes'] as $villcode) {
        if (isset($written[$villcode])) {
            continue;
        }
        file_put_contents(
            $outputPath . '/' . $villcode . '.json',
            json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        $written[$villcode] = true;
        $count++;
    }
}

echo "Done. Generated {$count} cunli JSON files in data/elections/2020-2024/\n";
