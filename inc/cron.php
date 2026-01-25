<?php
/* ------------------------------------------------------------------------
   Cron schedule for FA bid finalization
   Version: 2.1 (DFA Fix)
------------------------------------------------------------------------ */
function custom_cron_schedules( $schedules ) {
    if ( ! isset( $schedules['every_five_minutes'] ) ) {
        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display'  => __( 'Every 5 Minutes' ),
        );
    }
    return $schedules;
}
add_filter( 'cron_schedules', 'custom_cron_schedules' );

function schedule_fa_bid_finalization() {
    if ( ! wp_next_scheduled( 'fa_bid_finalization_event' ) ) {
        wp_schedule_event( time(), 'every_five_minutes', 'fa_bid_finalization_event' );
    }
}
add_action( 'wp', 'schedule_fa_bid_finalization' );

function fa_bid_finalization_handler() {
    if ( ! function_exists('get_field') || ! function_exists('update_field') ) {
        error_log("FA Bid Finalization Cron: ACF functions not available. Aborting.");
        return;
    }
    $now_mysql = current_time('mysql', true);

    $args = array(
        'post_type'      => 'playerdata',
        'posts_per_page' => -1,
        'meta_query'     => array(
            'relation' => 'AND',
            array( 'key' => 'fa_status', 'value' => 'pending_bid', 'compare' => '=' ),
            array( 'key' => 'bid_end_time', 'value' => $now_mysql, 'compare' => '<=', 'type' => 'DATETIME' ),
        ),
        'orderby'        => 'bid_end_time',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
    );

    $expired = new WP_Query($args);

    if ( $expired->have_posts() ) {
        foreach ( $expired->posts as $player_id ) {
            $player_name = get_the_title($player_id);
            $winning_team_id = get_post_meta($player_id, 'pending_bid_team_id', true);
            $winning_bid_points = (float) get_post_meta($player_id, 'pending_bid_amount', true);
            $winning_years = (int) get_post_meta($player_id, 'pending_bid_years', true);
            $winning_aav = (float) get_post_meta($player_id, 'pending_bid_aav', true);
            $winning_manager_id = get_post_meta($player_id, 'pending_bid_manager_id', true);
            $league_id = get_post_meta($player_id, 'league_id', true);
            $bid_type = get_post_meta($player_id, 'bid_type', true);

            // --- Case 1: MiLB Contract OR ISBP Signing ---
            if ( ($bid_type === 'milb' || $bid_type === 'isbp') && !empty($winning_team_id) ) {
                update_post_meta($player_id, 'fantasy_team_id', $winning_team_id);
                update_post_meta($player_id, 'fa_status', 'rostered');
                update_post_meta($player_id, 'status_40_man', ''); // Ensure off 40-man
                
                // Clear Contracts
                for ($y = 2026; $y <= 2040; $y++) { delete_post_meta($player_id, 'contract_' . $y); }

                // Deduct Balance (MiLB or ISBP)
                $balance_type = ($bid_type === 'isbp') ? 'isbp' : 'milb';
                $field_name = $balance_type . '_' . strtolower($league_id);
                
                $rows = get_field($field_name, 'option') ?: [];
                foreach ($rows as $idx => $row) {
                    if (($row['team_id'] ?? '') === $winning_team_id) {
                        $rows[$idx]['balance'] = (float)$rows[$idx]['balance'] - $winning_bid_points;
                        update_field($field_name, $rows, 'option');
                        break;
                    }
                }

                // Log
                if ( function_exists('log_league_transaction') ) {
                    $type_label = ($bid_type === 'isbp') ? 'International Signing (ISBP)' : 'Free Agent Signing (MiLB)';
                    log_league_transaction([
                        'transaction_type' => $type_label,
                        'player_ids'       => [$player_id],
                        'primary_team'     => $winning_team_id,
                        'league_id'        => $league_id,
                        'summary'          => esc_html($player_name) . " signed via $type_label by " . esc_html($winning_team_id) . ' ($' . number_format($winning_bid_points) . ').',
                    ]);
                }

            } 
            // --- Case 2: Standard Major League Contract ---
            elseif ( !empty($winning_team_id) && $winning_bid_points > 0 && $winning_years > 0 && $winning_aav >= 0 ) {
                
                update_post_meta($player_id, 'fantasy_team_id', $winning_team_id);
                update_post_meta($player_id, 'fa_status', 'rostered');
                update_post_meta($player_id, 'status_40_man', 'X'); // Force to 40-man

                // Clear old contract data before writing new
                for ($y = 2026; $y <= 2040; $y++) {
                    delete_post_meta($player_id, 'contract_' . $y);
                }

                // Write the new multi-year contract
                $start_year = (int) current_time('Y');
                for ($i = 0; $i < $winning_years; $i++) {
                    $contract_year = $start_year + $i;
                    update_post_meta($player_id, 'contract_' . $contract_year, $winning_aav);
                }

                // Log the transaction
                if ( function_exists('log_league_transaction') ) {
                    $log_args = [
                        'transaction_type' => 'Free Agent Signing',
                        'player_ids'       => [$player_id],
                        'primary_team'     => $winning_team_id,
                        'league_id'        => $league_id,
                        'summary'          => esc_html($player_name) . ' signed by ' . esc_html($winning_team_id) . ' (' . $winning_years . ' years @ $' . number_format($winning_aav) . '/yr).',
                    ];
                    log_league_transaction($log_args);
                }

                // Notify winner
                $mgr = get_userdata($winning_manager_id);
                if ($mgr && !empty($mgr->user_email)) {
                    wp_mail($mgr->user_email, 'Free Agent Bid Won!',
                        'Congratulations! Your bid for ' . $player_name . ' for ' . $winning_bid_points . ' points has won. The player has been assigned to your team: ' . $winning_team_id . '.');
                }

            } else {
                // No valid bid, revert to FA
                update_post_meta($player_id, 'fa_status', 'available');
            }

            // Cleanup all pending fields
            delete_post_meta($player_id, 'bid_type');
            delete_post_meta($player_id, 'pending_bid_team_id');
            delete_post_meta($player_id, 'pending_bid_manager_id');
            delete_post_meta($player_id, 'pending_bid_amount');
            delete_post_meta($player_id, 'pending_bid_years');
            delete_post_meta($player_id, 'pending_bid_aav');
            delete_post_meta($player_id, 'bid_start_time');
            delete_post_meta($player_id, 'bid_end_time');
        }
    }
    wp_reset_postdata();
}
add_action('fa_bid_finalization_event', 'fa_bid_finalization_handler');

