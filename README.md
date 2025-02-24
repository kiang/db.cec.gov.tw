# Taiwan Election Results Database

This project aims to collect and provide access to voting results of elections in Taiwan. The database includes comprehensive election data that is made publicly available for research, analysis, and other purposes.

## Data Source

The data is collected from official election results published by the Central Election Commission (CEC) of Taiwan. 

## Data Format

The data is organized in several key components:

### Main Data Files
1. `elections.csv`: Master list of elections with the following fields:
   - 代號 (Code)
   - 選舉名稱 (Election Name)
   - 選舉日期 (Election Date)

2. `report1.csv`: Detailed election results containing:
   - 姓名 (Name)
   - 性別 (Gender)
   - 出生年次 (Birth Year)
   - 選舉類型 (Election Type)
   - 區域 (District)
   - 推薦政黨 (Recommending Party)
   - 得票數 (Vote Count)
   - 得票率 (Vote Percentage)
   - 當選註記 (Election Status)
   - 是否現任 (Incumbent Status)

### Directory Structure
- `/data`: Contains organized election data
  - `/data/elections/`: Election-specific data
  - `/data/council/`: Council election data
  - `/data/town_council/`: Township council election data
  - `/data/ly/`: Legislative Yuan election data
  - `/data/count/`: Vote counting data
  - Year-specific directories (2014, 2018, 2022): Contains data for respective election years

### Data Format Notes
- Character encoding: UTF-8
- File format: CSV (Comma-Separated Values)
- Date format: YYYY-MM-DD
- Numerical values: 
  - Vote counts are represented as integers
  - Percentages are shown with two decimal places followed by %

## Usage

This project contains several PHP scripts organized by election type for data collection and processing. Here's an overview of the available scripts:

### Script Organization
The scripts are organized in the following directories:

#### Legislative Yuan Scripts (`scripts/ly/`)
- `2024_zone_cunli.php`: Processes 2024 district-level (村里) election data for legislative districts
- `2024_party_cunli.php`: Processes 2024 party list voting data at village/neighborhood level
- `2020_party_cunli.php`: Processes 2020 party list voting data at village/neighborhood level
- `2024_00_zones.php`: Sets up electoral zones for 2024 legislative elections

#### Council Election Scripts (`scripts/council/`)
- Year-specific processing scripts (2014, 2018, 2022, 2026):
  - `*_00_zones.php`: Initialize and set up electoral zones
  - `*_01_vcode.php`: Process voting codes and district mappings
  - `*_02_cunli.php`: Process village/neighborhood level data
  - `*_03_zones.php`: Process zone-level aggregated data
- Comparison scripts:
  - `2022_compare_2018.php`: Compare 2022 results with 2018 data
  - `2022_match_2024.php`: Map 2022 districts to 2024 boundaries
  - `2018_match_2020.php`: Map 2018 districts to 2020 boundaries

#### Other Directories
- `scripts/elections/`: General election data processing scripts
- `scripts/town_council/`: Township council election processing scripts
- `scripts/report/`: Report generation scripts
- `scripts/result/`: Results processing scripts

### Dependencies
The project uses Composer for PHP dependency management. To install dependencies:
```bash
cd scripts
composer install
```

### Running Scripts
Scripts should be run from the project root directory. For example:
```bash
php scripts/ly/2024_party_cunli.php
```

Note: Most scripts are designed to fetch and process data from the CEC website. Make sure you have proper internet connectivity when running the scripts.

## License

This project contains two types of content with different licenses:

1. **Source Code**: All scripts, software, and documentation files are licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
2. **Data**: All collected data is licensed under the Creative Commons Attribution 4.0 International License (CC BY 4.0). See the [LICENSE-DATA](LICENSE-DATA) file for details.

### Using the Data

When using the data from this project, please provide attribution as required by the CC BY 4.0 license. You can cite this project as:

```
Taiwan Election Results Database by Finjon Kiang
Data source: Central Election Commission (CEC) of Taiwan
```

### Using the Code

The code in this repository is free to use under the MIT License. Please refer to the [LICENSE](LICENSE) file for complete terms.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Contact

For questions or feedback, please open an issue in the repository.

---
Created and maintained by Finjon Kiang 