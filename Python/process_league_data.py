# -*- coding: utf-8 -*-
import pandas as pd
import os
import glob
import re
import requests
import time
import base64

# --- Configuration ---
CSV_FOLDER_PATH = r"C:\Users\Dan\Desktop\MLB Teams"
HEADER_ROW_INDEX = 0
SINGLE_FILE_TO_PROCESS = 'Moneyball Dynasty Rosters - NYM.csv'
LEAGUE_ID_FOR_IMPORT = 'MLB'
# --- End Configuration ---

# --- WordPress Details ---
WORDPRESS_CONFIG = {
    'base_url': 'https://frontofficedynastysports.com',
    'username': 'djwes487',
    'app_password': 'BEVM WcEq 9xZt KPHE w5EI bYta',
    'player_cpt_slug': 'playerdata'
}
# --- End WordPress Details ---

# --- Contract & ACF Field Config ---
CONTRACT_YEAR_COLS = [str(year) for year in range(2025, 2038)]

ACF_FIELD_NAME_MAP = {
    'league_id': 'League ID',
    'fantasy_team_id': 'Fantasy Team ID',
    'fa_status': 'FA Status',
    'position': 'POS',
    'mlb_team': 'Team',
    'status_40_man': '40-Man',
    'status_il': 'IL',
    'option_years_used': 'Option Years Used',
    'rule_5_eligibility_year': 'Rule 5 Eligibility',
}
for year in CONTRACT_YEAR_COLS:
    ACF_FIELD_NAME_MAP[f'contract_{year}'] = year
# --- End Config ---

session = requests.Session()
credentials = f"{WORDPRESS_CONFIG['username']}:{WORDPRESS_CONFIG['app_password']}"
token = base64.b64encode(credentials.encode())
session.headers.update({'Authorization': f'Basic {token.decode("utf-8")}'})


def extract_team_id_from_filename(filename):
    match = re.search(r'- ([A-Z]{2,4})\.csv$', filename, re.IGNORECASE)
    if match:
        return match.group(1).upper()
    else:
        base = os.path.splitext(os.path.basename(filename))[0]
        parts = base.split('-')
        if len(parts) > 1: return parts[-1].strip().upper()
        return base


def create_wp_player(player_data, config):
    """Sends data to create a new player."""
    rest_url = f"{config['base_url']}/wp-json/wp/v2/{config['player_cpt_slug']}"
    post_title = player_data.get('Name')

    # Start payload with fields from the map
    acf_payload = {acf_key: str(data_val).strip() for acf_key, data_val in player_data.items() if acf_key in ACF_FIELD_NAME_MAP and pd.notna(data_val)}

    # Manually add the specially handled fields to the payload if they exist
    if 'dfa_only' in player_data:
        acf_payload['dfa_only'] = player_data['dfa_only']
    if 'has_been_on_40_man' in player_data:
        acf_payload['has_been_on_40_man'] = player_data['has_been_on_40_man']
    if 'rule_5_eligibility_year' in player_data:
        acf_payload['rule_5_eligibility_year'] = player_data['rule_5_eligibility_year']

    # --- NEW DEBUGGING LINE ---
    print(f"  -> FINAL ACF PAYLOAD: {acf_payload}")
    # --- END DEBUGGING LINE ---

    data = { 'title': post_title, 'status': 'publish', 'acf': acf_payload }

    try:
        response = session.post(rest_url, json=data, timeout=30)
        response.raise_for_status()
        print(f"    -> Successfully CREATED Player: {post_title}")
        return True
    except requests.exceptions.RequestException as e:
        print(f"    -> Error creating player {post_title}: {e}")
        return False

def process_csv_files(folder_path, league_id_value, config):
    all_files = []
    if SINGLE_FILE_TO_PROCESS:
        single_file_path = os.path.join(folder_path, SINGLE_FILE_TO_PROCESS)
        if os.path.exists(single_file_path):
            all_files.append(single_file_path)
        else:
            print(f"Error: File not found: {single_file_path}"); return
    else:
        all_files = glob.glob(os.path.join(folder_path, "*.csv"))

    if not all_files: print("Error: No CSV files to process."); return

    print(f"Found {len(all_files)} CSV file(s) for a clean import.")
    total_created, total_failed = 0, 0

    for file_path in all_files:
        filename = os.path.basename(file_path)
        team_id = extract_team_id_from_filename(filename)
        print(f"\nProcessing: {filename} (Team ID: {team_id})...")
        try:
            df_team = pd.read_csv(file_path, header=HEADER_ROW_INDEX, on_bad_lines='warn', encoding='utf-8')
            df_team.dropna(subset=['Name'], inplace=True)
            df_team = df_team[df_team['Name'].str.strip().ne('')]

            if df_team.empty:
                print(f"  No valid player data in {filename} after cleaning.")
                continue

            for _, row in df_team.iterrows():
                player_name = str(row.get('Name')).strip()
                if not player_name: continue

                player_data_for_api = {
                    'Name': player_name,
                    'league_id': league_id_value,
                    'fantasy_team_id': team_id,
                    'fa_status': 'rostered'
                }

                for acf_key, csv_header in ACF_FIELD_NAME_MAP.items():
                    if acf_key not in player_data_for_api and csv_header in row and pd.notna(row[csv_header]):
                        player_data_for_api[acf_key] = row[csv_header]

                dfa_val = row.get('DFA Only', '')
                player_data_for_api['dfa_only'] = 1 if isinstance(dfa_val,
                                                                  str) and dfa_val.strip().lower() == 'x' else 0

                # --- THIS IS THE CORRECTED LINE ---
                ever_40_val = row.get('40-Man', '')  # Looks for '40-Man' column
                player_data_for_api['has_been_on_40_man'] = 1 if isinstance(ever_40_val,
                                                                            str) and ever_40_val.strip().lower() == 'x' else 0

                rule_5_val = row.get('Rule 5 Eligibility')
                if pd.notna(rule_5_val) and str(rule_5_val).strip() != '':
                    try:
                        player_data_for_api['rule_5_eligibility_year'] = str(int(float(rule_5_val)))
                    except ValueError:
                        player_data_for_api['rule_5_eligibility_year'] = str(rule_5_val)

                if create_wp_player(player_data_for_api, config):
                    total_created += 1
                else:
                    total_failed += 1
                time.sleep(0.1)
        except Exception as e:
            print(f"  FATAL Error processing file {filename}: {e}")

    print("\nClean Import Complete.")
    print(f"Successfully CREATED: {total_created}")
    print(f"Failed operations: {total_failed}")


# --- Main Execution ---
if __name__ == "__main__":
    if WORDPRESS_CONFIG['base_url'] and LEAGUE_ID_FOR_IMPORT:
        process_csv_files(CSV_FOLDER_PATH, LEAGUE_ID_FOR_IMPORT, WORDPRESS_CONFIG)
    else:
        print("\nScript cannot run due to missing configuration.")