/* ------------------------------------------------------------------------
   Cron job to reset seasonal player options on November 1st.
------------------------------------------------------------------------ */

function add_yearly_cron_schedule( $schedules ) {
    if ( ! isset( $schedules['yearly'] ) ) {
        $schedules['yearly'] = array(
            'interval' => YEAR_IN_SECONDS,
            'display'  => __( 'Once Yearly' ),
        );
    }
    return $schedules;
}
add_filter( 'cron_schedules', 'add_yearly_cron_schedule' );

function reset_yearly_player_options_handler() {
    $player_args = array(
        'post_type'      => 'playerdata',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key' => 'roster_moves_log',
                'compare' => 'EXISTS'
            )
        )
    );
    $player_query = new WP_Query($player_args);
    if ( $player_query->have_posts() ) {
        foreach( $player_query->posts as $player_id ) {
            $moves_log = get_field('roster_moves_log', $player_id);
            if ( ! empty($moves_log) && is_array($moves_log) ) {
                $new_log = array();
                foreach( $moves_log as $move ) {
                    if ( isset($move['move_type']) && $move['move_type'] !== 'Optioned' ) {
                        $new_log[] = $move;
                    }
                }
                update_field('roster_moves_log', $new_log, $player_id);
            }
        }
    }
    wp_reset_postdata();
}
add_action( 'reset_player_options_event', 'reset_yearly_player_options_handler' );

function schedule_yearly_options_reset() {
    if ( ! wp_next_scheduled( 'reset_player_options_event' ) ) {
        wp_schedule_event( strtotime('November 1st 2:00 AM'), 'yearly', 'reset_player_options_event' );
    }
}
add_action( 'wp', 'schedule_yearly_options_reset' );

/* ------------------------------------------------------------------------
   Cron job to process players who clear waivers.
------------------------------------------------------------------------ */

