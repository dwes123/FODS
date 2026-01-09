import csv
import os

# ==========================================
# CONFIGURATION
# ==========================================
INPUT_FOLDER = 'raw_free_agents'
OUTPUT_FOLDER = 'clean_free_agents'

# ==========================================
# SCRIPT
# ==========================================

def normalize_fa_csv(input_path, output_path, target_league_id):
    print(f"Processing {os.path.basename(input_path)} for League '{target_league_id}'...")
    
    # Detect delimiter
    delimiter = ','
    try:
        with open(input_path, 'r', encoding='utf-8-sig', errors='replace') as f_peek:
            first_line = f_peek.readline()
            if '\t' in first_line:
                delimiter = '\t'
                print("  -> Detected TAB delimiter")
            else:
                print("  -> Detected COMMA delimiter")
    except Exception as e:
        print(f"  -> Error reading file: {e}")
        return

    try:
        with open(input_path, 'r', encoding='utf-8-sig', errors='replace') as f_in:
            reader = csv.DictReader(f_in, delimiter=delimiter)
            
            # Normalize headers
            if reader.fieldnames:
                reader.fieldnames = [h.strip().lower().replace(' ', '_') for h in reader.fieldnames]
            else:
                print("  -> ERROR: Empty file or no headers.")
                return
            
            clean_rows = []
            
            for row in reader:
                # Try to find the name column
                player_name = row.get('player') or row.get('name') or row.get('player_name')
                
                if not player_name:
                    continue

                # Build Clean Row
                new_row = {
                    'name': player_name.strip(),
                    'position': row.get('position', '').strip(),
                    'mlb_team': row.get('team', '').strip(), # Sometimes FA lists have real team
                    'league_id': target_league_id,
                    'fantasy_team_id': '', # Always empty for FA
                    'fa_status': 'available', # Always available
                    'status_40_man': '',
                    'status_il': '',
                    'contract_2026': '', # No contracts for FA
                    'contract_2027': '',
                    'contract_2028': '',
                    'contract_2029': '',
                    'contract_2030': '',
                }
                clean_rows.append(new_row)

        # Write Output
        if clean_rows:
            # Get headers from the first row keys
            output_headers = list(clean_rows[0].keys())
            
            out_file = os.path.join(OUTPUT_FOLDER, f"clean_{os.path.basename(input_path)}")
            out_file = os.path.splitext(out_file)[0] + '.csv'
            
            with open(out_file, 'w', newline='', encoding='utf-8') as f_out:
                writer = csv.DictWriter(f_out, fieldnames=output_headers)
                writer.writeheader()
                writer.writerows(clean_rows)
            print(f"  -> Saved to {out_file} ({len(clean_rows)} players)")
        else:
            print(f"  -> No valid players found in {input_path}")

    except Exception as e:
        print(f"  -> Error processing file: {e}")

# ==========================================
# MAIN EXECUTION
# ==========================================
if __name__ == "__main__":
    league_input = input("Enter the League ID for this batch (e.g., AA, MLB): ").strip().upper()
    if not league_input:
        print("No League ID entered. Exiting.")
        exit()
    
    print(f"\nProcessing Free Agent files for League: {league_input}\n")

    if not os.path.exists(INPUT_FOLDER):
        os.makedirs(INPUT_FOLDER)
        print(f"Created folder '{INPUT_FOLDER}'. Please put your FA files there.")
    else:
        if not os.path.exists(OUTPUT_FOLDER):
            os.makedirs(OUTPUT_FOLDER)
        
        files = [f for f in os.listdir(INPUT_FOLDER) if f.lower().endswith(('.csv', '.tsv', '.txt'))]
        
        if not files:
            print(f"No files found in '{INPUT_FOLDER}'.")
        else:
            print(f"Found {len(files)} files. Starting processing...")
            for f in files:
                normalize_fa_csv(os.path.join(INPUT_FOLDER, f), OUTPUT_FOLDER, league_input)
            print("\nDone!")
