#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import urllib.request
import urllib.parse
import zipfile
import shutil
import pathlib

data_url='http://data.cec.gov.tw/選舉資料庫/votedata.zip'

def unzip(f):
    """ unzip file containing Big-5 encoding file names"""
    with zipfile.ZipFile(f) as z:
        for zi in z.infolist():
            path = pathlib.Path(zi.filename.encode('cp437', 'strict').decode('big5', 'strict'))
            if zi.is_dir():
                if not path.exists():
                    path.mkdir() # create intermediate directory
            else:
                with path.open('wb') as file:
                    file.write(z.read(zi.filename)) # write extracted file

if __name__ == '__main__':

    download = 'votedata.zip'
    # download source data file
    with urllib.request.urlopen(urllib.parse.quote(data_url, "\./_-:")) as response:
        with open(download, 'wb') as w:
            shutil.copyfileobj(response, w)
        # unzip source data file
        unzip(download)

