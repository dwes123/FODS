<?php
/**
 * All theme shortcodes are defined in this file.
 * Refactored to use helper functions from /inc/roster-helpers.php
 */

/* ------------------------------------------------------------------------
   [my_team_roster] — Roster w/ Dead Cap totals & breakdown
------------------------------------------------------------------------ */
function display_manager_roster_shortcode() {
    if ( ! is_user_logged_in() ) { return '<p>Please log in to view your team roster(s).</p>'; }
    if ( ! function_exists('get_field') || ! function_exists('fod_render_player_roster_row') ) { return '<p>Error: A required plugin or helper file is not active.</p>'; }

    $user_id  = get_current_user_id();
    $teams    = get_field('managed_teams', 'user_' . $user_id);

    if ( empty($teams) || ! is_array($teams) ) {
        return '<p>No teams are currently assigned to your account.</p>';
    }

    // --- Determine selected team ---
    $selected_league_id = null;
    $selected_team_id   = null;
    if ( isset($_GET['l_id'], $_GET['t_id']) ) {
        $rl = sanitize_text_field( wp_unslash($_GET['l_id']) );
        $rt = sanitize_text_field( wp_unslash($_GET['t_id']) );
        foreach ($teams as $t) {
            if ( is_array($t) && ($t['league_id'] ?? '') === $rl && ($t['fantasy_team_id'] ?? '') === $rt ) {
                $selected_league_id = $rl;
                $selected_team_id   = $rt;
                break;
            }
        }
    }
    if ( ! $selected_league_id || ! $selected_team_id ) {
        $first = $teams[0];
        $selected_league_id = $first['league_id'] ?? '';
        $selected_team_id   = $first['fantasy_team_id'] ?? '';
    }
    if ( ! $selected_league_id || ! $selected_team_id ) {
        return '<p>Error: Could not determine which team to display.</p>';
    }

    $years_to_process = range( 2026, 2040 );
    $salary_totals    = array_fill_keys($years_to_process, 0.0);

    // --- Build Rows using Helper ---
    $build_rows = function( $q, $is_readonly = false ) use (&$salary_totals, $years_to_process, $selected_league_id, $selected_team_id) {
        $rows = [];
        $config = [
            'is_readonly'        => $is_readonly,
            'years_to_process'   => $years_to_process,
            'selected_league_id' => $selected_league_id,
            'selected_team_id'   => $selected_team_id,
        ];
        foreach ( $q->posts as $player_id ) {
            $rows[] = fod_render_player_roster_row( $player_id, $config, $salary_totals );
        }
        return $rows;
    };

    // --- Queries ---
    $base_team_query = [
        'relation' => 'AND',
        [ 'key' => 'league_id', 'value' => $selected_league_id ],
        [ 'key' => 'fantasy_team_id', 'value' => $selected_team_id ],
        [ 'relation' => 'OR',
            [ 'key'=>'fa_status', 'value'=>'rostered', 'compare'=>'=' ],
            [ 'key'=>'fa_status', 'compare'=>'NOT EXISTS' ],
        ],
        'position_clause' => [ 'key'=>'position', 'compare'=>'EXISTS' ],
    ];
    $not_on_il_query = [
        'relation' => 'OR',
        [ 'key' => 'status_il', 'compare' => 'NOT EXISTS' ],
        [ 'key' => 'status_il', 'value' => '', 'compare' => '=' ],
    ];

    // 40-Man Roster
    $head = '<thead><tr><th>Action</th><th>Name</th><th>Position</th><th>MLB Team</th><th>IL</th><th>40-Man</th><th>26-Man</th><th>DFA Only</th><th>Options (Season)</th><th>Option Years Used</th><th>Rule 5 Year</th>';
    foreach ($years_to_process as $y) $head .= '<th>'.esc_html($y).'</th>';
    $head .= '</tr></thead>';
    $col_count = 11 + count($years_to_process);

    $meta_query_40 = $base_team_query;
    $meta_query_40[] = $not_on_il_query;
    $meta_query_40[] = [ 'key' => 'status_40_man', 'value' => 'X' ];
    $filter_26_man = isset($_GET['filter_26_man']) && $_GET['filter_26_man'] == '1';
    if ( $filter_26_man ) {
        $meta_query_40[] = [ 'key' => 'status_26_man', 'value' => '1' ];
    }
    $args_40 = [ 'post_type' => 'playerdata', 'posts_per_page' => 300, 'fields' => 'ids', 'meta_query' => $meta_query_40, 'orderby' => [ 'position_clause' => 'ASC', 'title' => 'ASC' ] ];
    $q_40 = new WP_Query($args_40);
    $forty_rows = $build_rows($q_40);
    $count_40 = $q_40->post_count;

    // Minor League / Off-Roster
    $meta_query_n40 = $base_team_query;
    $meta_query_n40[] = $not_on_il_query;
    $meta_query_n40[] = [ 'relation' => 'OR', [ 'key' => 'status_40_man', 'compare' => 'NOT EXISTS' ], [ 'key' => 'status_40_man', 'value' => 'X', 'compare' => '!=' ] ];
    $args_n40 = [ 'post_type' => 'playerdata', 'posts_per_page' => 300, 'fields' => 'ids', 'meta_query' => $meta_query_n40, 'orderby' => [ 'position_clause' => 'ASC', 'title' => 'ASC' ] ];
    $q_n40 = new WP_Query($args_n40);
    $non40_rows = $build_rows($q_n40);
    $count_minors = $q_n40->post_count;

    // Injured List Roster
    $meta_query_il = $base_team_query;
    $meta_query_il[] = [ 'key' => 'status_il', 'compare' => 'EXISTS' ];
    $meta_query_il[] = [ 'key' => 'status_il', 'value' => '', 'compare' => '!=' ];
    $args_il = [ 'post_type' => 'playerdata', 'posts_per_page' => 300, 'fields' => 'ids', 'meta_query' => $meta_query_il, 'orderby' => [ 'position_clause' => 'ASC', 'title' => 'ASC' ] ];
    $q_il = new WP_Query($args_il);
    $il_rows = $build_rows($q_il);
    $count_il = $q_il->post_count;

    // 26-Man Roster Count
    $meta_query_26 = $base_team_query;
    $meta_query_26[] = ['key' => 'status_26_man', 'value' => '1', 'compare' => '='];
    $q_26 = new WP_Query([
        'post_type' => 'playerdata',
        'fields' => 'ids',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'meta_query' => $meta_query_26
    ]);
    $count_26 = $q_26->post_count;

    // Dead Cap Data
    $dead_cap_data = fod_calculate_dead_cap( $selected_league_id, $selected_team_id, $years_to_process );
    $dead_cap_totals = $dead_cap_data['totals'];
    $grouped_dead_cap = $dead_cap_data['grouped'];

    // --- START: Print HTML ---
    ob_start();

    // Team Selector Links
    $links = [];
    $base = get_permalink();
    foreach ($teams as $t) {
        if (!is_array($t) || empty($t['league_id']) || empty($t['fantasy_team_id'])) continue;
        $url = add_query_arg(['l_id'=>rawurlencode($t['league_id']), 't_id'=>rawurlencode($t['fantasy_team_id'])], $base);
        $sel = ($t['league_id']===$selected_league_id && $t['fantasy_team_id']===$selected_team_id) ? ' class="is-selected"' : '';
        $links[] = '<a href="'.esc_url($url).'"'.$sel.'>'.esc_html($t['league_id']).' - '.esc_html($t['fantasy_team_id']).'</a>';
    }
    echo '<div class="team-selector-ui"><strong>View Roster For:</strong> ' . implode(' | ', $links) . '</div>';

    // 26-Man Filter Links
    $base_url = get_permalink();
    $url_params = [ 'l_id' => $selected_league_id, 't_id' => $selected_team_id ];
    echo '<div class="team-selector-ui" style="margin-top: 10px;"><strong>Filter Roster:</strong> ';
    if ( $filter_26_man ) {
        echo '<a href="' . esc_url(add_query_arg($url_params, $base_url)) . '">Show All 40-Man Players</a>';
    } else {
        $url_params['filter_26_man'] = '1';
        echo '<a href="' . esc_url(add_query_arg($url_params, $base_url)) . '">Show 26-Man Roster Only</a>';
    }
    echo '</div>';

    // Header
    $logo_url = ''; // This was undefined before, ensure it's initialized.
    if ($logo_url) {
        echo '<div class="team-header"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr($selected_team_id) . ' Logo" class="team-logo-img"></div>';
    }
    echo '<h2>Roster for League: '.esc_html($selected_league_id).', Team: '.esc_html($selected_team_id).'</h2>';
    echo '<div id="roster-notices-container"></div>';

    // Roster Counts
    echo '<h4>Roster Counts</h4>';
    echo '<table class="fantasy-table-base" style="width: auto; margin-bottom: 30px;">';
    echo '<thead><tr><th>26-Man</th><th>40-Man</th><th>Minors</th><th>Injured List</th></tr></thead>';
    echo '<tbody><tr>';
    $style_26 = ($count_26 > 26) ? 'style="color: red; font-weight: bold;"' : '';
    echo '<td ' . $style_26 . ' style="text-align: center;">' . esc_html($count_26) . ' / 26</td>';
    echo '<td style="text-align: center;">' . esc_html($count_40) . ' / 40</td>';
    echo '<td style="text-align: center;">' . esc_html($count_minors) . '</td>';
    echo '<td style="text-align: center;">' . esc_html($count_il) . '</td>';
    echo '</tr></tbody></table>';

    // Salary Table
    echo fod_render_salary_summary_table( $salary_totals, $dead_cap_totals, $years_to_process );

    // Dead Cap Breakdown
    if ( ! empty($grouped_dead_cap) ) {
        krsort($grouped_dead_cap);
        echo '<h3>Dead Cap Breakdown</h3>';
        echo '<table class="fantasy-table-base deadcap-breakdown"><thead><tr><th>Year</th><th>Player</th><th>Amount</th><th>Type</th></tr></thead><tbody>';
        foreach ( $grouped_dead_cap as $year => $penalties ) {
            usort($penalties, function($a, $b) { return $b['amount'] <=> $a['amount']; });
            $players_html = implode('<br>', array_map('esc_html', array_column($penalties, 'player')));
            $amounts_html = implode('<br>', array_map(function($p) { return '$' . number_format($p['amount'], 0); }, $penalties));
            $types_html = implode('<br>', array_map('esc_html', array_column($penalties, 'type')));
            echo '<tr><td><strong>'.esc_html($year).'</strong></td><td>'.$players_html.'</td><td>'.$amounts_html.'</td><td>'.$types_html.'</td></tr>';
        }
        echo '</tbody></table>';
    }

    // 40-Man Roster
    echo '<h3>40-Man Roster</h3>';
    echo '<table id="roster-40-table" class="fantasy-table-base">' . $head . '<tbody id="roster-40-tbody">';
    echo $forty_rows ? implode('', $forty_rows) : '<tr><td colspan="' . $col_count . '">No players found on the 40-man roster for this team.</td></tr>';
    echo '</tbody></table>';

    // Injured List
    echo '<h3>Injured List</h3>';
    echo '<table id="roster-il-table" class="fantasy-table-base">' . $head . '<tbody id="roster-il-tbody">';
    echo $il_rows ? implode('', $il_rows) : '<tr><td colspan="' . $col_count . '">No players found on the Injured List.</td></tr>';
    echo '</tbody></table>';

    // Minor League / Off-Roster
    echo '<h3>Minor League / Off-Roster</h3>';
    echo '<table id="roster-minors-table" class="fantasy-table-base">' . $head . '<tbody id="roster-minors-tbody">';
    echo $non40_rows ? implode('', $non40_rows) : '<tr><td colspan="' . $col_count . '">No players found off the 40-man roster for this team.</td></tr>';
    echo '</tbody></table>';

    // IL Modal
    ?>
    <div id="il-modal" class="fantasy-modal fa-modal-hidden">
        <div class="fa-modal-content">
            <span class="fa-modal-close"></span>
            <h3 id="il-modal-title">Place Player on IL</h3>
            <div id="il-modal-message"></div>
            <form id="il-form">
                <input type="hidden" id="il-modal-playerid" name="player_id" value="">
                <p>Select the IL duration. This will remove the player from the 26-man and 40-man rosters.</p>
                <div class="il-duration-options">
                    <div><input type="radio" name="il_duration" id="il-8" value="8" checked><label for="il-8"><strong>8-Day IL</strong> (Hitters)</label></div>
                    <div><input type="radio" name="il_duration" id="il-12" value="12"><label for="il-12"><strong>12-Day IL</strong> (Pitchers)</label></div>
                    <div><input type="radio" name="il_duration" id="il-60" value="60"><label for="il-60"><strong>60-Day IL</strong></label></div>
                </div>
                <hr>
                <button type="submit" id="il-submit-button" class="button button-primary">Place on IL</button>
                <button type="button" class="button button-secondary fa-modal-cancel">Cancel</button>
            </form>
        </div>
    </div>
    <div id="dfa-modal" class="fantasy-modal fa-modal-hidden">
        <div class="fa-modal-content">
            <span class="fa-modal-close"></span>
            <h3 id="dfa-modal-title">Designate Player for Assignment</h3>
            <div id="dfa-modal-message"></div>
            <form id="dfa-form">
                <input type="hidden" id="dfa-modal-playerid" name="player_id" value="">
                <p>This will place the player on waivers for 48 hours. If unclaimed, the selected action will be executed.</p>
                <div class="dfa-options">
                    <div><input type="radio" name="dfa_action" id="dfa-release" value="release" checked><label for="dfa-release"><strong>Release from Team</strong> (becomes Free Agent)</label></div>
                    <div><input type="radio" name="dfa_action" id="dfa-minors" value="minors"><label for="dfa-minors"><strong>Assign to Minors</strong> (moves to your minor league roster)</label></div>
                </div>
                <hr>
                <button type="submit" id="dfa-submit-button" class="button button-primary">Submit DFA</button>
                <button type="button" class="button button-secondary fa-modal-cancel">Cancel</button>
            </form>
        </div>
    </div>
    <style>
    #il-modal.fa-modal-hidden, #dfa-modal.fa-modal-hidden { display: none !important; }
    .il-duration-options, .dfa-options { display: flex; flex-direction: column; gap: 15px; margin: 20px 0; }
    .il-duration-options div, .dfa-options div { display: flex; align-items: center; gap: 8px; }
    .il-duration-options input[type="radio"], .dfa-options input[type="radio"] { width: 20px; height: 20px; }
    .il-duration-options label, .dfa-options label { font-size: 1.1em; margin-bottom: 0; }
    </style>
    <?php

    return ob_get_clean();
}
add_shortcode( 'my_team_roster', 'display_manager_roster_shortcode' );

