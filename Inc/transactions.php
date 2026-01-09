<?php
/**
 * Register the 'transaction' Custom Post Type.
 * This is required for the [league_activity_feed] shortcode to work.
 */
function fod_register_transaction_cpt() {
    $args = array(
        'label'                 => __( 'Transactions', 'text_domain' ),
        'description'           => __( 'Log of all league transactions', 'text_domain' ),
        'labels'                => array(
            'name'          => _x( 'Transactions', 'Post Type General Name', 'text_domain' ),
            'singular_name' => _x( 'Transaction', 'Post Type Singular Name', 'text_domain' ),
            'menu_name'     => __( 'Transactions', 'text_domain' ),
        ),
        'supports'              => array( 'title' ),
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 21,
        'menu_icon'             => 'dashicons-list-view',
        'show_in_admin_bar'     => false,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
    );
    register_post_type( 'transaction', $args );
}
add_action( 'init', 'fod_register_transaction_cpt', 0 );


/**
 * Helper function to create a new transaction log entry.
 */
function log_league_transaction( $args = [] ) {

    if ( ! function_exists('update_field') ) {
        error_log('log_league_transaction ERROR: update_field() function does not exist. Skipping transaction log.');
        return;
    }

    $defaults = [
        'transaction_type' => 'Unknown',
        'player_ids'       => [],
        'primary_team'     => '',
        'secondary_team'   => '',
        'summary'          => '',
        'league_id'        => '', // Add league_id to defaults
    ];
    $args = wp_parse_args( $args, $defaults );

    $post_title = $args['transaction_type'] . ': ' . $args['summary'];

    $post_data = [
        'post_title'  => wp_strip_all_tags( $post_title ),
        'post_type'   => 'transaction',
        'post_status' => 'publish',
    ];

    $post_id = wp_insert_post( $post_data, true );

    if ( $post_id && ! is_wp_error( $post_id ) ) {
        update_field( 'transaction_type', $args['transaction_type'], $post_id );
        update_field( 'involved_players', $args['player_ids'], $post_id );
        update_field( 'primary_team', $args['primary_team'], $post_id );
        update_field( 'secondary_team', $args['secondary_team'], $post_id );
        update_field( 'transaction_summary', $args['summary'], $post_id );
        
        // Save the league ID
        if ( !empty($args['league_id']) ) {
            update_post_meta( $post_id, 'league_id', $args['league_id'] );
        }

    } elseif ( is_wp_error( $post_id ) ) {
        error_log('log_league_transaction ERROR: wp_insert_post failed: ' . $post_id->get_error_message());
    }
}

/**
 * This is the new "listener" function. It waits for the 'my_fantasy_transaction'
 * action to be called, then it safely runs the logging function.
 */
function my_fantasy_transaction_handler( $args ) {
    if ( ! is_array($args) ) {
        $args = [];
    }
    log_league_transaction( $args );
}
add_action( 'my_fantasy_transaction', 'my_fantasy_transaction_handler' );
