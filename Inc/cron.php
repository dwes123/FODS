<?php
/* ------------------------------------------------------------------------
   Cron schedule for FA bid finalization
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

// Cron finalization: assign player + write contracts (from pending offers or 1yr default)
function fa_bid_finalization_handler() {
    if ( ! function_exists('get_field') || ! function_exists('update_field') ) {
        error_log("FA Bid Finalization Cron: ACF functions not available. Aborting.");
        return;
    }
    $now_mysql = current_time('mysql', true);

    $args = array(
        'post_type'      => 'player',
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
    $finalized = 0; $reverted = 0;

    if ( $expired->have_posts() ) {
        foreach ( $expired->posts as $player_id ) {
            $player_name = get_the_title($player_id);
            $team_id = get_field('pending_bid_team_id', $player_id);
            $amount  = get_field('pending_bid_amount',  $player_id);
            $manager = get_field('pending_bid_manager_id', $player_id);

            if ( !empty($team_id) && is_numeric($amount) && $amount > 0 ) {
                $ok = true;
                $ok = $ok && update_field('fantasy_team_id', $team_id, $player_id);
                $ok = $ok && update_field('fa_status', 'rostered', $player_id);

                // Apply contracts (from stored offers or default 1yr)
                $offers   = get_post_meta($player_id, 'pending_bid_contract_offers', true);
                $year_now = (int) current_time('Y');
                if ( is_array($offers) && !empty($offers) ) {
                    foreach ($offers as $yr => $amt) {
                        if (!is_numeric($yr) || !is_numeric($amt)) continue;
                        update_field('contract_' . (int)$yr, (float)$amt, $player_id);
                    }
                } else {
                    update_field('contract_' . $year_now, (float)$amount, $player_id);
                }

                // Clear pending fields
                update_field('pending_bid_team_id',    '', $player_id);
                update_field('pending_bid_manager_id', '', $player_id);
                update_field('pending_bid_amount',     '', $player_id);
                update_field('bid_start_time',         '', $player_id);
                update_field('bid_end_time',           '', $player_id);
                delete_post_meta($player_id, 'pending_bid_contract_offers');

                if ($ok) {
                    $finalized++;
                    $mgr = get_userdata($manager);
                    if ($mgr && !empty($mgr->user_email)) {
                        wp_mail($mgr->user_email, 'Free Agent Bid Won!',
                            'Congratulations! Your bid for ' . $player_name .
                            ' has been finalized. Player assigned to team ' . $team_id . '.');
                    }
                }
            } else {
                update_field('fa_status', 'available', $player_id);
                update_field('pending_bid_team_id',    '', $player_id);
                update_field('pending_bid_manager_id', '', $player_id);
                update_field('pending_bid_amount',     '', $player_id);
                update_field('bid_start_time',         '', $player_id);
                update_field('bid_end_time',           '', $player_id);
                delete_post_meta($player_id, 'pending_bid_contract_offers');
                $reverted++;
            }
        }
    }
    wp_reset_postdata();
    error_log("FA Bid Finalization Cron: Finished. Finalized {$finalized}, reverted {$reverted}.");
}
add_action('fa_bid_finalization_event', 'fa_bid_finalization_handler');

/* ------------------------------------------------------------------------
   Cron job to reset seasonal player options on November 1st.
------------------------------------------------------------------------ */

/**
 * Add a 'yearly' schedule if it doesn't exist.
 */
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

/**
 * The function that will run once a year to clean the logs.
 */
function reset_yearly_player_options_handler() {
    // Get all players that might have a moves log
    $player_args = array(
        'post_type'      => array('player', 'playerdata'),
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
                // Loop through the log and only keep moves that are NOT 'Optioned'
                foreach( $moves_log as $move ) {
                    if ( isset($move['move_type']) && $move['move_type'] !== 'Optioned' ) {
                        $new_log[] = $move;
                    }
                }
                // Save the cleaned log back to the player
                update_field('roster_moves_log', $new_log, $player_id);
            }
        }
    }
    wp_reset_postdata();
}
add_action( 'reset_player_options_event', 'reset_yearly_player_options_handler' );

/**
 * Schedule the cleanup event if it's not already scheduled.
 */
function schedule_yearly_options_reset() {
    if ( ! wp_next_scheduled( 'reset_player_options_event' ) ) {
        // Schedule to run at 2 AM on the next November 1st
        wp_schedule_event( strtotime('November 1st 2:00 AM'), 'yearly', 'reset_player_options_event' );
    }
}
add_action( 'wp', 'schedule_yearly_options_reset' );

/* ------------------------------------------------------------------------
   Cron job to process players who clear waivers.
------------------------------------------------------------------------ */
function process_cleared_waivers_handler() {
    $now_gmt = current_time('mysql', 1); // Get current time in GMT for comparison

    $cleared_waivers_args = array(
        'post_type'      => array('player', 'playerdata'),
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'   => 'fa_status',
                'value' => 'on_waivers',
            ),
            array(
                'key'     => 'waiver_end_time',
                'value'   => $now_gmt,
                'compare' => '<=',
                'type'    => 'DATETIME',
            ),
        ),
    );
    $cleared_query = new WP_Query($cleared_waivers_args);

    if ( $cleared_query->have_posts() ) {
        foreach ($cleared_query->posts as $player_id) {
            $waiving_team_id = get_field('waiving_team_id', $player_id);
            if (!$waiving_team_id) continue;

            // --- Apply original Drop Player logic ---
            $current_year = (int) date('Y');
            $contract_years = range($current_year, $current_year + 10);

            foreach ($contract_years as $year) {
                $salary_raw = get_field('contract_' . $year, $player_id);
                if (is_numeric($salary_raw) && $salary_raw > 0) {
                    $rate = ($year === $current_year) ? 0.75 : 0.50;
                    $penalty = round($salary_raw * $rate, 0);

                    $row = array(
                        'penalty_year'     => $year,
                        'penalty_amount'   => (float)$penalty,
                        'dead_cap_team_id' => $waiving_team_id,
                        'penalty_type'     => ($year === $current_year) ? 'Cleared Waivers (75%)' : 'Cleared Waivers (50%)',
                    );
                    add_row('dead_cap_penalties', $row, $player_id);
                }
            }

            // Clear contract and finalize status
            foreach ($contract_years as $year) {
                update_field('contract_' . $year, '', $player_id);
            }
            update_field('fa_status', 'available', $player_id);
            update_field('waiving_team_id', '', $player_id);
            update_field('waiver_end_time', '', $player_id);
        }
    }
    wp_reset_postdata();
}
add_action('process_waivers_event', 'process_cleared_waivers_handler');

// Schedule the event if it's not already scheduled
if ( ! wp_next_scheduled( 'process_waivers_event' ) ) {
    // Use the five-minute schedule we already created for FA bids
    wp_schedule_event( time(), 'every_five_minutes', 'process_waivers_event' );
}
