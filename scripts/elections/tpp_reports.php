<?php
$rootPath = dirname(dirname(__DIR__));
$cunliPath = $rootPath . '/data/elections/2020-2024';
$outputPath = $rootPath . '/data/elections/tpp_reports';

if (!file_exists($outputPath)) {
    mkdir($outputPath, 0777, true);
}

// Load 2026 zones metadata from zones.csv
echo "Loading 2026 zones...\n";
$zonesCsv = array_map('str_getcsv', file($rootPath . '/data/council/2026/zones.csv'));
array_shift($zonesCsv); // remove header: city,zone,code,areas,name,party
$zones = [];
foreach ($zonesCsv as $row) {
    if (count($row) < 4) continue;
    list($city, $zoneName, $code, $areas) = $row;
    $townNames = explode('/', $areas);
    $zones[$code] = [
        'city' => $city,
        'zone_name' => $zoneName,
        'code' => $code,
        'town_names' => $townNames,
        'cunlis' => [],
    ];
}

// Load cunli.csv for village_code => zone mapping
echo "Loading 2026 cunli zones...\n";
$cunliCsv = array_map('str_getcsv', file($rootPath . '/data/council/2026/cunli.csv'));
array_shift($cunliCsv); // remove header: zone,city,area,village,village_code
$villcodeToZone = []; // village_code => zone code
foreach ($cunliCsv as $row) {
    if (count($row) < 5) continue;
    $villcodeToZone[$row[4]] = $row[0];
}

// Load geojson to build VILLCODE => town mapping
echo "Loading geojson...\n";
$geoJson = json_decode(file_get_contents('/home/kiang/public_html/taiwan_basecode/cunli/geo/20221118.json'), true);

$villToTown = [];
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

