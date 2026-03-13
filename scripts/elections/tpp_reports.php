<?php
$rootPath = dirname(dirname(__DIR__));
$cunliPath = $rootPath . '/data/elections/2020-2024';
$outputPath = $rootPath . '/data/elections/tpp_reports';

if (!file_exists($outputPath)) {
    mkdir($outputPath, 0777, true);
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

// Load all cunli data grouped by town
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

    $c = [
        'villcode' => $villcode,
        'name' => $data['name'],
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

    // 2022議員 — full candidate data for competitive analysis
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

    $towns[$towncode]['cunlis'][] = $c;
}

// Generate reports
echo "Generating reports...\n";
$count = 0;
foreach ($towns as $towncode => $town) {
    $county = $town['county'];
    $townName = $town['town'];
    $cunlis = $town['cunlis'];
    $numCunlis = count($cunlis);

    // === Town-level aggregates ===
    $townTotals = [
        '2020_tpp' => 0, '2020_total' => 0, '2020_kmt' => 0, '2020_dpp' => 0,
        '2024_tpp' => 0, '2024_total' => 0, '2024_kmt' => 0, '2024_dpp' => 0,
        '2024pres_tpp' => 0, '2024pres_total' => 0,
        '2022_tpp_votes' => 0, '2022_total' => 0,
    ];
    foreach ($cunlis as $c) {
        foreach ($townTotals as $k => &$v) {
            $v += $c[$k];
        }
        unset($v);
    }
    $rate = function ($num, $den) {
        return $den > 0 ? round($num / $den * 100, 2) : 0;
    };
    $town2020Rate = $rate($townTotals['2020_tpp'], $townTotals['2020_total']);
    $town2024Rate = $rate($townTotals['2024_tpp'], $townTotals['2024_total']);
    $town2024PresRate = $rate($townTotals['2024pres_tpp'], $townTotals['2024pres_total']);
    $town2022Rate = $rate($townTotals['2022_tpp_votes'], $townTotals['2022_total']);
    $townRateChange = round($town2024Rate - $town2020Rate, 2);

    // === 2022 Council competitive analysis ===
    // Aggregate all candidates across cunlis (same candidates appear in each cunli within a zone)
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
    // Compute per-candidate averages (each candidate counted once per cunli)
    // The total votes are summed across cunlis, representing town-wide totals
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

    // Estimate 2026 TPP vote potential per cunli
    // Use 2024 party vote rate as base, adjusted by 2022 council turnout ratio
    $turnoutRatio2022 = ($townTotals['2024_total'] > 0 && $townTotals['2022_total'] > 0)
        ? $townTotals['2022_total'] / $townTotals['2024_total'] : 0.7;
    foreach ($cunlis as &$c) {
        // Estimate: 2022-equivalent turnout * current TPP party rate
        $estTurnout = round($c['2024_total'] * $turnoutRatio2022);
        $c['est_2026_votes'] = round($estTurnout * $c['2024_rate'] / 100);
        // Optimistic: use higher of party vote and presidential vote rate
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
        if ($c['party_rate_change'] > $townRateChange + 2) {
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

    // ===== BUILD REPORT =====
    $report = '';
    $report .= "╔══════════════════════════════════════════════════════════════════╗\n";
    $report .= "  2026 議員選舉備戰報告 — {$county}{$townName}（台灣民眾黨）\n";
    $report .= "╚══════════════════════════════════════════════════════════════════╝\n\n";

    // === Section 1: Election landscape ===
    $report .= "【一、選區基本資訊與選情概覽】\n\n";
    $report .= "  選區：{$county}{$townName}　村里數：{$numCunlis}\n";
    if ($numSeats > 0) {
        $report .= "  2022 應選席次：{$numSeats}　參選人數：{$numCands2022}\n";
        $report .= "  2022 當選門檻（最低當選票數）：{$winThreshold2022} 票";
        if ($lastElected2022) {
            $report .= "（{$lastElected2022}）";
        }
        $report .= "\n";
    }
    $report .= "\n";

    $report .= "  歷屆民眾黨得票：\n";
    $report .= "    2020 不分區政黨票：{$town2020Rate}%（{$townTotals['2020_tpp']} 票）\n";
    $report .= "    2022 議員候選人票：{$town2022Rate}%（{$townTotals['2022_tpp_votes']} 票）\n";
    $report .= "    2024 不分區政黨票：{$town2024Rate}%（{$townTotals['2024_tpp']} 票）\n";
    $report .= "    2024 總統票：　　　{$town2024PresRate}%（{$townTotals['2024pres_tpp']} 票）\n";
    $report .= "    政黨票成長（2020→2024）：" . ($townRateChange >= 0 ? '+' : '') . "{$townRateChange} 個百分點\n\n";

    // Three-party landscape
    $kmt2024Rate = $rate($townTotals['2024_kmt'], $townTotals['2024_total']);
    $dpp2024Rate = $rate($townTotals['2024_dpp'], $townTotals['2024_total']);
    $report .= "  2024 三黨政黨票版圖：\n";
    $report .= "    民進黨 {$dpp2024Rate}% ／ 國民黨 {$kmt2024Rate}% ／ 民眾黨 {$town2024Rate}%\n\n";

    // === Section 2: 2022 results deep dive ===
    $report .= "【二、2022 議員選舉結果分析】\n\n";

    if (count($allCands2022) > 0) {
        $report .= "  候選人得票排名：\n";
        $report .= sprintf("  %3s %-8s %-20s %8s %6s\n", '序', '姓名', '政黨', '得票數', '結果');
        $report .= "  " . str_repeat('─', 52) . "\n";
        foreach ($allCands2022 as $cand) {
            $result = $cand['elected'] ? '當選' : '';
            $isTpp = $cand['party'] === '台灣民眾黨' ? '◆' : ' ';
            $report .= sprintf("  %s%2d %-8s %-20s %7d %6s\n",
                $isTpp, $cand['no'], $cand['name'], $cand['party'], $cand['votes'], $result);
        }
        $report .= "\n";

        // TPP candidate performance analysis
        if (count($tppCands2022) > 0) {
            $tppTotalVotes = array_sum(array_column($tppCands2022, 'votes'));
            $tppElectedCount = count(array_filter($tppCands2022, function ($c) { return $c['elected']; }));
            $report .= "  民眾黨候選人表現：\n";
            $report .= "    提名人數：" . count($tppCands2022) . "　當選：{$tppElectedCount}\n";
            $report .= "    合計得票：{$tppTotalVotes}\n";
            if ($winThreshold2022 > 0) {
                foreach ($tppCands2022 as $tc) {
                    $gap = $tc['votes'] - $winThreshold2022;
                    $gapStr = $gap >= 0 ? "超過門檻 +{$gap} 票" : "距門檻差 " . abs($gap) . " 票";
                    $eStr = $tc['elected'] ? '當選' : '落選';
                    $report .= "    {$tc['name']}：{$tc['votes']} 票（{$eStr}，{$gapStr}）\n";
                }
            }
            $report .= "\n";

            // Compare TPP council vote vs party vote
            if ($townTotals['2020_tpp'] > 0) {
                $councilVsParty = round($tppTotalVotes / $townTotals['2020_tpp'] * 100, 1);
                $report .= "  議員票 vs 政黨票轉換率：{$councilVsParty}%\n";
                $report .= "  （2022議員票 {$tppTotalVotes} ÷ 2020政黨票 {$townTotals['2020_tpp']}）\n";
                if ($councilVsParty < 50) {
                    $report .= "  ⚠ 轉換率偏低，大量政黨票支持者未投給議員候選人，\n";
                    $report .= "    2026 需強化候選人知名度與政黨連結。\n";
                } elseif ($councilVsParty < 80) {
                    $report .= "  △ 轉換率中等，仍有提升空間。\n";
                } else {
                    $report .= "  ○ 轉換率良好，候選人有效承接政黨票。\n";
                }
                $report .= "\n";
            }
        } else {
            $report .= "  ⚠ 2022 年本區無民眾黨議員候選人。\n";
            $report .= "  2026 年為首次提名，需從零建立候選人知名度。\n\n";
        }
    } else {
        $report .= "  （本區無 2022 議員選舉資料）\n\n";
    }

    // === Section 3: 2026 vote estimation ===
    $report .= "【三、2026 議員選票預估】\n\n";

    $report .= "  預估方式：以 2024 政黨票得票率 × 議員選舉投票率推算\n";
    $report .= "  （議員選舉投票率以 2022/2024 投票人數比 " . round($turnoutRatio2022 * 100, 1) . "% 估算）\n\n";

    $report .= "  基本盤預估（以 2024 政黨票率）：{$totalEstVotes} 票\n";
    $report .= "  樂觀預估（含總統票外溢效應）：　{$totalEstOptimistic} 票\n";
    if ($winThreshold2022 > 0) {
        $report .= "  2022 當選門檻：　　　　　　　　{$winThreshold2022} 票\n";
        if ($totalEstVotes >= $winThreshold2022) {
            $surplus = $totalEstVotes - $winThreshold2022;
            $report .= "  ✓ 基本盤已超過 2022 當選門檻（餘裕 +{$surplus} 票）\n";
            // Can we win 2 seats?
            if (count($tppCands2022) > 1 || $totalEstVotes >= $winThreshold2022 * 2) {
                $report .= "  ✓ 預估票數可能支撐 2 席，可評估提名 2 人策略\n";
            }
        } else {
            $deficit = $winThreshold2022 - $totalEstVotes;
            $report .= "  △ 基本盤距 2022 門檻尚差 {$deficit} 票，需加強經營\n";
            if ($totalEstOptimistic >= $winThreshold2022) {
                $report .= "  ✓ 樂觀預估可達門檻，關鍵在於候選人能否承接政黨支持\n";
            }
        }
    }
    $report .= "\n";

    // Top cunlis by estimated votes
    $report .= "  各村里預估票數 TOP 10：\n";
    $topEst = array_slice($byEstVotes, 0, min(10, count($byEstVotes)));
    $topEstTotal = 0;
    foreach ($topEst as $c) {
        $report .= "    {$c['name']}：約 {$c['est_2026_votes']} 票（樂觀 {$c['est_2026_optimistic']}）\n";
        $topEstTotal += $c['est_2026_votes'];
    }
    $pct = $totalEstVotes > 0 ? round($topEstTotal / $totalEstVotes * 100, 1) : 0;
    $report .= "  TOP 10 合計約 {$topEstTotal} 票，佔全區預估 {$pct}%\n\n";

    // === Section 4: Strategy by cunli tier ===
    $report .= "【四、村里分級經營策略】\n\n";

    // Tier A: Strongholds
    $report .= "  【A 級｜核心票倉】2024政黨票 ≥ 25%，共 " . count($strongholds) . " 個村里\n";
    $report .= "  策略：鞏固基盤，維繫既有支持者，確保投票率。\n";
    if (count($strongholds) > 0) {
        $shVotes = array_sum(array_column($strongholds, 'est_2026_votes'));
        $report .= "  預估可貢獻：約 {$shVotes} 票\n\n";
        foreach ($strongholds as $c) {
            $report .= "    {$c['name']}：政黨 {$c['2024_rate']}%";
            $report .= "，總統 {$c['2024pres_rate']}%";
            $report .= "，預估 {$c['est_2026_votes']} 票";
            if ($c['2022_tpp_elected']) {
                $report .= "，有現任議員基礎";
            }
            $report .= "\n";
        }
    } else {
        $report .= "  （本區目前無核心票倉村里）\n";
    }
    $report .= "\n";

    // Tier B: High growth
    $report .= "  【B 級｜高成長區】成長幅度超過全區平均 +2%，共 " . count($highGrowth) . " 個村里\n";
    $report .= "  策略：趁勢加碼，加強社區活動與里民互動，將動能轉為穩定支持。\n";
    if (count($highGrowth) > 0) {
        $hgVotes = array_sum(array_column($highGrowth, 'est_2026_votes'));
        $report .= "  預估可貢獻：約 {$hgVotes} 票\n\n";
        foreach ($highGrowth as $c) {
            $report .= "    {$c['name']}：{$c['2020_rate']}% → {$c['2024_rate']}%（+{$c['party_rate_change']}）";
            $report .= "，預估 {$c['est_2026_votes']} 票\n";
        }
    }
    $report .= "\n";

    // Tier C: Conversion targets (pres > party)
    if (count($presStronger) > 0) {
        $top = array_slice($presStronger, 0, min(15, count($presStronger)));
        $report .= "  【C 級｜潛力轉化區】總統票高於政黨票 > 3%，共 " . count($presStronger) . " 個村里\n";
        $report .= "  策略：這些選民「認人不認黨」，需強化政黨品牌與候選人連結，\n";
        $report .= "  讓總統選舉的個人支持轉化為議員選舉的政黨支持。\n";
        $psVotes = 0;
        foreach ($presStronger as $c) {
            $psVotes += round(($c['2024pres_rate'] - $c['2024_rate']) / 100 * $c['2024_total'] * $turnoutRatio2022);
        }
        $report .= "  若能完全轉化，額外可得：約 {$psVotes} 票\n\n";
        foreach ($top as $c) {
            $extraVotes = round(($c['2024pres_rate'] - $c['2024_rate']) / 100 * $c['2024_total'] * $turnoutRatio2022);
            $report .= "    {$c['name']}：總統 {$c['2024pres_rate']}% vs 政黨 {$c['2024_rate']}%";
            $report .= "（差距 +{$c['pres_vs_party']}，潛力 +{$extraVotes} 票）\n";
        }
        $report .= "\n";
    }

    // Tier D: Competitive
    $competitiveOnly = array_values(array_filter($competitive, function ($c) use ($strongholds) {
        foreach ($strongholds as $s) {
            if ($s['villcode'] === $c['villcode']) return false;
        }
        return true;
    }));
    if (count($competitiveOnly) > 0) {
        usort($competitiveOnly, function ($a, $b) {
            return $b['est_2026_votes'] <=> $a['est_2026_votes'];
        });
        $coVotes = array_sum(array_column($competitiveOnly, 'est_2026_votes'));
        $report .= "  【D 級｜競爭區域】2024政黨票 15-25%，共 " . count($competitiveOnly) . " 個村里\n";
        $report .= "  策略：積極拓展，安排服務據點或活動，有機會提升至核心票倉。\n";
        $report .= "  預估可貢獻：約 {$coVotes} 票\n\n";
        foreach ($competitiveOnly as $c) {
            $trend = $c['party_rate_change'] >= 0 ? '↑' : '↓';
            $report .= "    {$c['name']}：{$c['2024_rate']}%（{$trend}" . abs($c['party_rate_change']) . "），預估 {$c['est_2026_votes']} 票\n";
        }
        $report .= "\n";
    }

    // Tier E: Weak areas
    if (count($weak) > 0) {
        $wkVotes = array_sum(array_column($weak, 'est_2026_votes'));
        $report .= "  【E 級｜低支持區域】2024政黨票 < 15%，共 " . count($weak) . " 個村里\n";
        $report .= "  策略：不宜投入過多資源，以議題經營建立接觸點，長期佈局。\n";
        $report .= "  預估可貢獻：約 {$wkVotes} 票\n\n";
        foreach ($weak as $c) {
            $trend = $c['party_rate_change'] >= 0 ? '↑' : '↓';
            $report .= "    {$c['name']}：{$c['2024_rate']}%（{$trend}" . abs($c['party_rate_change']) . "），預估 {$c['est_2026_votes']} 票\n";
        }
        $report .= "\n";
    }

    // Declining areas warning
    if (count($declining) > 0) {
        $report .= "  【⚠ 警示｜支持度下滑】成長 < -2%，共 " . count($declining) . " 個村里\n";
        $report .= "  策略：需實地訪查，了解退步原因，評估是否有在地議題未被回應。\n\n";
        foreach ($declining as $c) {
            $report .= "    {$c['name']}：{$c['2020_rate']}% → {$c['2024_rate']}%（{$c['party_rate_change']}）\n";
        }
        $report .= "\n";
    }

    // === Section 5: 2022 incumbent councillor info ===
    $report .= "【五、2022 現任議員與地方經營基礎】\n\n";

    $electedNames = [];
    $candCunlis = [];
    foreach ($cunlis as $c) {
        foreach ($c['2022_tpp_cands'] as $cand) {
            if ($cand['elected']) {
                $electedNames[$cand['name']] = true;
            }
            $candCunlis[$cand['name']][] = [
                'cunli_name' => $c['name'],
                'votes' => $cand['votes'],
                'rate' => $c['2022_rate'],
                '2024_rate' => $c['2024_rate'],
            ];
        }
    }

    if (count($electedNames) > 0) {
        $report .= "  現任民眾黨議員：" . implode('、', array_keys($electedNames)) . "\n\n";
        foreach ($candCunlis as $candName => $cls) {
            $isElected = isset($electedNames[$candName]);
            usort($cls, function ($a, $b) { return $b['votes'] <=> $a['votes']; });
            $totalCandVotes = array_sum(array_column($cls, 'votes'));
            $label = $isElected ? '當選' : '落選';
            $report .= "    {$candName}（{$label}，本區得票 {$totalCandVotes}）\n";
            $report .= "    票倉村里 TOP 5：\n";
            $topCls = array_slice($cls, 0, min(5, count($cls)));
            foreach ($topCls as $cl) {
                $report .= "      {$cl['cunli_name']}：{$cl['votes']} 票";
                $report .= "（2022議員 {$cl['rate']}% → 2024政黨 {$cl['2024_rate']}%）\n";
            }
            $report .= "\n";
        }

        $report .= "  現任議員經營建議：\n";
        $report .= "    - 以現任議員服務處為據點，向周邊村里擴展接觸面\n";
        $report .= "    - 盤點議員任內服務成果，作為 2026 競選素材\n";
        $report .= "    - 若規劃提名第二人，需評估票源分散風險\n\n";
    } else {
        $hasCands = count($candCunlis) > 0;
        if ($hasCands) {
            $report .= "  本區 2022 年無民眾黨當選議員，但有候選人參選紀錄：\n\n";
            foreach ($candCunlis as $candName => $cls) {
                usort($cls, function ($a, $b) { return $b['votes'] <=> $a['votes']; });
                $totalCandVotes = array_sum(array_column($cls, 'votes'));
                $report .= "    {$candName}（落選，得票 {$totalCandVotes}）\n";
                $topCls = array_slice($cls, 0, min(5, count($cls)));
                foreach ($topCls as $cl) {
                    $report .= "      {$cl['cunli_name']}：{$cl['votes']} 票\n";
                }
                $report .= "\n";
            }
            $report .= "  經營建議：\n";
            $report .= "    - 檢討 2022 落選原因，評估是否再次提名或更換人選\n";
            $report .= "    - 2024 政黨票已大幅成長，新候選人有更高基本盤可運用\n\n";
        } else {
            $report .= "  本區 2022 年無民眾黨議員候選人參選紀錄。\n";
            $report .= "  2026 年為首次提名，需從零建立候選人知名度。\n\n";
            $report .= "  經營建議：\n";
            $report .= "    - 提早佈局，讓候選人深入在地服務\n";
            $report .= "    - 優先經營 A 級與 B 級村里，快速建立口碑\n";
            $report .= "    - 善用 2024 政黨票基礎，連結政黨認同選民\n\n";
        }
    }

    // === Section 6: Full cunli ranking table ===
    $report .= "【六、村里完整數據表】\n\n";
    $report .= sprintf("  %-12s %7s %7s %6s %7s %6s %6s %6s\n",
        '村里', '2024政黨', '2024總統', '成長', '2022議員', '預估票', '樂觀', '分級');
    $report .= "  " . str_repeat('─', 68) . "\n";
    foreach ($cunlis as $c) {
        $changeStr = ($c['party_rate_change'] >= 0 ? '+' : '') . $c['party_rate_change'];
        $council = $c['2022_rate'] > 0 ? $c['2022_rate'] . '%' : '-';
        // Determine tier
        if ($c['2024_rate'] >= 25) {
            $tier = 'A';
        } elseif ($c['party_rate_change'] > $townRateChange + 2) {
            $tier = 'B';
        } elseif ($c['2024_rate'] >= 15) {
            $tier = 'D';
        } else {
            $tier = 'E';
        }
        if ($c['party_rate_change'] < -2) {
            $tier .= '⚠';
        }
        $report .= sprintf("  %-12s %6s%% %6s%% %6s %7s %6s %6s %4s\n",
            $c['name'],
            $c['2024_rate'],
            $c['2024pres_rate'],
            $changeStr,
            $council,
            $c['est_2026_votes'],
            $c['est_2026_optimistic'],
            $tier
        );
    }
    $report .= sprintf("\n  %-12s %37s %6s %6s\n", '合計', '', $totalEstVotes, $totalEstOptimistic);
    $report .= "\n";

    // === Section 7: Action plan ===
    $report .= "【七、2026 備戰重點行動】\n\n";

    $actionNum = 1;

    // Action 1: Estimated vote vs threshold
    if ($winThreshold2022 > 0) {
        if ($totalEstVotes >= $winThreshold2022) {
            $report .= "  {$actionNum}. 【當選可行性：高】基本盤 {$totalEstVotes} 票已超過 2022 門檻 {$winThreshold2022} 票，\n";
            $report .= "     關鍵在於候選人能否有效承接政黨票。2022 議員選舉的轉換率\n";
            $report .= "     是重要指標，需確保 2026 轉換率達到 80% 以上。\n\n";
        } else {
            $deficit = $winThreshold2022 - $totalEstVotes;
            $report .= "  {$actionNum}. 【當選可行性：需努力】基本盤預估 {$totalEstVotes} 票，距 2022 門檻\n";
            $report .= "     {$winThreshold2022} 票尚差 {$deficit} 票。需要：\n";
            $report .= "     (a) 提高候選人個人知名度，拉抬得票率\n";
            $report .= "     (b) 強化總統票→議員票的轉化（潛力約 " . ($totalEstOptimistic - $totalEstVotes) . " 票）\n";
            $report .= "     (c) 提高支持者投票率，降低棄投\n\n";
        }
        $actionNum++;
    }

    // Action 2: Priority cunli work
    $top5 = array_slice($byEstVotes, 0, min(5, count($byEstVotes)));
    $top5Names = implode('、', array_column($top5, 'name'));
    $top5Votes = array_sum(array_column($top5, 'est_2026_votes'));
    $report .= "  {$actionNum}. 【重點經營村里】{$top5Names}\n";
    $report .= "     這 5 個村里預估貢獻約 {$top5Votes} 票，為最大票源所在，\n";
    $report .= "     應優先設立服務據點、安排候選人走訪、建立里民聯繫網絡。\n\n";
    $actionNum++;

    // Action 3: Conversion opportunity
    if (count($presStronger) > 0) {
        $psExtra = 0;
        foreach ($presStronger as $c) {
            $psExtra += round(($c['2024pres_rate'] - $c['2024_rate']) / 100 * $c['2024_total'] * $turnoutRatio2022);
        }
        $ps3 = array_slice($presStronger, 0, min(3, count($presStronger)));
        $ps3Names = implode('、', array_column($ps3, 'name'));
        $report .= "  {$actionNum}. 【轉化潛力】{$ps3Names} 等 " . count($presStronger) . " 個村里\n";
        $report .= "     總統票顯著高於政黨票，反映選民認同候選人但未認同政黨。\n";
        $report .= "     若能有效轉化，額外可得約 {$psExtra} 票。建議：\n";
        $report .= "     - 候選人定位與柯文哲形象連結\n";
        $report .= "     - 強調地方服務實績而非純政黨訴求\n\n";
        $actionNum++;
    }

    // Action 4: High growth momentum
    if (count($highGrowth) > 0) {
        $hg3 = array_slice($highGrowth, 0, min(3, count($highGrowth)));
        $hg3Names = implode('、', array_column($hg3, 'name'));
        $report .= "  {$actionNum}. 【乘勝追擊】{$hg3Names} 等 " . count($highGrowth) . " 個高成長村里\n";
        $report .= "     支持度快速攀升，趁勢加強社區經營，固化成長動能。\n\n";
        $actionNum++;
    }

    // Action 5: Declining warning
    if (count($declining) > 0) {
        $d3 = array_slice($declining, 0, min(3, count($declining)));
        $d3Names = implode('、', array_column($d3, 'name'));
        $report .= "  {$actionNum}. 【止血警示】{$d3Names} 等 " . count($declining) . " 個村里支持度下滑\n";
        $report .= "     需實地訪查退步原因，是否有競爭對手強力經營或在地不滿。\n\n";
        $actionNum++;
    }

    // Action 6: 2022 incumbency leverage
    if (count($electedNames) > 0) {
        $report .= "  {$actionNum}. 【善用現任優勢】現任議員 " . implode('、', array_keys($electedNames)) . "\n";
        $report .= "     應盤點任內服務成果，以政績爭取連任支持。同時評估：\n";
        $report .= "     - 服務處覆蓋範圍是否涵蓋主要票源村里\n";
        $report .= "     - 是否需要增設服務據點擴大接觸面\n";
        $report .= "     - 若考慮提名第二席，票源分配策略為何\n\n";
        $actionNum++;
    }

    $report .= "═══════════════════════════════════════════════════════════════════\n";
    $report .= "  資料來源：中央選舉委員會選舉資料庫\n";
    $report .= "  涵蓋選舉：2020不分區、2022議員、2024不分區、2024總統\n";
    $report .= "  目標選舉：2026 縣市議員\n";
    $report .= "  報告產生時間：" . date('Y-m-d H:i:s') . "\n";
    $report .= "  注意：預估票數僅供參考，實際得票受候選人特質、選情變化、\n";
    $report .= "  　　　投票率及對手策略等多重因素影響。\n";
    $report .= "═══════════════════════════════════════════════════════════════════\n";

    file_put_contents($outputPath . '/' . $towncode . '.txt', $report);
    $count++;
}

echo "Done. Generated {$count} town reports in data/elections/tpp_reports/\n";
