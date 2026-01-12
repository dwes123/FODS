<?php
/**
 * Custom REST API Endpoints for Mobile App Integration
 * This allows a mobile app to fetch raw data (JSON) instead of HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the custom routes
 */
function fod_register_rest_routes() {
    register_rest_route( 'fod/v1', '/roster/(?P<league>[a-zA-Z0-9-]+)/(?P<team>[a-zA-Z0-9-]+)', array(
        'methods'  => 'GET',
        'callback' => 'fod_get_remote_roster',
        'permission_callback' => '__return_true', // In production, we would use token-based auth
    ));
}
add_action( 'rest_api_init', 'fod_register_rest_routes' );

/**
 * Callback to fetch roster data for a specific league and team
 */
function fod_get_remote_roster( $data ) {
    $league_id = strtoupper(sanitize_text_field($data['league']));
    $team_id   = strtoupper(sanitize_text_field($data['team']));

    $args = [
        'post_type'      => 'playerdata',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'league_id', 'value' => $league_id],
            ['key' => 'fantasy_team_id', 'value' => $team_id]
        ]
    ];

    $query = new WP_Query($args);
    $roster = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $pid = get_the_ID();
            
            // Collect salary data for the next few years
            $contracts = [];
            foreach (range(2026, 2030) as $year) {
                $salary = get_post_meta($pid, 'contract_' . $year, true);
                if ($salary) $contracts[$year] = $salary;
            }

            $roster[] = [
                'id'           => $pid,
                'name'         => get_the_title(),
                'position'     => get_post_meta($pid, 'position', true),
                'mlb_team'     => get_post_meta($pid, 'mlb_team', true),
                'status_40'    => get_post_meta($pid, 'status_40_man', true) === 'X',
                'status_26'    => get_post_meta($pid, 'status_26_man', true) == '1',
                'fa_status'    => get_post_meta($pid, 'fa_status', true),
                'contracts'    => $contracts
            ];
        }
    }
    wp_reset_postdata();

    if (empty($roster)) {
        return new WP_Error('no_roster', 'No players found for this team/league', ['status' => 404]);
    }

    return rest_ensure_response($roster);
}
