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
    // Test endpoint
    register_rest_route( 'fod/v1', '/test', array(
        'methods'  => 'GET',
        'callback' => function() { return rest_ensure_response(['status' => 'ok']); },
        'permission_callback' => '__return_true'
    ));

    // 1. Get User's Managed Teams
    register_rest_route( 'fod/v1', '/my-teams', array(
        'methods'  => 'GET',
        'callback' => 'fod_get_user_teams',
        'permission_callback' => '__return_true',
    ));

    // 2. Roster Data
    register_rest_route( 'fod/v1', '/roster/(?P<league>[^/]+)/(?P<team>[^/]+)', array(
        'methods'  => 'GET',
        'callback' => 'fod_get_remote_roster',
        'permission_callback' => '__return_true',
    ));

    // 3. Waiver Wire
    register_rest_route( 'fod/v1', '/waivers/(?P<league>[^/]+)', array(
        'methods'  => 'GET',
        'callback' => 'fod_get_remote_waivers',
        'permission_callback' => '__return_true',
    ));

    // 4. Free Agents (with Search)
    register_rest_route( 'fod/v1', '/free-agents/(?P<league>[^/]+)', array(
        'methods'  => 'GET',
        'callback' => 'fod_get_remote_free_agents',
        'permission_callback' => '__return_true',
    ));

    // 5. Activity Feed
    register_rest_route( 'fod/v1', '/activity/(?P<league>[^/]+)', array(
        'methods'  => 'GET',
        'callback' => 'fod_get_remote_activity',
        'permission_callback' => '__return_true',
    ));

    // 6. Bulk Dead Cap Update
    register_rest_route( 'fod/v1', '/bulk-dead-cap', array(
        'methods'  => 'POST',
        'callback' => 'fod_bulk_update_dead_cap',
        'permission_callback' => '__return_true', // In production, add a secret key check
    ));
}
add_action( 'rest_api_init', 'fod_register_rest_routes' );

/**
 * Bulk update dead cap penalties for players.
 */
function fod_bulk_update_dead_cap( $data ) {
    $penalties = $data->get_param('penalties');
    if ( ! is_array($penalties) ) {
        return new WP_Error('invalid_data', 'Penalties must be an array', ['status' => 400]);
    }

    $results = ['success' => 0, 'failed' => 0, 'messages' => []];

    foreach ( $penalties as $p ) {
        $player_name = sanitize_text_field($p['player_name']);
        $league_id   = strtoupper(sanitize_text_field($p['league_id']));
        $team_id     = strtoupper(sanitize_text_field($p['team_id']));
        $year        = intval($p['year']);
        $amount      = floatval($p['amount']);
        $type        = sanitize_text_field($p['type'] ?: 'Dead Cap');

        // Find the player by exact title and league
        $player_post_types = ['playerdata', 'nbaplayer'];
        $pid = 0;

        foreach ($player_post_types as $pt) {
            $check_query = new WP_Query([
                'post_type'      => $pt,
                'title'          => $player_name, // This works if the 'posts_where' filter or exact match is handled, but let's use a safer way
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [
                    ['key' => 'league_id', 'value' => $league_id]
                ]
            ]);

            // If the standard title param fails, try querying by title specifically via SQL
            if ( ! $check_query->have_posts() ) {
                global $wpdb;
                $sql = $wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts p 
                     INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id 
                     WHERE p.post_title = %s 
                     AND p.post_type = %s 
                     AND pm.meta_key = 'league_id' 
                     AND pm.meta_value = %s 
                     LIMIT 1",
                    $player_name, $pt, $league_id
                );
                $found_id = $wpdb->get_var($sql);
                if ($found_id) {
                    $pid = $found_id;
                    break;
                }
            } else {
                $pid = $check_query->posts[0];
                break;
            }
        }

        if ( $pid ) {
            // Add the dead cap penalty using ACF add_row with specific field keys
            if ( function_exists('add_row') ) {
                $new_row = [
                    'field_69250abc47d99' => $year,   // penalty_year
                    'field_69250abc47dd3' => $amount, // penalty_amount
                    'field_69250abc47e0c' => $team_id // dead_cap_team_id
                ];
                add_row('dead_cap_penalties', $new_row, $pid);
                $results['success']++;
            } else {
                $results['failed']++;
                $results['messages'][] = "ACF add_row not found for $player_name";
            }
        } else {
            $results['failed']++;
            $results['messages'][] = "Player not found: $player_name in $league_id";
        }
    }

    return rest_ensure_response($results);
}

