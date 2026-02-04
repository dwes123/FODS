<?php

/* ------------------------------------------------------------------------
   Commissioner Tools Parent Menu
------------------------------------------------------------------------ */
function fod_register_commissioner_tools_menu() {
    add_menu_page(
        'Commissioner Tools',
        'Commish Tools',
        'manage_options',
        'commissioner-tools',
        'fod_render_commish_tools_dashboard',
        'dashicons-shield',
        58
    );

    add_submenu_page(
        'commissioner-tools',
        'Arbitration Approvals',
        'Arbitration Approvals',
        'read', 
        'fod-arb-approvals',
        'fod_render_arb_approval_page'
    );

    add_submenu_page(
        'commissioner-tools',
        'Account Approvals',
        'Account Approvals',
        'manage_options',
        'fod-account-approvals',
        'fod_render_account_approval_page'
    );

    add_submenu_page(
        'commissioner-tools',
        'ID Generator',
        'ID Generator',
        'manage_options',
        'fod-id-gen',
        'fod_render_id_gen_page'
    );

    add_submenu_page(
        'commissioner-tools',
        'Trade Reversal',
        'Trade Reversal',
        'manage_options',
        'fod-trade-reversal',
        'fod_render_trade_reversal_page'
    );

    add_submenu_page(
        'commissioner-tools',
        'Bid Export',
        'Bid Export',
        'manage_options',
        'fod-bid-export',
        'fod_render_bid_export_page'
    );
}
add_action('admin_menu', 'fod_register_commissioner_tools_menu');

/**
 * Handle CSV Export for Bids
 */
function fod_handle_bid_export_trigger() {
    if ( isset($_GET['action']) && $_GET['action'] === 'fod_export_bids' && current_user_can('manage_options') ) {
        $team_id = sanitize_text_field($_GET['team_id']);
        $league_id = sanitize_text_field($_GET['league_id']);

        if ( empty($team_id) ) return;

        global $wpdb;

        // Query all players who have a bid from this team in their history
        $sql = $wpdb->prepare("
            SELECT DISTINCT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE 'bid_history_%_history_team_id' 
            AND meta_value = %s
        ", $team_id);
        
        $player_ids = $wpdb->get_col($sql);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=bids-' . $team_id . '-' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Player', 'League', 'Bid Amount', 'Years', 'AAV', 'Timestamp']);

        if ( !empty($player_ids) ) {
            foreach ($player_ids as $pid) {
                $history = get_field('bid_history', $pid);
                $p_name = get_the_title($pid);
                $p_league = get_post_meta($pid, 'league_id', true);

                if ( !empty($league_id) && $league_id !== $p_league ) continue;

                if ( is_array($history) ) {
                    foreach ($history as $bid) {
                        if ( ($bid['history_team_id'] ?? '') === $team_id ) {
                            fputcsv($output, [
                                $p_name,
                                $p_league,
                                $bid['history_bid_amount'],
                                $bid['history_bid_years'],
                                $bid['history_bid_aav'],
                                $bid['history_timestamp']
                            ]);
                        }
                    }
                }
            }
        }
        fclose($output);
        exit;
    }
}
add_action('admin_init', 'fod_handle_bid_export_trigger');

/**
 * Bid Export Page UI
 */
function fod_render_bid_export_page() {
    if ( ! current_user_can('manage_options') ) return;
    global $wpdb;

    $teams = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'fantasy_team_id' AND meta_value != '' ORDER BY meta_value ASC");
    $leagues = ['MLB', 'AAA', 'AA', 'NBA'];

    ?>
    <div class="wrap">
        <h1>Free Agency Bid Export</h1>
        <p>Select a team to export their entire bidding history across all players.</p>

        <div class="card" style="max-width: 500px; padding: 20px; margin-top: 20px;">
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="fod-bid-export">
                <input type="hidden" name="action" value="fod_export_bids">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="team_id">Select Team</label></th>
                        <td>
                            <select name="team_id" id="team_id" required style="width: 100%;">
                                <option value="">-- Choose Team --</option>
                                <?php foreach ($teams as $tid) echo '<option value="'.esc_attr($tid).'">'.esc_html($tid).'</option>'; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="league_id">Filter by League (Optional)</label></th>
                        <td>
                            <select name="league_id" id="league_id" style="width: 100%;">
                                <option value="">All Leagues</option>
                                <?php foreach ($leagues as $lid) echo '<option value="'.esc_attr($lid).'">'.esc_html($lid).'</option>'; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="Download CSV Export">
                </p>
            </form>
        </div>
    </div>
    <?php
}

