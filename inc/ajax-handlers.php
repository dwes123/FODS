<?php
/**
 * All AJAX and form submission handlers.
 * Version: 2.1 (DFA Fix)
 */

/**
 * Calculates bid points from years and AAV.
 * @return float
 */
function fod_calculate_bid_points($years, $aav) {
    $multipliers = [ 1 => 2.0, 2 => 1.8, 3 => 1.6, 4 => 1.4, 5 => 1.2, 6 => 1.0, 7 => 0.8, 8 => 0.6 ];
    $multiplier = $multipliers[ (int)$years ] ?? 0;
    if ($multiplier === 0) {
        return 0.0;
    }
    $total_value = (int)$years * (float)$aav;
    return ($total_value * $multiplier) / 1000000;
}

/**
 * Checks if a given user is the manager of the team that owns a specific player.
 */
function is_user_owner_of_player( $user_id, $player_id ) {
    if ( ! $user_id || ! $player_id ) { return false; }
    
    // Get player's current assignment directly from database to avoid cache issues
    $player_team_id = get_post_meta($player_id, 'fantasy_team_id', true);
    $player_league_id = get_post_meta($player_id, 'league_id', true);
    
    if ( empty($player_team_id) ) { return false; }

    // Get manager's assigned teams
    $managed_teams = get_field('managed_teams', 'user_' . $user_id);
    if ( empty($managed_teams) || ! is_array($managed_teams) ) { return false; }

    foreach ( $managed_teams as $team ) {
        if ( !is_array($team) ) continue;
        
        $m_league = $team['league_id'] ?? '';
        $m_team   = $team['fantasy_team_id'] ?? '';

        // Case-insensitive comparison to prevent string mismatch errors
        if ( strtoupper(trim($m_league)) === strtoupper(trim($player_league_id)) && 
             strtoupper(trim($m_team))   === strtoupper(trim($player_team_id)) ) {
            return true;
        }
    }
    
    return false;
}

/**
 * Helper function to get all rostered players for a given manager in a specific league.
 */
function get_rostered_players_for_manager($league_id, $manager_id) {
    if ( empty($league_id) || empty($manager_id) ) { return new WP_Error('missing_params', 'League ID or Manager ID is missing.'); }
    $user_key = 'user_' . $manager_id;
    $managed_teams = get_field('managed_teams', $user_key);
    $team_id = null;
    if ( !empty($managed_teams) && is_array($managed_teams) ) {
        foreach ($managed_teams as $team) {
            if ( is_array($team) && isset($team['league_id']) && $team['league_id'] === $league_id ) {
                $team_id = $team['fantasy_team_id'] ?? null;
                break;
            }
        }
    }
    if ( !$team_id ) { return new WP_Error('no_team_found', 'Could not find a team for this manager in the selected league.'); }
    $args = [ 'post_type' => 'playerdata', 'posts_per_page' => 500, 'no_found_rows'  => true, 'meta_query' => [ 'relation' => 'AND', ['key' => 'league_id', 'value' => $league_id], ['key' => 'fantasy_team_id', 'value' => $team_id] ], 'orderby' => 'title', 'order' => 'ASC' ];
    $query = new WP_Query($args);
    $players = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $player_id = get_the_ID();
            $player_name = get_the_title();
            
            $contract_parts = [];
            $years_to_scan = range(2026, 2040);
            foreach ($years_to_scan as $year) {
                $salary = get_post_meta($player_id, 'contract_' . $year, true);
                if (is_numeric($salary) && $salary > 0) {
                    $formatted_salary = ($salary >= 1000000) ? '$' . round($salary / 1000000, 1) . 'M' : '$' . round($salary / 1000) . 'K';
                    $contract_parts[] = substr($year, -2) . ': ' . $formatted_salary;
                } elseif (!empty($salary)) {
                    $contract_parts[] = substr($year, -2) . ': ' . esc_html($salary);
                }
            }
            $contract_display = !empty($contract_parts) ? ' (' . implode(', ', $contract_parts) . ')' : '';

            $salaries = [];
            foreach ([2026, 2027, 2028] as $y) {
                $salaries[$y] = get_post_meta($player_id, 'contract_' . $y, true);
            }

            $players[] = [
                'id' => $player_id, 
                'name' => $player_name . $contract_display,
                'salaries' => $salaries
            ];
        }
    }
    wp_reset_postdata();
    return $players;
}

/* ------------------------------------------------------------------------
   Trade Form Handlers (admin-post, not AJAX)
------------------------------------------------------------------------ */
function handle_trade_proposal_submission() {
    $redirect_url = wp_get_referer() ?: home_url();
    if ( ! isset( $_POST['trade_proposal_nonce_field'] ) || ! wp_verify_nonce( $_POST['trade_proposal_nonce_field'], 'process_trade_proposal_nonce' ) ) { wp_redirect( add_query_arg('trade_error', 'security_check_failed', $redirect_url) ); exit; }
    if ( ! is_user_logged_in() ) { wp_redirect( add_query_arg('trade_error', 'not_logged_in', $redirect_url) ); exit; }
    if ( ! function_exists('update_field') ) { error_log("ACF function error in process_trade_proposal: update_field not found."); wp_redirect( add_query_arg('trade_error', 'acf_missing', $redirect_url) ); exit; }
    
    $selected_league      = isset($_POST['trade_league']) ? sanitize_text_field( wp_unslash($_POST['trade_league']) ) : '';
    
    if ( ! fod_can_trade($selected_league) ) {
        wp_redirect( add_query_arg('trade_error', 'deadline_passed', $redirect_url) );
        exit;
    }

    $proposing_manager_id = get_current_user_id();
    $target_manager_id    = isset($_POST['target_manager']) ? absint($_POST['target_manager']) : 0;
    $offered_player_ids   = isset($_POST['players_offered'])   ? array_map('absint', (array)$_POST['players_offered'])   : array();
    $requested_player_ids = isset($_POST['players_requested']) ? array_map('absint', (array)$_POST['players_requested']) : array();
    $isbp_offered         = isset($_POST['isbp_offered']) ? absint($_POST['isbp_offered']) : 0;
    $isbp_requested       = isset($_POST['isbp_requested']) ? absint($_POST['isbp_requested']) : 0;
    $trade_comments       = isset($_POST['trade_comments']) ? sanitize_textarea_field(wp_unslash($_POST['trade_comments'])) : '';
    $retained_ids_str     = isset($_POST['retained_player_ids']) ? sanitize_text_field(wp_unslash($_POST['retained_player_ids'])) : '';

    $offered_player_ids   = array_filter($offered_player_ids);
    $requested_player_ids = array_filter($requested_player_ids);

    if ( empty($target_manager_id) || empty($selected_league) ) { wp_redirect(add_query_arg( 'trade_error', 'missing_fields', $redirect_url )); exit; }
    
    $has_assets = (!empty($offered_player_ids) || $isbp_offered > 0) && (!empty($requested_player_ids) || $isbp_requested > 0);
    if ( !$has_assets ) { wp_redirect(add_query_arg( 'trade_error', 'no_players', $redirect_url )); exit; }
    
    if ( $target_manager_id === $proposing_manager_id ) { wp_redirect(add_query_arg( 'trade_error', 'self_trade', $redirect_url )); exit; }

    $post_data = array('post_type' => 'trade_proposal', 'post_title'  => 'Trade Offer: User ' . $proposing_manager_id . ' to User ' . $target_manager_id . ' (' . $selected_league . ') - ' . date('Y-m-d H:i'), 'post_status' => 'publish', 'post_author' => $proposing_manager_id);
    $new_post_id = wp_insert_post($post_data, true);

    if ( ! is_wp_error($new_post_id) && $new_post_id > 0 ) {
        update_field('proposing_manager', $proposing_manager_id, $new_post_id);
        update_field('target_manager', $target_manager_id, $new_post_id);
        update_field('league_id', $selected_league, $new_post_id);
        update_field('players_offered', $offered_player_ids, $new_post_id);
        update_field('players_requested', $requested_player_ids, $new_post_id);
        update_field('isbp_offered', $isbp_offered, $new_post_id);
        update_field('isbp_requested', $isbp_requested, $new_post_id);
        update_field('trade_comments', $trade_comments, $new_post_id);
        update_field('retained_salary_players', $retained_ids_str, $new_post_id); // Save retention data
        update_field('trade_status', 'pending', $new_post_id);

        $target_user_info = get_userdata($target_manager_id);
        if ($target_user_info) {
            $to = $target_user_info->user_email;
            $proposer_info = get_userdata($proposing_manager_id);
            $proposer_name = $proposer_info ? $proposer_info->display_name : 'A manager';
            $subject = 'New Trade Proposal Received!';
            
            $offered_list   = !empty($offered_player_ids) ? implode(', ', array_map('get_the_title', $offered_player_ids)) : 'No players';
            if ($isbp_offered > 0) $offered_list .= " + $" . number_format($isbp_offered) . " ISBP";
            
            $requested_list = !empty($requested_player_ids) ? implode(', ', array_map('get_the_title', $requested_player_ids)) : 'No players';
            if ($isbp_requested > 0) $requested_list .= " + $" . number_format($isbp_requested) . " ISBP";
            
            // retention notice in email
            $retention_note = "";
            if (!empty($retained_ids_str)) {
                $r_ids = explode(',', $retained_ids_str);
                $r_names = array_map('get_the_title', $r_ids);
                $retention_note = "\n(Includes 50% Salary Retention for: " . implode(', ', $r_names) . ")";
            }

            $message  = "Hello " . esc_html($target_user_info->display_name) . ",\n\n";
            $message .= esc_html($proposer_name) . " has proposed a trade with you in the " . esc_html($selected_league) . " league.\n\n";
            $message .= "They Offer: " . $offered_list . "\n";
            $message .= "They Request: " . $requested_list . "\n";
            $message .= $retention_note . "\n\n";
            if ( ! empty( $trade_comments ) ) {
                $message .= "Comments: " . esc_html($trade_comments) . "\n\n";
            }
            $view_trades_page_id = get_field('view_pending_trades_page', 'option');
            $view_trades_link = $view_trades_page_id ? get_permalink($view_trades_page_id) : home_url('/');
            $message .= "Please log in to view and respond: " . esc_url($view_trades_link) . "\n\n";
            $message .= "-- " . get_bloginfo('name');
            wp_mail($to, $subject, $message);
        }
        wp_redirect(add_query_arg( 'trade_success', 'true', $redirect_url )); exit;
    } else {
        $error_string = is_wp_error($new_post_id) ? $new_post_id->get_error_message() : 'Unknown error creating post.';
        error_log("Trade Proposal Error: Failed to create post. " . $error_string);
        wp_redirect(add_query_arg( 'trade_error', 'post_creation_failed', $redirect_url )); exit;
    }
}
add_action( 'admin_post_process_trade_proposal', 'handle_trade_proposal_submission' );

