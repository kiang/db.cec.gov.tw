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
            // Aggregated TPP totals
            '2020不分區' => ['tpp' => 0, 'total' => 0],
            '2022議員' => ['tpp_candidates' => [], 'tpp_votes' => 0, 'total_votes' => 0],
            '2024不分區' => ['tpp' => 0, 'total' => 0],
            '2024總統' => ['tpp' => 0, 'total' => 0],
        ];
    }

    $data = json_decode(file_get_contents($jsonFile), true);

    $cunliEntry = [
        'villcode' => $villcode,
        'name' => $data['name'],
    ];

    // 2020不分區
    if (isset($data['2020不分區'])) {
        $tpp = isset($data['2020不分區']['台灣民眾黨']) ? $data['2020不分區']['台灣民眾黨'] : 0;
        $total = array_sum($data['2020不分區']);
        $cunliEntry['2020不分區'] = [
            'tpp_votes' => $tpp,
            'total_votes' => $total,
            'tpp_rate' => $total > 0 ? round($tpp / $total * 100, 2) : 0,
        ];
        $towns[$towncode]['2020不分區']['tpp'] += $tpp;
        $towns[$towncode]['2020不分區']['total'] += $total;
    }

    // 2022議員
    if (isset($data['2022議員'])) {
        $tppVotes = 0;
        $totalVotes = 0;
        $tppCandidates = [];
        foreach ($data['2022議員'] as $cand) {
            $totalVotes += $cand['votes'];
            if ($cand['party'] === '台灣民眾黨') {
                $tppVotes += $cand['votes'];
                $candKey = $cand['name'];
                $tppCandidates[$candKey] = [
                    'name' => $cand['name'],
                    'votes' => $cand['votes'],
                    'elected' => $cand['elected'],
                ];
            }
        }
        $cunliEntry['2022議員'] = [
            'tpp_votes' => $tppVotes,
            'total_votes' => $totalVotes,
            'tpp_rate' => $totalVotes > 0 ? round($tppVotes / $totalVotes * 100, 2) : 0,
        ];
        $towns[$towncode]['2022議員']['tpp_votes'] += $tppVotes;
        $towns[$towncode]['2022議員']['total_votes'] += $totalVotes;
        foreach ($tppCandidates as $key => $info) {
            if (!isset($towns[$towncode]['2022議員']['tpp_candidates'][$key])) {
                $towns[$towncode]['2022議員']['tpp_candidates'][$key] = [
                    'name' => $info['name'],
                    'votes' => 0,
                    'elected' => $info['elected'],
                ];
            }
            $towns[$towncode]['2022議員']['tpp_candidates'][$key]['votes'] += $info['votes'];
        }
    }

    // 2024不分區
    if (isset($data['2024不分區'])) {
        $tpp = isset($data['2024不分區']['台灣民眾黨']) ? $data['2024不分區']['台灣民眾黨'] : 0;
        $total = array_sum($data['2024不分區']);
        $cunliEntry['2024不分區'] = [
            'tpp_votes' => $tpp,
            'total_votes' => $total,
            'tpp_rate' => $total > 0 ? round($tpp / $total * 100, 2) : 0,
        ];
        $towns[$towncode]['2024不分區']['tpp'] += $tpp;
        $towns[$towncode]['2024不分區']['total'] += $total;
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
        $cunliEntry['2024總統'] = [
            'tpp_votes' => $tpp,
            'total_votes' => $total,
            'tpp_rate' => $total > 0 ? round($tpp / $total * 100, 2) : 0,
        ];
        $towns[$towncode]['2024總統']['tpp'] += $tpp;
        $towns[$towncode]['2024總統']['total'] += $total;
    }

    $towns[$towncode]['cunlis'][] = $cunliEntry;
}

// Build output
echo "Writing town JSON files...\n";
$count = 0;
foreach ($towns as $towncode => $town) {
    // Sort cunlis by 2024 party list TPP rate descending
    usort($town['cunlis'], function ($a, $b) {
        $rateA = isset($a['2024不分區']['tpp_rate']) ? $a['2024不分區']['tpp_rate'] : 0;
        $rateB = isset($b['2024不分區']['tpp_rate']) ? $b['2024不分區']['tpp_rate'] : 0;
        return $rateB <=> $rateA;
    });

    // Build town-level summary
    $summary = [
        '2020不分區' => [
            'tpp_votes' => $town['2020不分區']['tpp'],
            'total_votes' => $town['2020不分區']['total'],
            'tpp_rate' => $town['2020不分區']['total'] > 0
                ? round($town['2020不分區']['tpp'] / $town['2020不分區']['total'] * 100, 2) : 0,
        ],
        '2022議員' => [
            'tpp_votes' => $town['2022議員']['tpp_votes'],
            'total_votes' => $town['2022議員']['total_votes'],
            'tpp_rate' => $town['2022議員']['total_votes'] > 0
                ? round($town['2022議員']['tpp_votes'] / $town['2022議員']['total_votes'] * 100, 2) : 0,
            'candidates' => array_values($town['2022議員']['tpp_candidates']),
        ],
        '2024不分區' => [
            'tpp_votes' => $town['2024不分區']['tpp'],
            'total_votes' => $town['2024不分區']['total'],
            'tpp_rate' => $town['2024不分區']['total'] > 0
                ? round($town['2024不分區']['tpp'] / $town['2024不分區']['total'] * 100, 2) : 0,
        ],
        '2024總統' => [
            'tpp_votes' => $town['2024總統']['tpp'],
            'total_votes' => $town['2024總統']['total'],
            'tpp_rate' => $town['2024總統']['total'] > 0
                ? round($town['2024總統']['tpp'] / $town['2024總統']['total'] * 100, 2) : 0,
        ],
    ];

    // Rate change from 2020 to 2024 party list
    $summary['party_rate_change'] = round($summary['2024不分區']['tpp_rate'] - $summary['2020不分區']['tpp_rate'], 2);

    $output = [
        'county' => $town['county'],
        'town' => $town['town'],
        'summary' => $summary,
        'cunlis' => $town['cunlis'],
    ];

    file_put_contents(
        $outputPath . '/' . $towncode . '.json',
        json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
    $count++;
}

echo "Done. Generated {$count} town JSON files in data/elections/tpp/\n";
