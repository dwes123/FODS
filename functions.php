<?php
/**
 * Twenty Twenty-Two Child Theme Functions
 *
 * This file acts as a loader for organizing theme functions from the /inc/ directory.
 * All new functionality should be added to the appropriate file in that folder.
 */

// Load ACF Options Page setup
require_once get_stylesheet_directory() . '/inc/acf.php';

// Load custom cron jobs
require_once get_stylesheet_directory() . '/inc/cron.php';

// Load script and style enqueueing
require_once get_stylesheet_directory() . '/inc/enqueue.php';

// Load admin-specific functionality (like the "Dead Cap Admin" page)
require_once get_stylesheet_directory() . '/inc/admin.php';

// Load Roster Helper Functions
require_once get_stylesheet_directory() . '/inc/roster-helpers.php';

// Load all theme shortcodes
require_once get_stylesheet_directory() . '/inc/shortcodes.php';
require_once get_stylesheet_directory() . '/inc/nba-shortcodes.php';

//Load All Transactions (MUST BE LOADED BEFORE AJAX HANDLERS)
require_once get_stylesheet_directory() . '/inc/transactions.php';

// Load all AJAX and form submission handlers
require_once get_stylesheet_directory() . '/inc/ajax-handlers.php';

// Load CSV Importer
require_once get_stylesheet_directory() . '/inc/csv-importer.php';

// Load Player Assignment Tool
require_once get_stylesheet_directory() . '/inc/admin-player-assign.php';

// Load Weekly Rotation System
require_once get_stylesheet_directory() . '/inc/weekly-rotations.php';

// Load Admin Filters
require_once get_stylesheet_directory() . '/inc/admin-filters.php';

// Load Admin User Columns & Filters
require_once get_stylesheet_directory() . '/inc/admin-user-columns.php';

// Load Arbitration System
require_once get_stylesheet_directory() . '/inc/arbitration.php';
require_once get_stylesheet_directory() . '/inc/admin-arbitration-approval.php';

// Load Bug Reporting System
require_once get_stylesheet_directory() . '/inc/bug-reports.php';

// Load Contract Extension Calculator
require_once get_stylesheet_directory() . '/inc/contract-extensions.php';

// Load Mobile API Endpoints
require_once get_stylesheet_directory() . '/inc/api-endpoints.php';

/**
 * Register the NBA Player Custom Post Type
 */
function fod_register_nba_cpt() {
    $args = array(
        'label'                 => __( 'NBA Players', 'text_domain' ),
        'labels'                => array(
            'name'          => 'NBA Players',
            'singular_name' => 'NBA Player',
            'menu_name'     => 'NBA Players',
        ),
        'supports'              => array( 'title', 'custom-fields' ),
        'public'                => true,
        'show_ui'               => true,
        'menu_position'         => 6,
        'menu_icon'             => 'dashicons-basketball',
        'has_archive'           => false,
        'show_in_rest'          => true,
    );
    register_post_type( 'nbaplayer', $args );
}
add_action( 'init', 'fod_register_nba_cpt', 0 );

/**
 * Register the Pending Arbitration Custom Post Type
 */
function fod_register_pending_arb_cpt() {
    $args = array(
        'label'                 => __( 'Pending Arbitrations', 'text_domain' ),
        'labels'                => array(
            'name'          => 'Pending Arbitrations',
            'singular_name' => 'Pending Arbitration',
        ),
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => 'tools.php', // Hide under Tools menu
        'capability_type'       => 'post',
        'supports'              => array( 'title', 'custom-fields', 'author' ),
    );
    register_post_type( 'pending_arb', $args );
}
add_action( 'init', 'fod_register_pending_arb_cpt', 0 );


/**
 * Auto-create acf-json folder to sync fields for AI/IntelliJ
 */
add_filter('acf/settings/save_json', 'my_acf_json_save_point');
function my_acf_json_save_point( $path ) {
    // Set the path to the acf-json folder in your theme
    $path = get_stylesheet_directory() . '/acf-json';

    // Automatically create the folder if it doesn't exist
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }

    return $path;
}

/**
 * Unregister the conflicting 'player' post type
 */
function fod_unregister_player_cpt() {
    unregister_post_type( 'player' );
}
add_action( 'init', 'fod_unregister_player_cpt', 100 ); // High priority to run after it's registered

/**
 * SIMPLE DIAGNOSTICS SHORTCODE
 * Usage: [fod_status]
 */
add_shortcode('fod_status', function() {
    if (!current_user_can('manage_options')) return 'Admin Only';
    
    global $wpdb;
    $out = '<div style="padding:20px;background:#fff;border:4px solid #333;color:#000;z-index:99999;position:relative;">';
    $out .= '<h2 style="margin-top:0;">System Status Check</h2>';

    // 1. Check if "playerdata" CPT exists
    $pt = get_post_type_object('playerdata');
    $out .= '<p style="font-size:16px;"><strong>1. Player Data Post Type:</strong> ';
    if ($pt) {
        $out .= '<span style="color:green;font-weight:bold;">✅ REGISTERED</span> (Label: ' . $pt->label . ')';
    } else {
        $out .= '<span style="color:red;font-weight:bold;">❌ MISSING</span> <br><em>(This means the Post Type was deleted or not registered. Check CPT UI settings.)</em>';
    }
    $out .= '</p>';

    // 2. Check Database Content
    $count = wp_count_posts('playerdata');
    $published = $count->publish ?? 0;
    $out .= '<p style="font-size:16px;"><strong>2. Published Players in DB:</strong> ' . $published . '</p>';

    // 3. Test Team Discovery (The fix I applied earlier)
    $test_league = 'AAA';
    $sql = $wpdb->prepare(
        "SELECT DISTINCT pm_team.meta_value 
         FROM {$wpdb->postmeta} pm_team
         INNER JOIN {$wpdb->postmeta} pm_league ON pm_team.post_id = pm_league.post_id
         INNER JOIN {$wpdb->posts} p ON pm_team.post_id = p.ID
         WHERE pm_league.meta_key = 'league_id' 
           AND pm_league.meta_value = %s
           AND pm_team.meta_key = 'fantasy_team_id' 
           AND pm_team.meta_value != ''
           AND p.post_status = 'publish' LIMIT 5",
        $test_league
    );
    $teams = $wpdb->get_col($sql);
    
    $out .= '<p style="font-size:16px;"><strong>3. Team Discovery Test (AAA):</strong> ';
    if (!empty($teams)) {
        $out .= '<span style="color:green;font-weight:bold;">✅ SUCCESS</span> (Found: ' . implode(', ', $teams) . ')';
    } else {
        $out .= '<span style="color:red;font-weight:bold;">❌ FAILED</span> (No teams found via SQL query)';
    }
    $out .= '</p>';

    $out .= '</div>';
    return $out;
});
