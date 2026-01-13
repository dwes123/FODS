# -*- coding: utf-8 -*-
import pandas as pd
import requests
import base64
import os

# --- Configuration ---
CSV_FILE_PATH = r"C:\Users\Dan\Desktop\Front Office Dynasty Sports\Python\dead_cap_import.csv"
# --- End Configuration ---

# --- WordPress Configuration ---
WORDPRESS_CONFIG = {
    'base_url': 'https://staging-9cb1-frontofficedynastysports.wpcomstaging.com',
    'username': 'djwes487',
    'app_password': 'BEVM WcEq 9xZt KPHE w5EI bYta',
}
# --- End WORDPRESS Configuration ---

def import_dead_cap_csv(file_path):
    if not os.path.exists(file_path):
        # Create a template if it doesn't exist
        print(f"File not found: {file_path}")
        print("Creating a template CSV file for you...")
        template_df = pd.DataFrame(columns=['player_name', 'league_id', 'team_id', 'year', 'amount', 'type'])
        template_df.to_csv(file_path, index=False)
        print(f"Please fill out {file_path} and run the script again.")
        return

    try:
        df = pd.read_csv(file_path)
        # Clean column names
        df.columns = df.columns.str.strip().str.lower()
        
        # --- FIX: Drop rows where player_name is empty/NaN ---
        df.dropna(subset=['player_name'], inplace=True)
        
        required_cols = ['player_name', 'league_id', 'team_id', 'year', 'amount']
        if not all(col in df.columns for col in required_cols):
            print(f"Error: CSV must contain columns: {required_cols}")
            return

        penalties = []
        for _, row in df.iterrows():
            penalties.append({
                'player_name': str(row['player_name']).strip(),
                'league_id': str(row['league_id']).strip(),
                'team_id': str(row['team_id']).strip(),
                'year': int(row['year']),
                'amount': float(row['amount']),
                'type': str(row.get('type', 'Dead Cap')).strip()
            })

        if not penalties:
            print("No penalties found in CSV.")
            return

        # Prepare Request
        rest_url = f"{WORDPRESS_CONFIG['base_url']}/wp-json/fod/v1/bulk-dead-cap"
        credentials = f"{WORDPRESS_CONFIG['username']}:{WORDPRESS_CONFIG['app_password']}"
        token = base64.b64encode(credentials.encode())
        headers = {'Authorization': f'Basic {token.decode("utf-8")}'}

        print(f"Sending {len(penalties)} penalties to WordPress...")
        response = requests.post(rest_url, json={'penalties': penalties}, headers=headers)
        
        if response.status_code == 200:
            result = response.json()
            print(f"Success! {result['success']} penalties added.")
            if result['failed'] > 0:
                print(f"Failed: {result['failed']}")
                for msg in result['messages']:
                    print(f"  - {msg}")
        else:
            print(f"Error: {response.status_code} - {response.text}")

    except Exception as e:
        print(f"An error occurred: {e}")

if __name__ == "__main__":
    import_dead_cap_csv(CSV_FILE_PATH)
