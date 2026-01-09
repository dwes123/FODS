<?php
/**
 * Admin Tool: Manually Assign Player to a Team
 */

function fod_assign_player_tool_menu() {
    $hook = add_submenu_page(
        'tools.php',
        'Assign Player',
        'Assign Player',
        'manage_options',
        'fod-assign-player',
        'fod_render_assign_player_page'
    );
    
    // Enqueue Select2 only on this specific page
    add_action('admin_enqueue_scripts', function($current_hook) use ($hook) {
        if ($current_hook !== $hook) return;

        // Enqueue Select2 CSS & JS from CDN
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
    });
}
add_action('admin_menu', 'fod_assign_player_tool_menu');

function fod_render_assign_player_page() {
    if ( ! current_user_can('manage_options') ) {
        return;
    }

    $message = '';
    $error = '';

    if ( isset($_POST['fod_assign_nonce']) && wp_verify_nonce($_POST['fod_assign_nonce'], 'fod_assign_player') ) {
        $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
        $team_id   = isset($_POST['team_id'])   ? sanitize_text_field($_POST['team_id'])   : '';
        $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
        $contracts = isset($_POST['contracts']) ? $_POST['contracts'] : [];

        if ( $player_id && $team_id && $league_id ) {
            
            // Core Assignment
            update_post_meta($player_id, 'fantasy_team_id', $team_id);
            update_post_meta($player_id, 'league_id', $league_id);
            update_post_meta($player_id, 'fa_status', 'rostered');

            // Contracts (Updated to allow text like ARB 1)
            foreach($contracts as $year => $amount) {
                if ( is_numeric($year) && !empty($amount) ) {
                    // Sanitize as text field to allow letters
                    update_post_meta($player_id, 'contract_' . $year, sanitize_text_field($amount));
                }
            }

            // Additional Fields
            if ( isset($_POST['position']) ) update_post_meta($player_id, 'position', sanitize_text_field($_POST['position']));
            if ( isset($_POST['mlb_team']) ) update_post_meta($player_id, 'mlb_team', sanitize_text_field($_POST['mlb_team']));
            if ( isset($_POST['status_mlb']) ) update_post_meta($player_id, 'status_mlb', sanitize_text_field($_POST['status_mlb']));
            if ( isset($_POST['status_milb']) ) update_post_meta($player_id, 'status_milb', sanitize_text_field($_POST['status_milb']));
            
            // Checkboxes / Status
            $status_40 = isset($_POST['status_40_man']) ? 'X' : '';
            update_post_meta($player_id, 'status_40_man', $status_40);
            
            if ( isset($_POST['status_il']) ) update_post_meta($player_id, 'status_il', sanitize_text_field($_POST['status_il']));
            
            if ( isset($_POST['option_years_used']) ) update_post_meta($player_id, 'option_years_used', sanitize_text_field($_POST['option_years_used']));
            if ( isset($_POST['rule_5_eligibility_year']) ) update_post_meta($player_id, 'rule_5_eligibility_year', sanitize_text_field($_POST['rule_5_eligibility_year']));
            
            $dfa_only = isset($_POST['dfa_only']) ? 1 : 0;
            update_post_meta($player_id, 'dfa_only', $dfa_only);
            
            $message = get_the_title($player_id) . " has been updated and assigned to " . $team_id . ".";

        } else {
            $error = "Player, League, and Team are required.";
        }
    }

    ?>
    <div class="wrap">
        <h1>Assign Player to Team</h1>
        
        <?php if ($message): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>

        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
            <form method="post">
                <?php wp_nonce_field('fod_assign_player', 'fod_assign_nonce'); ?>
                
                <table class="form-table">
                    <!-- Core Assignment -->
                    <tr>
                        <th scope="row"><label for="league_id">Select League</label></th>
                        <td>
                            <select name="league_id" id="league_id" required>
                                <option value="">-- Select League --</option>
                                <option value="MLB">MLB</option>
                                <option value="AAA">AAA</option>
                                <option value="AA">AA</option>
                                <option value="NBA">NBA</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="team_id">Assign to Team</label></th>
                        <td>
                            <select name="team_id" id="team_id" required disabled>
                                <option value="">-- Select League First --</option>
                            </select>
                            <span id="team-loading" style="display:none;">Loading teams...</span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="player_id">Player</label></th>
                        <td>
                            <select name="player_id" id="player_id" class="player-search-select" style="width: 100%;" required disabled>
                                <option value="">Select League First</option>
                            </select>
                            <p class="description">Start typing a player's name to search.</p>
                        </td>
                    </tr>

                    <!-- Player Details -->
                    <tr>
                        <th scope="row">Player Details</th>
                        <td>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <div>
                                    <label for="position">Position</label><br>
                                    <input type="text" name="position" id="position" class="small-text">
                                </div>
                                <div>
                                    <label for="mlb_team">MLB Team</label><br>
                                    <input type="text" name="mlb_team" id="mlb_team" class="small-text">
                                </div>
                            </div>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <div>
                                    <label for="status_mlb">Status MLB</label><br>
                                    <input type="text" name="status_mlb" id="status_mlb" class="small-text" placeholder="e.g. X">
                                </div>
                                <div>
                                    <label for="status_milb">Status MiLB</label><br>
                                    <input type="text" name="status_milb" id="status_milb" class="small-text" placeholder="e.g. X">
                                </div>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label for="status_40_man">
                                    <input type="checkbox" name="status_40_man" id="status_40_man" value="X">
                                    On 40-Man Roster
                                </label>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label for="status_il">Status IL</label><br>
                                <input type="text" name="status_il" id="status_il" class="regular-text" placeholder="e.g. IL-15">
                            </div>
                            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                                <div>
                                    <label for="option_years_used">Option Years Used</label><br>
                                    <input type="number" name="option_years_used" id="option_years_used" class="small-text" min="0" max="3">
                                </div>
                                <div>
                                    <label for="rule_5_eligibility_year">Rule 5 Year</label><br>
                                    <input type="text" name="rule_5_eligibility_year" id="rule_5_eligibility_year" class="small-text">
                                </div>
                            </div>
                            <div>
                                <label for="dfa_only">
                                    <input type="checkbox" name="dfa_only" id="dfa_only" value="1">
                                    DFA Only
                                </label>
                            </div>
                        </td>
                    </tr>

                    <!-- Contracts -->
                    <tr>
                        <th scope="row">Contract Details</th>
                        <td>
                            <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                <?php for($y = 2026; $y <= 2040; $y++): ?>
                                    <div>
                                        <label for="contract_<?php echo $y; ?>" style="font-size: 12px;"><?php echo $y; ?></label><br>
                                        <!-- Changed type="number" to type="text" to allow ARB 1, etc. -->
                                        <input type="text" name="contracts[<?php echo $y; ?>]" id="contract_<?php echo $y; ?>" placeholder="$ or Status" style="width: 80px;">
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="Assign Player">
                </p>
            </form>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        const leagueSelect = $('#league_id');
        const teamSelect = $('#team_id');
        const playerSelect = $('#player_id');
        const teamLoading = $('#team-loading');

        // Handle League Change
        leagueSelect.on('change', function() {
            const leagueId = $(this).val();
            
            // Reset fields
            teamSelect.prop('disabled', true).html('<option value="">-- Select League First --</option>');
            playerSelect.prop('disabled', true).empty().append('<option value="">Select League First</option>');

            if (!leagueId) return;

            // Enable fields
            playerSelect.prop('disabled', false).empty().append('<option value="">Search for a player...</option>');
            
            // Fetch Teams via AJAX
            teamLoading.show();
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fod_get_teams_by_league',
                    league_id: leagueId
                },
                success: function(response) {
                    if (response.success) {
                        teamSelect.html('<option value="">-- Select Team --</option>');
                        response.data.forEach(function(team) {
                            teamSelect.append($('<option>', { value: team, text: team }));
                        });
                        teamSelect.prop('disabled', false);
                    }
                },
                complete: function() {
                    teamLoading.hide();
                }
            });
        });

        // Initialize Select2 for Player Search
        if (typeof $().select2 === 'function') {
            playerSelect.select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            action: 'fod_search_all_players',
                            q: params.term,
                            league_id: leagueSelect.val() // Pass selected league to filter
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                minimumInputLength: 2,
                placeholder: 'Search for a player...'
            });

            // When a player is selected, fetch their current data
            playerSelect.on('select2:select', function (e) {
                var data = e.params.data;
                var playerId = data.id;
                
                // We could add an AJAX call here to pre-fill the form fields 
                // with the player's existing data if you want.
                // For now, it just selects the ID.
            });
        }
    });
    </script>
    <?php
}

