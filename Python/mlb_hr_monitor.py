# -*- coding: utf-8 -*-
import requests
import time
import base64
import json
import os
from datetime import datetime

# ==========================================
# CONFIGURATION
# ==========================================
SITE_URL = "https://frontofficedynastysports.com"
USERNAME = "djwes487"
APP_PASSWORD = "YOUR_APP_PASSWORD_HERE" 

# Local file to remember processed home runs across runs
# Will be created in the same folder as the script
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
STATE_FILE = os.path.join(SCRIPT_DIR, "mlb_monitor_state.json")
# ==========================================

def load_state():
    if os.path.exists(STATE_FILE):
        try:
            with open(STATE_FILE, "r") as f:
                return set(json.load(f))
        except:
            return set()
    return set()

def save_state(processed_ids):
    with open(STATE_FILE, "w") as f:
        # Keep only last 500 play IDs to prevent file growing indefinitely
        json.dump(list(processed_ids)[-500:], f)

def get_auth_header():
    credentials = f"{USERNAME}:{APP_PASSWORD}"
    token = base64.b64encode(credentials.encode()).decode("utf-8")
    return {"Authorization": f"Basic {token}"}

def fetch_roster_map():
    url = f"{SITE_URL}/wp-json/fod/v1/mlb-owner-map"
    try:
        response = requests.get(url, timeout=30)
        response.raise_for_status()
        return response.json()
    except Exception as e:
        print(f"Error fetching roster map: {e}")
        return {}

def fetch_slack_config():
    url = f"{SITE_URL}/wp-json/fod/v1/slack-config"
    try:
        response = requests.get(url, headers=get_auth_header(), timeout=30)
        response.raise_for_status()
        return response.json()
    except Exception as e:
        print(f"Error fetching Slack config: {e}")
        return []

def send_slack_alert(message, league_id, slack_configs):
    config = next((item for item in slack_configs if item['league_id'].upper() == league_id.upper()), None)
    if not config: return
    
    token = config.get('bot_token')
    # Use ONLY the Stat Alerts channel. If blank, do not post.
    channel = config.get('stat_alerts_channel_id')
    
    if not token or not channel:
        return

    payload = {"channel": channel, "text": message}
    headers = {"Content-Type": "application/json; charset=utf-8", "Authorization": f"Bearer {token}"}
    try:
        requests.post("https://slack.com/api/chat.postMessage", json=payload, headers=headers, timeout=10)
    except: 
        pass

def monitor_games():
    processed_home_runs = load_state()
    roster_map = fetch_roster_map()
    slack_configs = fetch_slack_config()
    
    if not roster_map: return

    today = datetime.now().strftime('%Y-%m-%d')
    url = f"https://statsapi.mlb.com/api/v1/schedule/games/?sportId=1&date={today}"
    try:
        resp = requests.get(url, timeout=20)
        games = resp.json().get('dates', [{}])[0].get('games', [])
    except:
        return

    for game in games:
        game_id = game.get('gamePk')
        status = game.get('status', {}).get('abstractGameState')
        if status != 'Live': continue

        feed_url = f"https://statsapi.mlb.com/api/v1/game/{game_id}/feed/live"
        try:
            game_data = requests.get(feed_url, timeout=20).json()
            all_plays = game_data.get('liveData', {}).get('plays', {}).get('allPlays', [])
        except:
            continue

        for play in all_plays:
            result = play.get('result', {})
            if result.get('event', '').lower() == 'home run':
                play_id = str(game_id) + "_" + str(play.get('about', {}).get('atBatIndex'))
                if play_id in processed_home_runs: continue

                batter = play.get('matchup', {}).get('batter', {})
                batter_id = str(batter.get('id'))
                batter_name = batter.get('fullName')
                
                owner_info = roster_map.get(batter_id) or roster_map.get(batter_name)
                
                if owner_info:
                    msg = f"🚀 *HOME RUN!* _{batter_name}_ just went deep!\n"
                    msg += f"🏟️ *Fantasy Owner:* `{owner_info['team']}` ({owner_info['league']})\n"
                    msg += f"📝 _{result.get('description', '')}_"
                    send_slack_alert(msg, owner_info['league'], slack_configs)
                
                processed_home_runs.add(play_id)

    save_state(processed_home_runs)

if __name__ == "__main__":
    monitor_games()