function get_managers_for_trade_ajax_handler() {
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); wp_die(); }
    
    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    if ( empty($league_id) ) { wp_send_json_error('League ID missing.'); wp_die(); }

    $current_user_id = get_current_user_id();
    $users = get_users();
    $managers = [];

    foreach ( $users as $user ) {
        if ( $user->ID == $current_user_id ) continue;

        $managed_teams = get_field('managed_teams', 'user_' . $user->ID);
        if ( !empty($managed_teams) && is_array($managed_teams) ) {
            foreach ( $managed_teams as $team ) {
                if ( is_array($team) && isset($team['league_id']) && $team['league_id'] === $league_id ) {
                    $team_id = $team['fantasy_team_id'];
                    $managers[] = [
                        'id' => $user->ID,
                        'name' => $team_id,
                        'isbp_balance' => fod_get_team_isbp_balance($league_id, $team_id)
                    ];
                    break; 
                }
            }
        }
    }
    wp_send_json_success($managers);
    wp_die();
}

function get_my_players_for_trade_ajax_handler() {
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); wp_die(); }

    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    if ( empty($league_id) ) { wp_send_json_error('League ID missing.'); wp_die(); }

    $current_user_id = get_current_user_id();
    
    // Get my team ID for this league
    $my_team_id = '';
    $managed = get_field('managed_teams', 'user_' . $current_user_id);
    if (is_array($managed)) {
        foreach ($managed as $t) {
            if (($t['league_id'] ?? '') === $league_id) { $my_team_id = $t['fantasy_team_id']; break; }
        }
    }

    $players = get_rostered_players_for_manager($league_id, $current_user_id);

    if ( is_wp_error($players) ) {
        wp_send_json_error($players->get_error_message());
    } else {
        wp_send_json_success([
            'players' => $players,
            'isbp_balance' => fod_get_team_isbp_balance($league_id, $my_team_id)
        ]);
    }
    wp_die();
}

function get_target_players_for_trade_ajax_handler() {
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); wp_die(); }

    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    $manager_id = isset($_POST['target_manager_id']) ? absint($_POST['target_manager_id']) : (isset($_POST['manager_id']) ? absint($_POST['manager_id']) : 0);

    if ( empty($league_id) || empty($manager_id) ) { wp_send_json_error('League or Manager ID missing.'); wp_die(); }

    $players = get_rostered_players_for_manager($league_id, $manager_id);

    if ( is_wp_error($players) ) {
        wp_send_json_error($players->get_error_message());
    } else {
        wp_send_json_success($players);
    }
    wp_die();
}
add_action('wp_ajax_get_managers_for_trade', 'get_managers_for_trade_ajax_handler');
add_action('wp_ajax_get_my_players_for_trade', 'get_my_players_for_trade_ajax_handler');
add_action('wp_ajax_get_target_players_for_trade', 'get_target_players_for_trade_ajax_handler');

/**
 * AJAX handler to check if trading is allowed for a league.
 */
function check_league_trade_status_ajax_handler() {
    if ( ! is_user_logged_in() ) { wp_send_json_error('Not logged in.'); wp_die(); }
    
    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    if ( empty($league_id) ) { wp_send_json_error('League ID missing.'); wp_die(); }

    wp_send_json_success([
        'can_trade' => fod_can_trade($league_id)
    ]);
    wp_die();
}
add_action('wp_ajax_check_league_trade_status', 'check_league_trade_status_ajax_handler');

/* ------------------------------------------------------------------------
   Trade Action Handlers (Accept, Reject, Cancel)
------------------------------------------------------------------------ */
function handle_cancel_trade() {
    $redirect_url = wp_get_referer() ?: home_url();
    $trade_id = isset($_GET['trade_id']) ? absint($_GET['trade_id']) : 0;
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

    if ( ! $trade_id || ! wp_verify_nonce($nonce, 'handle_trade_nonce_' . $trade_id) ) {
        wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'security_failed'], $redirect_url) ); exit;
    }
    if ( ! is_user_logged_in() ) {
        wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'not_logged_in'], $redirect_url) ); exit;
    }

    $current_user_id = get_current_user_id();
    $proposer_id = get_field('proposing_manager', $trade_id);
    
    if ( $current_user_id != $proposer_id ) {
        wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'not_authorized'], $redirect_url) ); exit;
    }

    update_post_meta($trade_id, 'trade_status', 'cancelled');
    wp_update_post(['ID' => $trade_id, 'post_status' => 'draft']);

    wp_redirect( add_query_arg('trade_action', 'cancelled', $redirect_url) ); exit;
}