/**
 * Trade Reversal Tool logic and UI
 */
function fod_render_trade_reversal_page() {
    if ( ! current_user_can('manage_options') ) return;

    global $wpdb;
    $message = '';

    // --- 1. Handle the Reversal ---
    if ( isset($_GET['action']) && $_GET['action'] === 'reverse' && isset($_GET['trade_id']) ) {
        check_admin_referer('reverse_trade_' . $_GET['trade_id']);
        $trade_id = absint($_GET['trade_id']);
        
        // Use get_field because trade data is structured via ACF
        $league_id     = get_field('league_id', $trade_id);
        $proposer_id   = get_field('proposing_manager', $trade_id);
        $target_id     = get_field('target_manager', $trade_id);
        $offered_ids   = get_field('players_offered', $trade_id) ?: [];
        $requested_ids = get_field('players_requested', $trade_id) ?: [];
        $isbp_offered   = (int) get_field('isbp_offered', $trade_id);
        $isbp_requested = (int) get_field('isbp_requested', $trade_id);
        $status        = get_field('trade_status', $trade_id);

        if ( $status === 'accepted' ) {
            // A. Get Team IDs
            $get_team_id = function($uid, $lid) {
                $managed = get_field('managed_teams', 'user_' . $uid);
                if ($managed) { foreach($managed as $t) { if (($t['league_id']??'') === $lid) return $t['fantasy_team_id']; } }
                return false;
            };
            $proposer_team = $get_team_id($proposer_id, $league_id);
            $target_team   = $get_team_id($target_id, $league_id);

            if ( $proposer_team && $target_team ) {
                // B. Swap Players Back
                foreach ($offered_ids as $pid)   { update_post_meta($pid, 'fantasy_team_id', $proposer_team); }
                foreach ($requested_ids as $pid) { update_post_meta($pid, 'fantasy_team_id', $target_team); }

                // C. Reverse ISBP transfers
                if ($isbp_offered > 0 || $isbp_requested > 0) {
                    $field_name = 'isbp_' . strtolower($league_id);
                    $rows = get_field($field_name, 'option') ?: [];
                    
                    if (!empty($rows)) {
                        foreach ($rows as $idx => $row) {
                            // Target sends back offered ISBP to proposer
                            if ($isbp_offered > 0) {
                                if (($row['team_id'] ?? '') === $target_team)   $rows[$idx]['balance'] = (int)$rows[$idx]['balance'] - $isbp_offered;
                                if (($row['team_id'] ?? '') === $proposer_team) $rows[$idx]['balance'] = (int)$rows[$idx]['balance'] + $isbp_offered;
                            }
                            // Proposer sends back requested ISBP to target
                            if ($isbp_requested > 0) {
                                if (($row['team_id'] ?? '') === $proposer_team) $rows[$idx]['balance'] = (int)$rows[$idx]['balance'] - $isbp_requested;
                                if (($row['team_id'] ?? '') === $target_team)   $rows[$idx]['balance'] = (int)$rows[$idx]['balance'] + $isbp_requested;
                            }
                        }
                        update_field($field_name, $rows, 'option');
                    }
                }

                // D. Restore Pro-rated Salaries and Remove Dead Cap
                $all_traded = array_merge($offered_ids, $requested_ids);
                $curr_yr = date('Y');
                foreach ($all_traded as $pid) {
                    $penalties = get_field('dead_cap_penalties', $pid) ?: [];
                    if (!empty($penalties)) {
                        $new_penalties = [];
                        $restored_val = 0;
                        foreach ($penalties as $p) {
                            // Match trade-specific penalties
                            if ($p['penalty_year'] == $curr_yr && (strpos($p['penalty_type'], 'Pro-Rated') !== false || strpos($p['penalty_type'], 'Retained') !== false)) {
                                $restored_val += (float) $p['penalty_amount'];
                            } else {
                                $new_penalties[] = $p;
                            }
                        }
                        if ($restored_val > 0) {
                            $current_salary = (float) get_post_meta($pid, 'contract_' . $curr_yr, true);
                            update_post_meta($pid, 'contract_' . $curr_yr, $current_salary + $restored_val);
                            update_field('dead_cap_penalties', $new_penalties, $pid);
                        }
                    }
                }

                // E. Mark Trade as Reversed
                update_post_meta($trade_id, 'trade_status', 'reversed');
                $message = '<div class="notice notice-success"><p>Trade successfully reversed. Rosters and financials restored.</p></div>';
                
                if ( function_exists('log_league_transaction') ) {
                    log_league_transaction([
                        'transaction_type' => 'Admin Correction',
                        'primary_team'     => $proposer_team,
                        'secondary_team'   => $target_team,
                        'league_id'        => $league_id,
                        'summary'          => "TRADE REVERSED: $proposer_team and $target_team trade (Trade ID: $trade_id) has been undone by commissioner."
                    ]);
                }
            } else {
                $message = '<div class="notice notice-error"><p>Error: Could not identify teams for reversal.</p></div>';
            }
        }
    }

    // --- 2. Query Accepted Trades ---
    $accepted_trades = new WP_Query([
        'post_type'      => 'trade_proposal',
        'post_status'    => 'any',
        'posts_per_page' => 20,
        'meta_query'     => [['key' => 'trade_status', 'value' => 'accepted']]
    ]);

    // Closure to get team ID for display
    $get_team_id_display = function($uid, $lid) {
        $managed = get_field('managed_teams', 'user_' . $uid);
        if ($managed) { foreach($managed as $t) { if (($t['league_id']??'') === $lid) return $t['fantasy_team_id']; } }
        return 'N/A';
    };

    ?>
    <div class="wrap">
        <h1>Trade Reversal Tool</h1>
        <?php echo $message; ?>
        <p>List of recently accepted trades. Reversing a trade will swap players back, refund ISBP, and restore salaries.</p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>League</th>
                    <th>Teams</th>
                    <th>Summary</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($accepted_trades->have_posts()) : while ($accepted_trades->have_posts()) : $accepted_trades->the_post(); 
                    $tid = get_the_ID();
                    $l_id = get_field('league_id', $tid);
                    $p_uid = get_field('proposing_manager', $tid);
                    $t_uid = get_field('target_manager', $tid);
                    
                    $team_a = $get_team_id_display($p_uid, $l_id);
                    $team_b = $get_team_id_display($t_uid, $l_id);
                ?>
                    <tr>
                        <td><?php echo get_the_date('M j, Y'); ?></td>
                        <td><?php echo esc_html($l_id); ?></td>
                        <td><strong><?php echo esc_html($team_a); ?></strong> <span style="color:#999;">&</span> <strong><?php echo esc_html($team_b); ?></strong></td>
                        <td><?php the_title(); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(add_query_arg(['action'=>'reverse', 'trade_id'=>$tid]), 'reverse_trade_'.$tid); ?>" 
                               class="button" onclick="return confirm('Reverse this trade and restore all rosters/salaries?')">Reverse Trade</a>
                        </td>
                    </tr>
                <?php endwhile; else : ?>
                    <tr><td colspan="5">No accepted trades found.</td></tr>
                <?php endif; wp_reset_postdata(); ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * ID Generator Page Renderer
 */
function fod_render_id_gen_page() {
    if ( ! current_user_can('manage_options') ) return;

    global $wpdb;
    $batch_size = 1000;
    $is_running = isset($_GET['run']) && $_GET['run'] == '1';

    // Get Diagnostics
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
        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h2>System Status</h2>
            <ul style="font-size: 1.1em; line-height: 1.6;">
                <li><strong>Total Players:</strong> <?php echo number_format($grand_total); ?></li>
                <li><strong>Assigned IDs:</strong> <?php echo number_format($total_with_ids); ?></li>
                <li><strong>Missing IDs:</strong> <span style="color: <?php echo ($total_missing > 0) ? '#d9534f' : 'green'; ?>;"><?php echo number_format($total_missing); ?></span></li>
            </ul>

            <?php if ( $total_missing > 0 ) : ?>
                <hr>
                <?php if ( ! $is_running ) : ?>
                    <a href="<?php echo esc_url(add_query_arg('run', '1')); ?>" class="button button-primary button-large">Start Generation</a>
                <?php else : ?>
                    <div style="background: #f0f7ff; padding: 15px; border-left: 4px solid #0073aa;">
                        <h3 style="margin-top:0;">⚡ Processing Batch...</h3>
                        <?php
                        $player_ids = $wpdb->get_col($wpdb->prepare("
                            SELECT p.ID FROM {$wpdb->posts} p
                            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'fod_id'
                            WHERE p.post_type IN ('playerdata', 'nbaplayer') AND p.post_status != 'trash'
                            AND (pm.meta_id IS NULL OR pm.meta_value = '' OR pm.meta_value IS NULL)
                            LIMIT %d
                        ", $batch_size));

                        $count_updated = 0;
                        $current_counter = (int) get_option('fod_player_id_counter', 10000);

                        foreach ( $player_ids as $pid ) {
                            $current_counter++;
                            $new_id = 'FOD-' . $current_counter;
                            $wpdb->delete($wpdb->postmeta, ['post_id' => $pid, 'meta_key' => 'fod_id']);
                            $wpdb->insert($wpdb->postmeta, ['post_id' => $pid, 'meta_key' => 'fod_id', 'meta_value' => $new_id]);
                            $count_updated++;
                        }

                        update_option('fod_player_id_counter', $current_counter);
                        $remaining = $total_missing - $count_updated;
                        
                        echo '<p>Updated <strong>' . $count_updated . '</strong> players. <strong>' . number_format($remaining) . '</strong> remaining.</p>';
                        
                        if ( $remaining > 0 ) {
                            echo '<script>setTimeout(function(){ window.location.href="' . esc_url(add_query_arg('run', '1')) . '"; }, 1000);</script>';
                        } else {
                            echo '<p style="color:green; font-weight:bold;">Complete!</p>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Simple dashboard/overview for Commish Tools
 */
function fod_render_commish_tools_dashboard() {
    ?>
    <div class="wrap">
        <h1>League Commissioner Tools</h1>
        <p>Welcome to the central hub for league operations.</p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
            <div class="card" style="padding: 20px;">
                <h3>Arbitration Approvals</h3>
                <a href="<?php echo admin_url('admin.php?page=fod-arb-approvals'); ?>" class="button button-primary">Open Approvals</a>
            </div>
            
            <div class="card" style="padding: 20px;">
                <h3>Assign Player</h3>
                <a href="<?php echo admin_url('admin.php?page=fod-assign-player'); ?>" class="button button-primary">Open Assignment Tool</a>
            </div>

            <div class="card" style="padding: 20px;">
                <h3>Import Players</h3>
                <a href="<?php echo admin_url('admin.php?page=fod-player-importer'); ?>" class="button button-primary">Open Importer</a>
            </div>

            <div class="card" style="padding: 20px;">
                <h3>FOD ID Generator</h3>
                <a href="<?php echo admin_url('admin.php?page=fod-id-gen'); ?>" class="button button-secondary">Open ID Generator</a>
            </div>
        </div>

        <div class="card" style="margin-top: 20px; padding: 20px;">
            <h3>Recent Pending Fantrax Transactions</h3>
            <?php
            $pending_query = new WP_Query([
                'post_type' => 'transaction',
                'posts_per_page' => 10,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'relation' => 'OR',
                        ['key' => 'fantrax_processed', 'value' => '0'],
                        ['key' => 'fantrax_processed', 'compare' => 'NOT EXISTS']
                    ],
                    [
                        'key'     => 'transaction_type',
                        'value'   => ['Trade', 'Free Agent Signing', 'Waiver Claim', 'Draft Pick', 'Team Option', 'International Signing (ISBP)', 'Free Agent Signing (MiLB)'],
                        'compare' => 'IN'
                    ]
                ]
            ]);

            if ($pending_query->have_posts()) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Date</th><th>League</th><th>Summary</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php while ($pending_query->have_posts()) : $pending_query->the_post(); 
                            $tid = get_the_ID();
                            $toggle_url = wp_nonce_url(admin_url('admin-post.php?action=toggle_fantrax_processed&post_id=' . $tid), 'toggle_fantrax_' . $tid);
                        ?>
                            <tr>
                                <td><?php echo get_the_date('M j, g:i a'); ?></td>
                                <td><?php echo esc_html(get_post_meta($tid, 'league_id', true)); ?></td>
                                <td><?php the_title(); ?></td>
                                <td><a href="<?php echo esc_url($toggle_url); ?>" class="button button-small">Mark Processed</a></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>✅ All transactions processed!</p>
            <?php endif; wp_reset_postdata(); ?>
        </div>
    </div>
    <?php
}

/* ------------------------------------------------------------------------
   Player Data List Enhancements
------------------------------------------------------------------------ */
function fod_set_player_columns($columns) {
    $new_columns = [];
    foreach($columns as $key => $value) {
        if ($key === 'title') { $new_columns['fod_id'] = 'FOD ID'; }
        $new_columns[$key] = $value;
    }
    return $new_columns;
}
add_filter('manage_playerdata_posts_columns', 'fod_set_player_columns');
add_filter('manage_nbaplayer_posts_columns', 'fod_set_player_columns');

function fod_populate_player_columns($column, $post_id) {
    if ($column === 'fod_id') {
        $fod_id = get_post_meta($post_id, 'fod_id', true);
        echo '<strong>' . esc_html($fod_id ?: '–') . '</strong>';
    }
}
add_action('manage_playerdata_posts_custom_column', 'fod_populate_player_columns', 10, 2);
add_action('manage_nbaplayer_posts_custom_column', 'fod_populate_player_columns', 10, 2);

function fod_add_player_id_metabox() {
    foreach (['playerdata', 'nbaplayer'] as $screen) {
        add_meta_box('fod_player_id_box', 'Internal Unique ID', 'fod_render_player_id_metabox', $screen, 'side', 'high');
    }
}
add_action('add_meta_boxes', 'fod_add_player_id_metabox');

function fod_render_player_id_metabox($post) {
    $fod_id = get_post_meta($post->ID, 'fod_id', true);
    echo '<p style="font-size: 1.2em; font-weight: bold; color: #0073aa; margin: 0;">' . esc_html($fod_id ?: 'Not Generated Yet') . '</p>';
}

function fod_display_id_below_title() {
    global $post;
    if ( !is_object($post) || !in_array($post->post_type, ['playerdata', 'nbaplayer']) ) return;
    $fod_id = get_post_meta($post->ID, 'fod_id', true);
    if ( $fod_id ) {
        echo '<div class="notice notice-info" style="margin: 10px 0; padding: 10px; border-left: 4px solid #0073aa;"><code style="font-size: 1.4em; font-weight: bold; background: none;">' . esc_html($fod_id) . '</code></div>';
    }
}
add_action('edit_form_after_title', 'fod_display_id_below_title');
