<?php

class Crawler
{
    public function crawlNext($voteCode, $areas, $depth)
    {
        $sub_areas = array();
        $column_showed = false;
        if (file_exists(__DIR__ . "/elections/{$voteCode}-{$depth}.csv")) {
            return;
        }
        $fp = fopen(__DIR__ . "/elections/{$voteCode}-{$depth}.csv", 'w');
        $total = count($areas);
        $i = 0;
        foreach ($areas as $area_name => $area_url) {
            $i ++;
            error_log("{$i}/{$total} {$url}");
            $url = "http://db.cec.gov.tw/" . $area_url;
            $table = $this->getTableFromHTML($url);
            if (!$column_showed) {
                $column_showed = true;
                fputcsv($fp, $table['columns']);
            }
            $prev_rows = null;
            foreach ($table['values'] as $rows) {
                // 如果同號次的就不要重覆寫入了
                if (!is_null($prev_rows)) {
                    if ($prev_rows[0] == $rows[0] and $prev_rows[2] == $rows[2]) {
                        continue;
                    }
                }
                $prev_rows = $rows;
                if (is_array($rows[0])) {
                    $sub_areas[$rows[0][1]] = $rows[0][0];
                }
                fputcsv($fp, array_map(function($a){ return is_array($a) ? $a[1] : $a; }, $rows));
            }
        }
        fclose($fp);

        if (count($sub_areas)) {
            $this->crawlNext($voteCode, $sub_areas, $depth + 1);
        }
    }

    public function main($voteCode, $is_village)
    {
        // 全台灣含黨籍
        if (!$voteCode) {
            throw new Exception("php crawler.php {voteCode}");
        }
        if ($is_village != 'village') {
            $url = "http://db.cec.gov.tw/histQuery.jsp?voteCode=" . $voteCode . "&qryType=ctks";
            $table = $this->getTableFromHTML($url);
            file_put_contents(__DIR__ . '/crawler.log', "{$voteCode},{$table['title']}\n", FILE_APPEND);
            $fp = fopen(__DIR__ . "/elections/{$voteCode}.csv", 'w');
            fputcsv($fp, $table['columns']);
            $areas = array();
            foreach ($table['values'] as $rows) {
                $areas[$rows[0][1]] = $rows[0][0];
                fputcsv($fp, array_map(function($a){ return is_array($a) ? $a[1] : $a; }, $rows));
            }
            fclose($fp);

            $this->crawlNext($voteCode, $areas, 1);
        } else {
            $url = "http://db.cec.gov.tw/histQuery.jsp?voteCode={$voteCode}&qryType=ctks";
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $content = curl_exec($curl);
            $info = curl_getinfo($curl);
            curl_close($curl);

            preg_match_all('#histQuery.jsp\?[^"]*#', $content, $matches);

            $this->crawlNext($voteCode, $matches[0], 1);
        }
    }

    public function getTableFromHTML($url)
    {
        for ($i = 0; $i < 3; $i ++) {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $content = curl_exec($curl);
            $info = curl_getinfo($curl);
            if ($info['http_code']) {
                break;
            }
            curl_close($curl);
        }
        if ($i === 3) {
            throw new Exception("抓 {$url} 失敗三次");
        }

        //$content = file_get_contents('histQuery.jsp?voteCode=20091201C1C1&qryType=ctks');
        $doc = new DOMDocument;
        @$doc->loadHTML($content);

        $table_doms = $doc->getElementsByTagName('table');
        if ($table_doms->length != 1) {
            throw new Exception('應該只有一個 table: ' . $url);
        }

        foreach ($doc->getElementsByTagName('div') as $div_dom) {
            if ($div_dom->getAttribute('class') == 'head') {
                $title = $div_dom->nodeValue;
                break;
            }
        }

        $table_dom = $table_doms->item(0);

        $tr_doms = $table_dom->getElementsByTagName('tr');
        $columns = array();
        foreach ($tr_doms->item(0)->getElementsByTagName('td') as $td_dom) {
            $columns[] = $td_dom->nodeValue;
        }

        $row_spans = array();
        $last_values = array();
        $values = array();
        for ($i = 1; $i < $tr_doms->length; $i ++) {
            $td_doms = $tr_doms->item($i)->getElementsByTagName('td');
            $rows = array();
            $td_index = 0;
            for ($j = 0; $j < count($columns); $j ++) {
                if ($row_spans[$j]) {
                    $rows[] = $last_values[$j];
                    $row_spans[$j] --;
                    continue;
                }

                $td_dom = $td_doms->item($td_index ++);
                if ($td_dom->getElementsByTagName('a')->length) {
                    $a_dom = $td_dom->getElementsByTagName('a')->item(0);
                    $v = array($a_dom->getAttribute('href'), trim($a_dom->nodeValue));
                } else {
                    $v = trim($td_dom->nodeValue);
                }
                $rows[] = $v;
                if ($rowspan = $td_dom->getAttribute('rowspan')) {
                    $row_spans[$j] = $rowspan - 1;
                    $last_values[$j] = $v;
                }
            }
            $values[] = $rows;
        }

        return array(
            'title' => $title,
            'columns' => $columns,
            'values' => $values,
        );
    }
}

$c = new Crawler;
$c->main($_SERVER['argv'][1], $_SERVER['argv'][2]);
