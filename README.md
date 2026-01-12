# Front Office Dynasty Sports

A comprehensive fantasy sports management system built as a WordPress child theme. This project provides advanced tools for managing dynasty leagues, including complex roster rules, transaction tracking, and league financials.

## 🚀 Key Features

### 📋 Roster Management
- **MLB Standardized Rosters:** Support for 40-man and 26-man rosters.
- **Injured List (IL):** Automated placement and activation logic with eligibility time checks.
- **DFA & Waivers:** Full Designated for Assignment (DFA) workflow with a 48-hour waiver period, claiming priority logic, and dead cap penalties.
- **Position Sorting:** Custom sorting logic for player tables (C, 1B, 2B, SS, 3B, OF, SP, RP).

### 🤝 Transaction System
- **Advanced Trade Form:** Propose trades involving multiple players and In-Season Budget Points (ISBP).
- **Salary Impact Preview:** Real-time calculation of how a trade affects your team's payroll across future years.
- **Activity Feed:** Centralized log of all league transactions (signings, trades, waiver claims).

### 💰 Free Agency & Financials
- **Dynamic Bidding:** Point-based bidding system using multipliers based on contract length and AAV.
- **Automated Finalization:** Cron-based system to process expired bids and assign players to winning teams.
- **Arbitration System:** Workflow for managers to accept arbitration figures or negotiate multi-year extensions, with a commissioner approval queue.
- **Dead Cap Tracking:** Automated calculation and breakdown of dead cap penalties grouping by player and year.

### ⚾ Daily Operations
- **Pitcher Rotations:** Daily submission system for starting pitchers and banked starters.
- **Fantrax Integration:** Live standings fetched directly from Fantrax API.
- **League Dashboards:** Standardized views for MLB, AAA, AA, and NBA leagues.

## 🛠 Technology Stack
- **Backend:** PHP (WordPress Child Theme), MySQL
- **Frontend:** JavaScript (jQuery & Vanilla JS), CSS3 (Custom Brand Styling)
- **Data Pipeline:** Python (Pandas) for normalizing and importing large roster datasets via REST API.
- **Plugins:** Advanced Custom Fields (ACF) Pro for data structure.

## 📁 Project Structure
- `/inc`: Core PHP logic, AJAX handlers, and shortcode definitions.
- `/JS`: Frontend interactivity for trade forms and modals.
- `/Python`: Data processing scripts for league-wide roster imports.
- `/acf-json`: Synchronized field configurations for version control.

## 🔧 Installation
1. Install the [Twenty Twenty-Two](https://wordpress.org/themes/twentytwentytwo/) parent theme.
2. Clone this repository into your `/wp-content/themes/` directory.
3. Activate the child theme in the WordPress Admin.
4. Ensure the **Advanced Custom Fields (ACF)** plugin is active.
5. The theme will automatically sync the required field groups from the `/acf-json` folder.

---
*Created for Front Office Dynasty Sports.*