function handle_reject_trade() {
    $redirect_url = wp_get_referer() ?: home_url();
    $trade_id = isset($_GET['trade_id']) ? absint($_GET['trade_id']) : 0;
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

    if ( ! $trade_id || ! wp_verify_nonce($nonce, 'handle_trade_nonce_' . $trade_id) ) {
        wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'security_failed'], $redirect_url) ); exit;
    }
    if ( ! is_user_logged_in() ) {
        wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'not_logged_in'], $redirect_url) ); exit;
    }

    $current_user_id = get_current_user_id();
    $target_id = get_field('target_manager', $trade_id);
    $proposer_id = get_field('proposing_manager', $trade_id);
    
    if ( $current_user_id != $target_id ) {
        wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'not_authorized'], $redirect_url) ); exit;
    }

    update_post_meta($trade_id, 'trade_status', 'rejected');
    wp_update_post(['ID' => $trade_id, 'post_status' => 'draft']);

    // Notify proposing manager
    if ($proposer_id) {
        $proposer_info = get_userdata($proposer_id);
        $rejecter_info = get_userdata($current_user_id);
        if ($proposer_info && $rejecter_info) {
            $to = $proposer_info->user_email;
            $subject = 'Trade Proposal Rejected';
            $message = "Hello " . esc_html($proposer_info->display_name) . ",\n\n";
            $message .= "Your trade proposal to " . esc_html($rejecter_info->display_name) . " has been rejected.\n\n";
            $message .= "-- " . get_bloginfo('name');
            wp_mail($to, $subject, $message);
        }
    }

    wp_redirect( add_query_arg('trade_action', 'rejected', $redirect_url) ); exit;
}

function handle_accept_trade() {
    $redirect_url = wp_get_referer() ?: home_url();
    $trade_id = isset($_GET['trade_id']) ? absint($_GET['trade_id']) : 0;
    $nonce = isset($_GET['_wpnonce']) ? $_GET['_wpnonce'] : '';

    if ( ! $trade_id || ! wp_verify_nonce($nonce, 'handle_trade_nonce_' . $trade_id) ) {
        wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'security_failed'], $redirect_url) ); exit;
    }
    if ( ! is_user_logged_in() ) {
        wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'not_logged_in'], $redirect_url) ); exit;
    }

    $current_user_id = get_current_user_id();
    $target_id = get_field('target_manager', $trade_id);
    $proposer_id = get_field('proposing_manager', $trade_id);
    $league_id = get_field('league_id', $trade_id);
    
    if ( $current_user_id != $target_id ) {
        wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'not_authorized'], $redirect_url) ); exit;
    }

    $status = get_field('trade_status', $trade_id);
    if ( $status !== 'pending' ) {
        wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'not_pending'], $redirect_url) ); exit;
    }

    $offered_ids = get_field('players_offered', $trade_id) ?: [];
    $requested_ids = get_field('players_requested', $trade_id) ?: [];
    $isbp_offered = (int) get_field('isbp_offered', $trade_id);
    $isbp_requested = (int) get_field('isbp_requested', $trade_id);

    // Helper to get team ID for a user in a league
    $get_team_id = function($uid, $lid) {
        $teams = get_field('managed_teams', 'user_' . $uid);
        if ($teams) {
            foreach($teams as $t) {
                if (($t['league_id']??'') === $lid) return $t['fantasy_team_id'];
            }
        }
        return false;
    };

    $proposer_team = $get_team_id($proposer_id, $league_id);
    $target_team = $get_team_id($target_id, $league_id);

    if ( ! $proposer_team || ! $target_team ) {
        wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'team_id_missing'], $redirect_url) ); exit;
    }

    // Validate ownership
    foreach ($offered_ids as $pid) {
        if ( get_field('fantasy_team_id', $pid) !== $proposer_team ) {
             wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'ownership_mismatch'], $redirect_url) ); exit;
        }
    }
    foreach ($requested_ids as $pid) {
        if ( get_field('fantasy_team_id', $pid) !== $target_team ) {
             wp_redirect( add_query_arg(['trade_action'=>'trade_action_error', 'trade_action_error_detail'=>'ownership_mismatch'], $redirect_url) ); exit;
        }
    }

    // Execute Player Trade
    foreach ($offered_ids as $pid) { update_field('fantasy_team_id', $target_team, $pid); }
    foreach ($requested_ids as $pid) { update_field('fantasy_team_id', $proposer_team, $pid); }

    // --- Process Salary Retention (Mandatory Date-Based + Optional Checkbox) ---
    $retained_str = get_field('retained_salary_players', $trade_id);
    $retained_ids = !empty($retained_str) ? explode(',', $retained_str) : [];
    $all_traded_ids = array_merge($offered_ids, $requested_ids);
    $current_year = date('Y');
    
    // Date-Based Logic
    $today_ymd = date('Ymd');
    $curr_yr = date('Y');
    $opening_day = fod_get_opening_day($league_id, $curr_yr);
    $april_30 = $curr_yr . '0430';
    $may_31   = $curr_yr . '0531';
    $june_1   = $curr_yr . '0601';

    $pro_rate_pct = 0.0; 
    if ( $opening_day && $today_ymd >= $opening_day && $today_ymd <= $april_30 ) {
        $pro_rate_pct = 0.10; 
    } elseif ( $today_ymd >= ($curr_yr . '0501') && $today_ymd <= $may_31 ) {
        $pro_rate_pct = 0.25; 
    } elseif ( $today_ymd >= $june_1 ) {
        $pro_rate_pct = 0.50; 
    }

    foreach ($all_traded_ids as $pid) {
        $pid = (int) $pid;
        if (!$pid) continue;

        // Determine Sender
        $sender_team = '';
        if (in_array($pid, $offered_ids)) {
            $sender_team = $proposer_team;
        } elseif (in_array($pid, $requested_ids)) {
            $sender_team = $target_team;
        }

        if ($sender_team) {
            $raw_salary = get_post_meta($pid, 'contract_' . $current_year, true);
            $salary_val = (float) str_replace([',', '$'], '', $raw_salary);
            
            if ($salary_val > 0) {
                // 1. Mandatory Date-Based Retention
                $base_dead_cap = $salary_val * $pro_rate_pct;
                $remaining_salary = $salary_val - $base_dead_cap;

                // 2. Optional 50% Retention (on remainder)
                $extra_retention = 0.0;
                if (in_array($pid, $retained_ids)) {
                    $extra_retention = $remaining_salary * 0.50;
                }

                $total_dead_cap = $base_dead_cap + $extra_retention;
                $final_receiver_salary = $salary_val - $total_dead_cap;

                // Apply Updates
                if ( $total_dead_cap > 0 ) {
                    // Update Contract
                    update_post_meta($pid, 'contract_' . $current_year, $final_receiver_salary);

                    // Add Dead Cap
                    if ( function_exists('add_row') ) {
                        $note = 'Pro-Rated (' . ($pro_rate_pct*100) . '%)';
                        if ($extra_retention > 0) $note .= ' + 50% Retained';
                        
                        $penalty = [
                            'penalty_year' => $current_year,
                            'penalty_amount' => $total_dead_cap,
                            'dead_cap_team_id' => $sender_team,
                            'penalty_type' => $note
                        ];
                        add_row('dead_cap_penalties', $penalty, $pid);
                    }
                }
            }
        }
    }

    // --- Transfer ISBP Funds (Central Options Table) ---
    $transfer_isbp = function($league, $from_team, $to_team, $amount) {
        if ($amount <= 0 || !$from_team || !$to_team) return;
        
        $field_name = 'isbp_' . strtolower($league);
        $rows = get_field($field_name, 'option') ?: [];
        
        $from_idx = -1; $to_idx = -1;
        foreach ($rows as $idx => $row) {
            if (($row['team_id'] ?? '') === $from_team) $from_idx = $idx;
            if (($row['team_id'] ?? '') === $to_team) $to_idx = $idx;
        }

        // Auto-create rows if missing
        if ($from_idx === -1) {
            $rows[] = ['team_id' => $from_team, 'balance' => 0];
            $from_idx = count($rows) - 1;
        }
        if ($to_idx === -1) {
            $rows[] = ['team_id' => $to_team, 'balance' => 0];
            $to_idx = count($rows) - 1;
        }

        // Update balances
        $rows[$from_idx]['balance'] = (int)$rows[$from_idx]['balance'] - $amount;
        $rows[$to_idx]['balance']   = (int)$rows[$to_idx]['balance'] + $amount;

        update_field($field_name, $rows, 'option');
    };

    if ($isbp_offered > 0) { $transfer_isbp($league_id, $proposer_team, $target_team, $isbp_offered); }
    if ($isbp_requested > 0) { $transfer_isbp($league_id, $target_team, $proposer_team, $isbp_requested); }

    update_post_meta($trade_id, 'trade_status', 'accepted');
    wp_update_post(['ID' => $trade_id, 'post_status' => 'draft']);

    // Log Transaction
    $offered_names = array_map('get_the_title', $offered_ids);
    $requested_names = array_map('get_the_title', $requested_ids);
    
    $summary = "Trade Accepted: $proposer_team sends " . (empty($offered_names) ? 'no players' : implode(', ', $offered_names));
    if ($isbp_offered > 0) $summary .= " + $" . number_format($isbp_offered) . " ISBP";
    
    $summary .= " to $target_team for " . (empty($requested_names) ? 'no players' : implode(', ', $requested_names));
    if ($isbp_requested > 0) $summary .= " + $" . number_format($isbp_requested) . " ISBP";
    $summary .= ".";    do_action('my_fantasy_transaction', [
        'transaction_type' => 'Trade',
        'player_ids'       => array_merge($offered_ids, $requested_ids),
        'primary_team'     => $proposer_team,
        'secondary_team'   => $target_team,
        'summary'          => $summary,
        'league_id'        => $league_id
    ]);

    // Notify proposing manager
    if ($proposer_id) {
        $proposer_info = get_userdata($proposer_id);
        if ($proposer_info) {
            $to = $proposer_info->user_email;
            $subject = 'Trade Proposal Accepted!';
            $message = "Hello " . esc_html($proposer_info->display_name) . ",\n\n";
            $message .= "Your trade proposal with " . esc_html($target_team) . " has been accepted.\n\n";
            $message .= "The transaction has been processed.\n\n";
            $message .= "-- " . get_bloginfo('name');
            wp_mail($to, $subject, $message);
        }
    }

    // --- SLACK NOTIFICATION ---
    if ( function_exists('fod_send_slack_notification') ) {
        $slack_msg = "🚨 *TRADE ALERT!* 🚨\n\n";
        $slack_msg .= "🤝 *To $proposer_team:* " . (empty($requested_names) ? 'No players' : implode(', ', $requested_names));
        if ($isbp_requested > 0) $slack_msg .= " + $" . number_format($isbp_requested) . " ISBP";
        
        $slack_msg .= "\n\n";
        
        $slack_msg .= "🤝 *To $target_team:* " . (empty($offered_names) ? 'No players' : implode(', ', $offered_names));
        if ($isbp_offered > 0) $slack_msg .= " + $" . number_format($isbp_offered) . " ISBP";

        fod_send_slack_notification($slack_msg, $league_id, 'completed_trade');
    }

    wp_redirect( add_query_arg('trade_action', 'accepted', $redirect_url) ); exit;
}

