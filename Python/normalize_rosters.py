import csv
import os
import re

# Get the directory where the script is located
script_dir = os.path.dirname(os.path.abspath(__file__))

# ==========================================
# CONFIGURATION
# ==========================================
INPUT_FOLDER = os.path.join(script_dir, 'raw_rosters')
OUTPUT_FOLDER = os.path.join(script_dir, 'clean_rosters')

# ==========================================
# SCRIPT
# ==========================================

def normalize_csv(input_path, output_path, target_league_id):
    print(f"Processing {os.path.basename(input_path)} for League '{target_league_id}'...")
    
    # --- FANTASY TEAM ID FROM FILENAME ---
    filename = os.path.basename(input_path)
    match = re.search(r'([A-Z]{2,3})\.csv$', filename, re.IGNORECASE)
    if match:
        fantasy_team_id = match.group(1).upper()
        print(f"  -> Found Team ID from filename: {fantasy_team_id}")
    else:
        fantasy_team_id = "UNKNOWN"
        print(f"  -> WARNING: Could not determine Team ID from filename '{filename}'. Defaulting to UNKNOWN.")

    # --- FILE PROCESSING ---
    try:
        with open(input_path, 'r', encoding='utf-8-sig', errors='replace') as f_in:
            lines = f_in.readlines()
    except UnicodeDecodeError:
        with open(input_path, 'r', encoding='latin-1', errors='replace') as f_in:
            lines = f_in.readlines()

    clean_rows = []
    header_map = {}
    header_found = False
    is_fa_file = False
    
    delimiter = ','
    for line in lines[:20]: # Check first 20 lines for a tab
        if '\t' in line:
            delimiter = '\t'
            break
    print(f"  -> Detected delimiter: {'TAB' if delimiter == '\t' else 'COMMA'}")

    parsed_rows = list(csv.reader(lines, delimiter=delimiter))

    for i, row in enumerate(parsed_rows):
        row_clean = [h.strip().upper() for h in row]
        
        # --- SKIP UNWANTED SECTION HEADERS ---
        if any(x in row_clean[0] for x in ['PITCHERS', 'MINORS', 'POSITION PLAYERS']):
            continue

        if header_found and any("DEAD MONEY" in cell for cell in row_clean):
            print(f"  -> Found 'Dead Money' section at line {i}. Stopping processing for this file.")
            break

        # --- HEADER DETECTION ---
        if not header_found:
            # Check for standard roster headers
            if 'NAME' in row_clean and 'POS' in row_clean:
                for idx, header in enumerate(row_clean):
                    if header:
                        header_map[header] = idx
                header_found = True
                print(f"  -> Found ROSTER header map at line {i}.")
                continue
            
            # Check for simple FA headers (Player, Position)
            if 'PLAYER' in row_clean and 'POSITION' in row_clean:
                for idx, header in enumerate(row_clean):
                    if header:
                        header_map[header] = idx
                header_found = True
                is_fa_file = True
                print(f"  -> Found FREE AGENT header map at line {i}.")
                continue

        if not header_map:
            continue

        def get_data(header_name):
            header_name = header_name.upper().replace(' ', '_')
            # Try exact match
            if header_name in header_map and len(row) > header_map[header_name]:
                return row[header_map[header_name]].strip()
            # Try mapping "Player" to "Name" for FA files
            if header_name == 'NAME' and 'PLAYER' in header_map and len(row) > header_map['PLAYER']:
                return row[header_map['PLAYER']].strip()
            # Try mapping "Position" to "POS" for FA files
            if header_name == 'POS' and 'POSITION' in header_map and len(row) > header_map['POSITION']:
                return row[header_map['POSITION']].strip()
            return ''

        player_name = get_data('Name')
        
        # Skip rows that are just headers repeated or empty
        if not player_name or len(player_name) < 2 or player_name.upper() in ['NAME', 'PLAYER']:
            continue

        # --- DATA TRANSFORMATION ---
        
        if is_fa_file:
            # Simple FA Logic
            new_row = {
                'name': player_name,
                'position': get_data('POS'),
                'mlb_team': '', # FA files don't usually have this, or it's not mapped
                'league_id': target_league_id,
                'fantasy_team_id': '', # Always empty for FA
                'status_mlb': '',
                'status_milb': '',
                'status_40_man': '',
                'status_il': '', 
                'il_length': '', 
                'il_date': '',   
                'il_return': '', 
                'option_years_used': '',
                'dfa_only': '0',
                'rule_5_eligibility_year': '',
                'has_been_on_40_man': '0',
                'fa_status': 'available'
            }
            # No contracts for FA
            for year in range(2026, 2040):
                new_row[f'contract_{year}'] = ''
                
        else:
            # Standard Roster Logic
            raw_options = get_data('Option Years Used')
            dfa_only = '0'
            option_years_used = raw_options

            # --- FINAL CORRECTED DFA LOGIC ---
            dfa_only = '0'
            for cell in row:
                if cell.strip().upper() == 'D':
                    dfa_only = '1'
                    break
            
            raw_options = get_data('Option Years Used')
            if not raw_options:
                raw_options = get_data('Options Used')
                
            option_years_used = ''
            if dfa_only == '0':
                if ',' in raw_options:
                    parts = [p for p in raw_options.split(',') if p.strip()]
                    option_years_used = str(len(parts))
                elif raw_options.isdigit():
                    option_years_used = '1'
            
            rule_5_year = get_data('Rule 5 Eligibility')

            status_mlb = get_data('MLB')
            status_40_man = get_data('40')

            if status_mlb.upper() == 'X':
                status_40_man = 'X'

            new_row = {
                'name': player_name,
                'position': get_data('POS'),
                'mlb_team': get_data('Team'),
                'league_id': target_league_id,
                'fantasy_team_id': fantasy_team_id,
                'status_mlb': status_mlb,
                'status_milb': get_data('MiLB'),
                'status_40_man': status_40_man,
                'status_il': '', 
                'il_length': '', 
                'il_date': '',   
                'il_return': '', 
                'option_years_used': option_years_used,
                'dfa_only': dfa_only,
                'rule_5_eligibility_year': rule_5_year,
            }

            if new_row['status_40_man'].upper() == 'X':
                new_row['has_been_on_40_man'] = '1'
            else:
                new_row['has_been_on_40_man'] = '0'

            new_row['fa_status'] = 'rostered' if fantasy_team_id != "UNKNOWN" else 'available'

            for year in range(2026, 2040):
                val = get_data(str(year))
                if val:
                    val = val.replace('$', '').replace(',', '').strip()
                new_row[f'contract_{year}'] = val
        
        clean_rows.append(new_row)

    print(f"  -> Processed {len(lines)} lines. Found {len(clean_rows)} valid players.")

    if clean_rows:
        output_headers = list(clean_rows[0].keys())
        out_file = os.path.join(OUTPUT_FOLDER, f"clean_{filename}")
        out_file = os.path.splitext(out_file)[0] + '.csv'
        
        with open(out_file, 'w', newline='', encoding='utf-8') as f_out:
            writer = csv.DictWriter(f_out, fieldnames=output_headers)
            writer.writeheader()
            writer.writerows(clean_rows)
        print(f"  -> Saved to {out_file}")
    else:
        print(f"  -> WARNING: No valid players found in {input_path}")

# ==========================================
# MAIN EXECUTION
# ==========================================
if __name__ == "__main__":
    league_input = input("Enter the League ID for this batch (e.g., AA, MLB): ").strip().upper()
    if not league_input:
        print("No League ID entered. Exiting.")
        exit()
    
    print(f"\nProcessing all files for League: {league_input}\n")

    if not os.path.exists(INPUT_FOLDER):
        os.makedirs(INPUT_FOLDER)
        print(f"Created folder '{INPUT_FOLDER}'. Please put your files there.")
    else:
        if not os.path.exists(OUTPUT_FOLDER):
            os.makedirs(OUTPUT_FOLDER)
        
        files = [f for f in os.listdir(INPUT_FOLDER) if f.lower().endswith(('.csv', '.tsv', '.txt'))]
        
        if not files:
            print(f"No files found in '{INPUT_FOLDER}'.")
        else:
            print(f"Found {len(files)} files. Starting processing...")
            for f in files:
                normalize_csv(os.path.join(INPUT_FOLDER, f), OUTPUT_FOLDER, league_input)
            print("\nDone!")
