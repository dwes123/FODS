<?php
/**
 * Team Options Decision Tool
 * Shortcode: [team_option_decisions]
 */

function fod_register_team_options_shortcode() {
    add_shortcode('team_option_decisions', 'fod_render_team_option_decisions');
}
add_action('init', 'fod_register_team_options_shortcode');

function fod_render_team_option_decisions() {
    if ( !is_user_logged_in() ) return '<p>Please log in to view option decisions.</p>';
    
    $user_id = get_current_user_id();
    $managed_teams = get_field('managed_teams', 'user_' . $user_id);
    if ( empty($managed_teams) ) return '<p>No teams assigned.</p>';

    // --- 1. Handle Decision Submission ---
    if ( isset($_POST['fod_option_action'], $_POST['player_id'], $_POST['year']) && wp_verify_nonce($_POST['option_decision_nonce'], 'fod_option_decision') ) {
        $pid = absint($_POST['player_id']);
        $year = sanitize_text_field($_POST['year']);
        $action = $_POST['fod_option_action'];

        // Verify ownership
        if ( is_user_owner_of_player($user_id, $pid) ) {
            $salary = (float) get_post_meta($pid, 'contract_' . $year, true);
            $p_name = get_the_title($pid);
            $team_id = get_field('fantasy_team_id', $pid);
            $league_id = get_field('league_id', $pid);

            if ( $action === 'exercise' ) {
                // Remove year from Option Years list
                $options = get_field('contract_option_years', $pid) ?: [];
                $new_options = array_diff($options, [$year]);
                update_field('contract_option_years', $new_options, $pid);

                log_league_transaction([
                    'transaction_type' => 'Team Option',
                    'player_ids'       => [$pid],
                    'primary_team'     => $team_id,
                    'league_id'        => $league_id,
                    'summary'          => "$team_id EXERCISED the $year Team Option for $p_name ($" . number_format($salary) . ")."
                ]);
                echo '<div class="notice notice-success"><p>Option Exercised!</p></div>';
            } elseif ( $action === 'decline' ) {
                $buyout = $salary * 0.30;
                
                // Set current year to Buyout (Dead Cap)
                update_post_meta($pid, 'contract_' . $year, $buyout);
                
                // Clear future years and options
                update_field('contract_option_years', [], $pid);
                for($y = (int)$year + 1; $y <= 2040; $y++) {
                    update_post_meta($pid, 'contract_' . $y, '');
                }

                // Drop player
                update_post_meta($pid, 'fa_status', 'available');
                update_post_meta($pid, 'fantasy_team_id', '');
                update_post_meta($pid, 'status_40_man', '');
                update_post_meta($pid, 'status_26_man', '0');

                log_league_transaction([
                    'transaction_type' => 'Team Option',
                    'player_ids'       => [$pid],
                    'primary_team'     => $team_id,
                    'league_id'        => $league_id,
                    'summary'          => "$team_id DECLINED the $year Team Option for $p_name. Buyout: $" . number_format($buyout) . ". Player is now a Free Agent."
                ]);
                echo '<div class="notice notice-warning"><p>Option Declined. Player released with buyout.</p></div>';
            }
        }
    }

    // --- 2. Query Players with Options ---
    // We'll look for any option year between current year and next year
    $current_year = date('Y');
    $look_years = [(string)$current_year, (string)($current_year + 1)];
    
    $players_with_options = [];
    foreach ($managed_teams as $mt) {
        $q = new WP_Query([
            'post_type' => 'playerdata',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'fantasy_team_id', 'value' => $mt['fantasy_team_id']],
                ['key' => 'league_id', 'value' => $mt['league_id']],
                ['key' => 'contract_option_years', 'compare' => 'EXISTS']
            ]
        ]);

        if ( $q->have_posts() ) {
            foreach ( $q->posts as $p ) {
                $opts = get_field('contract_option_years', $p->ID) ?: [];
                foreach ($opts as $yr) {
                    if ( in_array($yr, $look_years) ) {
                        $players_with_options[] = [
                            'id' => $p->ID,
                            'name' => $p->post_title,
                            'year' => $yr,
                            'salary' => get_post_meta($p->ID, 'contract_' . $yr, true),
                            'team' => $mt['fantasy_team_id']
                        ];
                    }
                }
            }
        }
    }

    ob_start();
    ?>
    <div class="fod-option-decisions">
        <h3>Upcoming Team Options</h3>
        <?php if ( empty($players_with_options) ): ?>
            <p>You have no pending team options for the current or upcoming season.</p>
        <?php else: ?>
            <table class="fantasy-table-base">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Option Year</th>
                        <th>Salary</th>
                        <th>Buyout (30%)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players_with_options as $p): 
                        $buyout = (float)$p['salary'] * 0.30;
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($p['name']); ?></strong> (<?php echo esc_html($p['team']); ?>)</td>
                            <td><?php echo esc_html($p['year']); ?></td>
                            <td>$<?php echo number_format((float)$p['salary']); ?></td>
                            <td style="color:red;">$<?php echo number_format($buyout); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('fod_option_decision', 'option_decision_nonce'); ?>
                                    <input type="hidden" name="player_id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="year" value="<?php echo $p['year']; ?>">
                                    <button type="submit" name="fod_option_action" value="exercise" class="button button-primary" onclick="return confirm('Exercise this option? The salary will become guaranteed.')">Exercise</button>
                                    <button type="submit" name="fod_option_action" value="decline" class="button" style="color:red;" onclick="return confirm('Decline this option? The player will be released and you will pay a 30% buyout.')">Decline</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
