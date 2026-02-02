<?php
/**
 * Internal Unique ID Generator
 * Run via shortcode: [generate_fod_ids]
 */

function fod_generate_internal_ids() {
    if ( ! current_user_can('manage_options') ) return 'Admins only.';

    // 1. Get all players (MLB & NBA)
    $args = [
        'post_type'      => ['playerdata', 'nbaplayer'],
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => 'fod_id',
                'compare' => 'NOT EXISTS' // Only process those missing an ID
            ]
        ]
    ];

    $query = new WP_Query($args);
    $count = 0;
    
    // 2. Find the highest existing ID to start incrementing from
    // We'll use a site option to track the counter to avoid collisions
    $current_counter = (int) get_option('fod_player_id_counter', 10000);

    if ( $query->have_posts() ) {
        foreach ( $query->posts as $pid ) {
            $current_counter++;
            
            // Format: FOD-10001
            $new_id = 'FOD-' . $current_counter;
            
            update_post_meta($pid, 'fod_id', $new_id);
            $count++;
        }
        
        // Save the new counter state
        update_option('fod_player_id_counter', $current_counter);
    }

    return "Generated IDs for $count players. Next ID will be FOD-" . ($current_counter + 1);
}
add_shortcode('generate_fod_ids', 'fod_generate_internal_ids');
