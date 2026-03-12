# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Taiwan Election Results Database — collects and processes voting results from Taiwan's Central Election Commission (CEC) at `db.cec.gov.tw` and `data.cec.gov.tw`. Data covers legislative (立法委員), council (縣市議員), and township council (鄉鎮市民代表) elections from 2014 onward.

## Architecture

### Data Pipeline

1. **Crawling/Download**: Root-level scripts fetch raw data from CEC
   - `crawler.php` — scrapes the legacy `db.cec.gov.tw` HTML tables recursively, outputs CSV to `elections/`
   - `elections.php` — builds `elections.csv` master list and per-election CSVs in `elections/`
   - `votedata.php` / `votedata.py` — downloads and extracts the CEC bulk zip archive into `voteData/`
   - `report1.php` — cross-references all election CSVs to build `report1.csv` (candidates appearing in multiple elections)

2. **Processing scripts** (`scripts/`): Transform raw data into structured JSON under `data/`
   - Numbered sequentially: `*_00_zones.php` → `*_01_vcode.php` → `*_02_cunli.php` → `*_03_zones.php`
   - Each step depends on the previous one's output

3. **Output data** (`data/`): Organized by election type and year
   - `data/ly/` — Legislative Yuan
   - `data/council/` — County/city council
   - `data/town_council/` — Township council
   - `data/elections/2020-2024/` — per-cunli JSON files (keyed by geojson VILLCODE) aggregating multiple elections
   - `data/count/`, `data/report/` — other processed data

### Key Data Identifiers

- Vote codes follow CEC format (e.g., `20091201C1C1`)
- Election cunli codes: `{省市2}{縣市3}{鄉鎮市區3}{村里4}` (e.g., `630000100002` for 臺北市松山區莊敬里)
- Geojson VILLCODE: 11-digit code from `taiwan_basecode/cunli/geo/20221118.json` (e.g., `63000010002`)
- Area codes use Taiwan's administrative codes (e.g., `10002` for Yilan County)
- Zone identifiers: `{area_code}-{zone_number}` (e.g., `10002-01`)

### voteData Structure

Raw CEC bulk data in `voteData/` uses a standard set of CSV files per election:
- `elbase.csv` — administrative area hierarchy (province, county, zone, town, village, name)
- `elcand.csv` — candidate records (codes, name, party code, gender, etc.)
- `elctks.csv` — vote tallies per polling station (cunli-level aggregates have 投開票所=0000)
- `elpaty.csv` — party code-to-name lookup
- `elprof.csv` — electoral profile statistics (turnout, valid/invalid votes)

2020/2024 national election CSVs use quoted fields; 2022 local election CSVs are unquoted. No header rows in any file.

### Village Name Mapping Challenges

Geojson village names may use bracket notation for rare characters (e.g., `灰[磘]里`) while elbase uses actual Unicode characters (including supplementary plane chars like U+25562). Some villages also use Private Use Area characters (U+E006). A manual mapping table handles the ~10 villages where character variants differ between sources. Use `array + array` (not `array_merge`) when merging arrays with numeric string keys to avoid key renumbering.

## Common Commands

```bash
# Install PHP dependencies (geophp for spatial operations)
cd scripts && composer install

# Run processing scripts from project root
php scripts/council/2026_00_zones.php
php scripts/ly/2024_zone_cunli.php
php scripts/elections/2020-2024.php

# Download bulk election data
php votedata.php
# or Python alternative (handles Big-5 filenames better)
python3 votedata.py

# Crawl legacy CEC database
php crawler.php {voteCode}
php crawler.php {voteCode} village  # village-level detail

# Generate cross-election candidate report
php report1.php
```

## Conventions

- All text data uses UTF-8 encoding; CEC source data may be Big-5 encoded (see `votedata.py`)
- Processing scripts for a given election type/year must run in numbered order (00 → 01 → 02 → 03)
- Match/compare scripts (e.g., `2022_match_2024.php`) map districts across redistricting boundaries between election years
- JSON output files go under `data/{election_type}/{year}/`
- CSV field names use Chinese (e.g., 姓名, 得票數, 當選註記)