function process_cleared_waivers_handler() {
    if ( ! function_exists('get_field') || ! function_exists('update_field') ) {
        error_log("Waiver Cron Error: ACF functions not available.");
        return;
    }

    $now_gmt = current_time('mysql', 1);

    $cleared_waivers_args = array(
        'post_type'      => 'playerdata',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(
            'relation' => 'AND',
            array('key'   => 'fa_status', 'value' => 'on waivers'),
            array('key'     => 'waiver_end_time', 'value'   => $now_gmt, 'compare' => '<=')
        ),
    );
    $cleared_query = new WP_Query($cleared_waivers_args);

    if ( ! $cleared_query->have_posts() ) {
        wp_reset_postdata();
        return;
    }

    $standings_data = null;
    $standings_url = 'https://www.fantrax.com/fxea/general/getStandings?leagueId=w4wlt4b2mg5l9qja';
    $response = wp_remote_get($standings_url);

    if ( !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200 ) {
        $body = wp_remote_retrieve_body($response);
        $standings_data = json_decode($body, true);
    } else {
        error_log("Waiver Cron Error: Could not fetch Fantrax standings.");
    }

    $team_ranks = [];
    if ( is_array($standings_data) ) {
        foreach ($standings_data as $team) {
            if (isset($team['teamName']) && isset($team['rank'])) {
                $team_ranks[ $team['teamName'] ] = (int) $team['rank'];
            }
        }
    }

    foreach ($cleared_query->posts as $player_id) {
        $player_name = get_the_title($player_id);
        $waiving_team_id = get_field('waiving_team_id', $player_id);
        $league_id = get_field('league_id', $player_id);
        $claims = get_field('pending_waiver_claims', $player_id);
        $dfa_action = get_post_meta($player_id, 'dfa_clear_action', true);

        if ( empty($claims) || ! is_array($claims) ) {
            // Player cleared waivers. Check chosen DFA action.
            if ($dfa_action === 'minors') {
                // Return to roster
                update_post_meta($player_id, 'fa_status', 'rostered');
                
                if ( function_exists('log_league_transaction') ) {
                    log_league_transaction([
                        'transaction_type' => 'Roster Move',
                        'player_ids'       => [$player_id],
                        'primary_team'     => $waiving_team_id,
                        'league_id'        => $league_id,
                        'summary'          => $player_name . ' cleared waivers and was assigned to the minors by ' . $waiving_team_id . '.',
                    ]);
                }
            } else {
                // Default: Release player
                if ($waiving_team_id) {
                    $current_year = (int) date('Y');
                    $contract_years = range($current_year, $current_year + 10);
                    foreach ($contract_years as $year) {
                        $salary_raw = get_field('contract_' . $year, $player_id);
                        if (is_numeric($salary_raw) && $salary_raw > 0) {
                            $rate = ($year === $current_year) ? 0.75 : 0.50;
                            $penalty = round($salary_raw * $rate, 0);
                            add_row('dead_cap_penalties', array(
                                'penalty_year'     => $year,
                                'penalty_amount'   => (float)$penalty,
                                'dead_cap_team_id' => $waiving_team_id,
                                'penalty_type'     => ($year === $current_year) ? 'Cleared Waivers (75%)' : 'Cleared Waivers (50%)',
                            ), $player_id);
                        }
                        update_post_meta($player_id, 'contract_' . $year, '');
                    }
                }
                update_post_meta($player_id, 'fa_status', 'available');
                update_post_meta($player_id, 'fantasy_team_id', ''); // Remove from team
            }

        } else {
            $winning_team_id = null;
            $winning_rank = -1;

            foreach ($claims as $claim) {
                $team_id = $claim['claiming_team_id'];
                $team_rank = $team_ranks[ $team_id ] ?? 99;

                if ($team_rank > $winning_rank) {
                    $winning_rank = $team_rank;
                    $winning_team_id = $team_id;
                }
            }

            if ($winning_team_id) {
                update_post_meta($player_id, 'fantasy_team_id', $winning_team_id);
                update_post_meta($player_id, 'fa_status', 'rostered');

                if ( function_exists('log_league_transaction') ) {
                    $log_args = [
                        'transaction_type' => 'Waiver Claim',
                        'player_ids'       => [$player_id],
                        'primary_team'     => $winning_team_id,
                        'secondary_team'   => $waiving_team_id,
                        'league_id'        => $league_id,
                        'summary'          => esc_html($player_name) . ' was claimed off waivers by ' . esc_html($winning_team_id) . ' (Priority: #' . $winning_rank . ').',
                    ];
                    log_league_transaction($log_args);
                }

            } else {
                update_post_meta($player_id, 'fa_status', 'available');
            }
        }

        update_post_meta($player_id, 'waiving_team_id', '');
        update_post_meta($player_id, 'waiver_end_time', '');
        update_post_meta($player_id, 'dfa_clear_action', '');
        update_field('pending_waiver_claims', [], $player_id);

    }
    wp_reset_postdata();
}
add_action('process_waivers_event', 'process_cleared_waivers_handler');

if ( ! wp_next_scheduled( 'process_waivers_event' ) ) {
    wp_schedule_event( time(), 'every_five_minutes', 'process_waivers_event' );
}

/**
 * Daily check to clear IL during offseason.
 */
function fod_clear_injured_list_for_offseason() {
    if ( ! function_exists('fod_is_offseason') || ! fod_is_offseason() ) {
        return;
    }

    $args = [
        'post_type'      => ['playerdata', 'nbaplayer'],
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'status_il',
                'value'   => '',
                'compare' => '!='
            ]
        ]
    ];

    $query = new WP_Query($args);
    if ( $query->have_posts() ) {
        foreach ( $query->posts as $pid ) {
            update_post_meta($pid, 'status_il', '');
            update_post_meta($pid, 'il_start_date', '');
            
            // Log move
            $player_name = get_the_title($pid);
            $team_id = get_post_meta($pid, 'fantasy_team_id', true);
            $league_id = get_post_meta($pid, 'league_id', true);
            
            if ( function_exists('log_league_transaction') ) {
                log_league_transaction([
                    'transaction_type' => 'Roster Move',
                    'player_ids'       => [$pid],
                    'primary_team'     => $team_id,
                    'league_id'        => $league_id,
                    'summary'          => "$player_name automatically activated from IL (Offseason Start).",
                ]);
            }
        }
    }
    wp_reset_postdata();
}
add_action( 'fod_daily_offseason_event', 'fod_clear_injured_list_for_offseason' );

if ( ! wp_next_scheduled( 'fod_daily_offseason_event' ) ) {
    wp_schedule_event( time(), 'daily', 'fod_daily_offseason_event' );
}
