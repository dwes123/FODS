<?php
/**
 * Arbitration Acceptance System
 * Allows managers to convert "ARB" contract statuses into specific salary numbers.
 */

// 1. Shortcode to display the Arbitration Form
function fod_display_arbitration_form_shortcode($atts) {
    if ( ! is_user_logged_in() ) { return '<p>Please log in to access arbitration.</p>'; }
    if ( ! function_exists('get_field') ) { return '<p>Error: ACF not active.</p>'; }

    // Attributes: Allow setting the year via shortcode [arbitration_form year="2026"]
    $atts = shortcode_atts( array(
        'year' => '2026', 
    ), $atts );

    $target_year = $atts['year'];
    $contract_meta_key = 'contract_' . $target_year;
    $arb_status_key = 'arb_status_' . $target_year;

    $user_id = get_current_user_id();
    $user_key = 'user_' . $user_id;
    
    // Get all teams managed by user
    $mlb_teams = get_field('managed_teams', $user_key) ?: [];
    $nba_teams = get_field('managed_nba_teams', $user_key) ?: [];
    $all_teams = array_merge($mlb_teams, $nba_teams);

    if ( empty($all_teams) ) {
        return '<p>You do not manage any teams.</p>';
    }

    // --- League Filter Logic ---
    $manager_leagues = [];
    foreach ($all_teams as $t) {
        if (!empty($t['league_id'])) {
            $manager_leagues[$t['league_id']] = $t['league_id'];
        }
    }
    ksort($manager_leagues);

    $selected_league = '';
    if ( isset($_GET['arb_league']) ) {
        $requested = sanitize_text_field( wp_unslash($_GET['arb_league']) );
        if ( in_array($requested, $manager_leagues) ) {
            $selected_league = $requested;
        }
    }
    if ( empty($selected_league) && !empty($manager_leagues) ) {
        $selected_league = reset($manager_leagues);
    }
    // --- End League Filter Logic ---

    // Collect Team IDs for the SELECTED league
    $team_ids = [];
    foreach ($all_teams as $t) {
        if (!empty($t['fantasy_team_id']) && $t['league_id'] === $selected_league) {
            $team_ids[] = $t['fantasy_team_id'];
        }
    }

    // Query for players on these teams
    $post_type = ($selected_league === 'NBA') ? 'nbaplayer' : 'playerdata';
    
    $args = array(
        'post_type'      => $post_type,
        'posts_per_page' => -1,
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => 'fantasy_team_id',
                'value'   => $team_ids,
                'compare' => 'IN'
            ),
            array(
                'key'     => 'league_id',
                'value'   => $selected_league,
                'compare' => '='
            )
        ),
        'orderby' => 'title',
        'order'   => 'ASC'
    );

    $query = new WP_Query($args);
    $eligible_players = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $pid = get_the_ID();
            $contract_value = get_post_meta($pid, $contract_meta_key, true);
            $arb_status = get_post_meta($pid, $arb_status_key, true);

            // Check if contract value contains "ARB" (case-insensitive)
            if ( !empty($contract_value) && stripos($contract_value, 'ARB') !== false ) {
                $eligible_players[] = [
                    'id' => $pid,
                    'name' => get_the_title(),
                    'team' => get_post_meta($pid, 'fantasy_team_id', true),
                    'league' => get_post_meta($pid, 'league_id', true),
                    'current_status' => $contract_value,
                    'arb_status' => $arb_status
                ];
            }
        }
    }
    wp_reset_postdata();

    ob_start();

    // Messages
    if (isset($_GET['arb_success'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Arbitration decisions submitted successfully! They are now pending commissioner approval.</p></div>';
    }

    // --- League Selector UI ---
    if ( count($manager_leagues) > 1 ) {
        echo '<div class="league-selector-ui" style="margin-bottom: 20px;"><strong>View League:</strong> ';
        $links = [];
        $base_url = get_permalink();
        foreach ($manager_leagues as $lid) {
            $url = add_query_arg('arb_league', rawurlencode($lid), $base_url);
            $sel = ($lid === $selected_league) ? ' class="is-selected"' : '';
            $links[] = '<a href="'.esc_url($url).'"'.$sel.'>'.esc_html($lid).'</a>';
        }
        echo implode(' | ', $links);
        echo '</div>';
    }
    // --- End Selector ---

    echo '<h2>Arbitration Acceptance (' . esc_html($selected_league) . ' - ' . esc_html($target_year) . ')</h2>';

    if ( empty($eligible_players) ) {
        echo '<p>You have no players eligible for arbitration (marked as "ARB") in the ' . esc_html($selected_league) . ' league for ' . esc_html($target_year) . '.</p>';
    } else {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
            <input type="hidden" name="action" value="process_arbitration_submission">
            <input type="hidden" name="target_year" value="<?php echo esc_attr($target_year); ?>">
            <?php wp_nonce_field( 'process_arbitration_nonce', 'arb_nonce' ); ?>

            <table class="fantasy-table-base">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Team</th>
                        <th>Current Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eligible_players as $player): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($player['name']); ?></strong>
                                <input type="hidden" name="players[<?php echo $player['id']; ?>][name]" value="<?php echo esc_attr($player['name']); ?>">
                                <input type="hidden" name="players[<?php echo $player['id']; ?>][team]" value="<?php echo esc_attr($player['team']); ?>">
                                <input type="hidden" name="players[<?php echo $player['id']; ?>][league]" value="<?php echo esc_attr($player['league']); ?>">
                            </td>
                            <td><?php echo esc_html($player['team']); ?></td>
                            <td><?php echo esc_html($player['current_status']); ?></td>
                            <td>
                                <?php if ($player['arb_status'] === 'pending'): ?>
                                    <strong style="color: #e69c00;">Pending Commissioner Approval</strong>
                                <?php else: ?>
                                    <div style="display:flex; flex-direction: column; gap: 10px;">
                                        <div>
                                            <label>
                                                $ <input type="number" name="players[<?php echo $player['id']; ?>][amount]" placeholder="Enter Amount" min="0" style="margin-left:5px;">
                                            </label>
                                        </div>
                                        <div>
                                            <label>
                                                <input type="checkbox" name="players[<?php echo $player['id']; ?>][decline]" value="1">
                                                Decline Arbitration (Release to Free Agency)
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Submit Decisions</button>
            </p>
        </form>
        <?php
    }

    return ob_get_clean();
}
add_shortcode('arbitration_acceptance_form', 'fod_display_arbitration_form_shortcode');


