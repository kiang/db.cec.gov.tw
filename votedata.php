<?php

$tmpPath = __DIR__ . '/tmp/votedata';
if (!file_exists($tmpPath)) {
    mkdir($tmpPath, 0777, true);
}

$zipFile = $tmpPath . '/votedata.zip';

if (!file_exists($zipFile)) {
    file_put_contents($zipFile, file_get_contents('https://data.cec.gov.tw/' . urlencode('選舉資料庫') .'/voteData.zip'));
}

exec("cd {$tmpPath} && LANG=C 7z x {$zipFile}");

exec("convmv --replace -fbig5 -tutf8 -r --notest {$tmpPath}");

unlink($zipFile);

exec("rm -Rf " . __DIR__ . "/voteData");

exec("mv {$tmpPath}/* " . __DIR__ . '/');