add_action('admin_post_reject_trade', 'handle_reject_trade');
add_action('admin_post_cancel_trade', 'handle_cancel_trade');
add_action('admin_post_accept_trade', 'handle_accept_trade');

/* ------------------------------------------------------------------------
   Free Agent Handlers
------------------------------------------------------------------------ */
function handle_sign_free_agent_action() {
    $redirect_base = wp_get_referer() ?: home_url();
    $league_id_from_post = isset($_POST['league_id']) ? sanitize_text_field(wp_unslash($_POST['league_id'])) : '';
    if ($league_id_from_post) { $redirect_base = add_query_arg('show_league', rawurlencode($league_id_from_post), $redirect_base); }
    $redirect_base = remove_query_arg(array('sign_success', 'sign_error', 'signed_player', 'bid_points', 'status'), $redirect_base);
    
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $nonce = isset($_POST['_wpnonce_sign_fa']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce_sign_fa'])) : '';
    if ( !$player_id || !$nonce || !wp_verify_nonce($nonce, 'sign_fa_nonce_' . $player_id) ) { wp_redirect(add_query_arg('sign_error', 'security_failed', $redirect_base)); exit; }
    if ( ! is_user_logged_in() ) { wp_redirect(add_query_arg('sign_error', 'not_logged_in', $redirect_base)); exit; }
    
    $current_user_id = get_current_user_id();
    $league_id = isset($_POST['league_id']) ? sanitize_text_field(wp_unslash($_POST['league_id'])) : '';
    $team_id   = isset($_POST['team_id'])   ? sanitize_text_field(wp_unslash($_POST['team_id']))   : '';
    $bid_years = isset($_POST['bid_years']) ? absint($_POST['bid_years']) : 0;
    $bid_aav   = isset($_POST['bid_aav'])   ? str_replace(',', '', $_POST['bid_aav']) : 0;

    if ( empty($league_id) || empty($team_id) || empty($player_id) || $bid_years <= 0 || !is_numeric($bid_aav) || $bid_aav < 0 ) { wp_redirect(add_query_arg('sign_error', 'missing_data', $redirect_base)); exit; }
    if ( ! function_exists('get_field') || ! function_exists('update_post_meta') ) { wp_redirect(add_query_arg('sign_error', 'acf_missing', $redirect_base)); exit; }
    
    $is_manager_of_team = false;
    $managed_teams_check = get_field('managed_teams', 'user_' . $current_user_id);
    if (!empty($managed_teams_check) && is_array($managed_teams_check)) { foreach ($managed_teams_check as $t) { if (is_array($t) && ($t['league_id'] ?? '') === $league_id && ($t['fantasy_team_id'] ?? '') === $team_id) { $is_manager_of_team = true; break; } } }
    if (!$is_manager_of_team) { wp_redirect(add_query_arg('sign_error', 'not_manager', $redirect_base)); exit; }

    // --- 40-Man Roster Check ---
    $roster_count_args = [
        'post_type' => 'playerdata',
        'fields' => 'ids',
        'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'AND',
            ['key' => 'league_id', 'value' => $league_id],
            ['key' => 'fantasy_team_id', 'value' => $team_id],
            ['key' => 'status_40_man', 'value' => 'X']
        ]
    ];
    $roster_query = new WP_Query($roster_count_args);
    if ( $roster_query->post_count >= 40 ) {
        wp_redirect(add_query_arg('sign_error', 'roster_full', $redirect_base)); exit;
    }
    // ---------------------------

    $bid_points = fod_calculate_bid_points($bid_years, $bid_aav);
    if ($bid_points <= 0) { wp_redirect(add_query_arg('sign_error', 'invalid_bid', $redirect_base)); exit; }

    $current_fa_status = get_field('fa_status', $player_id);
    $current_bid_points = (float) get_field('pending_bid_amount', $player_id);
    $current_bid_type = get_field('bid_type', $player_id);

    if ($current_fa_status === 'available' || empty($current_fa_status) || ($current_fa_status === 'rostered' && empty(get_field('fantasy_team_id', $player_id)))) {
        // This is the first bid
    } elseif ($current_fa_status === 'pending_bid') {
        // Standard bid supersedes MiLB bid immediately (no price check needed)
        if ($current_bid_type === 'milb') {
            // Allow overwrite
        } elseif ($bid_points < $current_bid_points + 1) {
            wp_redirect(add_query_arg('sign_error', 'bid_too_low', $redirect_base)); exit;
        }
    } else {
        wp_redirect(add_query_arg('sign_error', 'player_signed', $redirect_base)); exit;
    }

    $now_mysql   = current_time('mysql', true);
    
    // --- Determine Bid Duration (24h In-Season, 48h Offseason) ---
    $current_year = date('Y');
    $opening_day = fod_get_opening_day($league_id, $current_year);
    $today_ymd = date('Ymd');
    $end_of_season = $current_year . '1001'; // Oct 1st cutoff
    
    $hours_to_add = 48; // Default Offseason
    if ( $opening_day && $today_ymd >= $opening_day && $today_ymd <= $end_of_season ) {
        $hours_to_add = 24; // In-Season
    }
    
    $bid_end_time = date('Y-m-d H:i:s', strtotime($now_mysql . ' +' . $hours_to_add . ' hours'));

    update_post_meta($player_id, 'fa_status', 'pending_bid');
    update_post_meta($player_id, 'bid_type', 'standard'); // Set type
    update_post_meta($player_id, 'pending_bid_team_id', $team_id);
    update_post_meta($player_id, 'pending_bid_manager_id', $current_user_id);
    update_post_meta($player_id, 'pending_bid_amount', $bid_points); // Store points now
    update_post_meta($player_id, 'pending_bid_years', $bid_years);   // Store years for cron
    update_post_meta($player_id, 'pending_bid_aav', $bid_aav);       // Store AAV for cron
    update_post_meta($player_id, 'bid_start_time', $now_mysql);
    update_post_meta($player_id, 'bid_end_time', $bid_end_time);

    // Add to bid history
    if ( function_exists('add_row') ) {
        $new_history_row = array(
            'history_team_id'    => $team_id,
            'history_bid_amount' => $bid_points,
            'history_bid_years'  => $bid_years,
            'history_bid_aav'    => $bid_aav,
            'history_timestamp'  => $now_mysql
        );
        add_row('bid_history', $new_history_row, $player_id);
    }

    wp_redirect(add_query_arg(['sign_success'=>'true','signed_player'=>$player_id,'bid_points'=>$bid_points,'status'=>'pending_bid'], $redirect_base));
    exit;
}
add_action('admin_post_sign_free_agent', 'handle_sign_free_agent_action');
add_action('wp_ajax_sign_free_agent', 'handle_sign_free_agent_action');