// Load all cunli data grouped by zone
echo "Processing cunli data...\n";
foreach (glob($cunliPath . '/*.json') as $jsonFile) {
    $villcode = basename($jsonFile, '.json');
    if (!isset($villToTown[$villcode])) {
        continue;
    }
    $meta = $villToTown[$villcode];
    if (!isset($villcodeToZone[$villcode])) {
        continue;
    }
    $zoneCode = $villcodeToZone[$villcode];

    $data = json_decode(file_get_contents($jsonFile), true);

    $c = [
        'villcode' => $villcode,
        'name' => $data['name'],
        'town' => $meta['town'],
    ];

    // 2020不分區
    if (isset($data['2020不分區'])) {
        $tpp = $data['2020不分區']['台灣民眾黨'] ?? 0;
        $total = array_sum($data['2020不分區']);
        $c['2020_tpp'] = $tpp;
        $c['2020_total'] = $total;
        $c['2020_rate'] = $total > 0 ? round($tpp / $total * 100, 2) : 0;
        $c['2020_kmt'] = $data['2020不分區']['中國國民黨'] ?? 0;
        $c['2020_dpp'] = $data['2020不分區']['民主進步黨'] ?? 0;
        $c['2020_kmt_rate'] = $total > 0 ? round($c['2020_kmt'] / $total * 100, 2) : 0;
        $c['2020_dpp_rate'] = $total > 0 ? round($c['2020_dpp'] / $total * 100, 2) : 0;
    } else {
        $c['2020_tpp'] = $c['2020_total'] = $c['2020_rate'] = 0;
        $c['2020_kmt'] = $c['2020_dpp'] = $c['2020_kmt_rate'] = $c['2020_dpp_rate'] = 0;
    }

    // 2022議員
    $c['2022_tpp_votes'] = 0;
    $c['2022_total'] = 0;
    $c['2022_rate'] = 0;
    $c['2022_tpp_cands'] = [];
    $c['2022_tpp_elected'] = false;
    $c['2022_all_cands'] = [];
    if (isset($data['2022議員'])) {
        $tppVotes = 0;
        $totalVotes = 0;
        foreach ($data['2022議員'] as $cand) {
            $totalVotes += $cand['votes'];
            $c['2022_all_cands'][] = $cand;
            if ($cand['party'] === '台灣民眾黨') {
                $tppVotes += $cand['votes'];
                $c['2022_tpp_cands'][] = [
                    'name' => $cand['name'],
                    'votes' => $cand['votes'],
                    'elected' => $cand['elected'],
                ];
                if ($cand['elected']) {
                    $c['2022_tpp_elected'] = true;
                }
            }
        }
        $c['2022_tpp_votes'] = $tppVotes;
        $c['2022_total'] = $totalVotes;
        $c['2022_rate'] = $totalVotes > 0 ? round($tppVotes / $totalVotes * 100, 2) : 0;
    }

    // 2024不分區
    if (isset($data['2024不分區'])) {
        $tpp = $data['2024不分區']['台灣民眾黨'] ?? 0;
        $total = array_sum($data['2024不分區']);
        $c['2024_tpp'] = $tpp;
        $c['2024_total'] = $total;
        $c['2024_rate'] = $total > 0 ? round($tpp / $total * 100, 2) : 0;
        $c['2024_kmt'] = $data['2024不分區']['中國國民黨'] ?? 0;
        $c['2024_dpp'] = $data['2024不分區']['民主進步黨'] ?? 0;
        $c['2024_kmt_rate'] = $total > 0 ? round($c['2024_kmt'] / $total * 100, 2) : 0;
        $c['2024_dpp_rate'] = $total > 0 ? round($c['2024_dpp'] / $total * 100, 2) : 0;
    } else {
        $c['2024_tpp'] = $c['2024_total'] = $c['2024_rate'] = 0;
        $c['2024_kmt'] = $c['2024_dpp'] = $c['2024_kmt_rate'] = $c['2024_dpp_rate'] = 0;
    }

    // 2024總統
    if (isset($data['2024總統'])) {
        $tpp = 0;
        $total = 0;
        foreach ($data['2024總統'] as $candName => $cand) {
            $total += $cand['votes'];
            if ($cand['party'] === '台灣民眾黨') {
                $tpp += $cand['votes'];
            }
        }
        $c['2024pres_tpp'] = $tpp;
        $c['2024pres_total'] = $total;
        $c['2024pres_rate'] = $total > 0 ? round($tpp / $total * 100, 2) : 0;
    } else {
        $c['2024pres_tpp'] = $c['2024pres_total'] = $c['2024pres_rate'] = 0;
    }

    $c['party_rate_change'] = round($c['2024_rate'] - $c['2020_rate'], 2);
    $c['pres_vs_party'] = round($c['2024pres_rate'] - $c['2024_rate'], 2);

    $zones[$zoneCode]['cunlis'][] = $c;
}

// Pre-compute global average conversion rate from zones where TPP had 2022 council candidates
$globalTpp2020 = 0;
$globalTpp2022 = 0;
foreach ($zones as $zone) {
    if (count($zone['cunlis']) === 0) continue;
    $hasTPPCand = false;
    $zoneTpp2020 = 0;
    $zoneTpp2022 = 0;
    foreach ($zone['cunlis'] as $c) {
        if (count($c['2022_tpp_cands']) > 0) {
            $hasTPPCand = true;
        }
        $zoneTpp2020 += $c['2020_tpp'];
        $zoneTpp2022 += $c['2022_tpp_votes'];
    }
    if ($hasTPPCand && $zoneTpp2020 > 0 && $zoneTpp2022 > 0) {
        $globalTpp2020 += $zoneTpp2020;
        $globalTpp2022 += $zoneTpp2022;
    }
}
$globalConversionRate = $globalTpp2020 > 0 ? $globalTpp2022 / $globalTpp2020 : 0;
echo "Global TPP 2020→2022 conversion rate: " . round($globalConversionRate * 100, 1) . "% (from zones with TPP candidates)\n";

