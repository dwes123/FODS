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
        update_field( 'involved_player_s', $args['player_ids'], $post_id );
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

/* ------------------------------------------------------------------------
   Admin Enhancements for Transactions (Fantrax Tracking)
------------------------------------------------------------------------ */

// 1. Add "Fantrax" status column to the list
function fod_set_transaction_columns($columns) {
    $new_columns = [];
    foreach($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['fantrax_status'] = 'Fantrax';
            $new_columns['league_id'] = 'League';
        }
    }
    return $new_columns;
}
add_filter('manage_transaction_posts_columns', 'fod_set_transaction_columns');

// 2. Populate the column with a toggle link
function fod_populate_transaction_columns($column, $post_id) {
    if ($column === 'fantrax_status') {
        $processed = get_field('fantrax_processed', $post_id);
        $url = wp_nonce_url(admin_url('admin-post.php?action=toggle_fantrax_processed&post_id=' . $post_id), 'toggle_fantrax_' . $post_id);
        
        if ($processed) {
            echo '<a href="' . esc_url($url) . '" title="Mark as Pending"><span class="dashicons dashicons-yes" style="color: green; font-size: 30px;"></span></a>';
        } else {
            echo '<a href="' . esc_url($url) . '" title="Mark as Processed"><span class="dashicons dashicons-no-alt" style="color: red; font-size: 30px;"></span></a>';
        }
    }
    if ($column === 'league_id') {
        echo esc_html(get_post_meta($post_id, 'league_id', true));
    }
}
add_action('manage_transaction_posts_custom_column', 'fod_populate_transaction_columns', 10, 2);

// 3. Handle the quick-toggle action
function fod_handle_fantrax_toggle() {
    if (!isset($_GET['post_id']) || !current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $post_id = absint($_GET['post_id']);
    check_admin_referer('toggle_fantrax_' . $post_id);

    $current_status = get_field('fantrax_processed', $post_id);
    update_field('fantrax_processed', !$current_status, $post_id);

    wp_redirect(wp_get_referer());
    exit;
}
add_action('admin_post_toggle_fantrax_processed', 'fod_handle_fantrax_toggle');

/**
 * Helper to send messages to a Slack channel via Web API.
 * Supports unique Bot Tokens and Channels per League (Multi-Workspace).
 * 
 * @param string $message   The message text
 * @param string $league_id The league (MLB, AAA, etc.)
 * @param string $type      'trade_block' (default), 'completed_trade', or 'stat_alert'
 */
function fod_send_slack_notification($message, $league_id = '', $type = 'trade_block') {
    $configs = get_field('league_slack_channels', 'option');
    
    if (!is_array($configs)) {
        return;
    }

    $bot_token  = '';
    $channel_id = '';

    foreach ( $configs as $row ) {
        if ( strtoupper($row['league_id'] ?? '') === strtoupper($league_id) ) {
            $bot_token = $row['bot_token'] ?? '';
            
            // Determine Channel ID based on type
            if ($type === 'completed_trade') {
                $channel_id = $row['completed_trades_channel_id'] ?? '';
            } elseif ($type === 'stat_alert') {
                $channel_id = $row['stat_alerts_channel_id'] ?? '';
            } else {
                $channel_id = $row['channel_id'] ?? ''; // Default: Trade Block
            }
            
            break;
        }
    }

    if (empty($bot_token) || empty($channel_id)) {
        return;
    }

    $payload = [
        'channel' => $channel_id,
        'text'    => $message
    ];

    wp_remote_post('https://slack.com/api/chat.postMessage', [
        'method'      => 'POST',
        'timeout'     => 15,
        'headers'     => [
            'Content-Type'  => 'application/json; charset=utf-8',
            'Authorization' => 'Bearer ' . $bot_token
        ],
        'body'        => json_encode($payload),
        'blocking'    => true
    ]);
}
