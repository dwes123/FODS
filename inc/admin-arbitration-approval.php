<?php
/**
 * Commissioner Tool: Arbitration Approvals
 */

function fod_arb_approval_tool_menu() {
    add_submenu_page(
        'tools.php',
        'Arbitration Approvals',
        'Arbitration Approvals',
        'read', // Allow subscribers to access if they are commissioners
        'fod-arb-approvals',
        'fod_render_arb_approval_page'
    );
}
add_action('admin_menu', 'fod_arb_approval_tool_menu');

function fod_render_arb_approval_page() {
    $user_id = get_current_user_id();
    
    // 1. Get Commissioner Leagues
    $commish_leagues = [];
    if ( function_exists('get_field') ) {
        $roles = get_field('commissioner_of_leagues', 'user_' . $user_id);
        if ( is_array($roles) ) {
            foreach($roles as $role) {
                if (!empty($role['league_id'])) {
                    $commish_leagues[] = $role['league_id'];
                }
            }
        }
    }

    // Allow full admins to see everything
    if ( current_user_can('manage_options') ) {
        $commish_leagues = ['MLB', 'AAA', 'AA', 'NBA']; 
    }

    if ( empty($commish_leagues) ) {
        echo '<div class="wrap"><h1>Arbitration Approvals</h1><p>You are not designated as a commissioner for any league.</p></div>';
        return;
    }

    // 2. Handle Actions (Approve/Reject)
    $message = '';
    if ( isset($_GET['action'], $_GET['id'], $_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'arb_action_' . $_GET['id']) ) {
        $post_id = absint($_GET['id']);
        $action = $_GET['action'];
        
        // Verify this pending item belongs to a league the user manages
        $item_league = get_post_meta($post_id, 'league_id', true);
        
        if ( in_array($item_league, $commish_leagues) ) {
            if ( $action === 'approve' ) {
                // Perform the update
                $pid = get_post_meta($post_id, 'player_id', true);
                $year = get_post_meta($post_id, 'target_year', true);
                $amount = get_post_meta($post_id, 'salary_amount', true);
                $team_id = get_post_meta($post_id, 'team_id', true);
                
                if ($pid && $year && $amount) {
                    update_post_meta($pid, 'contract_' . $year, $amount);
                    
                    // Log Transaction
                    if ( function_exists('log_league_transaction') ) {
                        $p_name = get_the_title($pid);
                        log_league_transaction([
                            'transaction_type' => 'Arbitration',
                            'player_ids'       => [$pid],
                            'primary_team'     => $team_id,
                            'league_id'        => $item_league,
                            'summary'          => "Arbitration APPROVED for $p_name ($team_id): $" . number_format($amount) . " for $year."
                        ]);
                    }
                    
                    wp_delete_post($post_id, true); // Remove pending item
                    $message = "Request approved and processed.";
                }
            } elseif ( $action === 'reject' ) {
                $pid = get_post_meta($post_id, 'player_id', true);
                $year = get_post_meta($post_id, 'target_year', true);
                
                if ($pid && $year) {
                    // Reset the status on the player so the manager can try again
                    update_post_meta($pid, 'arb_status_' . $year, '');
                }

                wp_delete_post($post_id, true);
                $message = "Request rejected and deleted. Player status has been reset for resubmission.";
            }
        } else {
            $message = "Error: You do not have permission to manage this league.";
        }
    }

    // 3. Query Pending Requests
    $args = array(
        'post_type' => 'pending_arb',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'league_id',
                'value' => $commish_leagues,
                'compare' => 'IN'
            )
        )
    );
    $query = new WP_Query($args);

    ?>
    <div class="wrap">
        <h1>Arbitration Approvals</h1>
        
        <?php if ($message): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
        <?php endif; ?>

        <?php if ( $query->have_posts() ): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>League</th>
                        <th>Team</th>
                        <th>Player</th>
                        <th>Year</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ( $query->have_posts() ): $query->the_post(); 
                        $id = get_the_ID();
                        $pid = get_post_meta($id, 'player_id', true);
                        $league = get_post_meta($id, 'league_id', true);
                        $team = get_post_meta($id, 'team_id', true);
                        $year = get_post_meta($id, 'target_year', true);
                        $amount = get_post_meta($id, 'salary_amount', true);
                        
                        $approve_url = add_query_arg(['action'=>'approve', 'id'=>$id, '_wpnonce'=>wp_create_nonce('arb_action_'.$id)]);
                        $reject_url = add_query_arg(['action'=>'reject', 'id'=>$id, '_wpnonce'=>wp_create_nonce('arb_action_'.$id)]);
                    ?>
                    <tr>
                        <td><?php echo get_the_date('Y-m-d H:i'); ?></td>
                        <td><?php echo esc_html($league); ?></td>
                        <td><?php echo esc_html($team); ?></td>
                        <td><strong><?php echo get_the_title($pid); ?></strong></td>
                        <td><?php echo esc_html($year); ?></td>
                        <td>$<?php echo number_format((float)$amount); ?></td>
                        <td>
                            <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary">Approve</a>
                            <a href="<?php echo esc_url($reject_url); ?>" class="button button-secondary" style="color: #b32d2e; border-color: #b32d2e;">Reject</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No pending arbitration requests found for your leagues.</p>
        <?php endif; ?>
    </div>
    <?php
}
