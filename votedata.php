<?php

$tmpPath = __DIR__ . '/tmp/votedata';
if (!file_exists($tmpPath)) {
    mkdir($tmpPath, 0777, true);
}

$zipFile = $tmpPath . '/votedata.zip';

if (!file_exists($zipFile)) {
    file_put_contents($zipFile, file_get_contents('http://data.cec.gov.tw/votedata.zip'));
}

exec("cd {$tmpPath} && LANG=C /usr/bin/7z x {$zipFile}");

exec("/usr/bin/convmv -fbig5 -tutf8 -r --notest {$tmpPath}");

unlink($zipFile);

exec("/bin/mv {$tmpPath}/* " . __DIR__ . '/');
