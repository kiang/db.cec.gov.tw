<?php

$tmpPath = __DIR__ . '/tmp/votedata';
if (!file_exists($tmpPath)) {
    mkdir($tmpPath, 0777, true);
}

$zipFile = $tmpPath . '/votedata.zip';

if (!file_exists($zipFile)) {
    file_put_contents($zipFile, file_get_contents('https://data.cec.gov.tw/' . urlencode('選舉資料庫') .'/votedata.zip'));
}

exec("cd {$tmpPath} && 7z x {$zipFile}");

unlink($zipFile);

exec("rm -Rf " . __DIR__ . "/voteData");

// Move votedata/voteData to root
exec("mv {$tmpPath}/votedata/voteData " . __DIR__ . '/');

// Move remaining votedata/* files into voteData folder
exec("mv {$tmpPath}/votedata/* " . __DIR__ . '/voteData/');
