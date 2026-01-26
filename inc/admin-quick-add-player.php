<?php
/**
 * Admin Tool: Quick Add New Player (Full Version)
 */

function fod_quick_add_player_tool_menu() {
    add_submenu_page(
        'commissioner-tools', 
        'Quick Add Player',
        'Quick Add Player',
        'manage_options',
        'fod-quick-add-player',
        'fod_render_quick_add_player_page'
    );
}
add_action('admin_menu', 'fod_quick_add_player_tool_menu', 25);

function fod_render_quick_add_player_page() {
    if ( ! current_user_can('manage_options') ) { return; }

    $message = '';
    $error = '';

    if ( isset($_POST['fod_quick_add_nonce']) && wp_verify_nonce($_POST['fod_quick_add_nonce'], 'fod_quick_add_player') ) {
        $player_name = sanitize_text_field($_POST['player_name']);
        $league_id   = sanitize_text_field($_POST['league_id']);
        $team_id     = sanitize_text_field($_POST['team_id']);
        $position    = strtoupper(sanitize_text_field($_POST['position']));
        $mlb_team    = sanitize_text_field($_POST['mlb_team']);
        $contracts   = isset($_POST['contracts']) ? $_POST['contracts'] : [];

        if ( !empty($player_name) && !empty($league_id) ) {
            
            // 1. Create the Post
            $new_player_id = wp_insert_post([
                'post_title'  => $player_name,
                'post_type'   => 'playerdata',
                'post_status' => 'publish',
            ]);

            if ( $new_player_id && !is_wp_error($new_player_id) ) {
                // 2. Set Core Meta
                update_post_meta($new_player_id, 'league_id', $league_id);
                update_post_meta($new_player_id, 'fantasy_team_id', $team_id);
                update_post_meta($new_player_id, 'position', $position);
                update_post_meta($new_player_id, 'mlb_team', $mlb_team);
                update_post_meta($new_player_id, 'fa_status', !empty($team_id) ? 'rostered' : 'available');

                // 3. Set Toggles/Checkboxes
                update_post_meta($new_player_id, 'status_40_man', isset($_POST['status_40_man']) ? 'X' : '');
                update_post_meta($new_player_id, 'status_26_man', isset($_POST['status_26_man']) ? '1' : '0');
                update_post_meta($new_player_id, 'dfa_only', isset($_POST['dfa_only']) ? '1' : '0');
                update_post_meta($new_player_id, 'on_trade_block', isset($_POST['on_trade_block']) ? '1' : '0');
                
                // 4. Status/Log Fields
                update_post_meta($new_player_id, 'status_il', sanitize_text_field($_POST['status_il']));
                update_post_meta($new_player_id, 'option_years_used', absint($_POST['option_years_used']));
                update_post_meta($new_player_id, 'rule_5_eligibility_year', sanitize_text_field($_POST['rule_5_year']));
                update_post_meta($new_player_id, 'trade_block_notes', sanitize_text_field($_POST['trade_block_notes']));

                // 5. Set Contracts (2026 - 2035)
                foreach($contracts as $year => $amount) {
                    if ( !empty($amount) ) {
                        update_post_meta($new_player_id, 'contract_' . $year, sanitize_text_field($amount));
                    }
                }

                $message = "<strong>$player_name</strong> has been created successfully.";
            } else {
                $error = "Failed to create player. Please try again.";
            }
        } else {
            $error = "Player Name and League are required.";
        }
    }

    ?>
    <div class="wrap">
        <h1>Quick Add New Player</h1>
        
        <?php if ($message): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo $message; ?></p></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>

        <div class="card" style="max-width: 900px; padding: 20px; margin-top: 20px;">
            <form method="post">
                <?php wp_nonce_field('fod_quick_add_player', 'fod_quick_add_nonce'); ?>
                
                <h2 style="border-bottom: 1px solid #ccc; padding-bottom: 10px;">1. Identity & Assignment</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="player_name">Full Name</label></th>
                        <td><input type="text" name="player_name" id="player_name" class="regular-text" required placeholder="e.g. Shohei Ohtani"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="league_id">League</label></th>
                        <td>
                            <select name="league_id" id="qa_league_id" required>
                                <option value="">-- Select League --</option>
                                <option value="MLB">MLB</option>
                                <option value="AAA">AAA</option>
                                <option value="AA">AA</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="team_id">Fantasy Team</label></th>
                        <td>
                            <select name="team_id" id="qa_team_id">
                                <option value="">-- Select League First --</option>
                            </select>
                            <span id="qa-team-loading" style="display:none;">Loading...</span>
                        </td>
                    </tr>
                </table>

                <h2 style="border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-top: 30px;">2. Player Details</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Position & Team</th>
                        <td>
                            <input type="text" name="position" placeholder="Pos (e.g. SP)" style="width: 80px;">
                            <input type="text" name="mlb_team" placeholder="MLB Team (e.g. LAD)" style="width: 120px;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Roster Status</th>
                        <td>
                            <label><input type="checkbox" name="status_40_man"> 40-Man Roster</label> &nbsp;&nbsp;
                            <label><input type="checkbox" name="status_26_man"> 26-Man Roster</label> &nbsp;&nbsp;
                            <label><input type="checkbox" name="dfa_only"> DFA Only</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Injured List / Options</th>
                        <td>
                            <input type="text" name="status_il" placeholder="IL Status (e.g. 15-Day IL)" style="width: 150px;">
                            <input type="number" name="option_years_used" placeholder="Options Used" style="width: 100px;" min="0" max="3">
                            <input type="text" name="rule_5_year" placeholder="Rule 5 Year" style="width: 100px;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Trade Block</th>
                        <td>
                            <label><input type="checkbox" name="on_trade_block"> List on Trade Block</label><br>
                            <input type="text" name="trade_block_notes" placeholder="Trade block notes..." class="regular-text" style="margin-top:5px;">
                        </td>
                    </tr>
                </table>

                <h2 style="border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-top: 30px;">3. Contract Details</h2>
                <div style="display: flex; flex-wrap: wrap; gap: 15px; background: #f9f9f9; padding: 15px; border-radius: 4px;">
                    <?php for($y = 2026; $y <= 2035; $y++): ?>
                        <div>
                            <label style="font-size: 11px; font-weight: bold;"><?php echo $y; ?></label><br>
                            <input type="text" name="contracts[<?php echo $y; ?>]" placeholder="$ or ARB" style="width: 100px;">
                        </div>
                    <?php endfor; ?>
                </div>

                <p class="submit" style="margin-top: 30px;">
                    <input type="submit" class="button button-primary button-large" value="Create Player & Save All Data">
                </p>
            </form>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#qa_league_id').on('change', function() {
            const leagueId = $(this).val();
            const teamSelect = $('#qa_team_id');
            const loading = $('#qa-team-loading');

            teamSelect.prop('disabled', true).html('<option value="">Loading...</option>');
            if (!leagueId) {
                teamSelect.html('<option value="">-- Select League First --</option>');
                return;
            }

            loading.show();
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { action: 'fod_get_teams_by_league', league_id: leagueId },
                success: function(response) {
                    if (response.success) {
                        teamSelect.html('<option value="">-- Free Agent (None) --</option>');
                        response.data.forEach(function(team) {
                            teamSelect.append($('<option>', { value: team, text: team }));
                        });
                        teamSelect.prop('disabled', false);
                    }
                },
                complete: function() { loading.hide(); }
            });
        });
    });
    </script>
    <?php
}