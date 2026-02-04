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
CSV_FOLDER_PATH = r"Python\raw_free_agents"  # Point to local project folder
LEAGUE_ID_TO_ASSIGN = 'AAA'  # Target AAA league
HEADER_ROW_INDEX = 0
# --- End Configuration ---

# --- WordPress Configuration ---
WORDPRESS_CONFIG = {
    'base_url': 'https://frontofficedynastysports.com',
    'username': 'djwes487',
    'app_password': 'YOUR_APP_PASSWORD_HERE',
    'player_cpt_slug': 'playerdata'
}
# --- End WORDPRESS Configuration ---

# --- ACF Field Map (Simplified for Free Agents) ---
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


def get_existing_player_id(name, league_id, config):
    """Searches for an existing player by name and league_id."""
    search_url = f"{config['base_url']}/wp-json/wp/v2/{config['player_cpt_slug']}"
    params = {
        'search': name,
        'per_page': 1  # We only need one match to start checking
    }
    
    try:
        response = session.get(search_url, params=params, timeout=30)
        response.raise_for_status()
        results = response.json()
        
        # Filter results because WP search is fuzzy
        for post in results:
            if post['title']['rendered'] == name:
                # We need to fetch the ACF fields to confirm the league_id
                # Note: The search endpoint might not return ACF data by default depending on setup
                # So we might need a second call, but let's try to be efficient.
                # If your API exposes 'acf' in the list view, we can check it directly.
                if 'acf' in post and post['acf'].get('league_id') == league_id:
                    return post['id']
                
                # If ACF isn't in list view, fetch full object
                full_player_url = f"{search_url}/{post['id']}"
                full_resp = session.get(full_player_url, timeout=30)
                if full_resp.status_code == 200:
                    full_data = full_resp.json()
                    if full_data.get('acf', {}).get('league_id') == league_id:
                        return post['id']
                        
        return None
    except Exception as e:
        print(f"    Warning: Search failed for {name}: {e}")
        return None

def create_wp_player(player_data, config):
    """Creates or Updates a player in the WordPress database."""
    base_api_url = f"{config['base_url']}/wp-json/wp/v2/{config['player_cpt_slug']}"
    post_title = player_data.get('Name')
    league_id = player_data.get('league_id')

    # 1. Check if player exists
    existing_id = get_existing_player_id(post_title, league_id, config)

    # Build Payload
    acf_payload = {acf_key: str(data_val).strip() for acf_key, data_val in player_data.items() if
                   acf_key in ACF_FIELD_NAME_MAP and pd.notna(data_val)}
    data = {'title': post_title, 'status': 'publish', 'acf': acf_payload}

    try:
        if existing_id:
            # UPDATE existing player
            update_url = f"{base_api_url}/{existing_id}"
            response = session.post(update_url, json=data, timeout=30)
            response.raise_for_status()
            print(f"    -> Successfully UPDATED Free Agent: {post_title} (ID: {existing_id})")
        else:
            # CREATE new player
            response = session.post(base_api_url, json=data, timeout=30)
            response.raise_for_status()
            new_post = response.json()
            print(f"    -> Successfully CREATED Free Agent: {post_title} (ID: {new_post.get('id')})")
        
        return True
    except requests.exceptions.RequestException as e:
        action = "updating" if existing_id else "creating"
        print(f"    -> Error {action} free agent {post_title}: {e}")
        if hasattr(e, 'response') and e.response is not None:
            print(f"    -> Raw Error Response: {e.response.text}")
        return False


def process_free_agent_csvs(folder_path, assigned_league_id, config):
    """Processes CSV files in the folder, treating all players as free agents."""
    all_files = [os.path.join(folder_path, "Fantrax-Players-Moneyball Dynasty - AAA.csv")]
    if not os.path.exists(all_files[0]):
        print(f"Error: Specific AAA file not found: {all_files[0]}")
        return

    print(f"Found {len(all_files)} CSV file(s) to process as Free Agents.")
    total_created, total_failed = 0, 0

    for file_path in all_files:
        filename = os.path.basename(file_path)
        print(f"\nProcessing: {filename}...")

        try:
            df_fa = pd.read_csv(file_path, header=HEADER_ROW_INDEX, on_bad_lines='warn', encoding='utf-8')

            # --- FIX: Proactively strip whitespace from column names ---
            df_fa.columns = df_fa.columns.str.strip()

            # Now, check for the 'Player' column (raw CSV uses Player instead of Name)
            name_col = 'Player' if 'Player' in df_fa.columns else 'Name'
            pos_col = 'Position' if 'Position' in df_fa.columns else 'POS'
            team_col = 'Team'

            if name_col not in df_fa.columns:
                print(f"  Error: Name column not found in {filename}. Skipping file.")
                continue

            df_fa.dropna(subset=[name_col], inplace=True)
            df_fa = df_fa[df_fa[name_col].astype(str).str.strip() != '']

            if df_fa.empty:
                print(f"  No valid player data found in {filename} after cleaning.")
                continue

            print(f"  Found {len(df_fa)} free agents to import...")

            for _, row in df_fa.iterrows():
                player_name = str(row.get(name_col)).strip()
                if not player_name: continue

                # --- Prepare the data for this player ---
                player_data_for_api = {
                    'Name': player_name,
                    'league_id': assigned_league_id,
                    'fantasy_team_id': '',  # Blank for Free Agents
                    'fa_status': 'available',  # Set to 'available'
                    'position': row.get(pos_col),
                    'mlb_team': row.get(team_col)
                }

                if create_wp_player(player_data_for_api, config):
                    total_created += 1
                else:
                    total_failed += 1

                time.sleep(0.1)

        except Exception as e:
            print(f"  FATAL Error processing file {filename}: {e}")
            import traceback
            traceback.print_exc()

    print("\nFree Agent Import Complete.")
    print(f"Successfully CREATED: {total_created}")
    print(f"Failed operations: {total_failed}")


# --- Main Execution ---
if __name__ == "__main__":
    if WORDPRESS_CONFIG['base_url'] and LEAGUE_ID_TO_ASSIGN:
        process_free_agent_csvs(CSV_FOLDER_PATH, LEAGUE_ID_TO_ASSIGN, WORDPRESS_CONFIG)
    else:
        print("\nScript cannot run due to missing configuration.")