<?php
/**
 * Internal Unique ID Generator
 * Run via shortcode: [generate_fod_ids]
 */

/**
 * Dedicated Admin Page for ID Generation
 */
function fod_render_id_gen_page() {
    if ( ! current_user_can('manage_options') ) return;

    global $wpdb;
    $batch_size = 1000;
    $is_running = isset($_GET['run']) && $_GET['run'] == '1';

    // --- 1. Get Diagnostics ---
    $grand_total = $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type IN ('playerdata', 'nbaplayer') AND post_status != 'trash'");
    $total_with_ids = $wpdb->get_var("SELECT COUNT(post_id) FROM {$wpdb->postmeta} WHERE meta_key = 'fod_id' AND meta_value != ''");
    
    $total_missing = $wpdb->get_var("
        SELECT COUNT(p.ID) 
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'fod_id'
        WHERE p.post_type IN ('playerdata', 'nbaplayer') 
        AND p.post_status != 'trash'
        AND (pm.meta_id IS NULL OR pm.meta_value = '' OR pm.meta_value IS NULL)
    ");

    ?>
    <div class="wrap">
        <h1>FOD Unique ID Generator</h1>
        <p>This tool assigns unique internal IDs (e.g., FOD-10001) to all players. These IDs are required for reliable CSV syncing.</p>

        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h2>System Status</h2>
            <ul style="font-size: 1.1em; line-height: 1.6;">
                <li><strong>Total Players in Database:</strong> <?php echo number_format($grand_total); ?></li>
                <li><strong>Players Already Assigned IDs:</strong> <?php echo number_format($total_with_ids); ?></li>
                <li><strong>Players Missing IDs:</strong> <span style="color: <?php echo ($total_missing > 0) ? '#d9534f' : 'green'; ?>; font-weight: bold;"><?php echo number_format($total_missing); ?></span></li>
            </ul>

            <?php if ( $total_missing > 0 ) : ?>
                <hr>
                <?php if ( ! $is_running ) : ?>
                    <p>Click the button below to start the batch generation process. This may take several minutes for large databases.</p>
                    <a href="<?php echo esc_url(add_query_arg('run', '1')); ?>" class="button button-primary button-large">Start Generation Process</a>
                <?php else : ?>
                    <div style="background: #f0f7ff; padding: 15px; border-left: 4px solid #0073aa;">
                        <h3 style="margin-top:0; color:#0073aa;">⚡ Processing Batch...</h3>
                        <?php
                        // --- Execute Batch ---
                        $player_ids = $wpdb->get_col($wpdb->prepare("
                            SELECT p.ID 
                            FROM {$wpdb->posts} p
                            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'fod_id'
                            WHERE p.post_type IN ('playerdata', 'nbaplayer') 
                            AND p.post_status != 'trash'
                            AND (pm.meta_id IS NULL OR pm.meta_value = '' OR pm.meta_value IS NULL)
                            LIMIT %d
                        ", $batch_size));

                        $count_updated = 0;
                        $current_counter = (int) get_option('fod_player_id_counter', 10000);

                        foreach ( $player_ids as $pid ) {
                            $current_counter++;
                            $new_id = 'FOD-' . $current_counter;
                            $wpdb->delete($wpdb->postmeta, ['post_id' => $pid, 'meta_key' => 'fod_id']);
                            $wpdb->insert($wpdb->postmeta, [
                                'post_id'    => $pid,
                                'meta_key'   => 'fod_id',
                                'meta_value' => $new_id
                            ]);
                            $count_updated++;
                        }

                        update_option('fod_player_id_counter', $current_counter);
                        $remaining = $total_missing - $count_updated;
                        
                        echo '<p style="font-size:1.2em;">Just assigned IDs to <strong>' . $count_updated . '</strong> players.</p>';
                        echo '<p>Remaining: <strong>' . number_format($remaining) . '</strong></p>';
                        
                        if ( $remaining > 0 ) {
                            $next_url = add_query_arg('run', '1');
                            echo '<p><em>The page will refresh in 1 second to continue...</em></p>';
                            echo '<script>setTimeout(function(){ window.location.href="' . $next_url . '"; }, 1000);</script>';
                        } else {
                            echo '<p style="color:green; font-weight:bold;">All players have been updated successfully!</p>';
                            echo '<a href="' . admin_url('admin.php?page=fod-id-gen') . '" class="button">Finish</a>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="notice notice-success inline"><p>All players currently have unique IDs. No action needed.</p></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function fod_generate_internal_ids() {
    // This function can remain as a fallback/shortcode trigger if desired
    // but the main logic is now in the dedicated page.
    return "Please use the ID Generator page in the Commish Tools menu.";
}
add_shortcode('generate_fod_ids', 'fod_generate_internal_ids');

/**
 * Automatically generate FOD ID when a player is saved (if missing)
 */
function fod_auto_generate_player_id( $post_id ) {
    // Check post type
    $post_type = get_post_type($post_id);
    if ( ! in_array($post_type, ['playerdata', 'nbaplayer']) ) {
        return;
    }

    // Don't run on autosave or revisions
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;

    // Check if ID already exists
    $existing_id = get_post_meta($post_id, 'fod_id', true);
    if ( ! empty($existing_id) ) {
        return;
    }

    // Generate new ID
    $current_counter = (int) get_option('fod_player_id_counter', 10000);
    $current_counter++;
    
    $new_id = 'FOD-' . $current_counter;
    
    update_post_meta($post_id, 'fod_id', $new_id);
    update_option('fod_player_id_counter', $current_counter);
}
add_action( 'save_post', 'fod_auto_generate_player_id' );
