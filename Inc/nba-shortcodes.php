<?php
/**
 * NBA Shortcodes
 * * Duplicated and adapted from Baseball shortcodes.
 * Post Type: nbaplayer
 * User Field: managed_nba_teams
 */

/* ------------------------------------------------------------------------
   [nba_my_team_roster] — Roster w/ Dead Cap totals & breakdown
------------------------------------------------------------------------ */
function display_nba_manager_roster_shortcode() {
    if ( ! is_user_logged_in() ) { return '<p>Please log in to view your team roster(s).</p>'; }
    if ( ! function_exists('get_field') ) { return '<p>Error: ACF not active.</p>'; }

    $user_id  = get_current_user_id();
    $user_key = 'user_' . $user_id;
    $teams    = get_field('managed_nba_teams', $user_key);

    if ( empty($teams) || ! is_array($teams) ) {
        return '<p>No NBA teams are currently assigned to your account.</p>';
    }

    // Determine selected team
    $selected_league_id = null;
    $selected_team_id   = null;
    if ( isset($_GET['l_id'], $_GET['t_id']) ) {
        $rl = sanitize_text_field( wp_unslash($_GET['l_id']) );
        $rt = sanitize_text_field( wp_unslash($_GET['t_id']) );
        foreach ($teams as $t) {
            if ( is_array($t) && isset($t['league_id'], $t['fantasy_team_id']) && $t['league_id'] === $rl && $t['fantasy_team_id'] === $rt ) {
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

    $years_to_process = range( (int)date('Y'), 2040 );
    ob_start();

    // Check if the Starting Lineup filter is active
    $filter_starter = isset($_GET['filter_starter']) && $_GET['filter_starter'] == '1';

    // Team selector
    echo '<div class="team-selector-ui"><strong>View Roster For:</strong> ';
    $links = []; $base = get_permalink();
    foreach ($teams as $t) {
        if (!is_array($t) || empty($t['league_id']) || empty($t['fantasy_team_id'])) continue;
        $url = add_query_arg(['l_id'=>rawurlencode($t['league_id']), 't_id'=>rawurlencode($t['fantasy_team_id'])], $base);
        $sel = ($t['league_id']===$selected_league_id && $t['fantasy_team_id']===$selected_team_id) ? ' class="is-selected"' : '';
        $links[] = '<a href="'.esc_url($url).'"'.$sel.'>'.esc_html($t['league_id']).' - '.esc_html($t['fantasy_team_id']).'</a>';
    }
    echo implode(' | ', $links) . '</div>';

    // --- Add Starting Lineup Filter ---
    $current_url = add_query_arg(null, null);

    echo '<div class="team-selector-ui" style="margin-top: 10px;"><strong>Filter Roster:</strong> ';
    if ( $filter_starter ) {
        $filter_url = remove_query_arg('filter_starter', $current_url);
        echo '<a href="' . esc_url($filter_url) . '">Show All Active Players</a>';
    } else {
        $filter_url = add_query_arg('filter_starter', '1', $current_url);
        echo '<a href="' . esc_url($filter_url) . '">Show Starting Lineup Only</a>';
    }
    echo '</div>';
    // --- End Filter ---

    $logo_url = '';
    foreach ($teams as $t) {
        if (is_array($t) && $t['league_id'] === $selected_league_id && $t['fantasy_team_id'] === $selected_team_id) {
            $logo_url = $t['team_logo'] ?? '';
            break;
        }
    }
    if ($logo_url) {
        if ( is_array($logo_url) ) { $logo_url = $logo_url['url']; }
        echo '<div class="team-header">';
        echo '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($selected_team_id) . ' Logo" class="team-logo-img">';
        echo '</div>';
    }

    echo '<h2>Roster for League: '.esc_html($selected_league_id).', Team: '.esc_html($selected_team_id).'</h2>';
    echo '<div id="roster-notices-container"></div>';

    $salary_totals = array_fill_keys($years_to_process, 0.0);
    $active_rows = []; $inactive_rows = []; $il_rows = [];

   $build_rows = function( $q ) use (&$salary_totals, $years_to_process, $selected_league_id, $selected_team_id) {
    $rows = [];
    while ( $q->have_posts() ) {
        $q->the_post();
        $pid  = get_the_ID();
        $name = get_the_title();

        // Note: "status_40_man" is used for "Active Roster" in NBA context
        // Note: "status_26_man" is used for "Starting Lineup" in NBA context
        $is_active = get_post_meta($pid, 'status_40_man', true) === 'X';
        $is_starter = get_post_meta($pid, 'status_26_man', true) == '1';
        $is_dfa_only  = get_post_meta($pid, 'dfa_only', true);

        $position     = get_post_meta($pid, 'position', true);
        $nba_team     = get_post_meta($pid, 'mlb_team', true); // Still uses mlb_team field key
        $status_il    = get_post_meta($pid, 'status_il', true);

        $tr = '<tr>';
        $tr .= '<td>'.esc_html($name).'</td>';
        $tr .= '<td>'.esc_html($position ?: 'N/A').'</td>';
        $tr .= '<td>'.esc_html($nba_team ?: 'N/A').'</td>';
        $tr .= '<td>'.esc_html($status_il ?: '–').'</td>';
        $tr .= '<td>'.($is_active ? 'X' : '–').'</td>';
        $tr .= '<td>'.($is_starter ? 'X' : '–').'</td>';

        foreach ($years_to_process as $yr) {
            $v = get_post_meta($pid, 'contract_'.$yr, true);
            $cell = (is_numeric($v)) ? '$'.number_format((float)$v, 0) : (($v===''||$v===null) ? '–' : esc_html($v));
            $tr .= '<td>'.$cell.'</td>';
            if ($v !== '' && $v !== null) {
                $clean = str_replace(',', '', (string)$v);
                if (is_numeric($clean)) $salary_totals[$yr] += (float)$clean;
            }
        }

        $tr .= '<td class="player-action-cell">';
        $common_data_attrs = 'data-playerid="'.esc_attr($pid).'" data-playername="'.esc_attr($name).'" data-leagueid="'.esc_attr($selected_league_id).'" data-teamid="'.esc_attr($selected_team_id).'"';

        $on_il = !empty( $status_il );

        if ( $on_il ) {
            $tr .= '<button type="button" class="button activate-from-il-button" '.$common_data_attrs.'>Activate from IL</button>';
        } else {
            // Note: Class names (move-to-il-button) remain same, handled by nba-modal.js
            $tr .= '<button type="button" class="button move-to-il-button" '.$common_data_attrs.'>Move to IL</button>';

            if ($is_active) {
                $tr .= '<button type="button" class="button option-minors-button" '.$common_data_attrs.'>Deactivate</button>';
                if (!$is_starter) {
                    $tr .= '<button type="button" class="button promote-26-button" '.$common_data_attrs.'>Make Starter</button>';
                }
            } else {
                $tr .= '<button type="button" class="button promote-40-button" '.$common_data_attrs.'>Activate</button>';
            }
            $tr .= '<button type="button" class="button waive-player-button" '.$common_data_attrs.'>Waive</button>';
        }
        $tr .= '</td>';
        $tr .= '</tr>';
        $rows[] = $tr;
    }
    return $rows;
};

    // --- Queries ---
    $base_team_query = array(
        'relation' => 'AND',
        array( 'key' => 'league_id', 'value' => $selected_league_id ),
        array( 'key' => 'fantasy_team_id', 'value' => $selected_team_id ),
        array( 'relation' => 'OR',
            array( 'key'=>'fa_status', 'value'=>'rostered', 'compare'=>'=' ),
            array( 'key'=>'fa_status', 'compare'=>'NOT EXISTS' ),
        ),
    );

    $not_on_il_query = array(
        'relation' => 'OR',
        array( 'key' => 'status_il', 'compare' => 'NOT EXISTS' ),
        array( 'key' => 'status_il', 'value' => '', 'compare' => '=' ),
    );

    // Query 1: Active Roster (formerly 40-man)
    $meta_query_active = array_merge($base_team_query, $not_on_il_query, array( array( 'key' => 'status_40_man', 'value' => 'X' ) ));
    if ( $filter_starter ) {
        $meta_query_active[] = array( 'key' => 'status_26_man', 'value' => '1', 'compare' => '=' );
    }
    $args_active = array( 'post_type' => 'nbaplayer', 'posts_per_page' => 300, 'meta_query' => $meta_query_active, 'orderby' => 'title', 'order' => 'ASC' );
    $q_active = new WP_Query($args_active);
    $active_rows = $build_rows($q_active);
    $count_active = (int) $q_active->post_count;
    wp_reset_postdata();

    // Query 2: Inactive / G-League (formerly Minors)
    $meta_query_inactive = array_merge($base_team_query, $not_on_il_query, array(
        array( 'relation' => 'OR',
            array( 'key' => 'status_40_man', 'value' => 'X', 'compare' => '!=' ),
            array( 'key' => 'status_40_man', 'compare' => 'NOT EXISTS' ),
        )
    ));
    $args_inactive = array( 'post_type' => 'nbaplayer', 'posts_per_page' => 300, 'meta_query' => $meta_query_inactive, 'orderby' => 'title', 'order' => 'ASC' );
    $q_inactive = new WP_Query($args_inactive);
    $inactive_rows = $build_rows($q_inactive);
    $count_inactive = (int) $q_inactive->post_count;
    wp_reset_postdata();

    // Query 3: Injured List
    $meta_query_il = array_merge($base_team_query, array(
        array( 'key' => 'status_il', 'compare' => 'EXISTS' ),
        array( 'key' => 'status_il', 'value' => '', 'compare' => '!=' ),
    ));
    $args_il = array( 'post_type' => 'nbaplayer', 'posts_per_page' => 300, 'meta_query' => $meta_query_il, 'orderby' => 'title', 'order' => 'ASC' );
    $q_il = new WP_Query($args_il);
    $il_rows = $build_rows($q_il);
    $count_il = (int) $q_il->post_count;
    wp_reset_postdata();

    // Query 4: Starters Count
    $count_starters = 0;
    if ($q_active->have_posts()) {
        foreach($q_active->posts as $p_id) {
            if ( $filter_starter ) {
                $count_starters++;
            } else {
                if (get_post_meta($p_id, 'status_26_man', true) == '1') {
                    $count_starters++;
                }
            }
        }
    }

    // Query 5: Dead Cap
    $dead_cap_totals = array_fill_keys($years_to_process, 0.0);
    $grouped_dead_cap = [];
    $dc_args = array(
        'post_type' => 'nbaplayer',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'meta_query' => array(
            'relation' => 'AND',
            array('key' => 'league_id', 'value' => $selected_league_id),
            array('key' => 'dead_cap_penalties_$_dead_cap_team_id', 'value' => $selected_team_id)
        )
    );
    $dc_q = new WP_Query($dc_args);

    if ($dc_q->have_posts()) {
        while ($dc_q->have_posts()) {
            $dc_q->the_post();
            $p_id = get_the_ID();
            $penalties = get_field('dead_cap_penalties', $p_id);
            if ($penalties) {
                foreach ($penalties as $row) {
                    $yr  = isset($row['penalty_year']) ? (int)$row['penalty_year'] : 0;
                    $amt = isset($row['penalty_amount']) ? (float)$row['penalty_amount'] : 0.0;
                    $tid = isset($row['dead_cap_team_id']) ? $row['dead_cap_team_id'] : '';
                    $typ = $row['penalty_type'] ?? '';
                    if ($tid === $selected_team_id && $yr && isset($dead_cap_totals[$yr])) {
                        $dead_cap_totals[$yr] += $amt;
                        $grouped_dead_cap[$yr][] = array('player' => get_the_title($p_id), 'amount' => $amt, 'type' => $typ ?: 'Dead Cap');
                    }
                }
            }
        }
    }
    wp_reset_postdata();

    // --- Output HTML ---
    ob_start();

    $links = [];
    $base = get_permalink();
    foreach ($teams as $t) {
        if (!is_array($t) || empty($t['league_id']) || empty($t['fantasy_team_id'])) continue;
        $url = add_query_arg(['l_id'=>rawurlencode($t['league_id']), 't_id'=>rawurlencode($t['fantasy_team_id'])], $base);
        $sel = ($t['league_id']===$selected_league_id && $t['fantasy_team_id']===$selected_team_id) ? ' class="is-selected"' : '';
        $links[] = '<a href="'.esc_url($url).'"'.$sel.'>'.esc_html($t['league_id']).' - '.esc_html($t['fantasy_team_id']).'</a>';
    }
    echo '<div class="team-selector-ui"><strong>View Roster For:</strong> ' . implode(' | ', $links) . '</div>';

    // Filter Link
    $base_url = get_permalink();
    $url_params = ['l_id' => $selected_league_id, 't_id' => $selected_team_id];
    echo '<div class="team-selector-ui" style="margin-top: 10px;"><strong>Filter Roster:</strong> ';
    if ( $filter_starter ) {
        $filter_url = add_query_arg($url_params, $base_url);
        echo '<a href="' . esc_url($filter_url) . '">Show All Active Players</a>';
    } else {
        $url_params['filter_starter'] = '1';
        $filter_url = add_query_arg($url_params, $base_url);
        echo '<a href="' . esc_url($filter_url) . '">Show Starting Lineup Only</a>';
    }
    echo '</div>';

    if ($logo_url) {
        echo '<div class="team-header"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr($selected_team_id) . ' Logo" class="team-logo-img"></div>';
    }
    echo '<h2>Roster for League: '.esc_html($selected_league_id).', Team: '.esc_html($selected_team_id).'</h2>';
    echo '<div id="roster-notices-container"></div>';

    echo '<h4>Roster Counts</h4>';
    echo '<table class="fantasy-table-base" style="width: auto; margin-bottom: 30px;">';
    echo '<thead><tr><th>Starters</th><th>Active</th><th>Inactive</th><th>IL</th></tr></thead>';
    echo '<tbody><tr>';
    echo '<td style="text-align: center;">' . esc_html($count_starters) . '</td>';
    echo '<td style="text-align: center;">' . esc_html($count_active) . '</td>';
    echo '<td style="text-align: center;">' . esc_html($count_inactive) . '</td>';
    echo '<td style="text-align: center;">' . esc_html($count_il) . '</td>';
    echo '</tr></tbody>';
    echo '</table>';

    echo '<h4>Team Salary Totals</h4>';
    echo '<table class="fantasy-table-base"><thead><tr><th>Year</th>';
    foreach ($years_to_process as $y) echo '<th>'.esc_html($y).'</th>';
    echo '</tr></thead><tbody>';
    echo '<tr><td>Active Payroll</td>';
    foreach ($years_to_process as $y) echo '<td>$'.esc_html(number_format($salary_totals[$y] ?? 0, 0)).'</td>';
    echo '</tr>';
    echo '<tr><td>Dead Cap</td>';
    foreach ($years_to_process as $y) echo '<td>$'.esc_html(number_format($dead_cap_totals[$y] ?? 0, 0)).'</td>';
    echo '</tr>';
    echo '<tr><td>Total Payroll</td>';
    foreach ($years_to_process as $y) { $tot = ($salary_totals[$y] ?? 0) + ($dead_cap_totals[$y] ?? 0); echo '<td>$'.esc_html(number_format($tot, 0)).'</td>'; }
    echo '</tr>';
    echo '</tbody></table>';

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

    // --- NBA Table Headers ---
    $head = '<thead><tr><th>Name</th><th>Pos</th><th>NBA Team</th><th>IL</th><th>Active</th><th>Starter</th>';
    foreach ($years_to_process as $y) $head .= '<th>'.esc_html($y).'</th>';
    $head .= '<th>Action</th></tr></thead>';

    echo '<h3>Active Roster</h3>';
    echo '<table id="roster-40-table" class="fantasy-table-base">' . $head . '<tbody id="roster-40-tbody">';
    if ($active_rows) {
        echo implode('', $active_rows);
    } else {
        $col_count = 7 + count($years_to_process);
        echo '<tr><td colspan="' . $col_count . '">No players found on the active roster.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h3>Injured List</h3>';
    echo '<table id="roster-il-table" class="fantasy-table-base">' . $head . '<tbody id="roster-il-tbody">';
    if ($il_rows) {
        echo implode('', $il_rows);
    } else {
        $col_count = 7 + count($years_to_process);
        echo '<tr><td colspan="' . $col_count . '">No players found on the Injured List.</td></tr>';
    }
    echo '</tbody></table>';

    echo '<h3>Inactive / G-League</h3>';
    echo '<table id="roster-minors-table" class="fantasy-table-base">' . $head . '<tbody id="roster-minors-tbody">';
    if ($inactive_rows) {
        echo implode('', $inactive_rows);
    } else {
        $col_count = 7 + count($years_to_process);
        echo '<tr><td colspan="' . $col_count . '">No inactive players found.</td></tr>';
    }
    echo '</tbody></table>';

    // Re-use the IL Modal (it is generic enough)
    ?>
    <div id="il-modal" class="fantasy-modal fa-modal-hidden">
        <div class="fa-modal-content">
            <span class="fa-modal-close"></span>
            <h3 id="il-modal-title">Place Player on IL</h3>
            <div id="il-modal-message"></div>
            <form id="il-form">
                <input type="hidden" id="il-modal-playerid" name="player_id" value="">
                <p>Select the IL duration.</p>
                <div class="il-duration-options">
                    <div><input type="radio" name="il_duration" id="il-8" value="8" checked><label for="il-8"><strong>Short Term</strong></label></div>
                    <div><input type="radio" name="il_duration" id="il-60" value="60"><label for="il-60"><strong>Long Term</strong></label></div>
                </div>
                <hr>
                <button type="submit" id="il-submit-button" class="button button-primary">Place on IL</button>
                <button type="button" class="button button-secondary fa-modal-cancel">Cancel</button>
            </form>
        </div>
    </div>
    <style>
    #il-modal.fa-modal-hidden { display: none !important; }
    .il-duration-options { display: flex; flex-direction: column; gap: 15px; margin: 20px 0; }
    .il-duration-options div { display: flex; align-items: center; gap: 8px; }
    .il-duration-options input[type="radio"] { width: 20px; height: 20px; }
    .il-duration-options label { font-size: 1.1em; margin-bottom: 0; }
    </style>
    <?php

    return ob_get_clean();
}
add_shortcode( 'nba_my_team_roster', 'display_nba_manager_roster_shortcode' );


/* ------------------------------------------------------------------------
   [nba_league_salary_summary]
------------------------------------------------------------------------ */
function display_nba_league_salary_summary_shortcode() {
    $current_year     = (int) date('Y');
    $years_to_process = range($current_year, 2040);
    $selected_league_id    = null;
    $manager_leagues       = [];
    $all_available_leagues = [];
    if ( is_user_logged_in() && function_exists('get_field') ) {
        $user_id  = get_current_user_id();
        $user_key = 'user_' . $user_id;
        $managed_teams = get_field('managed_nba_teams', $user_key);
        if ( ! empty($managed_teams) && is_array($managed_teams) ) {
            foreach ( $managed_teams as $team_data ) {
                if ( is_array($team_data) && ! empty($team_data['league_id']) ) {
                    $manager_leagues[ $team_data['league_id'] ] = $team_data['league_id'];
                }
            }
            $manager_leagues       = array_values($manager_leagues);
            sort($manager_leagues);
            $all_available_leagues = $manager_leagues;
        }
    }
    if ( empty($all_available_leagues) ) {
        global $wpdb;
        $results = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish' ORDER BY pm.meta_value ASC", 'league_id', 'nbaplayer' ) );
        $all_available_leagues = $results ?: ['NBA'];
    }
    if ( isset($_GET['show_league']) ) {
        $requested = sanitize_text_field( wp_unslash($_GET['show_league']) );
        if ( ( ! empty($manager_leagues) && in_array($requested, $manager_leagues, true) ) || ( empty($manager_leagues) && in_array($requested, $all_available_leagues, true) ) ) {
            $selected_league_id = $requested;
        }
    }
    if ( ! $selected_league_id ) {
        $selected_league_id = !empty($manager_leagues) ? $manager_leagues[0] : (!empty($all_available_leagues) ? $all_available_leagues[0] : null);
        if ( ! $selected_league_id ) { return '<p>No leagues available to display.</p>'; }
    }
    $active_totals = [];
    $args_active = array( 'post_type' => 'nbaplayer', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'title', 'order' => 'ASC', 'meta_query' => array( 'relation' => 'AND', array( 'key' => 'league_id', 'value' => $selected_league_id, 'compare' => '=' ), array( 'relation' => 'OR', array( 'key' => 'fa_status', 'value' => 'rostered', 'compare' => '=' ), array( 'key' => 'fa_status', 'compare' => 'NOT EXISTS' ), array( 'key' => 'fa_status', 'value' => '', 'compare' => '=' ), ), ), );
    $q_active = new WP_Query($args_active);
    if ( $q_active->have_posts() && function_exists('get_field') ) {
        foreach ( $q_active->posts as $player_id ) {
            $team_id = get_field('fantasy_team_id', $player_id);
            if ( empty($team_id) ) { continue; }
            if ( ! isset($active_totals[$team_id]) ) { $active_totals[$team_id] = array_fill_keys($years_to_process, 0.0); }
            foreach ( $years_to_process as $year ) {
                $salary_raw = get_field('contract_' . $year, $player_id);
                if ($salary_raw === '' || $salary_raw === null) { continue; }
                if ( is_scalar($salary_raw) ) { $clean = str_replace(',', '', (string)$salary_raw); if ( is_numeric($clean) ) { $active_totals[$team_id][$year] += (float) $clean; } }
            }
        }
    }
    wp_reset_postdata();
    $dead_cap_totals = [];
    $args_dc = array( 'post_type' => 'nbaplayer', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_query' => array( 'relation' => 'AND', array( 'key' => 'league_id', 'value' => $selected_league_id, 'compare' => '=' ), array( 'key' => 'dead_cap_penalties', 'compare' => 'EXISTS' ), ), );
    $q_dc = new WP_Query($args_dc);
    if ( $q_dc->have_posts() && function_exists('get_field') ) {
        foreach ( $q_dc->posts as $pid ) {
            $rows = get_field('dead_cap_penalties', $pid);
            if ( empty($rows) || !is_array($rows) ) { continue; }
            foreach ( $rows as $row ) {
                $year = isset($row['penalty_year']) ? (int) $row['penalty_year'] : 0;
                $amount  = isset($row['penalty_amount']) ? (float) $row['penalty_amount'] : 0.0;
                $dc_team = isset($row['dead_cap_team_id']) ? (string) $row['dead_cap_team_id'] : '';
                if ( ! $dc_team || ! in_array($year, $years_to_process, true) ) { continue; }
                if ( ! isset($dead_cap_totals[$dc_team]) ) { $dead_cap_totals[$dc_team] = array_fill_keys($years_to_process, 0.0); }
                $dead_cap_totals[$dc_team][$year] += $amount;
            }
        }
    }
    wp_reset_postdata();
    $all_team_ids = array_unique(array_merge(array_keys($active_totals), array_keys($dead_cap_totals)));
    sort($all_team_ids, SORT_NATURAL);
    ob_start();
    echo '<h2>League Salary Summary (Combined) — ' . esc_html($selected_league_id) . '</h2>';
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
            $active   = isset($active_totals[$team_id][$year])   ? $active_totals[$team_id][$year]   : 0.0;
            $dead_cap = isset($dead_cap_totals[$team_id][$year]) ? $dead_cap_totals[$team_id][$year] : 0.0;
            $total    = $active + $dead_cap;
            echo '<td>$' . esc_html( number_format($total, 0) ) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    return ob_get_clean();
}
add_shortcode( 'nba_league_salary_summary', 'display_nba_league_salary_summary_shortcode' );

/* ------------------------------------------------------------------------
   [nba_free_agent_list]
------------------------------------------------------------------------ */
function display_nba_free_agent_list_shortcode() {
    // Shortened for brevity, but assumes duplicated logic with 'nbaplayer' post type
    // You would replicate the MLB version here, changing post_type => 'nbaplayer'
    // and managed_teams => managed_nba_teams

    // Placeholder:
    return '<p>NBA Free Agent List (Coming Soon)</p>';
}
add_shortcode( 'nba_free_agent_list', 'display_nba_free_agent_list_shortcode' );
