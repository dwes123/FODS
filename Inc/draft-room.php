<?php
/**
 * Draft Room Functionality
 * Allows commissioners to draft players to teams for a specific league (e.g., AA).
 */

/* ------------------------------------------------------------------------
   [draft_room] Shortcode
------------------------------------------------------------------------ */
function display_draft_room_shortcode() {
    if ( ! current_user_can('manage_options') ) {
        return '<p>Access Denied: You must be an administrator to access the Draft Room.</p>';
    }

    ob_start();
    ?>
    <div class="draft-room-wrapper">
        <h2>League Draft Room</h2>
        <div class="draft-controls">
            <!-- League Selector -->
            <div class="draft-control-group">
                <label for="draft-league-select">Select League:</label>
                <select id="draft-league-select">
                    <option value="">-- Select League --</option>
                    <option value="AA">AA</option>
                    <option value="AAA">AAA</option>
                    <option value="MLB">MLB</option>
                    <option value="NBA">NBA</option>
                </select>
            </div>

            <!-- Team Selector (Populated via AJAX) -->
            <div class="draft-control-group">
                <label for="draft-team-select">Drafting Team:</label>
                <select id="draft-team-select" disabled>
                    <option value="">-- Select League First --</option>
                </select>
                <span id="draft-team-loading" style="display:none;">Loading teams...</span>
            </div>
        </div>

        <hr>

        <div class="draft-action-area" style="display:none;" id="draft-action-area">
            <h3>On The Clock: <span id="current-drafting-team" style="color: #0073aa;">None</span></h3>

            <!-- Player Search -->
            <div class="draft-player-search">
                <label for="draft-player-search-input">Search Available Player:</label>
                <input type="text" id="draft-player-search-input" placeholder="Type player name..." autocomplete="off">
                <input type="hidden" id="selected-player-id">
                <div id="draft-player-results" class="draft-search-results"></div>
            </div>

            <!-- Selected Player Display -->
            <div id="selected-player-display" style="display:none; margin: 20px 0; padding: 15px; background: #f0f0f1; border: 1px solid #ccc;">
                <strong>Selected Player:</strong> <span id="selected-player-name"></span> <span id="selected-player-pos"></span>
                <button type="button" id="clear-selected-player" class="button button-small">Change</button>
            </div>

            <!-- Contract Details (Optional) -->
            <div class="draft-contract-details">
                <h4>Initial Contract (Optional)</h4>
                <p class="description">Leave blank for standard minor league / empty contract.</p>
                <div class="contract-inputs" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php 
                    $current_year = (int) date('Y');
                    for ($i = 0; $i < 4; $i++) {
                        $y = $current_year + $i;
                        echo '<div><label>'.$y.'</label><input type="number" class="draft-contract-input" data-year="'.$y.'" placeholder="$" style="width: 80px;"></div>';
                    }
                    ?>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="button" id="submit-draft-pick" class="button button-primary button-large" disabled>Draft Player</button>
                <span id="draft-processing" style="display:none; margin-left: 10px;">Processing...</span>
            </div>
            
            <div id="draft-message-area" style="margin-top: 15px;"></div>
        </div>
    </div>

    <style>
        .draft-room-wrapper { max-width: 800px; margin: 0 auto; padding: 20px; background: #fff; border: 1px solid #e5e5e5; }
        .draft-control-group { margin-bottom: 15px; }
        .draft-control-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .draft-control-group select { width: 100%; max-width: 400px; }
        .draft-search-results { border: 1px solid #ccc; max-height: 200px; overflow-y: auto; display: none; position: absolute; background: #fff; width: 100%; max-width: 400px; z-index: 100; }
        .draft-search-result-item { padding: 8px; cursor: pointer; border-bottom: 1px solid #eee; }
        .draft-search-result-item:hover { background-color: #f0f0f1; }
        .draft-player-search { position: relative; max-width: 400px; }
        .draft-player-search input { width: 100%; }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('draft_room', 'display_draft_room_shortcode');

/* ------------------------------------------------------------------------
   AJAX Handlers
------------------------------------------------------------------------ */

/**
 * Get Teams for a specific League
 */
function ajax_get_draft_teams() {
    check_ajax_referer('draft_room_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) { wp_send_json_error('Unauthorized'); }

    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    if ( empty($league_id) ) { wp_send_json_error('Missing League ID'); }

    $teams = [];
    $users = get_users(); // Get all users to check their managed teams

    if ( function_exists('get_field') ) {
        foreach ( $users as $user ) {
            $managed = get_field('managed_teams', 'user_' . $user->ID);
            // Also check NBA teams if league is NBA
            if ( $league_id === 'NBA' ) {
                $managed = get_field('managed_nba_teams', 'user_' . $user->ID);
            }

            if ( is_array($managed) ) {
                foreach ( $managed as $t ) {
                    if ( isset($t['league_id']) && $t['league_id'] === $league_id && !empty($t['fantasy_team_id']) ) {
                        $teams[ $t['fantasy_team_id'] ] = $t['fantasy_team_id']; // Use key to dedup
                    }
                }
            }
        }
    }

    sort($teams);
    wp_send_json_success( array_values($teams) );
}
add_action('wp_ajax_get_draft_teams', 'ajax_get_draft_teams');

/**
 * Search Available Players in League
 */
function ajax_search_draft_players() {
    check_ajax_referer('draft_room_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) { wp_send_json_error('Unauthorized'); }

    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    $term      = isset($_POST['term'])      ? sanitize_text_field($_POST['term'])      : '';

    if ( empty($league_id) || strlen($term) < 2 ) { wp_send_json_error('Invalid request'); }

    $post_type = ($league_id === 'NBA') ? 'nbaplayer' : 'playerdata';

    $args = array(
        'post_type'      => $post_type,
        'posts_per_page' => 20,
        's'              => $term,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'   => 'league_id',
                'value' => $league_id
            ),
            array(
                'relation' => 'OR',
                array(
                    'key'     => 'fa_status',
                    'value'   => 'rostered',
                    'compare' => '!='
                ),
                array(
                    'key'     => 'fa_status',
                    'compare' => 'NOT EXISTS'
                )
            )
        )
    );

    $q = new WP_Query($args);
    $results = [];

    if ( $q->have_posts() ) {
        while ( $q->have_posts() ) {
            $q->the_post();
            $pid = get_the_ID();
            $pos = get_post_meta($pid, 'position', true);
            $mlb = get_post_meta($pid, 'mlb_team', true);
            $results[] = array(
                'id'   => $pid,
                'name' => get_the_title(),
                'info' => ($pos ? $pos : '') . ($mlb ? ' - ' . $mlb : '')
            );
        }
    }
    wp_reset_postdata();

    wp_send_json_success($results);
}
add_action('wp_ajax_search_draft_players', 'ajax_search_draft_players');

/**
 * Process Draft Pick
 */
function ajax_submit_draft_pick() {
    check_ajax_referer('draft_room_nonce', 'nonce');
    if ( ! current_user_can('manage_options') ) { wp_send_json_error('Unauthorized'); }

    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $team_id   = isset($_POST['team_id'])   ? sanitize_text_field($_POST['team_id']) : '';
    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    $contracts = isset($_POST['contracts']) ? $_POST['contracts'] : [];

    if ( !$player_id || !$team_id || !$league_id ) { wp_send_json_error('Missing required data.'); }

    // Verify player is in correct league
    $p_league = get_post_meta($player_id, 'league_id', true);
    if ( $p_league !== $league_id ) {
        wp_send_json_error("Player is in league '$p_league', but you are drafting for '$league_id'.");
    }

    // Update Player
    update_post_meta($player_id, 'fantasy_team_id', $team_id);
    update_post_meta($player_id, 'fa_status', 'rostered');
    
    // Clear any pending bid/waiver stuff just in case
    update_post_meta($player_id, 'pending_bid_team_id', '');
    update_post_meta($player_id, 'pending_bid_amount', '');
    update_post_meta($player_id, 'waiving_team_id', '');
    update_post_meta($player_id, 'waiver_end_time', '');

    // Contracts
    if ( is_array($contracts) ) {
        foreach ( $contracts as $year => $amount ) {
            if ( is_numeric($amount) && $amount >= 0 ) {
                update_post_meta($player_id, 'contract_' . intval($year), floatval($amount));
            }
        }
    }

    // Log Transaction
    $log_args = [
        'transaction_type' => 'Draft Pick',
        'player_ids'       => [$player_id],
        'primary_team'     => $team_id,
        'summary'          => esc_html($team_id) . ' drafted ' . get_the_title($player_id) . ' in the ' . esc_html($league_id) . ' draft.',
    ];
    do_action('my_fantasy_transaction', $log_args);

    wp_send_json_success( get_the_title($player_id) . ' successfully drafted to ' . $team_id );
}
add_action('wp_ajax_submit_draft_pick', 'ajax_submit_draft_pick');
