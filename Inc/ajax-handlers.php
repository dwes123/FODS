<?php

/**
 * Checks if a given user is the manager of the team that owns a specific player.
 */
function is_user_owner_of_player( $user_id, $player_id ) {
    if ( ! $user_id || ! $player_id ) {
        return false;
    }

    $player_team_id = get_field('fantasy_team_id', $player_id);
    $player_league_id = get_field('league_id', $player_id);

    if ( empty($player_team_id) ) {
        return false;
    }

    $managed_teams = get_field('managed_teams', 'user_' . $user_id);
    if ( empty($managed_teams) || ! is_array($managed_teams) ) {
        return false;
    }

    foreach ( $managed_teams as $team ) {
        if ( is_array($team) && ! empty($team['league_id']) && ! empty($team['fantasy_team_id']) ) {
            if ( $team['league_id'] === $player_league_id && $team['fantasy_team_id'] === $player_team_id ) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Helper function to get all rostered players for a given manager in a specific league.
 */
function get_rostered_players_for_manager($league_id, $manager_id) {
    if ( empty($league_id) || empty($manager_id) ) {
        return new WP_Error('missing_params', 'League ID or Manager ID is missing.');
    }

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

    if ( !$team_id ) {
        return new WP_Error('no_team_found', 'Could not find a team for this manager in the selected league.');
    }

    $args = array(
        'post_type'      => 'playerdata',
        'posts_per_page' => 500,
        'no_found_rows'  => true,
        'fields'         => 'ids',
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => array(
            'relation' => 'AND',
            array('key' => 'league_id',       'value' => $league_id),
            array('key' => 'fantasy_team_id', 'value' => $team_id)
        ),
    );

    $query = new WP_Query($args);
    $players = array();
    if ($query->have_posts()) {
        foreach ($query->posts as $pid) {
            $players[] = array('id' => $pid, 'name' => get_the_title($pid));
        }
    }
    wp_reset_postdata();

    return $players;
}

/* ------------------------------------------------------------------------
   Trade Form Handlers
------------------------------------------------------------------------ */
function handle_trade_proposal_submission() {
    // ... function content (unchanged) ...
    $redirect_url = wp_get_referer() ?: home_url();
    if ( ! isset( $_POST['trade_proposal_nonce_field'] ) || ! wp_verify_nonce( $_POST['trade_proposal_nonce_field'], 'process_trade_proposal_nonce' ) ) { wp_redirect( add_query_arg('trade_error', 'security_check_failed', $redirect_url) ); exit; }
    if ( ! is_user_logged_in() ) { wp_redirect( add_query_arg('trade_error', 'not_logged_in', $redirect_url) ); exit; }
    if ( ! function_exists('update_field') ) { error_log("ACF function error in process_trade_proposal: update_field not found."); wp_redirect( add_query_arg('trade_error', 'acf_missing', $redirect_url) ); exit; }
    $proposing_manager_id = get_current_user_id();
    $target_manager_id    = isset($_POST['target_manager']) ? absint($_POST['target_manager']) : 0;
    $selected_league      = isset($_POST['trade_league']) ? sanitize_text_field( wp_unslash($_POST['trade_league']) ) : '';
    $offered_player_ids   = isset($_POST['players_offered'])   ? array_map('absint', (array)$_POST['players_offered'])   : array();
    $requested_player_ids = isset($_POST['players_requested']) ? array_map('absint', (array)$_POST['players_requested']) : array();
    $offered_player_ids   = array_filter($offered_player_ids);
    $requested_player_ids = array_filter($requested_player_ids);
    if ( empty($target_manager_id) || empty($selected_league) || empty($offered_player_ids) || empty($requested_player_ids) ) { wp_redirect(add_query_arg( 'trade_error', 'missing_fields', $redirect_url )); exit; }
    if ( $target_manager_id === $proposing_manager_id ) { wp_redirect(add_query_arg( 'trade_error', 'self_trade', $redirect_url )); exit; }
    $post_data = array('post_type' => 'trade_proposal', 'post_title'  => 'Trade Offer: User ' . $proposing_manager_id . ' to User ' . $target_manager_id . ' (' . $selected_league . ') - ' . date('Y-m-d H:i'), 'post_status' => 'publish', 'post_author' => $proposing_manager_id);
    $new_post_id = wp_insert_post($post_data, true);
    if ( ! is_wp_error($new_post_id) && $new_post_id > 0 ) {
        update_field('proposing_manager', $proposing_manager_id, $new_post_id);
        update_field('target_manager', $target_manager_id, $new_post_id);
        update_field('league_id', $selected_league, $new_post_id);
        update_field('players_offered', $offered_player_ids, $new_post_id);
        update_field('players_requested', $requested_player_ids, $new_post_id);
        update_field('trade_status', 'pending', $new_post_id);
        $target_user_info = get_userdata($target_manager_id);
        if ($target_user_info) {
            $to = $target_user_info->user_email;
            $proposer_info = get_userdata($proposing_manager_id);
            $proposer_name = $proposer_info ? $proposer_info->display_name : 'A manager';
            $subject = 'New Trade Proposal Received!';
            $offered_names   = array_map(function($id){ return get_the_title($id) ?: '(?)'; }, $offered_player_ids);
            $requested_names = array_map(function($id){ return get_the_title($id) ?: '(?)'; }, $requested_player_ids);
            $message  = "Hello " . esc_html($target_user_info->display_name) . ",\n\n";
            $message .= esc_html($proposer_name) . " has proposed a trade with you in the " . esc_html($selected_league) . " league.\n\n";
            $message .= "They Offer: " . esc_html(implode(', ', $offered_names)) . "\n";
            $message .= "They Request: " . esc_html(implode(', ', $requested_names)) . "\n\n";
            $view_trades_id = function_exists('get_field') ? (url_to_postid(get_field('view_pending_trades_page', 'option') ?: '') ?: 0) : 0;
            $view_trades_link = $view_trades_id ? get_permalink($view_trades_id) : home_url('/');
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
    // ... function content (unchanged) ...
    check_ajax_referer('trade_form_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error('User not logged in.');
    $league = isset($_POST['league_id']) ? sanitize_text_field( wp_unslash( $_POST['league_id'] ) ) : null;
    if (!$league) wp_send_json_error('League ID not provided.');
    $current_user_id = get_current_user_id(); $managers = [];
    $args = array( 'exclude' => array($current_user_id), 'fields' => array('ID', 'display_name') );
    $all_other_users = get_users($args);
    if (!empty($all_other_users) && function_exists('get_field')) {
        foreach ($all_other_users as $user) {
            $user_key = 'user_' . $user->ID; $managed_teams = get_field('managed_teams', $user_key);
            if (!empty($managed_teams) && is_array($managed_teams)) {
                foreach ($managed_teams as $team_data) {
                    if (is_array($team_data) && isset($team_data['league_id']) && $team_data['league_id'] === $league) {
                        $managers[] = array('id' => $user->ID, 'name' => $user->display_name); break;
                    }
                }
            }
        }
    }
    wp_send_json_success($managers); wp_die();
}
add_action('wp_ajax_get_managers_for_trade', 'get_managers_for_trade_ajax_handler');

function get_my_players_for_trade_ajax_handler() {
    // ... function content (unchanged) ...
    check_ajax_referer('trade_form_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error('User not logged in.'); }
    $league_id = isset($_POST['league_id']) ? sanitize_text_field(wp_unslash($_POST['league_id'])) : '';
    $current_user_id = get_current_user_id();
    $players = get_rostered_players_for_manager($league_id, $current_user_id);
    if (is_wp_error($players)) { wp_send_json_error($players->get_error_message()); }
    else { wp_send_json_success($players); }
}
add_action('wp_ajax_get_my_players_for_trade', 'get_my_players_for_trade_ajax_handler');

function get_target_players_for_trade_ajax_handler() {
    // ... function content (unchanged) ...
    check_ajax_referer('trade_form_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error('User not logged in.'); }
    $league_id = isset($_POST['league_id']) ? sanitize_text_field(wp_unslash($_POST['league_id'])) : '';
    $target_manager_id = isset($_POST['target_manager_id']) ? absint($_POST['target_manager_id']) : 0;
    $players = get_rostered_players_for_manager($league_id, $target_manager_id);
    if (is_wp_error($players)) { wp_send_json_error($players->get_error_message()); }
    else { wp_send_json_success($players); }
}
add_action('wp_ajax_get_target_players_for_trade', 'get_target_players_for_trade_ajax_handler');

function handle_reject_trade() {
    // ... function content (unchanged) ...
    $trade_id = isset($_GET['trade_id']) ? absint($_GET['trade_id']) : 0;
    $nonce_action = 'handle_trade_nonce_' . $trade_id;
    $redirect_base = wp_get_referer() ?: home_url();
    if ( ! $trade_id || ! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], $nonce_action) ) { wp_redirect( add_query_arg(array('trade_action'=>'trade_action_error','trade_action_error_detail'=>'security_failed'), $redirect_base) ); exit; }
    if ( ! is_user_logged_in() ) { wp_redirect( add_query_arg(array('trade_action'=>'trade_action_error','trade_action_error_detail'=>'not_logged_in'), $redirect_base) ); exit; }
    if (!function_exists('get_field') || !function_exists('update_field')) { wp_redirect( add_query_arg(array('trade_action'=>'trade_action_error','trade_action_error_detail'=>'acf_missing'), $redirect_base) ); exit; }
    $current_user_id = get_current_user_id();
    $target_manager_id = get_field('target_manager', $trade_id);
    if ( $current_user_id != $target_manager_id ) { wp_redirect( add_query_arg(array('trade_action'=>'trade_action_error','trade_action_error_detail'=>'not_authorized'), $redirect_base) ); exit; }
    $current_status = get_field('trade_status', $trade_id);
    if ($current_status !== 'pending') { wp_redirect( $redirect_base ); exit; }
    update_field('trade_status', 'rejected', $trade_id);
    wp_redirect( add_query_arg('trade_action', 'rejected', $redirect_base) ); exit;
}
add_action('admin_post_reject_trade', 'handle_reject_trade');

function handle_accept_trade() {
    // ... function content (unchanged) ...
    $trade_id = isset($_GET['trade_id']) ? absint($_GET['trade_id']) : 0;
    $nonce_action = 'handle_trade_nonce_' . $trade_id;
    $redirect_base = wp_get_referer() ?: home_url();
    if ( ! $trade_id || ! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], $nonce_action) ) { wp_safe_redirect( add_query_arg(array('trade_action'=>'trade_action_error','trade_action_error_detail'=>'security_failed'), $redirect_base) ); exit; }
    if ( ! is_user_logged_in() ) { wp_safe_redirect( add_query_arg(array('trade_action'=>'trade_action_error','trade_action_error_detail'=>'not_logged_in'), $redirect_base) ); exit; }
    if (!function_exists('get_field') || !function_exists('update_field')) { wp_safe_redirect( add_query_arg(array('trade_action'=>'trade_action_error','trade_action_error_detail'=>'acf_missing'), $redirect_base) ); exit; }
    $current_user_id = get_current_user_id();
    $target_manager_id_from_trade = get_field('target_manager', $trade_id);
    if ( $current_user_id != $target_manager_id_from_trade ) { wp_safe_redirect( add_query_arg(array('trade_action'=>'trade_action_error','trade_action_error_detail'=>'not_authorized'), $redirect_base) ); exit; }
    $current_status = get_field('trade_status', $trade_id);
    if ($current_status !== 'pending') { wp_redirect( $redirect_base ); exit; }
    $proposer_id = get_field('proposing_manager', $trade_id);
    $target_id   = $current_user_id;
    $league_id   = get_field('league_id', $trade_id);
    $offered_player_ids   = get_field('players_offered',   $trade_id) ?: [];
    $requested_player_ids = get_field('players_requested', $trade_id) ?: [];
    if (!$proposer_id || !$target_id || !$league_id || empty($offered_player_ids) || empty($requested_player_ids)) { wp_redirect( add_query_arg(array('trade_action'=>'trade_action_error','trade_action_error_detail'=>'missing_trade_data'), $redirect_base) ); exit; }
    $get_team_id = function($user_id_to_check, $league_id_to_check) { $teams = get_field('managed_teams', 'user_' . $user_id_to_check); if (!empty($teams) && is_array($teams)) { foreach ($teams as $team) { if (is_array($team) && ($team['league_id'] ?? '') === $league_id_to_check) { return $team['fantasy_team_id'] ?? null; } } } return null; };
    $proposer_team_id = $get_team_id($proposer_id, $league_id);
    $target_team_id   = $get_team_id($target_id,   $league_id);
    if (!$proposer_team_id || !$target_team_id) { wp_redirect( add_query_arg(array('trade_action'=>'trade_action_error','trade_action_error_detail'=>'team_id_missing'), $redirect_base) ); exit; }
    $trade_successful = true; $error_log_messages = [];
    $initial_ownership = [];
    $all_players_in_trade = array_merge($offered_player_ids, $requested_player_ids);
    foreach ($all_players_in_trade as $player_id) {
        $current_owner_team = get_field('fantasy_team_id', $player_id);
        if (empty($current_owner_team)) { $trade_successful = false; $error_log_messages[] = "Player {$player_id} FA."; break; }
        $initial_ownership[$player_id] = $current_owner_team;
        if (in_array($player_id, $offered_player_ids)   && $current_owner_team != $proposer_team_id) { $trade_successful = false; $error_log_messages[] = "Offered {$player_id} mismatch."; break; }
        if (in_array($player_id, $requested_player_ids) && $current_owner_team != $target_team_id)   { $trade_successful = false; $error_log_messages[] = "Requested {$player_id} mismatch."; break; }
    }
    if (!$trade_successful) { wp_redirect( add_query_arg(array('trade_action'=>'trade_action_error','trade_action_error_detail'=>'ownership_mismatch'), $redirect_base) ); exit; }
    foreach ($offered_player_ids as $pid) { if (get_field('fantasy_team_id', $pid) != $proposer_team_id) { $trade_successful = false; $error_log_messages[] = "Offered {$pid} changed."; break; } if (!update_field('fantasy_team_id', $target_team_id, $pid)) { $trade_successful = false; $error_log_messages[] = "Failed update offered {$pid}."; break; } }
    if ($trade_successful) { foreach ($requested_player_ids as $pid) { if (get_field('fantasy_team_id', $pid) != $target_team_id) { $trade_successful = false; $error_log_messages[] = "Requested {$pid} changed."; break; } if (!update_field('fantasy_team_id', $proposer_team_id, $pid)) { $trade_successful = false; $error_log_messages[] = "Failed update requested {$pid}."; break; } } }
    if ($trade_successful) { update_field('trade_status', 'accepted', $trade_id); wp_redirect( add_query_arg('trade_action', 'accepted', $redirect_base) ); exit; }
    else { foreach ($all_players_in_trade as $pid) { $orig = $initial_ownership[$pid] ?? null; if ($orig) { update_field('fantasy_team_id', $orig, $pid); } } update_field('trade_status', 'failed_processing', $trade_id); error_log("Trade accept error {$trade_id}: ".implode(' | ', $error_log_messages)); wp_redirect( add_query_arg('trade_action', 'trade_action_error', $redirect_base) ); exit; }
}
add_action('admin_post_accept_trade', 'handle_accept_trade');

/* ------------------------------------------------------------------------
   Free Agent Handlers
------------------------------------------------------------------------ */
function handle_sign_free_agent_action() {
    // ... function content (unchanged) ...
    $redirect_base = wp_get_referer() ?: home_url();
    $league_id_from_post = isset($_POST['league_id']) ? sanitize_text_field(wp_unslash($_POST['league_id'])) : '';
    if ($league_id_from_post && strpos($redirect_base, 'show_league=') === false) { $redirect_base = add_query_arg('show_league', rawurlencode($league_id_from_post), $redirect_base); }
    $redirect_base = remove_query_arg(array('sign_success', 'sign_error', 'signed_player', 'salary', 'status'), $redirect_base);
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $nonce = isset($_POST['_wpnonce_sign_fa']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce_sign_fa'])) : '';
    $nonce_action = 'sign_fa_nonce_' . $player_id;
    if ( !$player_id || !$nonce || !wp_verify_nonce($nonce, $nonce_action) ) { wp_redirect(add_query_arg('sign_error', 'security_failed', $redirect_base)); exit; }
    if ( ! is_user_logged_in() ) { wp_redirect(add_query_arg('sign_error', 'not_logged_in', $redirect_base)); exit; }
    $current_user_id = get_current_user_id();
    $league_id = isset($_POST['league_id']) ? sanitize_text_field(wp_unslash($_POST['league_id'])) : '';
    $team_id   = isset($_POST['team_id'])   ? sanitize_text_field(wp_unslash($_POST['team_id']))   : '';
    $contract_offers = isset($_POST['contract_offers']) && is_array($_POST['contract_offers']) ? $_POST['contract_offers'] : [];
    if ( empty($league_id) || empty($team_id) || empty($player_id) ) { wp_redirect(add_query_arg('sign_error', 'missing_data', $redirect_base)); exit; }
    if ( ! function_exists('get_field') || ! function_exists('update_field') ) { wp_redirect(add_query_arg('sign_error', 'acf_missing', $redirect_base)); exit; }
    $is_manager_of_team = false;
    $managed_teams_check = get_field('managed_teams', 'user_' . $current_user_id);
    if (!empty($managed_teams_check) && is_array($managed_teams_check)) { foreach ($managed_teams_check as $t) { if (is_array($t) && ($t['league_id'] ?? '') === $league_id && ($t['fantasy_team_id'] ?? '') === $team_id) { $is_manager_of_team = true; break; } } }
    if (!$is_manager_of_team) { wp_redirect(add_query_arg('sign_error', 'not_manager', $redirect_base)); exit; }
    $validated_offers = []; $has_valid_offer = false; $first_offer_amount = null; $current_year = (int) current_time('Y'); $valid_years = range($current_year, $current_year + 5);
    foreach ($contract_offers as $year_raw => $salary_raw) {
        $year = absint($year_raw);
        if (in_array($year, $valid_years, true) && $salary_raw !== '' && $salary_raw !== null) {
            if (is_string($salary_raw)) $salary_raw = str_replace(',', '', $salary_raw);
            if (is_numeric($salary_raw) && floatval($salary_raw) >= 0) { $salary = floatval($salary_raw); $validated_offers[$year] = $salary; $has_valid_offer = true; if ($first_offer_amount === null && $salary > 0) { $first_offer_amount = $salary; } }
            else { wp_redirect(add_query_arg('sign_error', 'invalid_salary', $redirect_base)); exit; }
        }
    }
    if (!$has_valid_offer) { wp_redirect(add_query_arg('sign_error', 'no_offer', $redirect_base)); exit; }
    $current_fa_status = get_field('fa_status', $player_id); $current_pending_bid_amount = get_field('pending_bid_amount', $player_id); $current_pending_bid_team_id = get_field('pending_bid_team_id', $player_id);
    if ($current_fa_status === 'available' || empty($current_fa_status) || ($current_fa_status === 'rostered' && empty(get_field('fantasy_team_id', $player_id)))) {
        if ( !empty(get_field('fantasy_team_id', $player_id)) && get_field('fa_status', $player_id) === 'rostered') { wp_redirect(add_query_arg('sign_error', 'player_signed', $redirect_base)); exit; }
        $now_mysql   = current_time('mysql', true); $bid_end_time= date('Y-m-d H:i:s', strtotime($now_mysql . ' +24 hours'));
        $ok = true; $ok = $ok && update_field('fa_status', 'pending_bid', $player_id); $ok = $ok && update_field('pending_bid_team_id', $team_id, $player_id); $ok = $ok && update_field('pending_bid_manager_id', $current_user_id,$player_id); $ok = $ok && update_field('pending_bid_amount', $first_offer_amount, $player_id); $ok = $ok && update_field('bid_start_time', $now_mysql, $player_id); $ok = $ok && update_field('bid_end_time', $bid_end_time, $player_id);
        update_post_meta($player_id, 'pending_bid_contract_offers', $validated_offers);
        if ($ok) { wp_redirect(add_query_arg(array('sign_success'=>'true','signed_player'=>$player_id,'salary'=>$first_offer_amount,'status'=>'pending_bid'), $redirect_base)); exit; }
        else { wp_redirect(add_query_arg('sign_error', 'initial_bid_failed', $redirect_base)); exit; }
    }
    elseif ($current_fa_status === 'pending_bid') {
        if ($team_id === $current_pending_bid_team_id && $first_offer_amount <= $current_pending_bid_amount) { wp_redirect(add_query_arg('sign_error', 'bid_too_low', $redirect_base)); exit; }
        if ($first_offer_amount <= $current_pending_bid_amount) { wp_redirect(add_query_arg('sign_error', 'bid_too_low', $redirect_base)); exit; }
        $now_mysql   = current_time('mysql', true); $bid_end_time= date('Y-m-d H:i:s', strtotime($now_mysql . ' +24 hours'));
        $ok = true; $ok = $ok && update_field('pending_bid_team_id', $team_id, $player_id); $ok = $ok && update_field('pending_bid_manager_id', $current_user_id,$player_id); $ok = $ok && update_field('pending_bid_amount', $first_offer_amount, $player_id); $ok = $ok && update_field('bid_start_time', $now_mysql, $player_id); $ok = $ok && update_field('bid_end_time', $bid_end_time, $player_id);
        update_post_meta($player_id, 'pending_bid_contract_offers', $validated_offers);
        if ($ok) { wp_redirect(add_query_arg(array('sign_success'=>'true','signed_player'=>$player_id,'salary'=>$first_offer_amount,'status'=>'pending_bid'), $redirect_base)); exit; }
        else { wp_redirect(add_query_arg('sign_error', 'update_failed', $redirect_base)); exit; }
    }
    else { wp_redirect(add_query_arg('sign_error', 'player_signed', $redirect_base)); exit; }
}
add_action('admin_post_sign_free_agent', 'handle_sign_free_agent_action');

function ajax_get_fa_sign_nonce_handler() {
    // ... function content (unchanged) ...
    check_ajax_referer('fa_modal_nonce_action', '_ajax_nonce');
    if (!is_user_logged_in()) { wp_send_json_error('Not logged in.'); wp_die(); }
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    if (!$player_id) { wp_send_json_error('Player ID missing for nonce generation.'); wp_die(); }
    $nonce_action = 'sign_fa_nonce_' . $player_id;
    $nonce = wp_create_nonce($nonce_action);
    wp_send_json_success(array('nonce' => $nonce));
    wp_die();
}
add_action('wp_ajax_get_fa_sign_nonce', 'ajax_get_fa_sign_nonce_handler');

function fa_search_ajax_handler() {
    check_ajax_referer('fa_search_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error('You must be logged in.'); }

    $search_term = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
    $league_id   = isset($_POST['league_id'])   ? sanitize_text_field($_POST['league_id'])   : '';
    $paged       = isset($_POST['paged'])       ? absint($_POST['paged']) : 1;
    if (empty($league_id)) { wp_send_json_error('League ID is missing.'); }
    $manager_team_id = null;
    if ( function_exists('get_field') ) {
        $mt = get_field('managed_teams', 'user_' . get_current_user_id());
        if (is_array($mt)) {
            foreach ($mt as $t) {
                if (is_array($t) && ($t['league_id'] ?? '') === $league_id) {
                    $manager_team_id = $t['fantasy_team_id'] ?? null; break;
                }
            }
        }
    }
    $posts_per_page = 25;
    $years_to_display = range(date('Y'), date('Y') + 10);

    // --- THIS IS THE BUG ---
    $args = array(
        'post_type'      => 'player', // <-- Should be 'playerdata'
        'posts_per_page' => $posts_per_page, 'paged' => $paged, 'orderby' => 'title', 'order' => 'ASC',
        'meta_query'     => array(
            'relation' => 'AND',
            array('key' => 'league_id', 'value' => $league_id),
            array('key' => 'fa_status', 'value' => 'available', 'compare' => '='),
        ),
        's'              => $search_term,
    );

    $query = new WP_Query($args);
    $table_rows_html = '';
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $player_id   = get_the_ID(); $player_name = get_the_title();
            $table_rows_html .= '<tr>';
            $table_rows_html .= '<td>' . esc_html($player_name) . '</td>';
            $table_rows_html .= '<td>' . esc_html(get_field('position', $player_id) ?: 'N/A') . '</td>';
            $table_rows_html .= '<td>' . esc_html(get_field('mlb_team', $player_id) ?: 'N/A') . '</td>';
            foreach ($years_to_display as $year) { $table_rows_html .= '<td>–</td>'; }
            $table_rows_html .= '<td class="fa-action-cell">';
            if ($manager_team_id) {
                $table_rows_html .= '<button type="button" class="button fa-offer-button" '
                  .'data-playerid="'.esc_attr($player_id).'" '
                  .'data-playername="'.esc_attr($player_name).'" '
                  .'data-leagueid="'.esc_attr($league_id).'" '
                  .'data-teamid="'.esc_attr($manager_team_id).'">Offer Contract</button>';
            } else {
                $table_rows_html .= 'N/A';
            }
            $table_rows_html .= '</td></tr>';
        }
    } else {
        $col_count = 3 + count($years_to_display) + 1;
        $table_rows_html = '<tr><td colspan="'.$col_count.'">No free agents found matching your search.</td></tr>';
    }
    $pagination_html = paginate_links(array( 'base' => '#%#%', 'format' => '?fa_page=%#%', 'current' => $paged, 'total' => $query->max_num_pages, 'prev_next' => true, 'type' => 'plain', ));
    wp_send_json_success(array( 'table_rows' => $table_rows_html, 'pagination_html' => $pagination_html, ));
}
add_action('wp_ajax_fa_search', 'fa_search_ajax_handler');

/* ------------------------------------------------------------------------
   Waiver & Roster Move Handlers
------------------------------------------------------------------------ */
function waive_player_ajax_handler() {
    check_ajax_referer('drop_player_nonce', 'nonce');
    if ( ! is_user_logged_in() ) { wp_send_json_error('Not authorized.'); }

    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $team_id   = isset($_POST['team_id'])   ? sanitize_text_field($_POST['team_id'])   : '';

    // --- DEBUGGING ---
    error_log('--- WAIVE PLAYER ACTION FIRED ---');
    error_log('Player ID received: ' . $player_id);
    error_log('Waiving Team ID received from button: ' . $team_id);
    // --- END DEBUGGING ---

    if ( ! $player_id || ! $team_id ) { wp_send_json_error('Missing player or team information.'); }
    if ( ! is_user_owner_of_player( get_current_user_id(), $player_id ) ) { wp_send_json_error('You do not own this player.'); }

    // Get current time based on WordPress settings and add 24 hours.
$waiver_end = date('Y-m-d H:i:s', current_time('timestamp') + (24 * HOUR_IN_SECONDS));

    update_field('fa_status', 'on_waivers', $player_id);
    update_field('waiving_team_id', $team_id, $player_id); // This is the key line
    update_field('waiver_end_time', $waiver_end, $player_id);
    update_field('fantasy_team_id', '', $player_id);

    error_log('Attempted to save "' . $team_id . '" to waiving_team_id field for player ' . $player_id);

    wp_send_json_success( get_the_title($player_id) . ' has been placed on waivers for 24 hours.' );
}
add_action('wp_ajax_waive_player', 'waive_player_ajax_handler');

function promote_to_40man_ajax_handler() {
    // ... function content (unchanged) ...
    check_ajax_referer('roster_move_nonce', 'nonce');
    if ( ! is_user_logged_in() || ! function_exists('update_field') ) { wp_send_json_error('Not authorized or ACF missing.'); }
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    if ( ! $player_id ) { wp_send_json_error('Missing player ID.'); }
    if ( ! is_user_owner_of_player( get_current_user_id(), $player_id ) ) { wp_send_json_error('Permission denied: You do not manage this player.'); }
    $has_been_on_40 = get_field('has_been_on_40_man', $player_id);
    if ( ! $has_been_on_40 ) { update_field('has_been_on_40_man', true, $player_id); }
    $new_row = [ 'move_year' => date('Y'), 'move_type' => 'Promoted' ];
    add_row('roster_moves_log', $new_row, $player_id);
    if ( update_field('status_40_man', 'X', $player_id) ) { wp_send_json_success(array('message' => 'Player moved to 40-man roster.')); }
    else { wp_send_json_error('Failed to update player status in the database.'); }
}
add_action('wp_ajax_promote_to_40man', 'promote_to_40man_ajax_handler'); // --- MISSING HOOK ---

function option_to_minors_ajax_handler() {
    // ... function content (unchanged) ...
    check_ajax_referer('roster_move_nonce', 'nonce');
    if ( ! is_user_logged_in() || ! function_exists('update_field') ) { wp_send_json_error('Not authorized or ACF missing.'); }
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    if ( ! $player_id ) { wp_send_json_error('Missing player ID.'); }
    $current_user_id = get_current_user_id();
    if ( ! is_user_owner_of_player( $current_user_id, $player_id ) ) { wp_send_json_error('Permission denied: You do not manage this player.'); }
    $is_dfa_only = get_field('dfa_only', $player_id);
    if ( $is_dfa_only ) { wp_send_json_error('This player must be Designated for Assignment (DFA) and cannot be optioned to the minors.'); }
    $total_option_years_used = (int) (get_field('option_years_used', $player_id) ?: 0);
    $moves_log = get_field('roster_moves_log', $player_id) ?: [];
    $current_year = date('Y');
    $options_used_this_season = 0;
    $has_been_optioned_this_year = false;
    foreach ($moves_log as $move) { if (isset($move['move_year'], $move['move_type']) && $move['move_year'] === $current_year) { if ($move['move_type'] === 'Optioned') { $options_used_this_season++; $has_been_optioned_this_year = true; } } }
    if ( $total_option_years_used >= 3 && !$has_been_optioned_this_year ) { wp_send_json_error('Player is out of option years (3 used) and cannot be sent down.'); }
    if ( $options_used_this_season >= 5 ) { wp_send_json_error('Player has no options remaining for this season (5 used).'); }
    if ( !$has_been_optioned_this_year ) {
        $new_total_option_years = $total_option_years_used + 1;
        update_field('option_years_used', $new_total_option_years, $player_id);
        if ( $new_total_option_years >= 3 ) { update_field('dfa_only', true, $player_id); }
    }
    $new_row = [ 'move_year' => $current_year, 'move_type' => 'Optioned' ];
    add_row('roster_moves_log', $new_row, $player_id);
    clean_post_cache($player_id);
    if ( update_field('status_40_man', '', $player_id) ) { wp_send_json_success(array('message' => 'Player optioned to minors.')); }
    else { wp_send_json_error('Failed to update player status in the database.'); }
}
add_action('wp_ajax_option_to_minors', 'option_to_minors_ajax_handler');

function claim_player_ajax_handler() {
    // ... function content (unchanged) ...
    check_ajax_referer('drop_player_nonce', 'nonce');
    if ( ! is_user_logged_in() ) { wp_send_json_error('Not authorized.'); }
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    if ( ! $player_id ) { wp_send_json_error('Player ID not found.'); }
    $user_id = get_current_user_id();
    $player_league = get_field('league_id', $player_id);
    $user_teams = get_field('managed_teams', 'user_' . $user_id);
    $claiming_team_id = null;
    if (is_array($user_teams)) { foreach($user_teams as $team) { if (isset($team['league_id']) && $team['league_id'] === $player_league) { $claiming_team_id = $team['fantasy_team_id']; break; } } }
    if (!$claiming_team_id) { wp_send_json_error('You do not have a team in this player\'s league.'); }
    $current_status = get_field('fa_status', $player_id);
    $waiving_team_id = get_field('waiving_team_id', $player_id);
    if ($current_status !== 'on_waivers') { wp_send_json_error('This player is no longer on waivers.'); }
    if ($waiving_team_id === $claiming_team_id) { wp_send_json_error('You cannot claim a player that you placed on waivers.'); }
    update_field('fantasy_team_id', $claiming_team_id, $player_id);
    update_field('fa_status', 'rostered', $player_id);
    update_field('waiving_team_id', '', $player_id);
    update_field('waiver_end_time', '', $player_id);
    wp_send_json_success(get_the_title($player_id) . ' has been claimed and added to your roster.');
}
add_action('wp_ajax_claim_player', 'claim_player_ajax_handler'); // --- MISSING HOOK ---