// 2. Handle Form Submission
function fod_handle_arbitration_submission() {
    if ( !isset($_POST['arb_nonce']) || !wp_verify_nonce($_POST['arb_nonce'], 'process_arbitration_nonce') ) {
        wp_die('Security check failed.');
    }

    if ( ! current_user_can('read') ) {
        wp_die('Permission denied.');
    }

    $target_year = sanitize_text_field($_POST['target_year']);
    $arb_status_key = 'arb_status_' . $target_year;
    $players_data = $_POST['players'];
    $updated_count = 0;

    if ( is_array($players_data) ) {
        foreach ($players_data as $pid => $data) {
            $amount = $data['amount'];
            $decline = isset($data['decline']) && $data['decline'] == '1';

            $player_name = sanitize_text_field($data['name']);
            $team_id = sanitize_text_field($data['team']);
            $league_id = sanitize_text_field($data['league']);

            if ( $decline ) {
                // Handle Declined Arbitration
                update_post_meta($pid, 'fa_status', 'available');
                update_post_meta($pid, 'fantasy_team_id', '');
                update_post_meta($pid, $arb_status_key, 'declined'); // Mark as declined
                
                if ( function_exists('log_league_transaction') ) {
                    log_league_transaction([
                        'transaction_type' => 'Arbitration',
                        'player_ids'       => [$pid],
                        'primary_team'     => $team_id,
                        'league_id'        => $league_id,
                        'summary'          => "$player_name was released to Free Agency after declining arbitration from $team_id."
                    ]);
                }
                $updated_count++;

            } elseif ( is_numeric($amount) && $amount > 0 ) {
                // Check for existing pending arbitration post for this player/year
                $pending_args = [
                    'post_type' => 'pending_arb',
                    'posts_per_page' => 1,
                    'meta_query' => [
                        'relation' => 'AND',
                        ['key' => 'player_id', 'value' => $pid],
                        ['key' => 'target_year', 'value' => $target_year],
                        ['key' => 'approval_status', 'value' => 'pending']
                    ]
                ];
                $pending_query = new WP_Query($pending_args);
                $post_id = $pending_query->have_posts() ? $pending_query->posts[0]->ID : 0;

                if ($post_id) {
                    // Update existing pending post
                    update_post_meta($post_id, 'salary_amount', $amount);
                } else {
                    // Create new pending post
                    $post_title = "ARB: $player_name ($team_id) - $$amount for $target_year";
                    $post_id = wp_insert_post([
                        'post_type' => 'pending_arb',
                        'post_title' => $post_title,
                        'post_status' => 'publish',
                        'post_author' => get_current_user_id(),
                    ]);
                }

                if ($post_id) {
                    update_post_meta($post_id, 'player_id', $pid);
                    update_post_meta($post_id, 'team_id', $team_id);
                    update_post_meta($post_id, 'league_id', $league_id);
                    update_post_meta($post_id, 'target_year', $target_year);
                    update_post_meta($post_id, 'salary_amount', $amount);
                    update_post_meta($post_id, 'approval_status', 'pending');
                    
                    // Mark the player as pending
                    update_post_meta($pid, $arb_status_key, 'pending');
                }
                $updated_count++;
            }
        }
    }

    // Redirect back
    wp_redirect( add_query_arg('arb_success', $updated_count, wp_get_referer()) );
    exit;
}
add_action('admin_post_process_arbitration_submission', 'fod_handle_arbitration_submission');
