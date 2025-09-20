<?php
/**
 * All theme shortcodes are defined in this file.
 */

/* ------------------------------------------------------------------------
   [my_team_roster] – Roster w/ Dead Cap totals & breakdown
------------------------------------------------------------------------ */
function display_manager_roster_shortcode() {
    if ( ! is_user_logged_in() ) { return '<p>Please log in to view your team roster(s).</p>'; }
    if ( ! function_exists('get_field') ) { return '<p>Error: ACF not active.</p>'; }

    $user_id  = get_current_user_id();
    $user_key = 'user_' . $user_id;
    $teams    = get_field('managed_teams', $user_key);

    if ( empty($teams) || ! is_array($teams) ) {
        return '<p>No teams are currently assigned to your account.</p>';
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

    $years_to_process = range( (int)date('Y'), (int)date('Y') + 10 );
    ob_start();

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
    $forty_rows = []; $non40_rows = [];

    $build_rows = function( $q ) use (&$salary_totals, $years_to_process, $selected_league_id, $selected_team_id) {
    $rows = [];
    while ( $q->have_posts() ) {
        $q->the_post();
        $pid  = get_the_ID();
        $name = get_the_title();
        $is_on_40_man = get_field('status_40_man') === 'X';
        $is_dfa_only = get_field('dfa_only');
        $moves_log = get_field('roster_moves_log');
        $rule_5_year = get_field('rule_5_eligibility_year');
        $options_used_this_season = 0;
        $current_year_string = date('Y');
        if ( is_array($moves_log) ) {
            foreach ($moves_log as $move) {
                if ( isset($move['move_year'], $move['move_type']) && $move['move_year'] === $current_year_string && $move['move_type'] === 'Optioned' ) {
                    $options_used_this_season++;
                }
            }
        }
        $option_years = [];
        if ( is_array($moves_log) ) {
            foreach ($moves_log as $move) {
                if ( isset($move['move_type']) && $move['move_type'] === 'Optioned' ) {
                    $option_years[] = $move['move_year'];
                }
            }
        }
        $unique_option_years = array_unique($option_years);
        sort($unique_option_years);
        $option_years_display = !empty($unique_option_years) ? implode(', ', $unique_option_years) : '–';

        $tr = '<tr>';
        $tr .= '<td>'.esc_html($name).'</td>';
        $tr .= '<td>'.esc_html(get_field('position') ?: 'N/A').'</td>';
        $tr .= '<td>'.esc_html(get_field('mlb_team') ?: 'N/A').'</td>';
        $tr .= '<td>'.esc_html(get_field('status_il') ?: '–').'</td>';
        $tr .= '<td>'.($is_on_40_man ? 'X' : '–').'</td>';
        $tr .= '<td>'.($is_dfa_only ? 'Yes' : '–').'</td>';
        $tr .= '<td>'.esc_html($options_used_this_season).' / 5</td>';
        $tr .= '<td>'.esc_html($option_years_display).'</td>';
        $tr .= '<td>'.esc_html($rule_5_year ?: '–').'</td>';

        foreach ($years_to_process as $yr) {
            $v = get_field('contract_'.$yr);
            $cell = (is_numeric($v)) ? '$'.number_format((float)$v, 0) : (($v===''||$v===null) ? '–' : esc_html($v));
            $tr .= '<td>'.$cell.'</td>';
            if ($v !== '' && $v !== null) {
                $clean = str_replace(',', '', (string)$v);
                if (is_numeric($clean)) $salary_totals[$yr] += (float)$clean;
            }
        }

        // --- THIS IS THE CORRECTED BUTTON LOGIC ---
        $tr .= '<td class="player-action-cell">';
        $common_data_attrs = 'data-playerid="'.esc_attr($pid).'" data-playername="'.esc_attr($name).'" data-leagueid="'.esc_attr($selected_league_id).'" data-teamid="'.esc_attr($selected_team_id).'"';

        if ($is_on_40_man) {
            $tr .= '<button type="button" class="button option-minors-button" '.$common_data_attrs.'>Option to Minors</button>';
        } else {
            $tr .= '<button type="button" class="button promote-40-button" '.$common_data_attrs.'>Move to 40-Man</button>';
        }
        $tr .= '<button type="button" class="button waive-player-button" '.$common_data_attrs.'>Waive</button>';
        $tr .= '</td>';
        // --- END OF CORRECTION ---

        $tr .= '</tr>';
        $rows[] = $tr;
    }
    return $rows;
};

    $common_meta_query = array( 'relation' => 'AND', array( 'key' => 'league_id', 'value' => $selected_league_id ), array( 'key' => 'fantasy_team_id', 'value' => $selected_team_id ), array( 'relation' => 'OR', array( 'key'=>'fa_status', 'value'=>'rostered', 'compare'=>'=' ), array( 'key'=>'fa_status', 'compare'=>'NOT EXISTS' ), ), 'position_clause' => array( 'key'=>'position', 'compare'=>'EXISTS' ), );
    $args_40 = array( 'post_type' => 'playerdata', 'posts_per_page' => 300, 'no_found_rows' => true, 'cache_results' => false, 'update_post_meta_cache' => false, 'update_post_term_cache' => false, 'meta_query' => array_merge($common_meta_query, array( array( 'key' => 'status_40_man', 'value' => 'X' ) )), 'orderby' => array( 'position_clause' => 'ASC', 'title' => 'ASC' ), );
    $q_40 = new WP_Query($args_40);
    if ($q_40->have_posts()) { $forty_rows = $build_rows($q_40); }
    wp_reset_postdata();
    $args_n40 = array( 'post_type' => 'playerdata', 'posts_per_page' => 300, 'no_found_rows' => true, 'cache_results' => false, 'update_post_meta_cache' => false, 'update_post_term_cache' => false, 'meta_query' => array_merge($common_meta_query, array( array( 'relation' => 'OR', array( 'key' => 'status_40_man', 'value' => 'X', 'compare' => '!=' ), array( 'key' => 'status_40_man', 'compare' => 'NOT EXISTS' ), ) )), 'orderby' => array( 'position_clause' => 'ASC', 'title' => 'ASC' ), );
    $q_n40 = new WP_Query($args_n40);
    if ($q_n40->have_posts()) { $non40_rows = $build_rows($q_n40); }
    wp_reset_postdata();

    // Dead Cap totals & breakdown (This part was missing)
    $dead_cap_totals = array_fill_keys($years_to_process, 0.0);
    $grouped_dead_cap = [];
    $dc_q = new WP_Query(array('post_type' => 'playerdata', 'posts_per_page' => -1, 'meta_query' => array( array( 'key'=>'dead_cap_penalties', 'compare'=>'EXISTS' ) ), 'fields' => 'ids', 'no_found_rows' => true));
    if ($dc_q->have_posts()) {
        foreach ($dc_q->posts as $p_id) {
            $penalties = get_field('dead_cap_penalties', $p_id);
            if ($penalties) {
                foreach ($penalties as $row) {
                    $yr  = isset($row['penalty_year'])     ? (int)$row['penalty_year']     : 0;
                    $amt = isset($row['penalty_amount'])   ? (float)$row['penalty_amount'] : 0.0;
                    $tid = isset($row['dead_cap_team_id']) ? $row['dead_cap_team_id']       : '';
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

    echo '<div style="margin-bottom:30px;"><h4>Team Salary Totals</h4>';
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
    echo '</tbody></table></div>';

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

    $head = '<thead><tr><th>Name</th><th>Position</th><th>MLB Team</th><th>IL</th><th>40-Man</th><th>DFA Only</th><th>Options (Season)</th><th>Option Years Used</th><th>Rule 5 Year</th>';
    foreach ($years_to_process as $y) $head .= '<th>'.esc_html($y).'</th>';
    $head .= '<th>Action</th></tr></thead>';

    echo '<h3>40-Man Roster</h3>';
    if ($forty_rows) {
        echo '<table id="roster-40-table" class="fantasy-table-base">' . $head . '<tbody id="roster-40-tbody">' . implode('', $forty_rows) . '</tbody></table>';
    } else {
        echo '<p>No players found on the 40-man roster for this team.</p>';
    }

    echo '<h3>Minor League / Off-Roster</h3>';
    if ($non40_rows) {
        echo '<table id="roster-minors-table" class="fantasy-table-base">' . $head . '<tbody id="roster-minors-tbody">' . implode('', $non40_rows) . '</tbody></table>';
    } else {
        echo '<p>No players found off the 40-man roster for this team.</p>';
    }

    return ob_get_clean();
}
add_shortcode( 'my_team_roster', 'display_manager_roster_shortcode' );
/* ------------------------------------------------------------------------
   [league_salary_summary] — single table (Active + Dead Cap combined)
------------------------------------------------------------------------ */
function display_league_salary_summary_shortcode() {
    $current_year     = (int) date('Y');
    $years_to_process = range($current_year, $current_year + 10);

    $selected_league_id    = null;
    $manager_leagues       = [];
    $all_available_leagues = [];

    // Determine leagues the user manages (via ACF user field)
    if ( is_user_logged_in() && function_exists('get_field') ) {
        $user_id  = get_current_user_id();
        $user_key = 'user_' . $user_id;
        $managed_teams = get_field('managed_teams', $user_key);

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

    // If user has no leagues, discover distinct leagues from CPT `player`
    if ( empty($all_available_leagues) ) {
        global $wpdb;
        $results = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT pm.meta_value
               FROM {$wpdb->postmeta} pm
               JOIN {$wpdb->posts} p ON p.ID = pm.post_id
              WHERE pm.meta_key = %s
                AND p.post_type = %s
                AND p.post_status = 'publish'
              ORDER BY pm.meta_value ASC",
            'league_id', 'player'
        ) );
        $all_available_leagues = $results ?: ['AAA']; // Fallback
    }

    // Pick league (from ?show_league=) or default
    if ( isset($_GET['show_league']) ) {
        $requested = sanitize_text_field( wp_unslash($_GET['show_league']) );
        if (
            ( ! empty($manager_leagues) && in_array($requested, $manager_leagues, true) ) ||
            ( empty($manager_leagues) && in_array($requested, $all_available_leagues, true) )
        ) {
            $selected_league_id = $requested;
        }
    }
    if ( ! $selected_league_id ) {
        $selected_league_id = !empty($manager_leagues) ? $manager_leagues[0]
                              : (!empty($all_available_leagues) ? $all_available_leagues[0] : null);
        if ( ! $selected_league_id ) {
            return '<p>No leagues available to display.</p>';
        }
    }

    // ---------- Aggregate Active Payroll (rostered players only) ----------
    $active_totals = []; // [team_id][year] => float

    $args_active = array(
        'post_type'              => 'playerdata',
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'orderby'                => 'title',
        'order'                  => 'ASC',
        'update_post_meta_cache' => false,
        'meta_query'             => array(
            'relation' => 'AND',
            array( 'key' => 'league_id', 'value' => $selected_league_id, 'compare' => '=' ),
            array(
                'relation' => 'OR',
                array( 'key' => 'fa_status', 'value' => 'rostered', 'compare' => '=' ),
                array( 'key' => 'fa_status', 'compare' => 'NOT EXISTS' ),
                array( 'key' => 'fa_status', 'value' => '', 'compare' => '=' ),
            ),
        ),
    );

    $q_active = new WP_Query($args_active);
    if ( $q_active->have_posts() && function_exists('get_field') ) {
        foreach ( $q_active->posts as $player_id ) {
            $team_id = get_field('fantasy_team_id', $player_id);
            if ( empty($team_id) ) { continue; }

            if ( ! isset($active_totals[$team_id]) ) {
                $active_totals[$team_id] = array_fill_keys($years_to_process, 0.0);
            }

            foreach ( $years_to_process as $year ) {
                $salary_raw = get_field('contract_' . $year, $player_id);
                if ($salary_raw === '' || $salary_raw === null) { continue; }
                if ( is_scalar($salary_raw) ) {
                    $clean = str_replace(',', '', (string)$salary_raw);
                    if ( is_numeric($clean) ) {
                        $active_totals[$team_id][$year] += (float) $clean;
                    }
                }
            }
        }
    }
    wp_reset_postdata();

    // ---------- Aggregate Dead Cap (from repeater on any player in this league) ----------
    $dead_cap_totals = []; // [team_id][year] => float

    $args_dc = array(
        'post_type'              => 'playerdata',
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'meta_query'             => array(
            'relation' => 'AND',
            array( 'key' => 'league_id', 'value' => $selected_league_id, 'compare' => '=' ),
            array( 'key' => 'dead_cap_penalties', 'compare' => 'EXISTS' ),
        ),
    );

    $q_dc = new WP_Query($args_dc);
    if ( $q_dc->have_posts() && function_exists('get_field') ) {
        foreach ( $q_dc->posts as $pid ) {
            $rows = get_field('dead_cap_penalties', $pid);
            if ( empty($rows) || !is_array($rows) ) { continue; }

            foreach ( $rows as $row ) {
                $year    = isset($row['penalty_year'])     ? (int) $row['penalty_year']     : 0;
                $amount  = isset($row['penalty_amount'])   ? (float) $row['penalty_amount'] : 0.0;
                $dc_team = isset($row['dead_cap_team_id']) ? (string) $row['dead_cap_team_id'] : '';

                if ( ! $dc_team || ! in_array($year, $years_to_process, true) ) { continue; }

                if ( ! isset($dead_cap_totals[$dc_team]) ) {
                    $dead_cap_totals[$dc_team] = array_fill_keys($years_to_process, 0.0);
                }
                $dead_cap_totals[$dc_team][$year] += $amount;
            }
        }
    }
    wp_reset_postdata();

    // ---------- Combined rows (any team with Active or Dead Cap) ----------
    $all_team_ids = array_unique(array_merge(array_keys($active_totals), array_keys($dead_cap_totals)));
    sort($all_team_ids, SORT_NATURAL);

    ob_start();

    // League selector UI
    if ( !empty($all_available_leagues) && count($all_available_leagues) > 1 ) {
        echo '<div class="league-selector-ui"><strong>View Summary For League:</strong> ';
        $selector_links = [];
        $current_page_url = get_permalink();
        foreach ( $all_available_leagues as $lid ) {
            $url = add_query_arg('show_league', rawurlencode($lid), $current_page_url);
            $is_selected = ($lid === $selected_league_id);
            $selector_links[] = '<a href="' . esc_url($url) . '"' . ($is_selected ? ' class="is-selected"' : '') . '>' . esc_html($lid) . '</a>';
        }
        echo implode(' | ', $selector_links);
        echo '</div>';
    }

    echo '<h2>League Salary Summary (Combined: Active + Dead Cap) — ' . esc_html($selected_league_id) . '</h2>';

    if ( empty($all_team_ids) ) {
        echo "<p>No team salary data found for league " . esc_html($selected_league_id) . ".</p>";
        return ob_get_clean();
    }

    // Single table: Combined totals
    echo '<table class="fantasy-table-base">';
    echo '<thead><tr><th>Team</th>';
    foreach ( $years_to_process as $year ) {
        echo '<th>' . esc_html($year) . '</th>';
    }
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
add_shortcode( 'league_salary_summary', 'display_league_salary_summary_shortcode' );


/* ------------------------------------------------------------------------
   Trade proposal – form + AJAX population + handlers
------------------------------------------------------------------------ */
function display_trade_proposal_form_shortcode() {
    if ( ! is_user_logged_in() ) { return '<p>Please log in to propose a trade.</p>'; }
    if ( ! function_exists('get_field') ) { return '<p>Error: ACF not active.</p>'; }
    $current_user_id = get_current_user_id(); $user_key = 'user_' . $current_user_id; $managed_teams = get_field('managed_teams', $user_key);
    ob_start();

    if (isset($_GET['trade_success']) && $_GET['trade_success'] == 'true') { echo '<div class="trade-form-notice success">Trade proposed successfully! An email has been sent.</div>'; }
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
                    foreach ($managed_teams as $team_data) {
                        if(is_array($team_data) && !empty($team_data['league_id'])) {
                            $manager_leagues_dd[$team_data['league_id']] = 1;
                        }
                    }
                    $manager_leagues_dd = array_keys($manager_leagues_dd);
                    sort($manager_leagues_dd);
                    foreach($manager_leagues_dd as $l_id){
                        echo '<option value="'.esc_attr($l_id).'">'.esc_html($l_id).'</option>';
                    }
                }
                ?>
            </select><br><small><i>Select league to populate lists below.</i></small>
        </p>

        <p><label for="target_manager">Trade With Manager:</label><br>
            <select name="target_manager" id="target_manager" required disabled>
                <option value="">-- Select League First --</option>
            </select>
            <span id="target-manager-loading" style="display: none;">Loading managers...</span>
        </p>

        <p><label for="players_offered">You Offer (Ctrl/Cmd + Click for multiple):</label><br>
            <select name="players_offered[]" id="players_offered" multiple required size="8" disabled>
                <option value="" disabled>-- Select League First --</option>
            </select>
            <span id="players-offered-loading" style="display: none;">Loading your players...</span>
        </p>

        <p><label for="players_requested">You Request (Ctrl/Cmd + Click for multiple):</label><br>
            <select name="players_requested[]" id="players_requested" multiple required size="8" disabled>
                <option value="" disabled>-- Select League & Target First --</option>
            </select>
            <span id="players-requested-loading" style="display: none;">Loading target players...</span>
        </p>

        <p><button type="submit" id="propose-trade-submit" disabled>Propose Trade</button></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'trade_proposal_form', 'display_trade_proposal_form_shortcode' );


function display_pending_trades_shortcode() {
    if ( ! is_user_logged_in() ) { return '<p>Please log in to view pending trade proposals.</p>'; }
    if ( ! function_exists('get_field') ) { return '<p>Error: ACF not active.</p>'; }

    $current_user_id = get_current_user_id();
    $args = array(
        'post_type'      => 'trade_proposal',
        'posts_per_page' => 50,
        'post_status'    => 'publish',
        'meta_query'     => array( 'relation' => 'AND',
            array('key'=>'target_manager','value'=>$current_user_id,'compare'=>'=','type'=>'NUMERIC'),
            array('key'=>'trade_status',  'value'=>'pending',      'compare'=>'=')
        ),
        'orderby' => 'date', 'order'=>'DESC'
    );
    $q = new WP_Query( $args );
    ob_start();

    if (isset($_GET['trade_action'])) {
        $message_text = ''; $message_type = 'info';
        switch($_GET['trade_action']) {
            case 'rejected': $message_text = 'Trade proposal rejected.'; break;
            case 'accepted':
            case 'trade_accepted': $message_text = 'Trade proposal accepted and processed!'; $message_type = 'success'; break;
            case 'trade_action_error':
                $message_text = 'An error occurred processing the trade.'; $message_type = 'error';
                if (isset($_GET['trade_action_error_detail'])) {
                    switch($_GET['trade_action_error_detail']) {
                        case 'security_failed':   $message_text = 'Security check failed. Please refresh the page and try again.'; break;
                        case 'not_logged_in':     $message_text = 'You must be logged in to perform this action.'; break;
                        case 'acf_missing':       $message_text = 'A configuration error occurred. ACF functions missing.'; break;
                        case 'not_authorized':    $message_text = 'You are not authorized to accept this trade.'; break;
                        case 'missing_trade_data':$message_text = 'Missing critical trade data.'; break;
                        case 'team_id_missing':   $message_text = 'Could not verify team ownership for trade.'; break;
                        case 'ownership_mismatch':$message_text = 'Trade failed: player ownership changed. Please review and try again.'; break;
                    }
                }
                break;
        }
        if ($message_text) {
            $class = 'trade-form-notice ' . $message_type;
            echo '<div class="'.esc_attr($class).'">'.esc_html($message_text).'</div>';
        }
    }

    echo "<h2>Pending Trade Proposals Sent To You</h2>";
    if ( $q->have_posts() ) {
        echo '<ul class="pending-trades-list">';
        while ( $q->have_posts() ) { $q->the_post();
            $trade_id = get_the_ID();
            $proposing_manager_id = get_field('proposing_manager', $trade_id);
            $league_id = get_field('league_id', $trade_id);
            $players_offered_ids   = get_field('players_offered',   $trade_id) ?: [];
            $players_requested_ids = get_field('players_requested', $trade_id) ?: [];
            $proposer_info = get_userdata($proposing_manager_id);
            $proposer_name = $proposer_info ? $proposer_info->display_name : 'Unknown User';

            echo '<li class="pending-trade-item">';
            echo '<strong>Proposal From:</strong> ' . esc_html($proposer_name) . '<br>';
            echo '<strong>League:</strong> ' . esc_html($league_id) . '<br>';
            echo '<strong>You Receive:</strong> ';
            if (!empty($players_offered_ids)) { $offered_names = array_map(function($id){ return get_the_title($id) ?: '(?)'; }, $players_offered_ids); echo esc_html(implode(', ', $offered_names)); } else { echo 'N/A'; } echo '<br>';
            echo '<strong>You Give Up:</strong> ';
            if (!empty($players_requested_ids)) { $requested_names = array_map(function($id){ return get_the_title($id) ?: '(?)'; }, $players_requested_ids); echo esc_html(implode(', ', $requested_names)); } else { echo 'N/A'; } echo '<br><br>';

            $nonce_action = 'handle_trade_nonce_' . $trade_id;
            $accept_url = add_query_arg( array( 'action'=>'accept_trade', 'trade_id'=>$trade_id, '_wpnonce'=>wp_create_nonce($nonce_action) ), admin_url('admin-post.php') );
            $reject_url = add_query_arg( array( 'action'=>'reject_trade', 'trade_id'=>$trade_id, '_wpnonce'=>wp_create_nonce($nonce_action) ), admin_url('admin-post.php') );

            echo '<a href="'.esc_url($accept_url).'" class="button trade-action-button accept">Accept</a> ';
            echo '<a href="'.esc_url($reject_url).'" class="button trade-action-button reject">Reject</a>';
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
   Free Agents – list + modal + search + sign (bidding)
------------------------------------------------------------------------ */
function display_fa_sign_notices() {
    $notice = '';
    if ( isset($_GET['sign_success']) && $_GET['sign_success'] === 'true' ) {
        $player_id = isset($_GET['signed_player']) ? absint($_GET['signed_player']) : 0;
        $player_name = $player_id ? get_the_title($player_id) : 'Player';
        $signed_salary_raw = $_GET['salary'] ?? null;
        $salary_text = ($signed_salary_raw !== null && is_numeric($signed_salary_raw)) ? ' for $' . number_format(floatval($signed_salary_raw), 0) : '';
        $status_param = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        if ($status_param === 'pending_bid') {
            $notice = '<div class="notice notice-success is-dismissible">'.esc_html($player_name).' is now in a 24-hour bidding period with your bid of '.esc_html($salary_text).'.</div>';
        } else {
            $notice = '<div class="notice notice-success is-dismissible">'.esc_html($player_name).' successfully signed'.esc_html($salary_text).'!</div>';
        }
    } elseif ( isset($_GET['sign_error']) ) {
        $error_message = 'An error occurred.';
        switch ($_GET['sign_error']) {
            case 'player_signed':       $error_message = 'Player may already be signed or not a valid free agent.'; break;
            case 'update_failed':       $error_message = 'Database error while signing the player.'; break;
            case 'acf_missing':         $error_message = 'Configuration error (ACF).'; break;
            case 'missing_data':        $error_message = 'Missing Player, League, Team, or Salary.'; break;
            case 'not_manager':         $error_message = 'You are not authorized to sign for this team.'; break;
            case 'invalid_salary':      $error_message = 'Invalid salary amount.'; break;
            case 'no_offer':            $error_message = 'Submit at least one contract year.'; break;
            case 'initial_bid_failed':  $error_message = 'Failed to place initial bid.'; break;
            case 'security_failed':     $error_message = 'Security check failed.'; break;
            case 'not_logged_in':       $error_message = 'You must be logged in.'; break;
            case 'already_pending_bid': $error_message = 'Player already has a pending bid.'; break;
            case 'bid_too_low':         $error_message = 'Your bid must be higher than the current highest bid.'; break;
        }
        $notice = '<div class="notice notice-error is-dismissible">Error: '.esc_html($error_message).'</div>';
    }
    return $notice;
}

function display_free_agent_list_shortcode() {
    if ( ! is_user_logged_in() ) return '<p>Please log in to view available free agents.</p>';
    if ( ! function_exists('get_field') ) return '<p>Error: Advanced Custom Fields plugin is not active.</p>';

    $current_user_id = get_current_user_id();
    $user_key = 'user_' . $current_user_id;
    $managed_teams = get_field('managed_teams', $user_key);

    $manager_leagues = [];
    if (!empty($managed_teams) && is_array($managed_teams)) {
        foreach ($managed_teams as $team_data) {
            if (is_array($team_data) && !empty($team_data['league_id'])) {
                $manager_leagues[$team_data['league_id']] = $team_data['league_id'];
            }
        }
        $manager_leagues = array_values($manager_leagues);
        sort($manager_leagues);
    }
    if ( empty($manager_leagues) ) return '<p>You are not currently assigned to manage any teams in any leagues.</p>';

    $selected_league_id = $manager_leagues[0];
    if ( isset($_GET['show_league']) ) {
        $requested_league = sanitize_text_field( wp_unslash( $_GET['show_league'] ) );
        if ( in_array($requested_league, $manager_leagues, true) ) {
            $selected_league_id = $requested_league;
        }
    }

    $manager_team_id_for_league = null;
    foreach ($managed_teams as $team_data) {
        if (is_array($team_data) && ($team_data['league_id'] ?? '') === $selected_league_id) {
            $manager_team_id_for_league = $team_data['fantasy_team_id'] ?? null;
            break;
        }
    }

    $search_term = isset($_GET['fa_search']) ? sanitize_text_field( wp_unslash( $_GET['fa_search'] ) ) : '';
    $current_fa_page = isset($_GET['fa_page']) ? max(1, absint($_GET['fa_page'])) : 1;
    $posts_per_page = 25;

    $current_year = date('Y');
    $years_to_display_in_table = range($current_year, $current_year + 10);
    $contract_input_years = range($current_year, $current_year + 5);

    ob_start();
    echo display_fa_sign_notices();

    // League selector
    echo '<div class="fa-league-selector fa-controls-section"><strong>View Free Agents For League:</strong> ';
    $selector_links_fa = []; $current_page_url_base_fa = get_permalink();
    foreach ($manager_leagues as $league_id_fa) {
        $league_url_fa = add_query_arg('show_league', rawurlencode($league_id_fa), $current_page_url_base_fa);
        $is_selected_fa = ($league_id_fa === $selected_league_id);
        $selector_links_fa[] = '<a href="'.esc_url($league_url_fa).'"'.($is_selected_fa ? ' class="is-selected"' : '') .'>'.esc_html($league_id_fa).'</a>';
    }
    echo implode(' | ', $selector_links_fa); echo '</div>';

// Find and replace the search form block with this:

echo '<div class="fa-search-wrapper fa-search-form fa-controls-section">';
// NOTE: We added an ID to the form and removed the action attribute
echo '<form role="search" method="get" id="fa-search-form">';
echo '<input type="hidden" id="fa-search-league-id" name="show_league" value="' . esc_attr($selected_league_id) . '" />';
echo '<label for="fa-search-input-' . esc_attr($selected_league_id) . '" class="screen-reader-text">Search:</label><input type="search" id="fa-search-input-' . esc_attr($selected_league_id) . '" name="fa_search" value="' . esc_attr($search_term) . '" placeholder="Search name..." />';
echo '<input type="submit" value="Search" class="button" />';
if (!empty($search_term)) {
    $clear_search_url = remove_query_arg(array('fa_search', 'fa_page'), add_query_arg('show_league', $selected_league_id, get_permalink()));
    echo ' <a href="' . esc_url($clear_search_url) . '" class="button button-secondary">Clear</a>';
}
echo '<span id="fa-search-loading" style="display: none; margin-left: 10px;">Searching...</span>';
echo '</form></div>';

    // Pending bids table
    $pending_args = array(
        'post_type'      => 'player',
        'posts_per_page' => -1,
        'meta_query'     => array(
            'relation' => 'AND',
            array( 'key' => 'league_id', 'value' => $selected_league_id ),
            array( 'key' => 'fa_status', 'value' => 'pending_bid', 'compare' => '=' ),
        ),
        'orderby'        => 'bid_end_time',
        'order'          => 'ASC',
    );
    $pending_q = new WP_Query($pending_args);
    echo "<h2>Players with Pending Bids (" . esc_html($selected_league_id) . ")</h2>";
    if ( $pending_q->have_posts() ) {
        echo '<table class="fantasy-table-base">';
        echo '<thead><tr><th>Name</th><th>Highest Bid</th><th>Highest Bidder</th><th>Bid Ends</th><th>Action</th></tr></thead><tbody>';
        while ( $pending_q->have_posts() ) { $pending_q->the_post();
            $player_id = get_the_ID();
            $highest_bid_amount = get_field('pending_bid_amount', $player_id);
            $highest_bid_team_id = get_field('pending_bid_team_id', $player_id);
            $bid_end_time_raw = get_field('bid_end_time', $player_id);
            $bid_end_timestamp = $bid_end_time_raw ? strtotime($bid_end_time_raw) : 0;
            $bid_end_display   = $bid_end_timestamp ? date('M j, Y H:i T', $bid_end_timestamp) : '—';
            $time_remaining    = $bid_end_timestamp ? $bid_end_timestamp - current_time('timestamp', true) : 0;

            $bid_text_display = is_numeric($highest_bid_amount) ? '$' . number_format((float)$highest_bid_amount, 0) : '-';
            $highest_bidder_display = esc_html($highest_bid_team_id ?: 'N/A');

            echo '<tr>';
            echo '<td>' . esc_html(get_the_title()) . '</td>';
            echo '<td>' . $bid_text_display . '</td>';
            echo '<td>' . $highest_bidder_display . '</td>';
            echo '<td>' . esc_html($bid_end_display);
            if ($time_remaining > 0) {
                 echo ' (' . human_time_diff(current_time('timestamp', true), $bid_end_timestamp) . ' remaining)';
            } else {
                 echo ' (Expired)';
            }
            echo '</td>';
            echo '<td class="fa-action-cell">';
            if ($manager_team_id_for_league && $manager_team_id_for_league !== $highest_bid_team_id && $time_remaining > 0) {
                echo '<button type="button" class="button fa-offer-button" '
                    .'data-playerid="' . esc_attr($player_id) . '" '
                    .'data-playername="' . esc_attr(get_the_title($player_id)) . '" '
                    .'data-leagueid="' . esc_attr($selected_league_id) . '" '
                    .'data-teamid="' . esc_attr($manager_team_id_for_league) . '" '
                    .'data-currentbid="' . esc_attr($highest_bid_amount) . '">Place Higher Bid</button>';
            } elseif ($manager_team_id_for_league && $manager_team_id_for_league === $highest_bid_team_id) {
                echo '<span>Your Bid is Highest</span>';
            } elseif ($time_remaining <= 0) {
                echo '<span>Bid Period Ended</span>';
            } else {
                echo 'N/A';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No players currently with pending bids in this league.</p>';
    }
    wp_reset_postdata();

    // Available FAs
    echo "<h2>Available Free Agents (" . esc_html($selected_league_id) . ")";
    if (!empty($search_term)) { echo " matching &quot;" . esc_html($search_term) . "&quot;"; }
    echo "</h2>";

    $args = array(
        'post_type'      => 'playerdata',
        'posts_per_page' => $posts_per_page,
        'paged'          => $current_fa_page,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => array(
            'relation' => 'AND',
            array( 'key' => 'league_id', 'value' => $selected_league_id ),
            array( 'key' => 'fa_status', 'value' => 'available', 'compare' => '=' ),
        ),
        's'              => $search_term,
        'meta_key'       => 'league_id',
        'meta_compare'   => 'EXISTS',
    );
    $fa_q = new WP_Query($args);

    // Replace it with this block
echo '<div id="fa-table-container">'; // Added a wrapper
if ( $fa_q->have_posts() ) {
    echo '<table class="fantasy-table-base">';
    echo '<thead><tr><th>Name</th><th>Position</th><th>MLB Team</th>';
    foreach($years_to_display_in_table as $year) { echo '<th>'.esc_html($year).'</th>'; }
    echo '<th>Action</th></tr></thead><tbody id="fa-list-tbody">'; // Added ID here
    while ( $fa_q->have_posts() ) { $fa_q->the_post();
        // ... the rest of the while loop code is the same ...
        $player_id = get_the_ID(); $player_name = get_the_title();
        echo '<tr>';
        echo '<td>' . esc_html($player_name) . '</td>';
        echo '<td>' . esc_html(get_field('position', $player_id) ?: 'N/A') . '</td>';
        echo '<td>' . esc_html(get_field('mlb_team', $player_id) ?: 'N/A') . '</td>';
        foreach($years_to_display_in_table as $year) {
            echo '<td>–</td>';
        }
        echo '<td class="fa-action-cell">';
        if ($manager_team_id_for_league) {
            echo '<button type="button" class="button fa-offer-button" '
                .'data-playerid="' . esc_attr($player_id) . '" '
                .'data-playername="' . esc_attr($player_name) . '" '
                .'data-leagueid="' . esc_attr($selected_league_id) . '" '
                .'data-teamid="' . esc_attr($manager_team_id_for_league) . '">Offer Contract</button>';
        } else { echo 'N/A (No team in this league)'; }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
} else {
    if (!empty($search_term)) { echo '<p>No free agents found matching your search...</p>'; }
    else { echo '<p>No free agents currently available in this league.</p>'; }
}
echo '</div>'; // End wrapper

// Pagination
echo '<div id="fa-pagination-container">'; // Added a wrapper with ID
if ($fa_q->max_num_pages > 1) {
    echo '<div class="fa-pagination-wrapper pagination-links fa-pagination">';
    $pagination_base_url_fa = add_query_arg( 'show_league', $selected_league_id, $current_page_url_base_fa );
    if (!empty($search_term)) { $pagination_base_url_fa = add_query_arg( 'fa_search', $search_term, $pagination_base_url_fa ); }
    echo paginate_links(array(
        'base'      => esc_url( add_query_arg( 'fa_page', '%#%', $pagination_base_url_fa ) ),
        'format'    => '',
        'total'     => $fa_q->max_num_pages,
        'current'   => $current_fa_page,
        'prev_next' => true,
        'prev_text' => __('« Prev'),
        'next_text' => __('Next »'),
        'type'      => 'plain',
    ));
    echo '</div>';
}
echo '</div>'; // End wrapper
wp_reset_postdata();

    // Modal markup
    ?>
    <div id="fa-offer-modal" class="fa-modal-hidden">
        <div class="fa-modal-content">
            <span class="fa-modal-close"></span>
            <h3 id="fa-modal-title">Offer Contract</h3>
            <div id="fa-modal-message"></div>
            <form id="fa-offer-form" method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="sign_free_agent">
                <input type="hidden" id="fa-modal-playerid" name="player_id" value="">
                <input type="hidden" id="fa-modal-leagueid" name="league_id" value="">
                <input type="hidden" id="fa-modal-teamid"   name="team_id"   value="">
                <input type="hidden" id="fa-modal-nonce"    name="_wpnonce_sign_fa" value="">
                <p>Enter salary amounts for the desired contract years. Leave years blank if not offering.</p>
                <div class="fa-modal-salary-inputs">
                <?php foreach ($contract_input_years as $year_modal): ?>
                    <div class="fa-modal-year-input">
                        <label for="contract_offer_<?php echo esc_attr($year_modal); ?>"><?php echo esc_html($year_modal); ?>:</label>
                        $ <input type="number" name="contract_offers[<?php echo esc_attr($year_modal); ?>]" id="contract_offer_<?php echo esc_attr($year_modal); ?>]" min="0" step="1" placeholder="Amount">
                    </div>
                <?php endforeach; ?>
                </div>
                <hr>
                <button type="submit" class="button button-primary">Submit Offer</button>
                <button type="button" class="button button-secondary fa-modal-cancel">Cancel</button>
            </form>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'free_agent_list', 'display_free_agent_list_shortcode' );

/* ------------------------------------------------------------------------
   [waiver_wire_list] — Shows players currently on waivers with full contract
------------------------------------------------------------------------ */
function display_waiver_wire_shortcode() {
    if ( ! is_user_logged_in() ) { return '<p>Please log in to view the waiver wire.</p>'; }

    $user_id = get_current_user_id();
    $user_teams = get_field('managed_teams', 'user_' . $user_id);
    $user_team_ids = is_array($user_teams) ? wp_list_pluck($user_teams, 'fantasy_team_id') : [];

    $waiver_args = array(
        'post_type'      => 'playerdata',
        'posts_per_page' => 100,
        'meta_query'     => array( array('key' => 'fa_status', 'value' => 'on_waivers') ),
        'orderby'        => 'meta_value',
        'meta_key'       => 'waiver_end_time',
        'order'          => 'ASC'
    );
    $waiver_query = new WP_Query($waiver_args);

    ob_start();

    echo '<h2>Waiver Wire</h2>';
    echo '<div id="waiver-notices-container"></div>';

    if ( $waiver_query->have_posts() ) {
        $years_to_display = range( (int)date('Y'), (int)date('Y') + 10 );
        // UPDATED: Removed 'Waiving Team' from the header
        $table_header = '<thead><tr><th>Player</th><th>Position</th><th>Time Remaining</th>';
        foreach ($years_to_display as $year) {
            $table_header .= '<th>' . esc_html($year) . '</th>';
        }
        $table_header .= '<th>Action</th></tr></thead>';

        echo '<table class="fantasy-table-base">' . $table_header . '<tbody>';

        while( $waiver_query->have_posts() ) {
            $waiver_query->the_post();
            $player_id = get_the_ID();
            $waiving_team = get_field('waiving_team_id', $player_id);
            $end_time = get_field('waiver_end_time', $player_id);
            $time_remaining = strtotime($end_time) - current_time('timestamp');

            echo '<tr>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . esc_html(get_field('position', $player_id) ?: 'N/A') . '</td>';
            // UPDATED: Removed the 'Waiving Team' data cell
            echo '<td>' . ($time_remaining > 0 ? human_time_diff(current_time('timestamp'), strtotime($end_time)) : 'Expired') . '</td>';

            foreach ($years_to_display as $yr) {
                $v = get_field('contract_' . $yr, $player_id);
                $cell = (is_numeric($v)) ? '$' . number_format((float)$v, 0) : (($v === '' || $v === null) ? '–' : esc_html($v));
                echo '<td>' . $cell . '</td>';
            }

            echo '<td>';
            if ( !in_array($waiving_team, $user_team_ids, true) ) {
                echo '<button type="button" class="button claim-player-button" data-playerid="' . esc_attr($player_id) . '">Claim Player</button>';
            } else {
                echo 'Your Waived Player';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>There are currently no players on the waiver wire.</p>';
    }
    wp_reset_postdata();

    return ob_get_clean();
}

add_shortcode('waiver_wire_list', 'display_waiver_wire_shortcode');

/* ------------------------------------------------------------------------
   [league_activity_feed] — Shows recent league transactions
------------------------------------------------------------------------ */
function display_league_activity_feed_shortcode() {
    $trade_args = array(
        'post_type'      => 'trade_proposal',
        'posts_per_page' => 10, // Show the 10 most recent
        'meta_key'       => 'trade_status',
        'meta_value'     => 'accepted', // Only show completed trades,
        'date_query'        => array(
            array(
                'after'     => '48 hours ago',
                'inclusive' => true,
            ),
        ),
    );
    $trade_query = new WP_Query($trade_args);

    ob_start();
    echo '<h3>Recent League Activity</h3>';

    if( $trade_query->have_posts() ) {
        echo '<ul class="activity-feed">';
        while( $trade_query->have_posts() ) {
            $trade_query->the_post();
            $proposer_id = get_field('proposing_manager');
            $target_id = get_field('target_manager');
            $proposer = get_userdata($proposer_id);
            $target = get_userdata($target_id);

            if ($proposer && $target) {
                echo '<li>';
                echo '<span class="activity-date">' . get_the_date('M j') . '</span>';
                echo '<span class="activity-text">A trade was completed between <strong>' . esc_html($proposer->display_name) . '</strong> and <strong>' . esc_html($target->display_name) . '</strong>.</span>';
                echo '</li>';
            }
        }
        echo '</ul>';
    } else {
        echo '<p>No recent transactions to report.</p>';
    }
    wp_reset_postdata();

    return ob_get_clean();
}
add_shortcode('league_activity_feed', 'display_league_activity_feed_shortcode');

/* ------------------------------------------------------------------------
   [waiver_wire_spotlight] — Shows top 5 players on waivers
------------------------------------------------------------------------ */
function display_waiver_wire_spotlight_shortcode() {
    $waiver_args = array(
        'post_type'      => array('player', 'playerdata'),
        'posts_per_page' => 5, // Only show the top 5
        'meta_query'     => array(
            array(
                'key'   => 'fa_status',
                'value' => 'on_waivers'
            )
        ),
        'orderby'        => 'meta_value',
        'meta_key'       => 'waiver_end_time',
        'order'          => 'ASC'
    );
    $waiver_query = new WP_Query($waiver_args);

    ob_start();
    echo '<h3>Waiver Wire Spotlight</h3>';
    if($waiver_query->have_posts()){
        echo '<ul>';
        while($waiver_query->have_posts()){
            $waiver_query->the_post();
            $end_time = get_field('waiver_end_time');
            $time_remaining = strtotime($end_time) - current_time('timestamp');
            echo '<li><strong>' . get_the_title() . '</strong><br><small>Time remaining: ' . ($time_remaining > 0 ? human_time_diff(current_time('timestamp'), strtotime($end_time)) : 'Expired') . '</small></li>';
        }
        echo '</ul>';
        // Optional: Link to the full waiver wire page
        $waiver_page_url = 'https://frontofficedynastysports.com/?page_id=26409'; // Replace with your waiver page URL
        echo '<a href="' . esc_url($waiver_page_url) . '">View Full Waiver Wire</a>';
    } else {
        echo '<p>No players on waivers.</p>';
    }
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('waiver_wire_spotlight', 'display_waiver_wire_spotlight_shortcode');

/* ------------------------------------------------------------------------
   [league_members_only] — Restricts content to logged-in users.
------------------------------------------------------------------------ */
function league_members_only_shortcode( $atts, $content = null ) {
    if ( is_user_logged_in() && ! is_null( $content ) && ! is_feed() ) {
        // If the user is logged in, show the content inside the shortcode.
        return do_shortcode( $content );
    } else {
        // If the user is logged out, show a welcome message and the login form.
        $login_message = '<h2>Welcome to Front Office Dynasty Sports</h2>';
        $login_message .= '<p>This area is for league members only. Please log in to view the League Office.</p>';

        // Use a buffer to capture the wp_login_form() output
        ob_start();
        wp_login_form();
        $login_form = ob_get_clean();

        return $login_message . $login_form;
    }
}
add_shortcode( 'league_members_only', 'league_members_only_shortcode' );

/* ------------------------------------------------------------------------
   [waiver_wire_list] — Shows players currently on waivers
------------------------------------------------------------------------ */