/**
 * Get the teams managed by the currently logged-in user
 */
function fod_get_user_teams() {
    $user_id = get_current_user_id();
    
    // If user is not logged in (development mode), return some default teams or all teams
    if (!$user_id) {
       return rest_ensure_response([
           'mlb_leagues' => [
               ['league_id' => 'MLB', 'fantasy_team_id' => 'LAD'],
               ['league_id' => 'AA', 'fantasy_team_id' => 'ROC']
           ],
           'nba_leagues' => [
               ['league_id' => 'NBA', 'fantasy_team_id' => 'LAL']
           ]
       ]);
    }

    $mlb = get_field('managed_teams', 'user_' . $user_id) ?: [];
    $nba = get_field('managed_nba_teams', 'user_' . $user_id) ?: [];
    
    return rest_ensure_response([
        'mlb_leagues' => $mlb,
        'nba_leagues' => $nba
    ]);
}

/**
 * Callback to fetch roster data for a specific league and team
 */
function fod_get_remote_roster( $data ) {
    $league_id = strtoupper(sanitize_text_field($data['league']));
    $team_id   = strtoupper(sanitize_text_field($data['team']));

    $args = [
        'post_type'      => ($league_id === 'NBA') ? 'nbaplayer' : 'playerdata',
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
                'status_il'    => get_post_meta($pid, 'status_il', true),
                'fa_status'    => get_post_meta($pid, 'fa_status', true),
                'contracts'    => $contracts
            ];
        }
    }
    wp_reset_postdata();
    return rest_ensure_response($roster);
}

/**
 * Fetch players currently on waivers
 */
function fod_get_remote_waivers( $data ) {
    $league_id = strtoupper(sanitize_text_field($data['league']));
    
    $args = [
        'post_type' => ($league_id === 'NBA') ? 'nbaplayer' : 'playerdata',
        'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'AND',
            ['key' => 'league_id', 'value' => $league_id],
            ['key' => 'fa_status', 'value' => 'on waivers']
        ]
    ];

    $query = new WP_Query($args);
    $players = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $pid = get_the_ID();
            $players[] = [
                'id'           => $pid,
                'name'         => get_the_title(),
                'position'     => get_post_meta($pid, 'position', true),
                'waiving_team' => get_post_meta($pid, 'waiving_team_id', true),
                'end_time'     => get_post_meta($pid, 'waiver_end_time', true)
            ];
        }
    }
    wp_reset_postdata();
    
    // Always return a response, even if empty, to avoid 404/errors
    return rest_ensure_response($players);
}

/**
 * Fetch available free agents with optional search
 */
function fod_get_remote_free_agents( $data ) {
    $league_id = strtoupper(sanitize_text_field($data['league']));
    $search    = $data->get_param('s') ?: '';
    
    $args = [
        'post_type'      => ($league_id === 'NBA') ? 'nbaplayer' : 'playerdata',
        'posts_per_page' => 50,
        's'              => $search,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'league_id', 'value' => $league_id],
            ['key' => 'fa_status', 'value' => ['available', 'pending_bid'], 'compare' => 'IN']
        ]
    ];

    $query = new WP_Query($args);
    $fa = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $pid = get_the_ID();
            $fa[] = [
                'id'           => $pid,
                'name'         => get_the_title(),
                'position'     => get_post_meta($pid, 'position', true),
                'current_bid'  => get_post_meta($pid, 'pending_bid_amount', true),
                'end_time'     => get_post_meta($pid, 'bid_end_time', true)
            ];
        }
    }
    wp_reset_postdata();
    return rest_ensure_response($fa);
}

/**
 * Fetch recent league activity
 */
function fod_get_remote_activity( $data ) {
    $league_id = strtoupper(sanitize_text_field($data['league']));
    
    $args = [
        'post_type'      => 'transaction',
        'posts_per_page' => 20,
        'meta_query'     => [
            ['key' => 'league_id', 'value' => $league_id]
        ]
    ];

    $query = new WP_Query($args);
    $activity = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $activity[] = [
                'date'    => get_the_date('Y-m-d H:i'),
                'summary' => get_field('transaction_summary')
            ];
        }
    }
    wp_reset_postdata();
    return rest_ensure_response($activity);
}