// Generate JSON data files
echo "Generating JSON data...\n";
$count = 0;
$zoneIndex = []; // for index page
$rate = function ($num, $den) {
    return $den > 0 ? round($num / $den * 100, 2) : 0;
};

foreach ($zones as $zoneCode => $zone) {
    $cunlis = $zone['cunlis'];
    if (count($cunlis) === 0) {
        continue;
    }
    $city = $zone['city'];
    $zoneName = $zone['zone_name'];
    $townNames = $zone['town_names'];
    $areasStr = implode('、', $townNames);
    $numCunlis = count($cunlis);
    $isMultiTown = count($townNames) > 1;

    // === Zone-level aggregates ===
    $zoneTotals = [
        '2020_tpp' => 0, '2020_total' => 0, '2020_kmt' => 0, '2020_dpp' => 0,
        '2024_tpp' => 0, '2024_total' => 0, '2024_kmt' => 0, '2024_dpp' => 0,
        '2024pres_tpp' => 0, '2024pres_total' => 0,
        '2022_tpp_votes' => 0, '2022_total' => 0,
    ];
    foreach ($cunlis as $c) {
        foreach ($zoneTotals as $k => &$v) {
            $v += $c[$k];
        }
        unset($v);
    }
    $zone2020Rate = $rate($zoneTotals['2020_tpp'], $zoneTotals['2020_total']);
    $zone2024Rate = $rate($zoneTotals['2024_tpp'], $zoneTotals['2024_total']);
    $zone2024PresRate = $rate($zoneTotals['2024pres_tpp'], $zoneTotals['2024pres_total']);
    $zone2022Rate = $rate($zoneTotals['2022_tpp_votes'], $zoneTotals['2022_total']);
    $zoneRateChange = round($zone2024Rate - $zone2020Rate, 2);

    // Per-town subtotals (for multi-town zones)
    $townSubtotals = [];
    if ($isMultiTown) {
        foreach ($cunlis as $c) {
            $tn = $c['town'];
            if (!isset($townSubtotals[$tn])) {
                $townSubtotals[$tn] = [
                    '2020_tpp' => 0, '2020_total' => 0,
                    '2024_tpp' => 0, '2024_total' => 0,
                    '2024pres_tpp' => 0, '2024pres_total' => 0,
                    '2022_tpp_votes' => 0, '2022_total' => 0,
                    'cunli_count' => 0,
                ];
            }
            $townSubtotals[$tn]['2020_tpp'] += $c['2020_tpp'];
            $townSubtotals[$tn]['2020_total'] += $c['2020_total'];
            $townSubtotals[$tn]['2024_tpp'] += $c['2024_tpp'];
            $townSubtotals[$tn]['2024_total'] += $c['2024_total'];
            $townSubtotals[$tn]['2024pres_tpp'] += $c['2024pres_tpp'];
            $townSubtotals[$tn]['2024pres_total'] += $c['2024pres_total'];
            $townSubtotals[$tn]['2022_tpp_votes'] += $c['2022_tpp_votes'];
            $townSubtotals[$tn]['2022_total'] += $c['2022_total'];
            $townSubtotals[$tn]['cunli_count']++;
        }
    }

    // === 2022 Council competitive analysis ===
    $allCands2022 = [];
    foreach ($cunlis as $c) {
        foreach ($c['2022_all_cands'] as $cand) {
            $key = $cand['no'] . '_' . $cand['name'];
            if (!isset($allCands2022[$key])) {
                $allCands2022[$key] = [
                    'no' => $cand['no'],
                    'name' => $cand['name'],
                    'party' => $cand['party'],
                    'votes' => 0,
                    'elected' => $cand['elected'],
                    'cunli_count' => 0,
                ];
            }
            $allCands2022[$key]['votes'] += $cand['votes'];
            $allCands2022[$key]['cunli_count']++;
        }
    }
    usort($allCands2022, function ($a, $b) {
        return $b['votes'] <=> $a['votes'];
    });

    $electedCands2022 = array_filter($allCands2022, function ($c) { return $c['elected']; });
    $tppCands2022 = array_filter($allCands2022, function ($c) { return $c['party'] === '台灣民眾黨'; });
    $numSeats = count($electedCands2022);
    $numCands2022 = count($allCands2022);
    $electedVotes = array_column($electedCands2022, 'votes');
    $winThreshold2022 = count($electedVotes) > 0 ? min($electedVotes) : 0;
    $lastElected2022 = '';
    foreach ($allCands2022 as $cand) {
        if ($cand['elected'] && $cand['votes'] === $winThreshold2022) {
            $lastElected2022 = $cand['name'] . '（' . $cand['party'] . '）';
            break;
        }
    }

    // Estimate 2026 TPP vote potential
    // Use 2020→2022 conversion rate to predict how 2024 support translates to 2026 council votes
    $turnoutRatio2022 = ($zoneTotals['2024_total'] > 0 && $zoneTotals['2022_total'] > 0)
        ? $zoneTotals['2022_total'] / $zoneTotals['2024_total'] : 0.7;
    // Zone-level conversion: only meaningful if TPP had candidates in this zone in 2022
    $zoneHasTPPCand = false;
    foreach ($cunlis as $c) {
        if (count($c['2022_tpp_cands']) > 0) {
            $zoneHasTPPCand = true;
            break;
        }
    }
    $zoneConversionRate = ($zoneHasTPPCand && $zoneTotals['2020_tpp'] > 0 && $zoneTotals['2022_tpp_votes'] > 0)
        ? $zoneTotals['2022_tpp_votes'] / $zoneTotals['2020_tpp'] : $globalConversionRate;
    foreach ($cunlis as &$c) {
        $estTurnout = round($c['2024_total'] * $turnoutRatio2022);
        // Per-cunli conversion: use own rate if had TPP candidates, otherwise zone, otherwise global
        $cunliConversion = ($c['2020_tpp'] > 0 && $c['2022_tpp_votes'] > 0)
            ? $c['2022_tpp_votes'] / $c['2020_tpp'] : $zoneConversionRate;
        // Base estimate: apply conversion rate to 2024 party-list votes, scaled by turnout
        $c['est_2026_votes'] = round($c['2024_tpp'] * $cunliConversion * $turnoutRatio2022);
        // Optimistic: use 2024 rate directly (old method)
        $maxRate = max($c['2024_rate'], $c['2024pres_rate']);
        $c['est_2026_optimistic'] = round($estTurnout * $maxRate / 100);
    }
    unset($c);

    $totalEstVotes = array_sum(array_column($cunlis, 'est_2026_votes'));
    $totalEstOptimistic = array_sum(array_column($cunlis, 'est_2026_optimistic'));

    // Sort by party_rate_change descending
    usort($cunlis, function ($a, $b) {
        return $b['party_rate_change'] <=> $a['party_rate_change'];
    });

    // Classify cunlis
    $highGrowth = [];
    $growing = [];
    $stable = [];
    $declining = [];
    $strongholds = [];
    $competitive = [];
    $weak = [];

    foreach ($cunlis as $c) {
        if ($c['party_rate_change'] > $zoneRateChange + 2) {
            $highGrowth[] = $c;
        } elseif ($c['party_rate_change'] > 0) {
            $growing[] = $c;
        } elseif ($c['party_rate_change'] >= -2) {
            $stable[] = $c;
        } else {
            $declining[] = $c;
        }

        if ($c['2024_rate'] >= 25) {
            $strongholds[] = $c;
        } elseif ($c['2024_rate'] >= 15) {
            $competitive[] = $c;
        } else {
            $weak[] = $c;
        }
    }

    // Presidential > party (conversion potential)
    $presStronger = [];
    foreach ($cunlis as $c) {
        if ($c['pres_vs_party'] > 3 && $c['2024pres_rate'] > 0) {
            $presStronger[] = $c;
        }
    }
    usort($presStronger, function ($a, $b) {
        return $b['pres_vs_party'] <=> $a['pres_vs_party'];
    });

    // Top cunlis by estimated 2026 votes
    $byEstVotes = $cunlis;
    usort($byEstVotes, function ($a, $b) {
        return $b['est_2026_votes'] <=> $a['est_2026_votes'];
    });

    // Helper: determine tier
    $tierOf = function ($c) use ($zoneRateChange) {
        if ($c['2024_rate'] >= 25) return 'A';
        if ($c['party_rate_change'] > $zoneRateChange + 2) return 'B';
        if ($c['2024_rate'] >= 15) return 'D';
        return 'E';
    };

    // Collect councillor data
    $electedNames = [];
    $candCunlis = [];
    $isMultiTown = count($townNames) > 1;
    $cunliLabel = function ($c) use ($isMultiTown) {
        return $isMultiTown ? $c['town'] . $c['name'] : $c['name'];
    };
    foreach ($cunlis as $c) {
        foreach ($c['2022_tpp_cands'] as $cand) {
            if ($cand['elected']) $electedNames[$cand['name']] = true;
            $candCunlis[$cand['name']][] = [
                'cunli' => $cunliLabel($c), 'votes' => $cand['votes'],
                'rate_2022' => $c['2022_rate'], 'rate_2024' => $c['2024_rate'],
            ];
        }
    }
    $councillors = [];
    foreach ($candCunlis as $candName => $cls) {
        usort($cls, function ($a, $b) { return $b['votes'] <=> $a['votes']; });
        $councillors[] = [
            'name' => $candName,
            'elected' => isset($electedNames[$candName]),
            'total_votes' => array_sum(array_column($cls, 'votes')),
            'top_cunlis' => array_slice($cls, 0, 5),
        ];
    }

    $kmt2024Rate = $rate($zoneTotals['2024_kmt'], $zoneTotals['2024_total']);
    $dpp2024Rate = $rate($zoneTotals['2024_dpp'], $zoneTotals['2024_total']);

    $tppTotalVotes2022 = count($tppCands2022) > 0 ? array_sum(array_column($tppCands2022, 'votes')) : 0;
    $councilVsParty = ($zoneTotals['2020_tpp'] > 0 && $tppTotalVotes2022 > 0)
        ? round($tppTotalVotes2022 / $zoneTotals['2020_tpp'] * 100, 1) : 0;

    // Build clean cunli array for JSON (strip internal fields)
    $cunliRows = [];
    foreach ($cunlis as $c) {
        $tier = $tierOf($c);
        if ($c['party_rate_change'] < -2) $tier .= '⚠';
        $cunliRows[] = [
            'name' => $c['name'],
            'town' => $c['town'],
            'label' => $cunliLabel($c),
            'r2020' => $c['2020_rate'],
            'r2024' => $c['2024_rate'],
            'r2024p' => $c['2024pres_rate'],
            'r2022c' => $c['2022_rate'],
            'chg' => $c['party_rate_change'],
            'pvp' => $c['pres_vs_party'],
            'est' => $c['est_2026_votes'],
            'estO' => $c['est_2026_optimistic'],
            'tier' => $tier,
            'v2024' => $c['2024_tpp'],
            'total2024' => $c['2024_total'],
        ];
    }

    // Town subtotals
    $townSubs = [];
    if ($isMultiTown) {
        foreach ($townSubtotals as $tn => $ts) {
            $townSubs[] = [
                'name' => $tn,
                'cunlis' => $ts['cunli_count'],
                'r2020' => $rate($ts['2020_tpp'], $ts['2020_total']),
                'r2024' => $rate($ts['2024_tpp'], $ts['2024_total']),
                'r2024p' => $rate($ts['2024pres_tpp'], $ts['2024pres_total']),
                'chg' => round($rate($ts['2024_tpp'], $ts['2024_total']) - $rate($ts['2020_tpp'], $ts['2020_total']), 2),
            ];
        }
    }

    // Build JSON
    $json = [
        'zone' => $zoneName,
        'code' => $zoneCode,
        'city' => $city,
        'areas' => $areasStr,
        'towns' => $townNames,
        'multi_town' => $isMultiTown,
        'cunli_count' => $numCunlis,
        'seats_2022' => $numSeats,
        'cands_2022' => $numCands2022,
        'threshold_2022' => $winThreshold2022,
        'threshold_who' => $lastElected2022,
        'rates' => [
            'tpp_2020' => $zone2020Rate, 'tpp_2022c' => $zone2022Rate,
            'tpp_2024' => $zone2024Rate, 'tpp_2024p' => $zone2024PresRate,
            'kmt_2024' => $kmt2024Rate, 'dpp_2024' => $dpp2024Rate,
            'change' => $zoneRateChange,
        ],
        'votes' => [
            'tpp_2020' => $zoneTotals['2020_tpp'], 'tpp_2022c' => $zoneTotals['2022_tpp_votes'],
            'tpp_2024' => $zoneTotals['2024_tpp'], 'tpp_2024p' => $zoneTotals['2024pres_tpp'],
        ],
        'turnout_ratio' => round($turnoutRatio2022, 4),
        'conversion_2020_2022' => round($zoneConversionRate, 4),
        'est_base' => $totalEstVotes,
        'est_opt' => $totalEstOptimistic,
        'candidates_2022' => array_map(function ($c) {
            return ['no' => $c['no'], 'name' => $c['name'], 'party' => $c['party'],
                    'votes' => $c['votes'], 'elected' => $c['elected']];
        }, $allCands2022),
        'tpp_cands_2022' => array_values(array_map(function ($c) use ($winThreshold2022) {
            return ['name' => $c['name'], 'votes' => $c['votes'], 'elected' => $c['elected'],
                    'gap' => $c['votes'] - $winThreshold2022];
        }, $tppCands2022)),
        'conversion_rate' => $councilVsParty,
        'councillors' => $councillors,
        'elected_names' => array_keys($electedNames),
        'town_subtotals' => $townSubs,
        'cunlis' => $cunliRows,
        'generated' => date('Y-m-d H:i:s'),
    ];

    file_put_contents($outputPath . '/' . $zoneCode . '.json', json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $tppElected2022 = count(array_filter($tppCands2022, function ($c) { return $c['elected']; }));
    $zoneIndex[] = [
        'code' => $zoneCode,
        'zone' => $zoneName,
        'city' => $city,
        'areas' => $areasStr,
        'seats' => $numSeats,
        'r2020' => $zone2020Rate,
        'r2024' => $zone2024Rate,
        'r2024p' => $zone2024PresRate,
        'r2022c' => $zone2022Rate,
        'kmt' => $kmt2024Rate,
        'dpp' => $dpp2024Rate,
        'chg' => $zoneRateChange,
        'est' => $totalEstVotes,
        'estO' => $totalEstOptimistic,
        'threshold' => $winThreshold2022,
        'v2024' => $zoneTotals['2024_tpp'],
        'v2020' => $zoneTotals['2020_tpp'],
        'v2022c' => $tppTotalVotes2022,
        'conv' => $councilVsParty,
        'tpp_nominated' => count($tppCands2022),
        'tpp_elected' => $tppElected2022,
        'elected_names' => array_keys($electedNames),
        'cunli_count' => $numCunlis,
    ];
    $count++;
}

// Write zone index
file_put_contents($outputPath . '/zones.json', json_encode($zoneIndex, JSON_UNESCAPED_UNICODE));

echo "Done. Generated {$count} zone JSON files + zones.json in data/elections/tpp_reports/\n";
