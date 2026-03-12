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
   - `data/elections/`, `data/count/`, `data/report/` — other processed data

### Key Data Identifiers

- Vote codes follow CEC format (e.g., `20091201C1C1`)
- Area codes use Taiwan's administrative codes (e.g., `10002` for Yilan County)
- Zone identifiers: `{area_code}-{zone_number}` (e.g., `10002-01`)

## Common Commands

```bash
# Install PHP dependencies (geophp for spatial operations)
cd scripts && composer install

# Run processing scripts from project root
php scripts/council/2026_00_zones.php
php scripts/ly/2024_zone_cunli.php

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