function ajax_get_fa_sign_nonce_handler() {
    check_ajax_referer('get_fa_sign_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error('Not logged in.'); wp_die(); }
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    if (!$player_id) { wp_send_json_error('Player ID missing for nonce generation.'); wp_die(); }
    $nonce_action = 'sign_fa_nonce_' . $player_id;
    $nonce = wp_create_nonce($nonce_action);
    wp_send_json_success(array('nonce' => $nonce));
    wp_die();
}
add_action('wp_ajax_get_fa_sign_nonce', 'ajax_get_fa_sign_nonce_handler');

/**
 * AJAX: Handle Minor League Contract Offer
 */
function handle_sign_milb_free_agent_action() {
    check_ajax_referer('sign_fa_nonce_milb', 'nonce'); // Generic nonce or specific
    if ( ! is_user_logged_in() ) { wp_send_json_error('Not logged in.'); }

    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    $team_id   = isset($_POST['team_id'])   ? sanitize_text_field($_POST['team_id'])   : '';
    $stat_type = isset($_POST['stat_type']) ? sanitize_text_field($_POST['stat_type']) : '';
    $stat_val  = isset($_POST['stat_value']) ? floatval($_POST['stat_value']) : 0;
    $bid_amt   = isset($_POST['bid_amount']) ? floatval($_POST['bid_amount']) : 0;

    if ( !$player_id || !$team_id || !$bid_amt ) { wp_send_json_error('Missing required fields.'); }

    // 1. Verify Stats Eligibility
    if ( $stat_type === 'IP' ) {
        if ( $stat_val > 30 ) wp_send_json_error('Player ineligible: IP must be <= 30.');
    } elseif ( $stat_type === 'AB' ) {
        if ( $stat_val > 150 ) wp_send_json_error('Player ineligible: AB must be <= 150.');
    } else {
        wp_send_json_error('Invalid stat type.');
    }

    // 2. Verify Balance
    $milb_bal = fod_get_team_milb_balance($league_id, $team_id);
    if ( $bid_amt > $milb_bal ) {
        wp_send_json_error('Insufficient MiLB Balance. You have $' . number_format($milb_bal));
    }

    // 3. Process Bid
    $current_fa_status = get_field('fa_status', $player_id);
    if ($current_fa_status === 'pending_bid') {
        wp_send_json_error('Player already has a pending bid. You cannot offer a MiLB contract while a standard auction is active.');
    } elseif ($current_fa_status === 'rostered' && !empty(get_field('fantasy_team_id', $player_id))) {
        wp_send_json_error('Player is already rostered.');
    }

    // Start Clock
    $now_mysql = current_time('mysql', true);
    $current_year = date('Y');
    $opening_day = fod_get_opening_day($league_id, $current_year);
    $today_ymd = date('Ymd');
    $end_of_season = $current_year . '1001';
    
    $hours_to_add = 48; 
    if ( $opening_day && $today_ymd >= $opening_day && $today_ymd <= $end_of_season ) {
        $hours_to_add = 24;
    }
    $bid_end_time = date('Y-m-d H:i:s', strtotime($now_mysql . ' +' . $hours_to_add . ' hours'));

    update_post_meta($player_id, 'fa_status', 'pending_bid');
    update_post_meta($player_id, 'bid_type', 'milb'); // Set Type
    update_post_meta($player_id, 'pending_bid_team_id', $team_id);
    update_post_meta($player_id, 'pending_bid_manager_id', get_current_user_id());
    update_post_meta($player_id, 'pending_bid_amount', $bid_amt);
    update_post_meta($player_id, 'milb_qualifying_stat', "$stat_val $stat_type");
    update_post_meta($player_id, 'bid_start_time', $now_mysql);
    update_post_meta($player_id, 'bid_end_time', $bid_end_time);

    // Deduct Balance Immediately (Hold funds) - Or deduct on finalize?
    // Usually deduct on finalize. For now, we just validate.

    wp_send_json_success('MiLB Offer Submitted! Pending for ' . $hours_to_add . ' hours.');
}
add_action('wp_ajax_sign_milb_free_agent', 'handle_sign_milb_free_agent_action');

/**
 * AJAX: Handle International Free Agent (ISBP) Bid
 */
function handle_sign_isbp_free_agent_action() {
    check_ajax_referer('sign_fa_nonce_milb', 'nonce'); // Reusing generic/MiLB nonce for simplicity
    if ( ! is_user_logged_in() ) { wp_send_json_error('Not logged in.'); }

    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    $team_id   = isset($_POST['team_id'])   ? sanitize_text_field($_POST['team_id'])   : '';
    $bid_amt   = isset($_POST['bid_amount']) ? floatval($_POST['bid_amount']) : 0;

    if ( !$player_id || !$team_id || !$bid_amt ) { wp_send_json_error('Missing required fields.'); }

    // 1. Verify Player Type
    if ( ! get_field('is_international_free_agent', $player_id) ) {
        wp_send_json_error('This player is not an International Free Agent.');
    }

    // 2. Verify Balance (including committed pending bids)
    $isbp_bal = fod_get_team_isbp_balance($league_id, $team_id);
    
    // Calculate committed funds on OTHER players
    $committed_args = [
        'post_type'      => ['playerdata', 'nbaplayer'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'post__not_in'   => [$player_id], // Exclude current player
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'league_id', 'value' => $league_id],
            ['key' => 'fa_status', 'value' => 'pending_bid'],
            ['key' => 'pending_bid_team_id', 'value' => $team_id],
            ['key' => 'bid_type', 'value' => 'isbp']
        ]
    ];
    $committed_query = new WP_Query($committed_args);
    $committed_total = 0;
    foreach ($committed_query->posts as $p_id) {
        $committed_total += (float) get_post_meta($p_id, 'pending_bid_amount', true);
    }

    $available_bal = $isbp_bal - $committed_total;

    if ( $bid_amt > $available_bal ) {
        wp_send_json_error('Insufficient ISBP Funds. Balance: $' . number_format($isbp_bal) . '. Pending Bids: $' . number_format($committed_total) . '. Available: $' . number_format($available_bal));
    }

    // 3. Process Bid (Auction Logic)
    $current_fa_status = get_field('fa_status', $player_id);
    $current_bid_amt   = (float) get_field('pending_bid_amount', $player_id);
    
    if ($current_fa_status === 'pending_bid') {
        // Rule: Must beat previous bid by DOUBLE or +$100k, whichever is LESS.
        $double_bid = $current_bid_amt * 2;
        $plus_100k  = $current_bid_amt + 100000;
        
        $min_required_bid = min($double_bid, $plus_100k);

        // Exception: If bidding full available balance, only need to beat by $1.
        $is_all_in = ($bid_amt >= $available_bal);

        if ($is_all_in) {
            if ($bid_amt <= $current_bid_amt) {
                wp_send_json_error('Bid too low. Your "All-In" bid of $' . number_format($bid_amt) . ' must still beat the current bid of $' . number_format($current_bid_amt) . '.');
            }
            // Otherwise, it's allowed!
        } elseif ($bid_amt < $min_required_bid) {
            wp_send_json_error('Bid too low. Minimum bid is $' . number_format($min_required_bid) . ' (Double or +$100k rule). To bid less, you must go All-In with your remaining $' . number_format($available_bal) . '.');
        }
    } elseif ($current_fa_status === 'rostered' && !empty(get_field('fantasy_team_id', $player_id))) {
        wp_send_json_error('Player is already rostered.');
    }

    // Start Clock
    $now_mysql = current_time('mysql', true);
    $current_year = date('Y');
    $opening_day = fod_get_opening_day($league_id, $current_year);
    $today_ymd = date('Ymd');
    $end_of_season = $current_year . '1001';
    
    $hours_to_add = 48; 
    if ( $opening_day && $today_ymd >= $opening_day && $today_ymd <= $end_of_season ) {
        $hours_to_add = 24;
    }
    $bid_end_time = date('Y-m-d H:i:s', strtotime($now_mysql . ' +' . $hours_to_add . ' hours'));

    update_post_meta($player_id, 'fa_status', 'pending_bid');
    update_post_meta($player_id, 'bid_type', 'isbp'); // Set Type
    update_post_meta($player_id, 'pending_bid_team_id', $team_id);
    update_post_meta($player_id, 'pending_bid_manager_id', get_current_user_id());
    update_post_meta($player_id, 'pending_bid_amount', $bid_amt);
    update_post_meta($player_id, 'bid_start_time', $now_mysql);
    update_post_meta($player_id, 'bid_end_time', $bid_end_time);

    // Add to history
    if ( function_exists('add_row') ) {
        $new_history_row = array(
            'history_team_id'    => $team_id,
            'history_bid_amount' => $bid_amt, // Cash amount
            'history_bid_years'  => 0,        // N/A for ISBP
            'history_bid_aav'    => 0,
            'history_timestamp'  => $now_mysql
        );
        add_row('bid_history', $new_history_row, $player_id);
    }

    wp_send_json_success('ISBP Bid Submitted! Pending for ' . $hours_to_add . ' hours.');
}
add_action('wp_ajax_sign_isbp_free_agent', 'handle_sign_isbp_free_agent_action');

