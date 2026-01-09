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
