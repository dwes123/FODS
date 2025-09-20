# -*- coding: utf-8 -*-
# Filename: import_free_agents.py

import pandas as pd
import os
import glob
import re
import requests
import time
import base64

# --- Configuration ---
CSV_FOLDER_PATH = r"C:\Users\Dan\Desktop\Free Agents" # Your FA folder path
LEAGUE_ID_TO_ASSIGN = 'MLB' # League ID for this import batch
HEADER_ROW_INDEX = 0 # Headers are in the first row
# --- End Configuration ---

# --- WordPress Configuration ---
WORDPRESS_CONFIG = {
    'base_url': 'https://frontofficedynastysports.com',
    'username': 'djwes487',
    'app_password': 'BEVM WcEq 9xZt KPHE w5EI bYta',
    'player_cpt_slug': 'playerdata' # CORRECTED: Points to your main player CPT
}
# --- End WORDPRESS Configuration ---

# --- ACF Field Map (Simplified for Free Agents) ---
# This now matches your simple FA spreadsheet
ACF_FIELD_NAME_MAP = {
    'fa_status': 'FA Status',
    'league_id': 'League ID',
    'fantasy_team_id': 'Fantasy Team ID',
    'position': 'POS',
    'mlb_team': 'Team',
}
# --- End ACF Field Map ---

# --- Global Session for performance ---
session = requests.Session()
credentials = f"{WORDPRESS_CONFIG['username']}:{WORDPRESS_CONFIG['app_password']}"
token = base64.b64encode(credentials.encode())
session.headers.update({'Authorization': f'Basic {token.decode("utf-8")}'})


def create_wp_player(player_data, config):
    """Sends data for one player to the WordPress REST API to create a post."""
    rest_url = f"{config['base_url']}/wp-json/wp/v2/{config['player_cpt_slug']}"
    post_title = player_data.get('Name')

    acf_payload = {acf_key: str(data_val).strip() for acf_key, data_val in player_data.items() if acf_key in ACF_FIELD_NAME_MAP and pd.notna(data_val)}
    data = { 'title': post_title, 'status': 'publish', 'acf': acf_payload }

    try:
        response = session.post(rest_url, json=data, timeout=30)
        response.raise_for_status()
        created_post = response.json()
        print(f"    -> Successfully CREATED Free Agent: {post_title} (ID: {created_post.get('id')})")
        return True
    except requests.exceptions.RequestException as e:
        print(f"    -> Error creating free agent {post_title}: {e}")
        if hasattr(e, 'response') and e.response is not None:
             print(f"    -> Raw Error Response: {e.response.text}")
        return False

def process_free_agent_csvs(folder_path, assigned_league_id, config):
    """Processes CSV files in the folder, treating all players as free agents."""
    all_files = glob.glob(os.path.join(folder_path, "*.csv"))
    if not all_files:
        print(f"Error: No CSV files found in folder: {folder_path}")
        return

    print(f"Found {len(all_files)} CSV file(s) to process as Free Agents.")
    total_created, total_failed = 0, 0

    for file_path in all_files:
        filename = os.path.basename(file_path)
        print(f"\nProcessing: {filename}...")

        try:
            df_fa = pd.read_csv(file_path, header=HEADER_ROW_INDEX, on_bad_lines='warn', encoding='utf-8')
            df_fa.dropna(subset=['Name'], inplace=True)
            df_fa = df_fa[df_fa['Name'].str.strip() != '']

            if df_fa.empty:
                print(f"  No valid player data found in {filename} after cleaning.")
                continue

            for _, row in df_fa.iterrows():
                player_name = str(row.get('Name')).strip()
                if not player_name: continue

                # --- Prepare the data for this player ---
                player_data_for_api = {
                    'Name': player_name,
                    'league_id': assigned_league_id,
                    'fantasy_team_id': '', # Blank for Free Agents
                    'fa_status': 'available', # Set to 'available' as requested
                    'position': row.get('POS'),
                    'mlb_team': row.get('Team')
                }

                if create_wp_player(player_data_for_api, config):
                    total_created += 1
                else:
                    total_failed += 1

                time.sleep(0.1)

        except Exception as e:
            print(f"  FATAL Error processing file {filename}: {e}")

    print("\nFree Agent Import Complete.")
    print(f"Successfully CREATED: {total_created}")
    print(f"Failed operations: {total_failed}")


# --- Main Execution ---
if __name__ == "__main__":
    if WORDPRESS_CONFIG['base_url'] and LEAGUE_ID_TO_ASSIGN:
        process_free_agent_csvs(CSV_FOLDER_PATH, LEAGUE_ID_TO_ASSIGN, WORDPRESS_CONFIG)
    else:
        print("\nScript cannot run due to missing configuration.")