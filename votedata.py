#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""

- retreive votedata.zip (which containing big-5 encoded file filename.
- the script is alternative to original votedata.php

"""

import urllib.request
import urllib.parse
import zipfile
import shutil
import pathlib
import os

data_url='http://data.cec.gov.tw/選舉資料庫/votedata.zip'

def unzip(f, dest):
    """ unzip file $f containing Big-5 encoding file names to the specified destination folder $dest """
    with zipfile.ZipFile(f) as z:
        for zi in z.infolist():
            path = dest / zi.filename.encode('cp437', 'strict').decode('big5', 'strict')
            if zi.is_dir():
                if not path.exists():
                    path.mkdir() # create intermediate directory
            else:
                with path.open('wb') as file:
                    file.write(z.read(zi.filename)) # write extracted file

def download(url, dest):
    """ download file from $url and save to the file $dest """
    with urllib.request.urlopen(urllib.parse.quote(url, "\./_-:")) as response:
        with open(dest, 'wb') as w:
            shutil.copyfileobj(response, w)

if __name__ == '__main__':

    tmpdir     = pathlib.Path('tmp')
    tmpzip     = tmpdir / 'votedata.zip'
    tmpextract = tmpdir / 'votedata'

    # clean up tmp folder to use
    shutil.rmtree(tmpextract, ignore_errors=True)
    os.mkdir(tmpextract)

    # download source data file
    download(data_url, tmpzip)

    # unzip source data file
    unzip(tmpzip, tmpextract)

    # copy extracted files to current directory
    for f in os.listdir(tmpextract):
        src = tmpextract / f
        shutil.copytree(src, f)