// AJAX: Get Teams by League
function fod_get_teams_by_league_handler() {
    // No nonce check needed for simple read-only list, but good practice to add if public
    // Since this is admin-only page, capability check is handled by the page load, but AJAX is separate.
    if ( ! current_user_can('manage_options') ) { wp_send_json_error('Unauthorized'); }

    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    if ( empty($league_id) ) { wp_send_json_error('Missing League ID'); }

    $teams = [];
    $users = get_users();

    if ( function_exists('get_field') ) {
        foreach ( $users as $user ) {
            $managed = get_field('managed_teams', 'user_' . $user->ID);
            if ( $league_id === 'NBA' ) {
                $managed = get_field('managed_nba_teams', 'user_' . $user->ID);
            }

            if ( is_array($managed) ) {
                foreach ( $managed as $t ) {
                    if ( isset($t['league_id']) && $t['league_id'] === $league_id && !empty($t['fantasy_team_id']) ) {
                        $teams[] = $t['fantasy_team_id'];
                    }
                }
            }
        }
    }
    $teams = array_unique($teams);
    sort($teams);
    wp_send_json_success($teams);
}
add_action('wp_ajax_fod_get_teams_by_league', 'fod_get_teams_by_league_handler');

// AJAX: Search Players (Filtered by League)
function fod_search_all_players_ajax_handler() {
    $term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $league_id = isset($_GET['league_id']) ? sanitize_text_field($_GET['league_id']) : '';
    
    if (empty($term)) { wp_send_json([]); }

    $post_type = ($league_id === 'NBA') ? 'nbaplayer' : 'playerdata';

    $query_args = [
        'post_type' => $post_type,
        's' => $term,
        'posts_per_page' => 50,
        'meta_query' => [
            'relation' => 'AND',
            // Filter by League ID if provided
            $league_id ? ['key' => 'league_id', 'value' => $league_id] : [],
        ]
    ];

    $query = new WP_Query($query_args);
    $results = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $results[] = [
                'id' => get_the_ID(),
                'text' => get_the_title() . ' (' . (get_field('position') ?: 'N/A') . ' - ' . (get_field('mlb_team') ?: 'FA') . ')'
            ];
        }
    }
    wp_send_json($results);
}
add_action('wp_ajax_fod_search_all_players', 'fod_search_all_players_ajax_handler');
