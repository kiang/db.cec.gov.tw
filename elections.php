<?php

$tmpPath = __DIR__ . '/tmp';
$electionPath = __DIR__ . '/elections';
if (!file_exists($tmpPath)) {
    mkdir($tmpPath, 0777, true);
}

if (!file_exists($electionPath)) {
    mkdir($electionPath, 0777, true);
}

$listFile = $tmpPath . '/list';
if (!file_exists($listFile)) {
    file_put_contents($listFile, file_get_contents('http://db.cec.gov.tw/'));
}
$list = file_get_contents($listFile);

$items = array();
$pos = strpos($list, 'histMain.jsp');
while (false !== $pos) {
    $item = array();
    $posEnd = strpos($list, '"', $pos);
    $item['url'] = 'http://db.cec.gov.tw/' . substr($list, $pos, $posEnd - $pos);
    $pos = strpos($list, '>', $posEnd) + 1;
    $posEnd = strpos($list, '<', $pos);
    $item['title'] = trim(substr($list, $pos, $posEnd - $pos));
    if (!empty($item['title'])) {
        $items[] = $item;
    }
    $pos = strpos($list, 'histMain.jsp', $posEnd);
}
$electionLinks = array();
$items = parsehistQuery($items);
$result = array();
$fh = fopen(__DIR__ . '/elections.csv', 'w');
fputcsv($fh, array('代號', '選舉名稱'));
foreach ($electionLinks AS $electionLink) {
    $urlParts = explode('voteCode=', $electionLink['url']);
    $electionCode = substr($urlParts[1], 0, strpos($urlParts[1], '&'));
    $electionTitlePos = strpos($electionLink['title'], ' > 候選人得票明細');
    if (false !== $electionTitlePos) {
        $electionTitle = substr($electionLink['title'], 0, $electionTitlePos);
    } else {
        $electionTitle = $electionLink['title'];
    }
    $electionLinkFile = $tmpPath . '/vote_' . md5($electionLink['url']);
    if (!file_exists($electionLinkFile)) {
        file_put_contents($electionLinkFile, file_get_contents($electionLink['url']));
    }
    $electionLinkText = file_get_contents($electionLinkFile);
    if (!isset($result[$electionTitle])) {
        $result[$electionTitle] = array();
        fputcsv($fh, array($electionCode, $electionTitle));
        $pos = strpos($electionLinkText, '<tr class="title">');
        $posEnd = strpos($electionLinkText, '</tr>', $pos);
        $titleText = substr($electionLinkText, $pos, $posEnd - $pos);
        $titles = explode('</td>', $titleText);
        foreach ($titles AS $k => $v) {
            $titles[$k] = trim(strip_tags($v));
        }
        array_pop($titles);
        $result[$electionTitle]['code'] = $electionCode;
        $result[$electionTitle]['titles'] = $titles;
    }
    $pos = strpos($electionLinkText, '<tr class="data">');
    if (false !== $pos) {
        $posEnd = strpos($electionLinkText, '</table>', $pos);
        $electionLinkText = substr($electionLinkText, $pos, $posEnd - $pos);
        $lines = explode('</tr>', $electionLinkText);
        $firstColsCount = false;
        foreach ($lines AS $line) {
            $cols = explode('</td>', $line);
            foreach ($cols AS $k => $v) {
                $cols[$k] = trim(strip_tags($v));
            }
            $colsCount = count($cols);
            if (false === $firstColsCount) {
                $firstColsCount = $colsCount;
            }
            if ($firstColsCount === $colsCount) {
                $currentArea = $cols[0];
                $currentAreaKey = 0;
                $result[$electionTitle][$currentArea] = array();
                $newCols = array();
                for ($i = 1; $i < $firstColsCount; $i++) {
                    $newCols[] = $cols[$i];
                }
                array_pop($newCols);
                $result[$electionTitle][$currentArea][$currentAreaKey] = $newCols;
            } elseif ($colsCount === $firstColsCount - 1) {
                ++$currentAreaKey;
                array_pop($cols);
                $result[$electionTitle][$currentArea][$currentAreaKey] = $cols;
            } elseif ($colsCount > 1) {
                $result[$electionTitle][$currentArea][$currentAreaKey][0] .= '|' . $cols[0];
                $result[$electionTitle][$currentArea][$currentAreaKey][2] .= '|' . $cols[1];
                $result[$electionTitle][$currentArea][$currentAreaKey][3] .= '|' . $cols[2];
            }
        }
    }
}
fclose($fh);

foreach ($result AS $election => $data) {
    $eFh = fopen("{$electionPath}/{$data['code']}.csv", 'w');
    fputcsv($eFh, $data['titles']);
    unset($result[$election]['titles']);
    unset($result[$election]['code']);
    foreach ($result[$election] AS $area => $candidates) {
        foreach ($candidates AS $key => $candidate) {
            fputcsv($eFh, array_merge(array($area), $candidate));
        }
    }
}

function parsehistQuery($items) {
    global $tmpPath, $electionLinks;
    foreach ($items AS $itemKey => $item) {
        $electionIndexFile = $tmpPath . '/vote_' . md5($item['url']);
        if (!file_exists($electionIndexFile)) {
            echo "downloading {$item['url']}\n";
            file_put_contents($electionIndexFile, file_get_contents($item['url']));
        }
        $electionIndex = file_get_contents($electionIndexFile);
        $tableCheck = strpos($electionIndex, '得票率');
        if (false === $tableCheck) {
            $items[$itemKey]['links'] = array();
            $pos = strpos($electionIndex, 'histQuery.jsp');
            while (false !== $pos) {
                $link = array();
                $posEnd = strpos($electionIndex, '"', $pos);
                $link['url'] = 'http://db.cec.gov.tw/' . substr($electionIndex, $pos, $posEnd - $pos);
                if (false === strpos($link['url'], 'candNo') && false !== strpos($link['url'], 'ctks')) {
                    $pos = strpos($electionIndex, '>', $posEnd) + 1;
                    $posEnd = strpos($electionIndex, '<', $pos);
                    $link['title'] = trim(substr($electionIndex, $pos, $posEnd - $pos));
                    if (!empty($link['title'])) {
                        if (!empty($item['title'])) {
                            $link['title'] = "{$item['title']} > {$link['title']}";
                        }
                        $items[$itemKey]['links'][] = $link;
                    }
                }
                $pos = strpos($electionIndex, 'histQuery.jsp', $posEnd);
            }
            if (!empty($items[$itemKey]['links'])) {
                parsehistQuery($items[$itemKey]['links']);
            }
        } else {
            $electionLinks[] = $item;
        }
    }
    return $items;
}