/**
 * AJAX: Get Team Financials
 */
function get_team_financials_ajax_handler() {
    if ( !is_user_logged_in() ) wp_send_json_error();
    $league = $_POST['league_id'] ?? '';
    $team = $_POST['team_id'] ?? '';
    wp_send_json_success([
        'isbp' => fod_get_team_isbp_balance($league, $team),
        'milb' => fod_get_team_milb_balance($league, $team)
    ]);
}
add_action('wp_ajax_get_team_financials', 'get_team_financials_ajax_handler');

function dfa_player_ajax_handler() {
    check_ajax_referer('dfa_player_nonce', 'nonce');
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); wp_die(); }
    
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $dfa_action = isset($_POST['dfa_action']) ? sanitize_text_field($_POST['dfa_action']) : '';
    $league_id = get_post_meta($player_id, 'league_id', true);
    $team_id = get_post_meta($player_id, 'fantasy_team_id', true);

    if ( !$player_id || !$dfa_action || !$league_id || !$team_id ) { wp_send_json_error('Missing required data.'); wp_die(); }
    if ( !is_user_owner_of_player( get_current_user_id(), $player_id ) ) { wp_send_json_error('You do not have permission to manage this player.'); wp_die(); }

    // Set waiver status and end time
    update_post_meta($player_id, 'fa_status', 'on waivers');
    update_post_meta($player_id, 'waiver_end_time', date('Y-m-d H:i:s', strtotime('+48 hours')));
    update_post_meta($player_id, 'waiving_team_id', $team_id);
    update_post_meta($player_id, 'dfa_clear_action', $dfa_action);

    // Remove from 26-man and 40-man rosters
    update_post_meta($player_id, 'status_26_man', '0');
    update_post_meta($player_id, 'status_40_man', '');

    // Log Transaction
    $player_name = get_the_title($player_id);
    $summary = "$team_id has designated $player_name for assignment.";
    do_action('my_fantasy_transaction', [
        'transaction_type' => 'DFA',
        'player_ids'       => [$player_id],
        'primary_team'     => $team_id,
        'summary'          => $summary,
        'league_id'        => $league_id
    ]);

    wp_send_json_success(['message' => 'Player has been placed on waivers for 48 hours.']);
    wp_die();
}
add_action('wp_ajax_dfa_player', 'dfa_player_ajax_handler');

function claim_player_ajax_handler() {
    check_ajax_referer('claim_player_nonce', 'nonce');
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); wp_die(); }

    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $team_id = isset($_POST['team_id']) ? sanitize_text_field($_POST['team_id']) : '';
    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';

    if ( !$player_id || !$team_id || !$league_id ) { wp_send_json_error('Missing required data.'); wp_die(); }

    // Add the new claim to the 'pending_waiver_claims' repeater field
    $new_claim = [
        'claiming_team_id' => $team_id,
        'claim_timestamp' => current_time('mysql'),
    ];
    add_row('pending_waiver_claims', $new_claim, $player_id);

    wp_send_json_success(['message' => 'Waiver claim submitted successfully.']);
    wp_die();
}
add_action('wp_ajax_claim_player', 'claim_player_ajax_handler');

function promote_to_40man_ajax_handler() {
    check_ajax_referer('promote_to_40man_nonce', 'nonce');
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); wp_die(); }
    
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    $team_id = isset($_POST['team_id']) ? sanitize_text_field($_POST['team_id']) : '';

    if ( !$player_id || !$league_id || !$team_id ) { wp_send_json_error('Missing required data.'); wp_die(); }
    if ( !is_user_owner_of_player( get_current_user_id(), $player_id ) ) { wp_send_json_error('You do not have permission to manage this player.'); wp_die(); }

    update_post_meta($player_id, 'status_40_man', 'X');

    // --- Auto-Apply Rookie Contract if Missing ---
    // Rule: If promoted to 40-man for the first time (empty contract), apply 760k -> TC -> TC -> ARB -> ARB -> ARB -> UFA
    $current_month = (int)date('n');
    $start_year = (int)date('Y');
    // If it's Nov or Dec, we assume this is a roster move for the UPCOMING season (e.g. Rule 5 protection)
    if ($current_month >= 11) {
        $start_year++;
    }

    // Check if contract exists for start year
    $existing_contract = get_post_meta($player_id, 'contract_' . $start_year, true);
    
    if ( empty($existing_contract) ) {
        $structure = [
            0 => '760000',
            1 => 'TC',
            2 => 'TC',
            3 => 'ARB 1',
            4 => 'ARB 2',
            5 => 'ARB 3',
            6 => 'UFA'
        ];

        foreach ($structure as $offset => $val) {
            update_post_meta($player_id, 'contract_' . ($start_year + $offset), $val);
        }
    }
    // ---------------------------------------------

    // Log Transaction
    $player_name = get_the_title($player_id);
    $summary = "$player_name has been promoted to the 40-man roster by $team_id.";
    do_action('my_fantasy_transaction', [
        'transaction_type' => 'Roster Move',
        'player_ids'       => [$player_id],
        'primary_team'     => $team_id,
        'summary'          => $summary,
        'league_id'        => $league_id
    ]);

    wp_send_json_success(['message' => 'Player promoted to 40-man roster.']);
    wp_die();
}
add_action('wp_ajax_promote_to_40man', 'promote_to_40man_ajax_handler');