/* ------------------------------------------------------------------------
   [league_rosters] — Read-only roster viewer for all teams
------------------------------------------------------------------------ */
function display_league_rosters_shortcode() {
    if ( ! is_user_logged_in() ) { return '<p>Please log in to view league rosters.</p>'; }
    if ( ! function_exists('get_field') || ! function_exists('fod_render_player_roster_row') ) { return '<p>Error: A required plugin or helper file is not active.</p>'; }

    global $wpdb;
    $all_leagues = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s ORDER BY meta_value ASC", 'league_id', 'playerdata' ) );
    if ( empty($all_leagues) ) { return '<p>No leagues found.</p>'; }

    $selected_league_id = $all_leagues[0];
    if ( isset($_GET['show_league']) ) {
        $requested_league = sanitize_text_field( wp_unslash( $_GET['show_league'] ) );
        if ( in_array($requested_league, $all_leagues, true) ) { $selected_league_id = $requested_league; }
    }

    $all_teams_in_league = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm_team.meta_value FROM {$wpdb->postmeta} pm_team INNER JOIN {$wpdb->postmeta} pm_league ON pm_team.post_id = pm_league.post_id WHERE pm_team.meta_key = %s AND pm_league.meta_key = %s AND pm_league.meta_value = %s AND pm_team.meta_value != '' ORDER BY pm_team.meta_value ASC", 'fantasy_team_id', 'league_id', $selected_league_id ) );
    if ( empty($all_teams_in_league) ) { return '<p>No teams found in this league.</p>'; }

    $selected_team_id = $all_teams_in_league[0];
    if ( isset($_GET['show_team']) ) {
        $requested_team = sanitize_text_field( wp_unslash( $_GET['show_team'] ) );
        if ( in_array($requested_team, $all_teams_in_league, true) ) { $selected_team_id = $requested_team; }
    }

    ob_start();
    $base_url = get_permalink();

    // League and Team Selectors
    echo '<div class="league-selector-ui"><strong>View League:</strong> ';
    $links_l = [];
    foreach ($all_leagues as $league) {
        $url = add_query_arg(['show_league' => rawurlencode($league), 'show_team' => false], $base_url);
        $links_l[] = '<a href="'.esc_url($url).'"'.($league === $selected_league_id ? ' class="is-selected"' : '').'>'.esc_html($league).'</a>';
    }
    echo implode(' | ', $links_l) . '</div>';
    echo '<div class="team-selector-ui" style="margin-top: 10px;"><strong>View Team:</strong> ';
    $links_t = [];
    foreach ($all_teams_in_league as $team) {
        $url = add_query_arg(['show_league' => rawurlencode($selected_league_id), 'show_team' => rawurlencode($team)], $base_url);
        $links_t[] = '<a href="'.esc_url($url).'"'.($team === $selected_team_id ? ' class="is-selected"' : '').'>'.esc_html($team).'</a>';
    }
    echo implode(' | ', $links_t) . '</div>';

    echo '<h2>Roster for League: '.esc_html($selected_league_id).', Team: '.esc_html($selected_team_id).'</h2>';

    $years_to_process = range( 2026, 2040 );
    $salary_totals    = array_fill_keys($years_to_process, 0.0);

    // --- Build Rows using Helper ---
    $build_rows_readonly = function( $q ) use (&$salary_totals, $years_to_process, $selected_league_id, $selected_team_id) {
        $rows = [];
        $config = [
            'is_readonly'        => true,
            'years_to_process'   => $years_to_process,
            'selected_league_id' => $selected_league_id,
            'selected_team_id'   => $selected_team_id,
        ];
        foreach ( $q->posts as $player_id ) {
            $rows[] = fod_render_player_roster_row( $player_id, $config, $salary_totals );
        }
        return $rows;
    };

    // --- Queries ---
    $common_meta_query = [ 'relation' => 'AND', [ 'key' => 'league_id', 'value' => $selected_league_id ], [ 'key' => 'fantasy_team_id', 'value' => $selected_team_id ], [ 'relation' => 'OR', [ 'key'=>'fa_status', 'value'=>'rostered' ], [ 'key'=>'fa_status', 'compare'=>'NOT EXISTS' ] ], 'position_clause' => [ 'key'=>'position', 'compare'=>'EXISTS' ] ];
    $args_40 = [ 'post_type' => 'playerdata', 'posts_per_page' => 300, 'fields' => 'ids', 'meta_query' => array_merge($common_meta_query, [ [ 'key' => 'status_40_man', 'value' => 'X' ] ]), 'orderby' => [ 'position_clause' => 'ASC', 'title' => 'ASC' ] ];
    $q_40 = new WP_Query($args_40);
    $forty_rows = $build_rows_readonly($q_40);

    $args_n40 = [ 'post_type' => 'playerdata', 'posts_per_page' => 300, 'fields' => 'ids', 'meta_query' => array_merge($common_meta_query, [ [ 'relation' => 'OR', [ 'key' => 'status_40_man', 'value' => 'X', 'compare' => '!=' ], [ 'key' => 'status_40_man', 'compare' => 'NOT EXISTS' ] ] ]), 'orderby' => [ 'position_clause' => 'ASC', 'title' => 'ASC' ] ];
    $q_n40 = new WP_Query($args_n40);
    $non40_rows = $build_rows_readonly($q_n40);

    $q_26 = new WP_Query([ 'post_type' => 'playerdata', 'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => true, 'meta_query' => [ 'relation' => 'AND', ['key' => 'league_id', 'value' => $selected_league_id], ['key' => 'fantasy_team_id', 'value' => $selected_team_id], ['key' => 'status_26_man', 'value' => '1', 'compare' => '='] ] ]);
    $count_26 = $q_26->post_count;
    $count_40 = $q_40->post_count;
    $count_minors = $q_n40->post_count;

    // Roster Counts
    echo '<h4>Roster Counts</h4>';
    echo '<table class="fantasy-table-base" style="width: auto; margin-bottom: 30px;"><thead><tr><th>26-Man</th><th>40-Man</th><th>Minors</th></tr></thead><tbody>';
    $style_26 = ($count_26 > 26) ? 'style="color: red; font-weight: bold;"' : '';
    echo '<td ' . $style_26 . ' style="text-align: center;">' . esc_html($count_26) . ' / 26</td>';
    echo '<td style="text-align: center;">' . esc_html($count_40) . ' / 40</td>';
    echo '<td style="text-align: center;">' . esc_html($count_minors) . '</td>';
    echo '</tr></tbody></table>';

    // Dead Cap & Salary Tables
    $dead_cap_data = fod_calculate_dead_cap( $selected_league_id, $selected_team_id, $years_to_process );
    echo fod_render_salary_summary_table( $salary_totals, $dead_cap_data['totals'], $years_to_process );

    // Dead Cap Breakdown
    if ( ! empty($dead_cap_data['grouped']) ) {
        krsort($grouped_dead_cap);
        echo '<h3>Dead Cap Breakdown</h3>';
        echo '<table class="fantasy-table-base deadcap-breakdown"><thead><tr><th>Year</th><th>Player</th><th>Amount</th><th>Type</th></tr></thead><tbody>';
        foreach ( $grouped_dead_cap as $year => $penalties ) {
            usort($penalties, function($a, $b) { return $b['amount'] <=> $a['amount']; });
            $players_html = implode('<br>', array_map('esc_html', array_column($penalties, 'player')));
            $amounts_html = implode('<br>', array_map(function($p) { return '$' . number_format($p['amount'], 0); }, $penalties));
            $types_html = implode('<br>', array_map('esc_html', array_column($penalties, 'type')));
            echo '<tr><td><strong>'.esc_html($year).'</strong></td><td>'.$players_html.'</td><td>'.$amounts_html.'</td><td>'.$types_html.'</td></tr>';
        }
        echo '</tbody></table>';
    }

    // Table Headers
    $head = '<thead><tr><th>Name</th><th>Position</th><th>MLB Team</th><th>IL</th><th>40-Man</th><th>26-Man</th><th>DFA Only</th><th>Options (Season)</th><th>Option Years Used</th><th>Rule 5 Year</th>';
    foreach ($years_to_process as $y) $head .= '<th>'.esc_html($y).'</th>';
    $head .= '</tr></thead>';
    $col_count = 10 + count($years_to_process);

    // 40-Man Roster
    echo '<h3>40-Man Roster</h3>';
    echo '<table id="roster-40-table" class="fantasy-table-base">' . $head . '<tbody id="roster-40-tbody">';
    echo $forty_rows ? implode('', $forty_rows) : '<tr><td colspan="' . $col_count . '">No players found on the 40-man roster.</td></tr>';
    echo '</tbody></table>';

    // Minor League / Off-Roster
    echo '<h3>Minor League / Off-Roster</h3>';
    echo '<table id="roster-minors-table" class="fantasy-table-base">' . $head . '<tbody id="roster-minors-tbody">';
    echo $non40_rows ? implode('', $non40_rows) : '<tr><td colspan="' . $col_count . '">No players found off the 40-man roster.</td></tr>';
    echo '</tbody></table>';

    return ob_get_clean();
}
add_shortcode( 'league_rosters', 'display_league_rosters_shortcode' );


/* ------------------------------------------------------------------------
   [league_salary_summary] — single table (Active + Dead Cap combined)
------------------------------------------------------------------------ */
function display_league_salary_summary_shortcode() {
    $years_to_process = range(2026, 2040);
    $selected_league_id    = null;
    $manager_leagues       = [];
    $all_available_leagues = [];
    if ( is_user_logged_in() && function_exists('get_field') ) {
        $user_id  = get_current_user_id();
        $managed_teams = get_field('managed_teams', 'user_' . $user_id);
        if ( ! empty($managed_teams) && is_array($managed_teams) ) {
            foreach ( $managed_teams as $team_data ) {
                if ( is_array($team_data) && ! empty($team_data['league_id']) ) {
                    $manager_leagues[ $team_data['league_id'] ] = $team_data['league_id'];
                }
            }
            $manager_leagues       = array_values(array_unique($manager_leagues));
            sort($manager_leagues);
            $all_available_leagues = $manager_leagues;
        }
    }
    if ( empty($all_available_leagues) ) {
        global $wpdb;
        $results = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish' ORDER BY pm.meta_value ASC", 'league_id', 'playerdata' ) );
        $all_available_leagues = $results ?: ['AAA'];
    }
    if ( isset($_GET['show_league']) ) {
        $requested = sanitize_text_field( wp_unslash($_GET['show_league']) );
        if ( in_array($requested, $all_available_leagues, true) ) {
            $selected_league_id = $requested;
        }
    }
    if ( ! $selected_league_id ) {
        $selected_league_id = !empty($manager_leagues) ? $manager_leagues[0] : ($all_available_leagues[0] ?? null);
        if ( ! $selected_league_id ) { return '<p>No leagues available to display.</p>'; }
    }
    $active_totals = [];
    $args_active = array( 'post_type' => 'playerdata', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_query' => [ 'relation' => 'AND', [ 'key' => 'league_id', 'value' => $selected_league_id ], [ 'relation' => 'OR', [ 'key' => 'fa_status', 'value' => 'rostered' ], [ 'key' => 'fa_status', 'compare' => 'NOT EXISTS' ], [ 'key' => 'fa_status', 'value' => '' ] ] ] );
    $q_active = new WP_Query($args_active);
    if ( $q_active->have_posts() ) {
        foreach ( $q_active->posts as $player_id ) {
            $team_id = get_post_meta($player_id, 'fantasy_team_id', true);
            if ( empty($team_id) ) { continue; }
            if ( ! isset($active_totals[$team_id]) ) { $active_totals[$team_id] = array_fill_keys($years_to_process, 0.0); }
            foreach ( $years_to_process as $year ) {
                $salary_raw = get_post_meta($player_id, 'contract_' . $year, true);
                if ($salary_raw === '' || $salary_raw === null) { continue; }
                $clean = str_replace(',', '', (string)$salary_raw);
                if ( is_numeric($clean) ) { $active_totals[$team_id][$year] += (float) $clean; }
            }
        }
    }
    $dead_cap_totals = [];
    $args_dc = array( 'post_type' => 'playerdata', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_query' => [ 'relation' => 'AND', [ 'key' => 'league_id', 'value' => $selected_league_id ], [ 'key' => 'dead_cap_penalties', 'compare' => 'EXISTS' ] ] );
    $q_dc = new WP_Query($args_dc);
    if ( $q_dc->have_posts() ) {
        foreach ( $q_dc->posts as $pid ) {
            $rows = get_field('dead_cap_penalties', $pid);
            if ( empty($rows) || !is_array($rows) ) { continue; }
            foreach ( $rows as $row ) {
                $year = isset($row['penalty_year']) ? (int) $row['penalty_year'] : 0;
                $amount  = isset($row['penalty_amount']) ? (float) $row['penalty_amount'] : 0.0;
                $dc_team = $row['dead_cap_team_id'] ?? '';
                if ( ! $dc_team || ! in_array($year, $years_to_process, true) ) { continue; }
                if ( ! isset($dead_cap_totals[$dc_team]) ) { $dead_cap_totals[$dc_team] = array_fill_keys($years_to_process, 0.0); }
                $dead_cap_totals[$dc_team][$year] += $amount;
            }
        }
    }
    $all_team_ids = array_unique(array_merge(array_keys($active_totals), array_keys($dead_cap_totals)));
    sort($all_team_ids, SORT_NATURAL);
    ob_start();
    if ( count($all_available_leagues) > 1 ) {
        echo '<div class="league-selector-ui"><strong>View Summary For League:</strong> ';
        $selector_links = [];
        $current_page_url = get_permalink();
        foreach ( $all_available_leagues as $lid ) {
            $url = add_query_arg('show_league', rawurlencode($lid), $current_page_url);
            $selector_links[] = '<a href="' . esc_url($url) . '"' . ($lid === $selected_league_id ? ' class="is-selected"' : '') . '>' . esc_html($lid) . '</a>';
        }
        echo implode(' | ', $selector_links);
        echo '</div>';
    }
    echo '<h2>League Salary Summary (Combined: Active + Dead Cap) — ' . esc_html($selected_league_id) . '</h2>';
    if ( empty($all_team_ids) ) {
        echo "<p>No team salary data found for league " . esc_html($selected_league_id) . ".</p>";
        return ob_get_clean();
    }
    echo '<table class="fantasy-table-base">';
    echo '<thead><tr><th>Team</th>';
    foreach ( $years_to_process as $year ) { echo '<th>' . esc_html($year) . '</th>'; }
    echo '</tr></thead><tbody>';
    foreach ( $all_team_ids as $team_id ) {
        echo '<tr><td><strong>' . esc_html($team_id) . '</strong></td>';
        foreach ( $years_to_process as $year ) {
            $active   = $active_totals[$team_id][$year]   ?? 0.0;
            $dead_cap = $dead_cap_totals[$team_id][$year] ?? 0.0;
            echo '<td>$' . esc_html( number_format($active + $dead_cap, 0) ) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    return ob_get_clean();
}
add_shortcode( 'league_salary_summary', 'display_league_salary_summary_shortcode' );


/* ------------------------------------------------------------------------
   Trade proposal – Form and Handlers
------------------------------------------------------------------------ */
function display_trade_proposal_form_shortcode() {
    if ( ! is_user_logged_in() ) { return '<p>Please log in to propose a trade.</p>'; }
    if ( ! function_exists('get_field') ) { return '<p>Error: ACF not active.</p>'; }
    $current_user_id = get_current_user_id(); $managed_teams = get_field('managed_teams', 'user_' . $current_user_id);
    ob_start();
    if (isset($_GET['trade_success'])) { echo '<div class="trade-form-notice success">Trade proposed successfully! An email has been sent.</div>'; }
    if (isset($_GET['trade_error'])) {
        $error_message = 'An error occurred.';
        switch ($_GET['trade_error']) {
            case 'missing_fields': $error_message = 'Please fill out all required fields.'; break;
            case 'self_trade': $error_message = 'You cannot propose a trade with yourself.'; break;
            case 'post_creation_failed': $error_message = 'Could not save the trade proposal. Please try again later.'; break;
            case 'security_check_failed': $error_message = 'Security check failed. Please refresh the page and try again.'; break;
            case 'not_logged_in': $error_message = 'You must be logged in to propose a trade.'; break;
            case 'acf_missing': $error_message = 'A configuration error occurred. ACF functions missing.'; break;
            case 'ownership_mismatch': $error_message = 'Trade failed: A player involved in the trade is no longer on the expected team.'; break;
            case 'no_players': $error_message = 'A trade must involve at least one player.'; break;
        }
        echo '<div class="trade-form-notice error">Error: ' . esc_html($error_message) . '</div>';
    }
    ?>
    <form id="trade-proposal-form" class="trade-proposal-form-class" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
        <input type="hidden" name="action" value="process_trade_proposal">
        <?php wp_nonce_field( 'process_trade_proposal_nonce', 'trade_proposal_nonce_field' ); ?>
        <input type="hidden" name="proposing_manager_id" value="<?php echo esc_attr($current_user_id); ?>">
        <p><label for="trade_league">Select League:</label><br>
            <select name="trade_league" id="trade_league" required>
                <option value="">-- Select League --</option>
                <?php
                $manager_leagues_dd = [];
                if (!empty($managed_teams) && is_array($managed_teams)) {
                    $manager_leagues_dd = array_unique(array_column($managed_teams, 'league_id'));
                    sort($manager_leagues_dd);
                    foreach($manager_leagues_dd as $l_id){ if($l_id) echo '<option value="'.esc_attr($l_id).'">'.esc_html($l_id).'</option>'; }
                }
                ?>
            </select><br><small><i>Select league to populate lists below.</i></small>
        </p>
        <p><label for="target_manager">Trade With Manager:</label><br>
            <select name="target_manager" id="target_manager" required disabled><option value="">-- Select League First --</option></select>
            <span id="target-manager-loading" style="display: none;">Loading managers...</span>
        </p>
        <p><label for="players_offered">You Offer (Ctrl/Cmd + Click for multiple):</label><br>
            <select name="players_offered[]" id="players_offered" multiple size="8" disabled><option value="" disabled>-- Select League First --</option></select>
            <span id="players-offered-loading" style="display: none;">Loading your players...</span>
        </p>
        <p><label for="players_requested">You Request (Ctrl/Cmd + Click for multiple):</label><br>
            <select name="players_requested[]" id="players_requested" multiple size="8" disabled><option value="" disabled>-- Select League & Target First --</option></select>
            <span id="players-requested-loading" style="display: none;">Loading target players...</span>
        </p>
        <p>
            <label for="trade_comments">Comments (Optional):</label><br>
            <textarea name="trade_comments" id="trade_comments" rows="4" style="width: 100%;"></textarea>
        </p>
        <p><button type="submit" id="propose-trade-submit" disabled>Propose Trade</button></p>
    </form>
    <script>
    jQuery(document).ready(function($) {
        $('#trade_league').on('change', function() {
            var leagueId = $(this).val();
            console.log('League changed to: ' + leagueId);
        });

        $('#target_manager').on('change', function() {
            var managerId = $(this).val();
            var leagueId = $('#trade_league').val();
            console.log('Target manager changed to: ' + managerId);
            console.log('League ID for target players: ' + leagueId);

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_target_players_for_trade',
                    league_id: leagueId,
                    manager_id: managerId
                },
                success: function(response) {
                    console.log('Target players response:', response);
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'trade_proposal_form', 'display_trade_proposal_form_shortcode' );


function display_pending_trades_shortcode() {
    if ( ! is_user_logged_in() ) { return '<p>Please log in to view pending trade proposals.</p>'; }
    if ( ! function_exists('get_field') ) { return '<p>Error: ACF not active.</p>'; }
    $current_user_id = get_current_user_id();
    $args = array(
        'post_type' => 'trade_proposal', 'posts_per_page' => 50, 'post_status' => 'publish',
        'meta_query' => [ 'relation' => 'AND',
            [ 'key' => 'trade_status', 'value' => 'pending' ],
            [ 'relation' => 'OR',
                [ 'key' => 'target_manager', 'value' => $current_user_id, 'type' => 'NUMERIC' ],
                [ 'key' => 'proposing_manager', 'value' => $current_user_id, 'type' => 'NUMERIC' ]
            ]
        ],
        'orderby' => 'date', 'order'=>'DESC'
    );
    $q = new WP_Query( $args );
    ob_start();
    if (isset($_GET['trade_action'])) {
        $message_text = ''; $message_type = 'info';
        switch($_GET['trade_action']) {
            case 'rejected': $message_text = 'Trade proposal rejected.'; break;
            case 'cancelled': $message_text = 'Trade proposal cancelled.'; break;
            case 'accepted':
            case 'trade_accepted': $message_text = 'Trade proposal accepted and processed!'; $message_type = 'success'; break;
            case 'trade_action_error':
                $message_text = 'An error occurred processing the trade.'; $message_type = 'error';
                if (isset($_GET['trade_action_error_detail'])) {
                    switch($_GET['trade_action_error_detail']) {
                        case 'security_failed':   $message_text = 'Security check failed. Please refresh the page and try again.'; break;
                        case 'not_logged_in':     $message_text = 'You must be logged in to perform this action.'; break;
                        case 'acf_missing':       $message_text = 'A configuration error occurred. ACF functions missing.'; break;
                        case 'not_authorized':    $message_text = 'You are not authorized to perform this action.'; break;
                        case 'missing_trade_data':$message_text = 'Missing critical trade data.'; break;
                        case 'team_id_missing':   $message_text = 'Could not verify team ownership for trade.'; break;
                        case 'ownership_mismatch':$message_text = 'Trade failed: player ownership changed. Please review and try again.'; break;
                    }
                }
                break;
        }
        if ($message_text) { echo '<div class="trade-form-notice '.esc_attr($message_type).'">'.esc_html($message_text).'</div>'; }
    }
    echo "<h2>Pending Trade Proposals</h2>";
    if ( $q->have_posts() ) {
        echo '<ul class="pending-trades-list">';
        while ( $q->have_posts() ) { $q->the_post();
            $trade_id = get_the_ID();
            $proposing_manager_id = get_field('proposing_manager', $trade_id);
            $target_manager_id = get_field('target_manager', $trade_id);
            $league_id = get_field('league_id', $trade_id);
            $players_offered_ids   = get_field('players_offered',   $trade_id) ?: [];
            $players_requested_ids = get_field('players_requested', $trade_id) ?: [];
            $trade_comments = get_field('trade_comments', $trade_id);
            
            $is_proposer = ($current_user_id == $proposing_manager_id);
            $other_party_id = $is_proposer ? $target_manager_id : $proposing_manager_id;
            
            $other_party_team_name = 'Unknown Team';
            $managed_teams = get_field('managed_teams', 'user_' . $other_party_id);
            if (!empty($managed_teams) && is_array($managed_teams)) {
                foreach ($managed_teams as $team) {
                    if (($team['league_id'] ?? '') === $league_id) {
                        $other_party_team_name = $team['fantasy_team_id'];
                        break;
                    }
                }
            }

            echo '<li class="pending-trade-item">';
            echo '<strong>Proposal ' . ($is_proposer ? 'To:' : 'From:') . '</strong> ' . esc_html($other_party_team_name) . '<br>';
            echo '<strong>League:</strong> ' . esc_html($league_id) . '<br>';
            
            $receive_ids = $is_proposer ? $players_requested_ids : $players_offered_ids;
            $give_ids    = $is_proposer ? $players_offered_ids : $players_requested_ids;

            echo '<strong>You Receive:</strong> ';
            if (!empty($receive_ids)) { $names = array_map(function($id){ return get_the_title($id) ?: '(?)'; }, $receive_ids); echo esc_html(implode(', ', $names)); } else { echo 'N/A'; } echo '<br>';
            
            echo '<strong>You Give Up:</strong> ';
            if (!empty($give_ids)) { $names = array_map(function($id){ return get_the_title($id) ?: '(?)'; }, $give_ids); echo esc_html(implode(', ', $names)); } else { echo 'N/A'; } echo '<br>';

            if ( ! empty( $trade_comments ) ) {
                echo '<strong>Comments:</strong><div style="padding: 5px; border: 1px solid #eee; margin-top: 5px; margin-bottom: 10px;">' . nl2br(esc_html( $trade_comments )) . '</div>';
            } else {
                echo '<br>';
            }
            
            $nonce_action = 'handle_trade_nonce_' . $trade_id;
            if (!$is_proposer) {
                $accept_url = add_query_arg( [ 'action'=>'accept_trade', 'trade_id'=>$trade_id, '_wpnonce'=>wp_create_nonce($nonce_action) ], admin_url('admin-post.php') );
                $reject_url = add_query_arg( [ 'action'=>'reject_trade', 'trade_id'=>$trade_id, '_wpnonce'=>wp_create_nonce($nonce_action) ], admin_url('admin-post.php') );
                echo '<a href="'.esc_url($accept_url).'" class="button trade-action-button accept">Accept</a> ';
                echo '<a href="'.esc_url($reject_url).'" class="button trade-action-button reject">Reject</a>';
            } else {
                $cancel_url = add_query_arg( [ 'action'=>'cancel_trade', 'trade_id'=>$trade_id, '_wpnonce'=>wp_create_nonce($nonce_action) ], admin_url('admin-post.php') );
                echo '<a href="'.esc_url($cancel_url).'" class="button trade-action-button reject">Cancel Proposal</a>';
            }
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>You have no pending trade proposals.</p>';
    }
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode( 'view_pending_trades', 'display_pending_trades_shortcode' );


/* ------------------------------------------------------------------------
   Free Agents – List, Modal, and Bidding
------------------------------------------------------------------------ */
function display_fa_sign_notices() {
    $notice = '';
    if ( isset($_GET['sign_success']) && $_GET['sign_success'] === 'true' ) {
        $player_id = isset($_GET['signed_player']) ? absint($_GET['signed_player']) : 0;
        $player_name = $player_id ? get_the_title($player_id) : 'Player';
        $bid_points_raw = $_GET['bid_points'] ?? null;
        $bid_text = ($bid_points_raw !== null && is_numeric($bid_points_raw)) ? ' for ' . number_format(floatval($bid_points_raw), 2) . ' points' : '';
        $status_param = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        if ($status_param === 'pending_bid') {
            $notice = '<div class="notice notice-success is-dismissible">'.esc_html($player_name).' is now in a 48-hour bidding period with your bid of '.esc_html($bid_text).'.</div>';
        } else {
            $notice = '<div class="notice notice-success is-dismissible">'.esc_html($player_name).' successfully signed'.esc_html($bid_text).'!</div>';
        }
    } elseif ( isset($_GET['sign_error']) ) {
        $error_message = 'An error occurred.';
        switch ($_GET['sign_error']) {
            case 'player_signed':       $error_message = 'Player may already be signed or not a valid free agent.'; break;
            case 'update_failed':       $error_message = 'Database error while signing the player.'; break;
            case 'acf_missing':         $error_message = 'Configuration error (ACF).'; break;
            case 'missing_data':        $error_message = 'Missing Player, League, Team, or Bid information.'; break;
            case 'not_manager':         $error_message = 'You are not authorized to sign for this team.'; break;
            case 'invalid_bid':         $error_message = 'Invalid bid amount or contract years.'; break;
            case 'no_offer':            $error_message = 'You must submit a valid bid.'; break;
            case 'initial_bid_failed':  $error_message = 'Failed to place initial bid.'; break;
            case 'security_failed':     $error_message = 'Security check failed.'; break;
            case 'not_logged_in':       $error_message = 'You must be logged in.'; break;
            case 'already_pending_bid': $error_message = 'Player already has a pending bid.'; break;
            case 'bid_too_low':         $error_message = 'Your bid must be at least 1 point higher than the current bid.'; break;
        }
        $notice = '<div class="notice notice-error is-dismissible">Error: '.esc_html($error_message).'</div>';
    }
    return $notice;
}

function display_free_agent_list_shortcode($atts) {
    if ( ! is_user_logged_in() ) return '<p>Please log in to view available free agents.</p>';
    if ( ! function_exists('get_field') ) return '<p>Error: Advanced Custom Fields plugin is not active.</p>';

    $atts = shortcode_atts( [ 'league' => '', 'players' => '' ], $atts );

    $current_user_id = get_current_user_id();
    $managed_teams = get_field('managed_teams', 'user_' . $current_user_id);
    $manager_leagues = [];
    if (!empty($managed_teams) && is_array($managed_teams)) {
        $manager_leagues = array_values(array_unique(array_column($managed_teams, 'league_id')));
        sort($manager_leagues);
    }
    if ( empty($manager_leagues) ) return '<p>You are not currently assigned to manage any teams in any leagues.</p>';
    
    $selected_league_id = $manager_leagues[0];
    if ( isset($_GET['show_league']) ) {
        $requested_league = sanitize_text_field( wp_unslash( $_GET['show_league'] ) );
        if ( in_array($requested_league, $manager_leagues, true) ) { $selected_league_id = $requested_league; }
    }

    $manager_team_id_for_league = null;
    foreach ($managed_teams as $team_data) {
        if (($team_data['league_id'] ?? '') === $selected_league_id) { $manager_team_id_for_league = $team_data['fantasy_team_id'] ?? null; break; }
    }
    
    $search_term = isset($_GET['fa_search']) ? sanitize_text_field( wp_unslash( $_GET['fa_search'] ) ) : '';
    $current_fa_page = isset($_GET['fa_page']) ? max(1, absint($_GET['fa_page'])) : 1;
    
    ob_start();
    echo display_fa_sign_notices();
    echo '<div class="fa-league-selector fa-controls-section"><strong>View Free Agents For League:</strong> ';
    $selector_links_fa = []; $current_page_url_base_fa = get_permalink();
    foreach ($manager_leagues as $league_id_fa) {
        $league_url_fa = add_query_arg('show_league', rawurlencode($league_id_fa), $current_page_url_base_fa);
        $selector_links_fa[] = '<a href="'.esc_url($league_url_fa).'"'.($league_id_fa === $selected_league_id ? ' class="is-selected"' : '') .'>'.esc_html($league_id_fa).'</a>';
    }
    echo implode(' | ', $selector_links_fa); echo '</div>';

    $is_special_list = ($selected_league_id === $atts['league'] && !empty($atts['players']));

    if ($is_special_list) {
        $player_names = array_map('trim', explode(',', $atts['players']));
        $args = [ 'post_type' => 'playerdata', 'post_title__in' => $player_names, 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'meta_query' => [ ['key' => 'league_id', 'value' => $selected_league_id] ] ];
    } else {
        echo '<div id="fa-search-wrapper" class="fa-search-form fa-controls-section"><form role="search" method="get" id="fa-search-form">';
        echo '<input type="hidden" name="show_league" value="' . esc_attr($selected_league_id) . '" />';
        echo '<label for="fa-search-input-' . esc_attr($selected_league_id) . '" class="screen-reader-text">Search:</label><input type="search" id="fa-search-input-' . esc_attr($selected_league_id) . '" name="fa_search" value="' . esc_attr($search_term) . '" placeholder="Search name..." />';
        echo '<input type="submit" value="Search" class="button" />';
        if (!empty($search_term)) {
            echo ' <a href="' . esc_url(remove_query_arg(['fa_search', 'fa_page'])) . '" class="button button-secondary">Clear</a>';
        }
        echo '</form></div>';

        $args = [
            'post_type' => 'playerdata',
            'posts_per_page' => 25,
            'paged' => $current_fa_page,
            'orderby' => 'title',
            'order' => 'ASC',
            's' => $search_term,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'league_id', 'value' => $selected_league_id],
                [
                    'relation' => 'OR',
                    ['key' => 'fa_status', 'value' => 'available'],
                    ['key' => 'fa_status', 'value' => 'pending_bid']
                ]
            ]
        ];
    }
    
    $fa_q = new WP_Query($args);
    echo '<div id="fa-table-container">';
    if ( $fa_q->have_posts() ) {
        echo '<table class="fantasy-table-base"><thead><tr><th>Name</th><th>Position</th><th>MLB Team</th><th>Current Bid</th><th>Time Left</th><th>Action</th></tr></thead><tbody id="fa-list-tbody">';
        while ( $fa_q->have_posts() ) { $fa_q->the_post();
            $player_id = get_the_ID();
            $player_name = get_the_title();
            $fa_status = get_post_meta($player_id, 'fa_status', true);
            $bid_amount = get_post_meta($player_id, 'pending_bid_amount', true);
            $end_time_str = get_post_meta($player_id, 'bid_end_time', true);
            
            $bid_display = '–';
            $time_display = '–';

            if ($fa_status === 'pending_bid' && !empty($bid_amount)) {
                $bid_display = number_format(floatval($bid_amount), 2) . ' pts';
                $end_timestamp = strtotime($end_time_str);
                if ($end_timestamp > current_time('timestamp')) {
                    $time_display = human_time_diff(current_time('timestamp'), $end_timestamp) . ' left';
                } else {
                    $time_display = 'Processing...';
                }
            }

            echo '<tr><td>' . esc_html($player_name) . '</td>';
            echo '<td>' . esc_html(get_post_meta($player_id, 'position', true) ?: 'N/A') . '</td>';
            echo '<td>' . esc_html(get_post_meta($player_id, 'mlb_team', true) ?: 'N/A') . '</td>';
            echo '<td>' . esc_html($bid_display) . '</td>';
            echo '<td>' . esc_html($time_display) . '</td>';
            echo '<td class="fa-action-cell">';
            if ($manager_team_id_for_league) {
                echo '<button type="button" class="button fa-offer-button" data-playerid="' . esc_attr($player_id) . '" data-playername="' . esc_attr($player_name) . '" data-leagueid="' . esc_attr($selected_league_id) . '" data-teamid="' . esc_attr($manager_team_id_for_league) . '">Bid</button>';
            } else { echo 'N/A'; }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No free agents found matching your criteria.</p>';
    }
    echo '</div>';

    if (!$is_special_list && $fa_q->max_num_pages > 1) {
        echo '<div id="fa-pagination-container"><div class="fa-pagination-wrapper pagination-links fa-pagination">';
        $pagination_base_url_fa = add_query_arg( 'show_league', $selected_league_id, get_permalink() );
        if (!empty($search_term)) { $pagination_base_url_fa = add_query_arg( 'fa_search', $search_term, $pagination_base_url_fa ); }
        echo paginate_links(['base' => esc_url( add_query_arg( 'fa_page', '%#%', $pagination_base_url_fa ) ), 'format' => '?fa_page=%#%', 'total' => $fa_q->max_num_pages, 'current' => $current_fa_page, 'type' => 'plain' ]);
        echo '</div></div>';
    }
    wp_reset_postdata();
    
    echo fod_render_fa_bid_modal();
    
    return ob_get_clean();
}
add_shortcode( 'free_agent_list', 'display_free_agent_list_shortcode' );


/**
 * [waiver_wire_list] — Displays players on waivers with contract info and claim button
 */
function display_waiver_wire_shortcode() {
    if ( ! is_user_logged_in() ) return '<p>Please log in to view the waiver wire.</p>';
    if ( ! function_exists('get_field') ) return '<p>Error: Advanced Custom Fields plugin is not active.</p>';

    $current_user_id = get_current_user_id();
    $managed_teams = get_field('managed_teams', 'user_' . $current_user_id);
    $manager_leagues = [];

    if (!empty($managed_teams) && is_array($managed_teams)) {
        $manager_leagues = array_values(array_unique(array_column($managed_teams, 'league_id')));
        sort($manager_leagues);
    }
    if ( empty($manager_leagues) ) return '<p>You are not currently assigned to manage any teams in any leagues.</p>';

    $selected_league_id = $manager_leagues[0];
    if ( isset($_GET['show_league']) ) {
        $requested_league = sanitize_text_field( wp_unslash( $_GET['show_league'] ) );
        if ( in_array($requested_league, $manager_leagues, true) ) { $selected_league_id = $requested_league; }
    }

    $manager_team_id_for_league = null;
    foreach ($managed_teams as $team_data) {
        if (($team_data['league_id'] ?? '') === $selected_league_id) {
            $manager_team_id_for_league = $team_data['fantasy_team_id'] ?? null;
            break;
        }
    }

    ob_start();
    echo '<div id="waiver-notices-container"></div>';
    echo '<div class="league-selector-ui fa-controls-section"><strong>View Waiver Wire For League:</strong> ';
    $selector_links_waiver = [];
    $current_page_url_base_waiver = get_permalink();
    foreach ($manager_leagues as $league_id_waiver) {
        $league_url_waiver = add_query_arg('show_league', rawurlencode($league_id_waiver), $current_page_url_base_waiver);
        $selector_links_waiver[] = '<a href="'.esc_url($league_url_waiver).'"'.($league_id_waiver === $selected_league_id ? ' class="is-selected"' : '') .'>'.esc_html($league_id_waiver).'</a>';
    }
    echo implode(' | ', $selector_links_waiver);
    echo '</div>';

    $waiver_args = [ 'post_type' => 'playerdata', 'posts_per_page' => -1, 'meta_query' => [ 'relation' => 'AND', [ 'key' => 'league_id', 'value' => $selected_league_id ], [ 'key' => 'fa_status', 'value' => 'on_waivers' ] ], 'orderby' => 'meta_value', 'meta_key' => 'waiver_end_time', 'order' => 'ASC' ];
    $waiver_query = new WP_Query($waiver_args);

    echo "<h2>Waiver Wire (" . esc_html($selected_league_id) . ")</h2>";

    if ( $waiver_query->have_posts() ) {
        $years_to_scan = range(2026, 2040);
        echo '<table class="fantasy-table-base"><thead><tr><th>Name</th><th>Pos</th><th>MLB Team</th><th>Waiving Team</th><th>Time Remaining</th><th>Contract</th><th>Action</th></tr></thead><tbody>';
        while( $waiver_query->have_posts() ) {
            $waiver_query->the_post();
            $player_id = get_the_ID();
            $player_name = get_the_title();
            $position = get_post_meta($player_id, 'position', true) ?: 'N/A';
            $mlb_team = get_post_meta($player_id, 'mlb_team', true) ?: 'N/A';
            $waiving_team = get_post_meta($player_id, 'waiving_team_id', true) ?: 'N/A';

            $end_time_raw = get_field('waiver_end_time', $player_id);
            $end_timestamp = $end_time_raw ? strtotime($end_time_raw) : 0;
            $time_remaining_seconds = $end_timestamp ? $end_timestamp - current_time('timestamp', true) : -1;
            $time_display = ($time_remaining_seconds > 0) ? human_time_diff(current_time('timestamp', true), $end_timestamp) . ' left' : 'Processing...';

            $contract_parts = [];
            foreach ($years_to_scan as $year) {
                $salary = get_post_meta($player_id, 'contract_' . $year, true);
                if (is_numeric($salary) && $salary > 0) {
                    $formatted_salary = ($salary >= 1000000) ? '$' . round($salary / 1000000, 1) . 'M' : '$' . round($salary / 1000) . 'K';
                    $contract_parts[] = substr($year, -2) . ': ' . $formatted_salary;
                } elseif (!empty($salary)) {
                    $contract_parts[] = substr($year, -2) . ': ' . esc_html($salary);
                }
            }
            $contract_display = !empty($contract_parts) ? implode('<br>', $contract_parts) : 'N/A';

            $has_claim = false;
            if ( $manager_team_id_for_league && is_array($existing_claims = get_field('pending_waiver_claims', $player_id)) ) {
                foreach ($existing_claims as $claim) {
                    if (($claim['claiming_team_id'] ?? '') === $manager_team_id_for_league) {
                        $has_claim = true;
                        break;
                    }
                }
            }

            $button_html = '';
            if ($time_remaining_seconds <= 0) { $button_html = '<span>Processing...</span>'; }
            elseif (!$manager_team_id_for_league) { $button_html = '<span>N/A</span>'; }
            elseif ($manager_team_id_for_league === $waiving_team) { $button_html = '<span>Your Waiver</span>'; }
            elseif ($has_claim) { $button_html = '<strong>Claim Pending</strong>'; }
            else { $button_html = '<button type="button" class="button claim-player-button" data-playerid="' . esc_attr($player_id) . '" data-playername="' . esc_attr($player_name) . '" data-leagueid="' . esc_attr($selected_league_id) . '" data-teamid="' . esc_attr($manager_team_id_for_league) . '">Claim Player</button>'; }

            echo '<tr><td>' . esc_html($player_name) . '</td><td>' . esc_html($position) . '</td><td>' . esc_html($mlb_team) . '</td><td>' . esc_html($waiving_team) . '</td><td>' . esc_html($time_display) . '</td><td>' . $contract_display . '</td><td class="fa-action-cell">' . $button_html . '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No players are currently on waivers in this league.</p>';
    }
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('waiver_wire_list', 'display_waiver_wire_shortcode');

/* ------------------------------------------------------------------------
   [league_activity_feed] — Shows recent league transactions from the log
------------------------------------------------------------------------ */
function display_league_activity_feed_shortcode($atts) {
    $atts = shortcode_atts( [ 'league' => '' ], $atts );

    $selected_league_id = $atts['league'];
    $manager_leagues = [];

    if ( is_user_logged_in() && function_exists('get_field') ) {
        $user_id = get_current_user_id();
        $managed_teams = get_field('managed_teams', 'user_' . $user_id);
        $managed_nba_teams = get_field('managed_nba_teams', 'user_' . $user_id);

        if ( !empty($managed_teams) && is_array($managed_teams) ) {
            foreach ($managed_teams as $team) { if ( !empty($team['league_id']) ) { $manager_leagues[$team['league_id']] = 1; } }
        }
        if ( !empty($managed_nba_teams) && is_array($managed_nba_teams) ) {
            foreach ($managed_nba_teams as $team) { if ( !empty($team['league_id']) ) { $manager_leagues[$team['league_id']] = 1; } }
        }
        $manager_leagues = array_keys($manager_leagues);
        sort($manager_leagues);
    }

    if ( isset($_GET['activity_league']) ) {
        $requested = sanitize_text_field( wp_unslash($_GET['activity_league']) );
        if ( empty($manager_leagues) || in_array($requested, $manager_leagues) ) {
            $selected_league_id = $requested;
        }
    }

    if ( empty($selected_league_id) ) {
        $selected_league_id = !empty($manager_leagues) ? reset($manager_leagues) : 'MLB';
    }

    $args = [ 'post_type' => 'transaction', 'posts_per_page' => 15, 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => [ [ 'key' => 'league_id', 'value' => $selected_league_id ] ] ];
    $activity_query = new WP_Query($args);

    ob_start();
    ?>
    <div class="activity-feed-wrapper">
        <?php if ( count($manager_leagues) > 1 ) : ?>
            <div class="league-selector-ui" style="margin-bottom: 15px;">
                <strong>View Activity For:</strong> 
                <?php 
                $links = [];
                $base_url = get_permalink();
                foreach ($manager_leagues as $lid) {
                    $url = add_query_arg('activity_league', rawurlencode($lid), $base_url);
                    $links[] = '<a href="'.esc_url($url).'"'.($lid === $selected_league_id ? ' class="is-selected"' : '').'>'.esc_html($lid).'</a>';
                }
                echo implode(' | ', $links);
                ?>
            </div>
        <?php endif; ?>

        <h3>Recent Activity (<?php echo esc_html($selected_league_id); ?>)</h3>

        <?php if( $activity_query->have_posts() ) : ?>
            <ul class="activity-feed">
                <?php while( $activity_query->have_posts() ) : $activity_query->the_post(); ?>
                    <li>
                        <span class="activity-date"><?php echo get_the_date('M j'); ?></span>
                        <span class="activity-text"><?php echo esc_html(get_field('transaction_summary')); ?></span>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p>No recent transactions to report for the <?php echo esc_html($selected_league_id); ?> league.</p>
        <?php endif; ?>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('league_activity_feed', 'display_league_activity_feed_shortcode');


/* ------------------------------------------------------------------------
   [waiver_wire_spotlight] — Shows top 5 players on waivers
------------------------------------------------------------------------ */
function display_waiver_wire_spotlight_shortcode() {
    ob_start();
    echo '<h3>Waiver Wire Spotlight</h3>';
    $waiver_args = [ 'post_type' => 'playerdata', 'posts_per_page' => 5, 'meta_query' => [ [ 'key' => 'fa_status', 'value' => 'on_waivers' ] ], 'orderby' => 'meta_value', 'meta_key' => 'waiver_end_time', 'order' => 'ASC' ];
    $waiver_query = new WP_Query($waiver_args);

    if ( $waiver_query->have_posts() ) {
        echo '<ul class="activity-feed">';
        while( $waiver_query->have_posts() ) {
            $waiver_query->the_post();
            $end_time = get_field('waiver_end_time');
            $time_remaining = is_string($end_time) ? strtotime($end_time) - current_time('timestamp') : -1;
            echo '<li><span class="activity-date">' . ($time_remaining > 0 ? human_time_diff(current_time('timestamp'), strtotime($end_time)) . ' left' : 'Processing...') . '</span><span class="activity-text"><strong>' . get_the_title() . '</strong> is available on waivers.</span></li>';
        }
        echo '</ul>';
        $waiver_page_url = function_exists('get_field') ? get_field('waiver_wire_page', 'option') : '';
        if ($waiver_page_url) {
            echo '<a href="' . esc_url( get_permalink($waiver_page_url) ) . '" style="margin-top: 10px; display: inline-block;">View Full Waiver Wire &rarr;</a>';
        }
    } else {
        echo '<p>No players on waivers.</p>';
    }
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('waiver_wire_spotlight', 'display_waiver_wire_spotlight_shortcode');


/* ------------------------------------------------------------------------
   [fantrax_standings] — Fetches and displays Fantrax standings
------------------------------------------------------------------------ */
function fod_display_fantrax_standings_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'league' => '' ], $atts );
    $selected_league_id = $atts['league'];
    $manager_leagues = [];

    if ( is_user_logged_in() && function_exists('get_field') ) {
        $user_id = get_current_user_id();
        $managed_teams = get_field('managed_teams', 'user_' . $user_id);
        $managed_nba_teams = get_field('managed_nba_teams', 'user_' . $user_id);
        if ( !empty($managed_teams) && is_array($managed_teams) ) { foreach ($managed_teams as $team) { if ( !empty($team['league_id']) ) { $manager_leagues[$team['league_id']] = 1; } } }
        if ( !empty($managed_nba_teams) && is_array($managed_nba_teams) ) { foreach ($managed_nba_teams as $team) { if ( !empty($team['league_id']) ) { $manager_leagues[$team['league_id']] = 1; } } }
        $manager_leagues = array_keys($manager_leagues);
        sort($manager_leagues);
    }

    if ( isset($_GET['standings_league']) ) {
        $requested = sanitize_text_field( wp_unslash($_GET['standings_league']) );
        if ( empty($manager_leagues) || in_array($requested, $manager_leagues) ) { $selected_league_id = $requested; }
    }

    if ( empty($selected_league_id) ) { $selected_league_id = !empty($manager_leagues) ? reset($manager_leagues) : 'MLB'; }

    $fantrax_urls = [
        'MLB' => 'https://www.fantrax.com/fxea/general/getStandings?leagueId=w4wlt4b2mg5l9qja',
        'AAA' => 'https://www.fantrax.com/fxea/general/getStandings?leagueId=m7qxa1w9mg5uk295',
        'AA'  => 'https://www.fantrax.com/fxea/general/getStandings?leagueId=q0zuqdpdmg7xgmfg',
        'NBA' => 'https://www.fantrax.com/fxea/general/getStandings?leagueId=hzkk6ta8ma6yh2fu',
    ];
    $url = $fantrax_urls[$selected_league_id] ?? '';

    if ( empty($url) ) { return '<p>Standings are not yet configured for the ' . esc_html($selected_league_id) . ' league.</p>'; }

    $transient_key = 'fod_fantrax_standings_' . $selected_league_id;
    if ( false !== ( $cached_html = get_transient( $transient_key ) ) ) { return $cached_html; }

    $response = wp_remote_get( $url, [ 'timeout' => 20 ] );
    if ( is_wp_error( $response ) ) { return '<p>Error: Could not retrieve standings. (' . $response->get_error_message() . ')</p>'; }

    $standings_rows = json_decode( wp_remote_retrieve_body( $response ) );
    if ( ! is_array($standings_rows) || ! isset( $standings_rows[0]->teamName ) ) { return '<p>Error: Standings data is in an unexpected format.</p>'; }

    ob_start();
    ?>
    <div class="fantrax-standings-wrapper">
        <?php if ( count($manager_leagues) > 1 ) : ?>
            <div class="league-selector-ui" style="margin-bottom: 15px;">
                <strong>View Standings For:</strong> 
                <?php 
                $links = [];
                $base_url = get_permalink();
                foreach ($manager_leagues as $lid) {
                    $url = add_query_arg('standings_league', rawurlencode($lid), $base_url);
                    $links[] = '<a href="'.esc_url($url).'"'.($lid === $selected_league_id ? ' class="is-selected"' : '').'>'.esc_html($lid).'</a>';
                }
                echo implode(' | ', $links);
                ?>
            </div>
        <?php endif; ?>
        <h3>Standings (<?php echo esc_html($selected_league_id); ?>)</h3>
        <table class="fantasy-table-base">
            <thead><tr><th>Rank</th><th>Team</th><th>W-L-T</th><th>Win %</th><th>GB</th></tr></thead>
            <tbody>
                <?php foreach ( $standings_rows as $row ) : ?>
                    <tr>
                        <td style="text-align: center;"><?php echo esc_html( $row->rank ?? 'N/A' ); ?></td>
                        <td><?php echo esc_html( $row->teamName ?? 'N/A' ); ?></td>
                        <td style="text-align: center;"><?php echo esc_html( $row->winPercentage ?? '0.0' ); ?></td>
                        <td style="text-align: center;"><?php echo esc_html( $row->gamesBack ?? '0.0' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    $html_output = ob_get_clean();
    set_transient( $transient_key, $html_output, 2 * HOUR_IN_SECONDS );
    return $html_output;
}
add_shortcode( 'fantrax_standings', 'fod_display_fantrax_standings_shortcode' );


/* ------------------------------------------------------------------------
   [fantasy_quick_links] — Shows a menu of the main league pages
------------------------------------------------------------------------ */
function display_fantasy_quick_links_shortcode() {
    if ( ! function_exists('get_field') ) { return ''; }
    $page_ids = [
        'My Team'        => get_field('roster_page', 'option'),
        'League Rosters' => get_field('league_rosters_page', 'option'),
        'Free Agents'    => get_field('free_agent_page', 'option'),
        'Waiver Wire'    => get_field('waiver_wire_page', 'option'),
        'Propose Trade'  => get_field('trade_page', 'option'),
        'Pending Trades' => get_field('view_pending_trades_page', 'option'),
    ];
    $html = '<ul class="fantasy-quick-links">';
    foreach ( $page_ids as $label => $id ) {
        if ( $id ) { $html .= '<li><a href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $label ) . ' &rarr;</a></li>'; }
    }
    $html .= '</ul>';
    return $html;
}
add_shortcode( 'fantasy_quick_links', 'display_fantasy_quick_links_shortcode' );


/* ------------------------------------------------------------------------
   [league_members_only] — Restricts content to logged-in users.
------------------------------------------------------------------------ */
function league_members_only_shortcode( $atts, $content = null ) {
    if ( is_user_logged_in() && ! is_null( $content ) && ! is_feed() ) {
        return do_shortcode( $content );
    } else {
        $info_column = '<div class="login-info"><h2>Welcome to Front Office Dynasty Sports</h2><p>This is a private, members-only league. Please log in to access the League Office.</p></div>';
        ob_start();
        wp_login_form( [ 'remember' => true, 'label_username' => __( 'Email or Username' ) ] );
        $login_form = ob_get_clean();
        $login_column = '<div class="login-form-widget">' . $login_form . '</div>';
        return '<div class="public-login-page-wrapper">' . $info_column . $login_column . '</div>';
    }
}
add_shortcode( 'league_members_only', 'league_members_only_shortcode' );

/* ------------------------------------------------------------------------
   [league_key_dates] — Displays a table of key dates for the league
------------------------------------------------------------------------ */
function display_league_key_dates_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'league' => '' ], $atts );
    $selected_league_id = $atts['league'];
    $manager_leagues = [];

    if ( is_user_logged_in() && function_exists('get_field') ) {
        $user_id = get_current_user_id();
        $managed_teams = get_field('managed_teams', 'user_' . $user_id);
        $managed_nba_teams = get_field('managed_nba_teams', 'user_' . $user_id);
        if ( !empty($managed_teams) ) { foreach ($managed_teams as $team) { if ( !empty($team['league_id']) ) { $manager_leagues[$team['league_id']] = 1; } } }
        if ( !empty($managed_nba_teams) ) { foreach ($managed_nba_teams as $team) { if ( !empty($team['league_id']) ) { $manager_leagues[$team['league_id']] = 1; } } }
        $manager_leagues = array_keys($manager_leagues);
        sort($manager_leagues);
    }

    if ( isset($_GET['dates_league']) ) {
        $requested = sanitize_text_field( wp_unslash($_GET['dates_league']) );
        if ( empty($manager_leagues) || in_array($requested, $manager_leagues) ) { $selected_league_id = $requested; }
    }

    if ( empty($selected_league_id) ) { $selected_league_id = !empty($manager_leagues) ? reset($manager_leagues) : 'MLB'; }

    $dates = get_field('dates_' . strtolower($selected_league_id), 'option');

    ob_start();
    ?>
    <div class="key-dates-wrapper">
        <?php if ( count($manager_leagues) > 1 ) : ?>
            <div class="league-selector-ui" style="margin-bottom: 15px;">
                <strong>View Dates For:</strong> 
                <?php 
                $links = [];
                $base_url = get_permalink();
                foreach ($manager_leagues as $lid) {
                    $url = add_query_arg('dates_league', rawurlencode($lid), $base_url);
                    $links[] = '<a href="'.esc_url($url).'"'.($lid === $selected_league_id ? ' class="is-selected"' : '').'>'.esc_html($lid).'</a>';
                }
                echo implode(' | ', $links);
                ?>
            </div>
        <?php endif; ?>
        <h3>Key Dates (<?php echo esc_html($selected_league_id); ?>)</h3>
        <?php if ( !empty($dates) && is_array($dates) ) : ?>
            <table class="fantasy-table-base key-dates-table">
                <thead><tr><th>Date</th><th>Event</th></tr></thead>
                <tbody>
                    <?php foreach ( $dates as $row ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($row['date']); ?></strong></td>
                            <td><?php echo esc_html($row['event']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No key dates have been set for the <?php echo esc_html($selected_league_id); ?> league.</p>
        <?php endif; ?>
    </div>
    <style>.key-dates-wrapper{margin-top:20px;margin-bottom:20px;}.key-dates-table th{background-color:#f0f0f1;text-align:left;}</style>
    <?php
    return ob_get_clean();
}
add_shortcode( 'league_key_dates', 'display_league_key_dates_shortcode' );

/* ------------------------------------------------------------------------
   [fa_bid_calculator] — Calculates total bid points based on Years & AAV
------------------------------------------------------------------------ */
function display_fa_bid_calculator_shortcode() {
    $multipliers = [ 1 => 2.0, 2 => 1.8, 3 => 1.6, 4 => 1.4, 5 => 1.2, 6 => 1.0, 7 => 0.8, 8 => 0.6 ];
    ob_start();
    ?>
    <div class="bid-calculator-wrapper" style="padding: 20px; border: 1px solid #ccc; border-radius: 5px; max-width: 400px; margin-top: 20px;">
        <h3>Free Agent Bid Calculator</h3>
        <form id="bid-calculator-form" onsubmit="return false;">
            <p>
                <label for="bid-years">Contract Years (1-8):</label><br>
                <select id="bid-years" style="width: 100%;">
                    <?php foreach ($multipliers as $year => $mult): ?>
                        <option value="<?php echo esc_attr($year); ?>" data-multiplier="<?php echo esc_attr($mult); ?>">
                            <?php echo esc_html($year); ?> Year<?php echo $year > 1 ? 's' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="fa-bid-aav">AAV Amount ($):</label><br>
                    <input type="number" id="fa-bid-aav" placeholder="e.g., 5000000" style="width: 100%;">
                </p>
                <div id="fa-bid-result" style="font-size: 1.2em; font-weight: bold; margin-top: 10px;">
                    Total Bid Points: <span id="bid-points-value" style="color: #0073aa;">0</span>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    add_shortcode('fa_bid_calculator', 'display_fa_bid_calculator_shortcode');
    
    /* ------------------------------------------------------------------------
       [fa_bidding_history] — Shows current bidding wars and their history
    ------------------------------------------------------------------------ */
    function display_fa_bidding_history_shortcode() {
        if ( ! is_user_logged_in() ) return '<p>Please log in to view bidding history.</p>';
        if ( ! function_exists('get_field') ) return '<p>Error: Advanced Custom Fields plugin is not active.</p>';
    
        // League selection logic
        $manager_leagues = [];
        $current_user_id = get_current_user_id();
        $managed_teams = get_field('managed_teams', 'user_' . $current_user_id);
        if (!empty($managed_teams) && is_array($managed_teams)) {
            $manager_leagues = array_values(array_unique(array_column($managed_teams, 'league_id')));
            sort($manager_leagues);
        }
        if ( empty($manager_leagues) ) return '<p>You are not currently assigned to manage any teams in any leagues.</p>';
        
        $selected_league_id = $manager_leagues[0];
        if ( isset($_GET['show_league']) ) {
            $requested_league = sanitize_text_field( wp_unslash( $_GET['show_league'] ) );
            if ( in_array($requested_league, $manager_leagues, true) ) { $selected_league_id = $requested_league; }
        }
    
        $manager_team_id_for_league = null;
        foreach ($managed_teams as $team_data) {
            if (($team_data['league_id'] ?? '') === $selected_league_id) { $manager_team_id_for_league = $team_data['fantasy_team_id'] ?? null; break; }
        }
    
        ob_start();
    
        // League selector UI
        echo '<div class="fa-league-selector fa-controls-section"><strong>View Bidding For League:</strong> ';
        $selector_links = []; 
        $current_page_url_base = get_permalink();
        foreach ($manager_leagues as $league_id) {
            $league_url = add_query_arg('show_league', rawurlencode($league_id), $current_page_url_base);
            $selector_links[] = '<a href="'.esc_url($league_url).'"'.($league_id === $selected_league_id ? ' class="is-selected"' : '') .'>'.esc_html($league_id).'</a>';
        }
        echo implode(' | ', $selector_links); 
        echo '</div>';
    
        // Query for players with pending bids in the selected league
        $args = [
            'post_type' => 'playerdata',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'league_id', 'value' => $selected_league_id],
                ['key' => 'fa_status', 'value' => 'pending_bid']
            ],
            'orderby' => 'meta_value',
            'meta_key' => 'bid_end_time',
            'order' => 'DESC'
        ];
        $bidding_query = new WP_Query($args);
    
        echo '<h2>Current Bidding Wars (' . esc_html($selected_league_id) . ')</h2>';
    
        if ( $bidding_query->have_posts() ) {
            echo '<ul class="bidding-history-list">';
            while( $bidding_query->have_posts() ) {
                $bidding_query->the_post();
                $player_id = get_the_ID();
                $player_name = get_the_title();
    
                // Get top bid info
                $top_bid_amount = get_post_meta($player_id, 'pending_bid_amount', true);
                $top_bid_aav = get_post_meta($player_id, 'pending_bid_aav', true);
                $top_bid_team = get_post_meta($player_id, 'pending_bid_team_id', true);
                $end_time_str = get_post_meta($player_id, 'bid_end_time', true);
                $end_timestamp = strtotime($end_time_str);
                $time_display = ($end_timestamp > current_time('timestamp')) 
                    ? human_time_diff(current_time('timestamp'), $end_timestamp) . ' left' 
                    : 'Processing...';
    
                // Get full bid history
                $bid_history = get_field('bid_history', $player_id);
    
                echo '<li class="bidding-player-item">';
                echo '<h4>' . esc_html($player_name) . '</h4>';
                echo '<div class="bidding-summary">';
                echo '<strong>High Bid:</strong> ' . esc_html(number_format(floatval($top_bid_amount), 2)) . ' pts<br>';
                if ($top_bid_aav) {
                    echo '<strong>AAV:</strong> $' . esc_html(number_format(floatval($top_bid_aav))) . '<br>';
                }
                echo '<strong>High Bidder:</strong> ' . esc_html($top_bid_team) . '<br>';
                echo '<strong>Time Remaining:</strong> ' . esc_html($time_display);
                echo '</div>';
    
                if ( !empty($bid_history) && is_array($bid_history) ) {
                    // Sort history descending by bid amount
                    usort($bid_history, function($a, $b) {
                        $amount_a = floatval($a['history_bid_amount'] ?? 0);
                        $amount_b = floatval($b['history_bid_amount'] ?? 0);
                        return $amount_b <=> $amount_a;
                    });
    
                    echo '<details class="bid-history-details">';
                    echo '<summary>View Full Bid History (' . count($bid_history) . ' bids)</summary>';
                    echo '<table class="fantasy-table-base bid-history-table">';
                    echo '<thead><tr><th>Team</th><th>Bid Amount</th><th>Years</th><th>AAV</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($bid_history as $bid) {
                        echo '<tr>';
                        echo '<td>' . esc_html($bid['history_team_id'] ?? '') . '</td>';
                        echo '<td>' . esc_html(number_format(floatval($bid['history_bid_amount'] ?? 0), 2)) . ' pts</td>';
                        echo '<td>' . esc_html($bid['history_bid_years'] ?? '') . '</td>';
                        echo '<td>$' . esc_html(number_format(floatval($bid['history_bid_aav'] ?? 0))) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table></details>';
                } else {
                    echo '<p style="margin-top: 10px;"><em>No bid history found for this player. This may be the initial bid.</em></p>';
                }
                if ($manager_team_id_for_league) {
                    echo '<button type="button" class="button fa-offer-button" data-playerid="' . esc_attr($player_id) . '" data-playername="' . esc_attr($player_name) . '" data-leagueid="' . esc_attr($selected_league_id) . '" data-teamid="' . esc_attr($manager_team_id_for_league) . '" style="margin-top: 10px;">Place New Bid</button>';
                }
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>No active bidding wars in this league.</p>';
        }
        wp_reset_postdata();
    
        echo fod_render_fa_bid_modal();
        ?>
        <style>
            .bidding-history-list { list-style: none; padding: 0; margin-top: 20px; }
            .bidding-player-item { background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px; }
            .bidding-player-item h4 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; }
            .bidding-summary { font-size: 1.1em; }
            .bid-history-details { margin-top: 15px; }
            .bid-history-details summary { cursor: pointer; font-weight: bold; color: #0073aa; margin-bottom: 10px; }
            .bid-history-table { margin-top: 10px; width: 100%; }
            .bid-history-table th, .bid-history-table td { text-align: left; font-size: 0.95em; }
        </style>
        <?php
    
        return ob_get_clean();
    }
    add_shortcode('fa_bidding_history', 'display_fa_bidding_history_shortcode');
    
    /**
     * Renders the Free Agent Bid Modal.
     */
    function fod_render_fa_bid_modal() {
        $multipliers = [ 1 => 2.0, 2 => 1.8, 3 => 1.6, 4 => 1.4, 5 => 1.2, 6 => 1.0, 7 => 0.8, 8 => 0.6 ];
        ob_start();
        ?>
        <div id="fa-offer-modal" class="fantasy-modal fa-modal-hidden">
            <div class="fa-modal-content">
                <span class="fa-modal-close"></span>
                <h3 id="fa-modal-title">Place Bid</h3>
                <div id="fa-modal-message"></div>
                <form id="fa-offer-form" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sign_free_agent">
                    <input type="hidden" id="fa-modal-playerid" name="player_id" value="">
                    <input type="hidden" id="fa-modal-leagueid" name="league_id" value="">
                    <input type="hidden" id="fa-modal-teamid"   name="team_id"   value="">
                    <input type="hidden" id="fa-modal-nonce"    name="_wpnonce_sign_fa" value="">
                    
                    <p>
                        <label for="fa-bid-years">Contract Years (1-8):</label><br>
                        <select id="fa-bid-years" name="bid_years" style="width: 100%;">
                            <?php foreach ($multipliers as $year => $mult): ?>
                                <option value="<?php echo esc_attr($year); ?>" data-multiplier="<?php echo esc_attr($mult); ?>">
                                    <?php echo esc_html($year); ?> Year<?php echo $year > 1 ? 's' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="fa-bid-aav">AAV Amount ($):</label><br>
                        <input type="number" id="fa-bid-aav" name="bid_aav" placeholder="e.g., 5000000" style="width: 100%;" required min="0">
                    </p>
                    <div id="fa-bid-result" style="font-size: 1.2em; font-weight: bold; margin-top: 10px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">
                        Total Bid Points: <span id="fa-bid-points-value" style="color: #0073aa;">0</span>
                    </div>
                    
                    <hr>
                    <button type="submit" class="button button-primary">Submit Bid</button>
                    <button type="button" class="button button-secondary fa-modal-cancel">Cancel</button>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

function display_all_transactions_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>Please log in to view transactions.</p>';
    }

    global $wpdb;
    $all_leagues = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} pm
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE p.post_type = %s AND pm.meta_key = %s
         ORDER BY meta_value ASC",
        'transaction', 'league_id'
    ) );

    $selected_league_id = '';
    if ( ! empty( $all_leagues ) ) {
        $selected_league_id = $all_leagues[0];
    }
    if ( isset( $_GET['show_league'] ) && in_array( wp_unslash( $_GET['show_league'] ), $all_leagues, true ) ) {
        $selected_league_id = sanitize_text_field( wp_unslash( $_GET['show_league'] ) );
    }

    $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
    
    $meta_query = [
        'relation' => 'AND',
        [
            'relation' => 'OR',
            [ 'key' => 'transaction_type', 'value' => 'Trade', 'compare' => '=' ],
            [ 'key' => 'transaction_type', 'value' => 'Free Agent Signing', 'compare' => '=' ],
            [ 'key' => 'transaction_type', 'value' => 'Waiver Claim', 'compare' => '=' ],
        ]
    ];

    if ( ! empty( $selected_league_id ) ) {
        $meta_query[] = [
            'key'     => 'league_id',
            'value'   => $selected_league_id,
            'compare' => '=',
        ];
    }

    $args = [
        'post_type'      => 'transaction',
        'posts_per_page' => 20,
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => $meta_query,
    ];

    $transactions_query = new WP_Query( $args );

    ob_start();

    if ( count( $all_leagues ) > 1 ) {
        echo '<div class="league-selector-ui" style="margin-bottom: 15px;">';
        echo '<strong>View Transactions For:</strong> ';
        $links = [];
        $base_url = get_permalink();
        foreach ( $all_leagues as $lid ) {
            $url = add_query_arg( 'show_league', rawurlencode( $lid ), $base_url );
            $links[] = '<a href="' . esc_url( $url ) . '"' . ( $lid === $selected_league_id ? ' class="is-selected"' : '' ) . '>' . esc_html( $lid ) . '</a>';
        }
        echo implode( ' | ', $links );
        echo '</div>';
    }

    if ( $transactions_query->have_posts() ) {
        echo '<h2>Completed Transactions (' . esc_html($selected_league_id) . ')</h2>';
        echo '<table class="fantasy-table-base"><thead><tr><th>Date</th><th>League</th><th>Transaction</th></tr></thead><tbody>';

        while ( $transactions_query->have_posts() ) {
            $transactions_query->the_post();
            $league_id = get_field( 'league_id' );
            $summary   = get_field( 'transaction_summary' );

            echo '<tr>';
            echo '<td>' . get_the_date() . '</td>';
            echo '<td>' . esc_html( $league_id ) . '</td>';
            echo '<td>' . esc_html( $summary ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        // Pagination
        $big = 999999999; // need an unlikely integer
        echo paginate_links( array(
            'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
            'format'  => '?paged=%#%',
            'current' => max( 1, get_query_var('paged') ),
            'total'   => $transactions_query->max_num_pages
        ) );

    } else {
        echo '<p>No completed transactions found for this league.</p>';
    }

    wp_reset_postdata();

    return ob_get_clean();
}
add_shortcode( 'all_transactions', 'display_all_transactions_shortcode' );
