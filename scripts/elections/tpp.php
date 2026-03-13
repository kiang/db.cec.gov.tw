<?php
$rootPath = dirname(dirname(__DIR__));
$cunliPath = $rootPath . '/data/elections/2020-2024';
$outputPath = $rootPath . '/data/elections/tpp';

if (!file_exists($outputPath)) {
    mkdir($outputPath, 0777, true);
}

// Load geojson to build VILLCODE => town mapping
echo "Loading geojson...\n";
$geoJson = json_decode(file_get_contents('/home/kiang/public_html/taiwan_basecode/cunli/geo/20221118.json'), true);

$villToTown = []; // VILLCODE => [towncode, countyname, townname, villname]
foreach ($geoJson['features'] as $f) {
    $p = $f['properties'];
    if (empty($p['VILLCODE']) || empty($p['VILLNAME'])) {
        continue;
    }
    $villToTown[$p['VILLCODE']] = [
        'towncode' => $p['TOWNCODE'],
        'county' => $p['COUNTYNAME'],
        'town' => $p['TOWNNAME'],
        'villname' => $p['VILLNAME'],
    ];
}
unset($geoJson);

// Group cunli files by town
echo "Processing cunli data...\n";
$towns = [];
foreach (glob($cunliPath . '/*.json') as $jsonFile) {
    $villcode = basename($jsonFile, '.json');
    if (!isset($villToTown[$villcode])) {
        continue;
    }
    $meta = $villToTown[$villcode];
    $towncode = $meta['towncode'];

    if (!isset($towns[$towncode])) {
        $towns[$towncode] = [
            'county' => $meta['county'],
            'town' => $meta['town'],
            'cunlis' => [],
        ];
    }

    $data = json_decode(file_get_contents($jsonFile), true);

    $cunliEntry = [
        'villcode' => $villcode,
        'county' => $meta['county'],
        'town' => $meta['town'],
        'name' => $data['name'],
    ];

    // 2020不分區
    if (isset($data['2020不分區'])) {
        $tpp = isset($data['2020不分區']['台灣民眾黨']) ? $data['2020不分區']['台灣民眾黨'] : 0;
        $total = array_sum($data['2020不分區']);
        $cunliEntry['2020_tpp_votes'] = $tpp;
        $cunliEntry['2020_total_votes'] = $total;
        $cunliEntry['2020_tpp_rate'] = $total > 0 ? round($tpp / $total * 100, 2) : 0;
    } else {
        $cunliEntry['2020_tpp_votes'] = 0;
        $cunliEntry['2020_total_votes'] = 0;
        $cunliEntry['2020_tpp_rate'] = 0;
    }

    // 2022議員
    if (isset($data['2022議員'])) {
        $tppVotes = 0;
        $totalVotes = 0;
        $tppCandNames = [];
        $tppElected = false;
        foreach ($data['2022議員'] as $cand) {
            $totalVotes += $cand['votes'];
            if ($cand['party'] === '台灣民眾黨') {
                $tppVotes += $cand['votes'];
                $tppCandNames[] = $cand['name'];
                if ($cand['elected']) {
                    $tppElected = true;
                }
            }
        }
        $cunliEntry['2022_tpp_votes'] = $tppVotes;
        $cunliEntry['2022_total_votes'] = $totalVotes;
        $cunliEntry['2022_tpp_rate'] = $totalVotes > 0 ? round($tppVotes / $totalVotes * 100, 2) : 0;
        $cunliEntry['2022_tpp_candidates'] = implode('/', $tppCandNames);
        $cunliEntry['2022_tpp_elected'] = $tppElected ? 'Y' : '';
    } else {
        $cunliEntry['2022_tpp_votes'] = 0;
        $cunliEntry['2022_total_votes'] = 0;
        $cunliEntry['2022_tpp_rate'] = 0;
        $cunliEntry['2022_tpp_candidates'] = '';
        $cunliEntry['2022_tpp_elected'] = '';
    }

    // 2024不分區
    if (isset($data['2024不分區'])) {
        $tpp = isset($data['2024不分區']['台灣民眾黨']) ? $data['2024不分區']['台灣民眾黨'] : 0;
        $total = array_sum($data['2024不分區']);
        $cunliEntry['2024_tpp_votes'] = $tpp;
        $cunliEntry['2024_total_votes'] = $total;
        $cunliEntry['2024_tpp_rate'] = $total > 0 ? round($tpp / $total * 100, 2) : 0;
    } else {
        $cunliEntry['2024_tpp_votes'] = 0;
        $cunliEntry['2024_total_votes'] = 0;
        $cunliEntry['2024_tpp_rate'] = 0;
    }

    // 2024總統
    if (isset($data['2024總統'])) {
        $tpp = 0;
        $total = 0;
        foreach ($data['2024總統'] as $cand) {
            $total += $cand['votes'];
            if ($cand['party'] === '台灣民眾黨') {
                $tpp += $cand['votes'];
            }
        }
        $cunliEntry['2024pres_tpp_votes'] = $tpp;
        $cunliEntry['2024pres_total_votes'] = $total;
        $cunliEntry['2024pres_tpp_rate'] = $total > 0 ? round($tpp / $total * 100, 2) : 0;
    } else {
        $cunliEntry['2024pres_tpp_votes'] = 0;
        $cunliEntry['2024pres_total_votes'] = 0;
        $cunliEntry['2024pres_tpp_rate'] = 0;
    }

    // Rate change: 2024不分區 - 2020不分區
    $cunliEntry['party_rate_change'] = round($cunliEntry['2024_tpp_rate'] - $cunliEntry['2020_tpp_rate'], 2);

    $towns[$towncode]['cunlis'][] = $cunliEntry;
}

// Write per-town CSV files
echo "Writing town CSV files...\n";
$header = [
    'villcode', 'county', 'town', 'name',
    '2020_tpp_votes', '2020_total_votes', '2020_tpp_rate',
    '2022_tpp_votes', '2022_total_votes', '2022_tpp_rate', '2022_tpp_candidates', '2022_tpp_elected',
    '2024_tpp_votes', '2024_total_votes', '2024_tpp_rate',
    '2024pres_tpp_votes', '2024pres_total_votes', '2024pres_tpp_rate',
    'party_rate_change',
];

$count = 0;
foreach ($towns as $towncode => $town) {
    // Sort cunlis by party_rate_change descending
    usort($town['cunlis'], function ($a, $b) {
        return $b['party_rate_change'] <=> $a['party_rate_change'];
    });

    $fh = fopen($outputPath . '/' . $towncode . '.csv', 'w');
    fputcsv($fh, $header);
    foreach ($town['cunlis'] as $row) {
        fputcsv($fh, $row);
    }
    fclose($fh);
    $count++;
}

echo "Done. Generated {$count} town CSV files in data/elections/tpp/\n";