function promote_to_26man_ajax_handler() {
    check_ajax_referer('promote_to_26man_nonce', 'nonce');
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); wp_die(); }
    
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    $team_id = isset($_POST['team_id']) ? sanitize_text_field($_POST['team_id']) : '';

    if ( !$player_id || !$league_id || !$team_id ) { wp_send_json_error('Missing required data.'); wp_die(); }
    if ( !is_user_owner_of_player( get_current_user_id(), $player_id ) ) { wp_send_json_error('You do not have permission to manage this player.'); wp_die(); }

    update_post_meta($player_id, 'status_26_man', '1');

    // Log Transaction
    $player_name = get_the_title($player_id);
    $summary = "$player_name has been promoted to the 26-man roster by $team_id.";
    do_action('my_fantasy_transaction', [
        'transaction_type' => 'Roster Move',
        'player_ids'       => [$player_id],
        'primary_team'     => $team_id,
        'summary'          => $summary,
        'league_id'        => $league_id
    ]);

    wp_send_json_success(['message' => 'Player promoted to 26-man roster.']);
    wp_die();
}
add_action('wp_ajax_promote_to_26man', 'promote_to_26man_ajax_handler');

/**
 * Moves a player from 26-man roster to minors.
 * Uses an option year if it's the first time this season.
 */
function option_to_minors_ajax_handler() {
    check_ajax_referer('option_to_minors_nonce', 'nonce');
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); wp_die(); }
    
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    $team_id = isset($_POST['team_id']) ? sanitize_text_field($_POST['team_id']) : '';

    if ( !$player_id || !$league_id || !$team_id ) { wp_send_json_error('Missing required data.'); wp_die(); }
    if ( !is_user_owner_of_player( get_current_user_id(), $player_id ) ) { wp_send_json_error('You do not have permission to manage this player.'); wp_die(); }

    // 1. Remove from 26-man
    update_post_meta($player_id, 'status_26_man', '0');

    // 2. Handle Option Years Logic
    $current_year = date('Y');
    $moves_log = get_field('roster_moves_log', $player_id) ?: [];
    
    $already_optioned_this_year = false;
    if (is_array($moves_log)) {
        foreach ($moves_log as $move) {
            if (($move['move_year'] ?? '') === $current_year && ($move['move_type'] ?? '') === 'Optioned') {
                $already_optioned_this_year = true;
                break;
            }
        }
    }

    if (!$already_optioned_this_year) {
        $option_years_used = (int) get_post_meta($player_id, 'option_years_used', true);
        update_post_meta($player_id, 'option_years_used', $option_years_used + 1);
    }

    // 3. Add to moves log
    add_row('roster_moves_log', [
        'move_type' => 'Optioned',
        'move_year' => $current_year,
        'move_date' => current_time('mysql')
    ], $player_id);

    // 4. Log Transaction
    $player_name = get_the_title($player_id);
    $summary = "$player_name has been optioned to the minors by $team_id.";
    do_action('my_fantasy_transaction', [
        'transaction_type' => 'Roster Move',
        'player_ids'       => [$player_id],
        'primary_team'     => $team_id,
        'summary'          => $summary,
        'league_id'        => $league_id
    ]);

    wp_send_json_success(['message' => 'Player optioned to minors.']);
    wp_die();
}
add_action('wp_ajax_option_to_minors', 'option_to_minors_ajax_handler');

/**
 * Places a player on the Injured List.
 */
function move_player_to_il_ajax_handler() {
    check_ajax_referer('move_to_il_nonce', 'nonce');
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); wp_die(); }
    
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $il_duration = isset($_POST['il_duration']) ? sanitize_text_field($_POST['il_duration']) : '';
    
    $league_id = get_post_meta($player_id, 'league_id', true);
    $team_id = get_post_meta($player_id, 'fantasy_team_id', true);

    if ( !$player_id || !$il_duration || !$league_id || !$team_id ) { wp_send_json_error('Missing required data.'); wp_die(); }
    if ( !is_user_owner_of_player( get_current_user_id(), $player_id ) ) { wp_send_json_error('You do not have permission to manage this player.'); wp_die(); }

    // Update statuses
    update_post_meta($player_id, 'status_il', $il_duration . '-Day IL');
    update_post_meta($player_id, 'il_start_date', current_time('mysql'));
    update_post_meta($player_id, 'status_26_man', '0');
    
    if ($il_duration === '60') {
        update_post_meta($player_id, 'status_40_man', '');
    }

    // Log Transaction
    $player_name = get_the_title($player_id);
    $summary = "$player_name has been placed on the $il_duration-Day IL by $team_id.";
    do_action('my_fantasy_transaction', [
        'transaction_type' => 'Roster Move',
        'player_ids'       => [$player_id],
        'primary_team'     => $team_id,
        'summary'          => $summary,
        'league_id'        => $league_id
    ]);

    wp_send_json_success(['message' => 'Player placed on IL.']);
    wp_die();
}
add_action('wp_ajax_move_player_to_il', 'move_player_to_il_ajax_handler');

/**
 * Activates a player from the Injured List.
 * Includes a check to ensure minimum time has passed.
 */
function activate_player_from_il_ajax_handler() {
    check_ajax_referer('activate_from_il_nonce', 'nonce');
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); wp_die(); }
    
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    
    $status_il = get_post_meta($player_id, 'status_il', true); // e.g. "8-Day IL"
    $start_date = get_post_meta($player_id, 'il_start_date', true);
    
    // --- IL Duration Logic ---
    if ($start_date) {
        $days_on_il = floor((current_time('timestamp') - strtotime($start_date)) / 86400);
        $required_days = (int) $status_il; // Extracts 8, 12, or 60 from string
        
        if ($days_on_il < $required_days) {
            $days_left = $required_days - $days_on_il;
            wp_send_json_error("Player not eligible to be reinstated from IL. $days_left more day(s) must pass.");
            wp_die();
        }
    }

    $league_id = get_post_meta($player_id, 'league_id', true);
    $team_id = get_post_meta($player_id, 'fantasy_team_id', true);

    if ( !$player_id || !$league_id || !$team_id ) { wp_send_json_error('Missing required data.'); wp_die(); }
    if ( !is_user_owner_of_player( get_current_user_id(), $player_id ) ) { wp_send_json_error('You do not have permission to manage this player.'); wp_die(); }

    // Remove IL status
    update_post_meta($player_id, 'status_il', '');
    update_post_meta($player_id, 'il_start_date', '');

    // Log Transaction
    $player_name = get_the_title($player_id);
    $summary = "$player_name has been activated from the IL by $team_id.";
    do_action('my_fantasy_transaction', [
        'transaction_type' => 'Roster Move',
        'player_ids'       => [$player_id],
        'primary_team'     => $team_id,
        'summary'          => $summary,
        'league_id'        => $league_id
    ]);

    wp_send_json_success(['message' => 'Player activated from IL. They are now off-roster.']);
    wp_die();
}
add_action('wp_ajax_activate_from_il', 'activate_player_from_il_ajax_handler');

// Other AJAX handlers...
add_action('wp_ajax_fa_search', 'fa_search_ajax_handler');
add_action('wp_ajax_waive_player', 'waive_player_ajax_handler');

/* ------------------------------------------------------------------------
   Contract Restructure System
------------------------------------------------------------------------ */

/**
 * AJAX: Fetch player contract data for the restructure modal
 */
function fod_get_restructure_data_handler() {
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); }
    
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    if ( !$player_id ) { wp_send_json_error('Invalid player ID.'); }

    if ( !is_user_owner_of_player( get_current_user_id(), $player_id ) ) {
        wp_send_json_error('You do not own this player.');
    }

    // Check Eligibility
    if ( ! fod_is_offseason() ) {
        wp_send_json_error('Contracts can only be restructured during the offseason (Oct 15 - March 15).');
    }

    if ( get_field('has_been_restructured', $player_id) ) {
        wp_send_json_error('This contract has already been restructured and cannot be changed again.');
    }

    $contracts = [];
    foreach (range(2026, 2040) as $year) {
        $val = get_post_meta($player_id, 'contract_' . $year, true);
        if ( is_numeric($val) && $val > 0 ) {
            $contracts[$year] = (float)$val;
        }
    }

    if ( count($contracts) < 2 ) {
        wp_send_json_error('Player must have at least 2 years of contract remaining to restructure.');
    }

    wp_send_json_success([
        'name'      => get_the_title($player_id),
        'contracts' => $contracts
    ]);
}
add_action('wp_ajax_get_restructure_data', 'fod_get_restructure_data_handler');

/**
 * AJAX: Process the contract restructure
 */
function fod_process_contract_restructure_handler() {
    check_ajax_referer('roster_move_nonce', 'nonce');
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); }

    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $from_year = isset($_POST['from_year']) ? absint($_POST['from_year']) : 0;
    $to_year   = isset($_POST['to_year'])   ? absint($_POST['to_year'])   : 0;
    $amount    = isset($_POST['amount'])    ? floatval($_POST['amount'])  : 0;

    if ( !$player_id || !$from_year || !$to_year || $amount <= 0 ) {
        wp_send_json_error('Missing required fields.');
    }

    if ( !fod_is_offseason() ) { wp_send_json_error('Not in offseason.'); }
    if ( !is_user_owner_of_player( get_current_user_id(), $player_id ) ) { wp_send_json_error('Permission denied.'); }
    if ( get_field('has_been_restructured', $player_id) ) { wp_send_json_error('Already restructured.'); }

    $team_id = get_field('fantasy_team_id', $player_id);
    $league_id = get_field('league_id', $player_id);
    $current_year = date('Y');

    // Check Team Limit (1 per year)
    if ( fod_has_team_restructured_this_year($team_id, $current_year) ) {
        wp_send_json_error("Team $team_id has already used its 1 contract restructure for this year.");
    }

    // Validate Amount (Max 50%)
    $source_salary = (float) get_post_meta($player_id, 'contract_' . $from_year, true);
    if ( $amount > ($source_salary * 0.501) ) { // Allow small rounding
        wp_send_json_error('You can only move up to 50% of the original salary.');
    }

    // Execute Move
    $target_salary = (float) get_post_meta($player_id, 'contract_' . $to_year, true);
    
    update_post_meta($player_id, 'contract_' . $from_year, $source_salary - $amount);
    update_post_meta($player_id, 'contract_' . $to_year,   $target_salary + $amount);
    update_field('has_been_restructured', 1, $player_id);

    // Log Team Usage
    add_row('restructure_usage_log', [
        'team_id'     => $team_id,
        'league_year' => $current_year,
        'player_id'   => $player_id
    ], 'option');

    // Log Transaction
    do_action('my_fantasy_transaction', [
        'transaction_type' => 'Restructure',
        'player_ids'       => [$player_id],
        'primary_team'     => $team_id,
        'league_id'        => $league_id,
        'summary'          => get_the_title($player_id) . " contract restructured by $team_id. Moved $" . number_format($amount) . " from $from_year to $to_year."
    ]);

    wp_send_json_success('Contract successfully restructured.');
}
add_action('wp_ajax_process_restructure', 'fod_process_contract_restructure_handler');

/**
 * Helper: Check if team has used their restructure
 */
function fod_has_team_restructured_this_year($team_id, $year) {
    $log = get_field('restructure_usage_log', 'option');
    if ( !is_array($log) ) return false;
    foreach ($log as $row) {
        if ( ($row['team_id'] ?? '') === $team_id && ($row['league_year'] ?? '') == $year ) {
            return true;
        }
    }
    return false;
}

/**
 * AJAX: Toggle Trade Block status and update notes
 */
function fod_update_trade_block_handler() {
    // 1. Basic Security Checks
    check_ajax_referer('roster_move_nonce', 'nonce');
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); }

    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    // Explicitly cast to boolean for consistency
    $on_block  = (isset($_POST['on_block']) && $_POST['on_block'] == '1');
    $notes     = isset($_POST['notes']) ? sanitize_text_field($_POST['notes']) : '';

    if ( !$player_id ) { wp_send_json_error('Invalid player ID.'); }

    // 2. Ownership check
    if ( !is_user_owner_of_player( get_current_user_id(), $player_id ) ) {
        wp_send_json_error('You do not have permission to manage this player.');
    }

    // 3. SAFE UPDATE: Use WordPress Core Meta first (Always works)
    $meta_val_block = $on_block ? '1' : '0';
    update_post_meta($player_id, 'on_trade_block', $meta_val_block);
    update_post_meta($player_id, 'trade_block_notes', $notes);

    // 4. ACF UPDATE: Try to sync with ACF if available
    if ( function_exists('update_field') ) {
        update_field('on_trade_block', $on_block, $player_id);
        update_field('trade_block_notes', $notes, $player_id);
    }

    // 5. CACHE BUSTING
    clean_post_cache($player_id);
    update_option('fod_trade_block_last_updated', time());

    // 6. NOTIFICATIONS (Wrapped in try/catch so it never kills the response)
    if ($on_block) {
        try {
            if (function_exists('fod_send_slack_notification')) {
                $player_name = get_the_title($player_id);
                $team_id     = get_post_meta($player_id, 'fantasy_team_id', true);
                $pos         = get_post_meta($player_id, 'position', true);
                $league_id   = get_post_meta($player_id, 'league_id', true);
                
                $contract_summary = "";
                foreach (range(2026, 2028) as $y) {
                    $val = get_post_meta($player_id, 'contract_' . $y, true);
                    if ($val) {
                        if (is_numeric($val)) {
                            $fmt = ($val >= 1000000) ? round($val/1000000, 1) . 'M' : round($val/1000) . 'K';
                            $contract_summary .= "'$y: $$fmt, ";
                        } else {
                            $contract_summary .= "'$y: $val, ";
                        }
                    }
                }
                $contract_summary = rtrim($contract_summary, ", ");

                $slack_msg = "📢 *Trade Block Alert:* _" . $player_name . "_ (" . $pos . ")\n";
                $slack_msg .= "🏟️ *Team:* `" . $team_id . "`\n";
                $slack_msg .= "💰 *Contract:* " . ($contract_summary ?: "N/A") . "\n";
                if (!empty($notes)) {
                    $slack_msg .= "📝 *Manager Notes:* " . $notes;
                }

                fod_send_slack_notification($slack_msg, $league_id);
            }
        } catch (Exception $e) {
            error_log("Trade Block Slack Error: " . $e->getMessage());
        }
    }

    $msg = $on_block ? 'Player added to trade block.' : 'Player removed from trade block.';
    wp_send_json_success(['message' => $msg]);
}
add_action('wp_ajax_update_trade_block', 'fod_update_trade_block_handler');

/**
 * AJAX: Save manual depth chart ordering
 */
function fod_save_depth_order_handler() {
    check_ajax_referer('roster_move_nonce', 'nonce');
    if ( !is_user_logged_in() ) wp_send_json_error('Not logged in.');

    $ranks = isset($_POST['ranks']) ? $_POST['ranks'] : [];
    if ( !is_array($ranks) ) wp_send_json_error('Invalid data.');

    foreach ( $ranks as $player_id => $rank ) {
        $player_id = absint($player_id);
        
        // Ownership check
        if ( is_user_owner_of_player( get_current_user_id(), $player_id ) ) {
            update_post_meta($player_id, 'depth_rank', absint($rank));
        }
    }

    wp_send_json_success('Depth order saved successfully.');
}
add_action('wp_ajax_save_depth_order', 'fod_save_depth_order_handler');
