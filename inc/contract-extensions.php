<?php
/* ------------------------------------------------------------------------
   Contract Extension Calculator System
   - Shortcode: [extension_calculator]
   - AJAX Handler: fod_submit_extension_request
------------------------------------------------------------------------ */

function fod_register_extension_calculator_shortcode() {
    add_shortcode('extension_calculator', 'fod_render_extension_calculator');
}
add_action('init', 'fod_register_extension_calculator_shortcode');

function fod_render_extension_calculator($atts) {
    // Basic permissions check
    if ( !is_user_logged_in() ) {
        return '<p>Please log in to use the Extension Calculator.</p>';
    }

    // Get current user's players for the dropdown
    $user_id = get_current_user_id();
    $team_id = ''; 
    $league_id = '';
    
    // Attempt to find the user's primary team/league from ACF
    $managed_teams = get_field('managed_teams', 'user_' . $user_id);
    $players = [];
    
    if ( $managed_teams ) {
        foreach ( $managed_teams as $mt ) {
            $tid = $mt['fantasy_team_id'];
            $lid = $mt['league_id'];
            
            // Query players for this team
            $args = [
                'post_type' => ['playerdata', 'nbaplayer'],
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'AND',
                    ['key' => 'fantasy_team_id', 'value' => $tid],
                    ['key' => 'league_id', 'value' => $lid]
                ],
                'orderby' => 'title',
                'order' => 'ASC'
            ];
            $q = new WP_Query($args);
            foreach ($q->posts as $p) {
                $players[] = [
                    'id' => $p->ID,
                    'name' => $p->post_title,
                    'team' => $tid,
                    'league' => $lid
                ];
            }
        }
    }

    // Enqueue the JS for this specific widget
    wp_enqueue_script('fod-extension-js', get_stylesheet_directory_uri() . '/JS/extension-calculator.js', array('jquery'), '1.0', true);
    wp_localize_script('fod-extension-js', 'fodExtData', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('extension_calc_nonce')
    ));

    ob_start();
    ?>
    <div id="fod-extension-calculator-wrapper" class="fod-widget">
        <h3>Contract Extension Calculator</h3>
        
        <form id="fod-extension-form">
            <!-- Player Selection -->
            <div class="fod-form-group">
                <label for="ext-player-select">Select Player:</label>
                <select id="ext-player-select" name="player_id" class="fod-input" required>
                    <option value="">-- Choose a Player --</option>
                    <?php foreach ($players as $p): ?>
                        <option value="<?php echo esc_attr($p['id']); ?>" 
                                data-team="<?php echo esc_attr($p['team']); ?>" 
                                data-league="<?php echo esc_attr($p['league']); ?>">
                            <?php echo esc_html($p['name'] . ' (' . $p['team'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Position Tabs -->
            <div class="fod-tabs">
                <button type="button" class="fod-tab active" data-pos="sp">Starting Pitcher</button>
                <button type="button" class="fod-tab" data-pos="rp">Relief Pitcher</button>
                <button type="button" class="fod-tab" data-pos="hitter">Hitter</button>
            </div>
            <input type="hidden" id="ext-position-type" name="position_type" value="sp">

            <!-- WAR Inputs -->
            <div class="fod-input-grid">
                <div class="fod-form-group">
                    <label>1-Year WAR (Helper)</label>
                    <input type="number" id="ext-war-1yr" step="0.1" placeholder="0.0">
                </div>
                <div class="fod-form-group">
                    <label>2-Year WAR (Helper)</label>
                    <input type="number" id="ext-war-2yr" step="0.1" placeholder="0.0">
                </div>
                <div class="fod-form-group main-input">
                    <label>Total 3-Year WAR (Main)</label>
                    <input type="number" id="ext-war-3yr" name="war_3yr" step="0.1" placeholder="0.0" required>
                </div>
            </div>

            <!-- Pricing Table -->
            <div id="ext-pricing-table" class="hidden">
                <h4>Calculated Extension Offers</h4>
                <table class="fod-table">
                    <thead>
                        <tr>
                            <th>Years</th>
                            <th>AAV ($)</th>
                            <th>Total ($)</th>
                            <th>Select</th>
                        </tr>
                    </thead>
                    <tbody id="ext-pricing-body">
                        <!-- Rows generated by JS -->
                    </tbody>
                </table>
            </div>

            <div id="ext-submit-area" class="hidden" style="margin-top: 20px;">
                <p><strong>Selected Contract:</strong> <span id="ext-selected-summary">None</span></p>
                <button type="submit" id="ext-submit-btn" class="button button-primary">Submit Extension Request</button>
                <div id="ext-message"></div>
            </div>
        </form>
    </div>

    <!-- Styles for the widget -->
    <style>
        .fod-widget { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; max-width: 600px; margin-bottom: 30px; }
        .fod-form-group { margin-bottom: 15px; }
        .fod-input { width: 100%; padding: 8px; }
        .fod-tabs { display: flex; border-bottom: 2px solid #ddd; margin-bottom: 15px; }
        .fod-tab { flex: 1; padding: 10px; border: none; background: #f1f1f1; cursor: pointer; font-weight: bold; }
        .fod-tab.active { background: #0073aa; color: white; }
        .fod-input-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        .main-input input { border: 2px solid #0073aa; background-color: #f0f7ff; font-weight: bold; }
        .fod-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .fod-table th, .fod-table td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        .fod-table th { background: #f9f9f9; }
        .hidden { display: none; }
        .fod-row-select { cursor: pointer; }
        .fod-row-selected { background-color: #d1e7dd !important; }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * AJAX Handler for Submission
 */
function fod_submit_extension_request_handler() {
    // 1. Verify Nonce & Login
    check_ajax_referer('extension_calc_nonce', 'nonce');
    if ( !is_user_logged_in() ) { wp_send_json_error('Not logged in.'); }

    // 2. Gather Data
    $player_id = isset($_POST['player_id']) ? absint($_POST['player_id']) : 0;
    $years     = isset($_POST['years']) ? absint($_POST['years']) : 0;
    $aav       = isset($_POST['aav']) ? floatval($_POST['aav']) : 0;
    $war       = isset($_POST['war']) ? floatval($_POST['war']) : 0;
    
    if ( !$player_id || !$years || !$aav ) {
        wp_send_json_error('Missing required contract data.');
    }

    // 3. Check Extension Limit (Max 2 per team per year)
    $team_id   = get_post_meta($player_id, 'fantasy_team_id', true);
    $current_year = date('Y');
    
    $usage_log = get_field('extension_usage_log', 'option');
    $usage_count = 0;
    if ( is_array($usage_log) ) {
        foreach ( $usage_log as $log ) {
            if ( ($log['team_id'] ?? '') === $team_id && (int)($log['league_year'] ?? 0) === (int)$current_year ) {
                $usage_count++;
            }
        }
    }

    if ( $usage_count >= 2 ) {
        wp_send_json_error("Limit Reached: Your team ($team_id) has already submitted 2 extension requests for the $current_year season.");
    }

    // 4. Create Pending Arb Post
    // We reuse the 'pending_arb' post type from admin-arbitration-approval.php
    $league_id = get_post_meta($player_id, 'league_id', true);
    $player_name = get_the_title($player_id);

    // Determine Start Year
    // Find the first year that does NOT have a guaranteed (numeric) salary
    $start_year = 2026; 
    $scan_limit = 2035;
    
    for ($y = 2026; $y <= $scan_limit; $y++) {
        $val = get_post_meta($player_id, 'contract_' . $y, true);
        // If value is empty OR not numeric (e.g. 'ARB', 'TC', 'UFA'), this is our start
        if ( empty($val) || !is_numeric(str_replace(',', '', $val)) ) {
            $start_year = $y;
            break;
        }
    }

    // Construct the multi-year array
    $multi_contract = [];
    for ($i = 0; $i < $years; $i++) {
        $multi_contract[$start_year + $i] = $aav;
    }

    $post_data = array(
        'post_title'  => 'Extension Request: ' . $player_name,
        'post_type'   => 'pending_arb',
        'post_status' => 'publish', // It's "published" to the admin list
    );

    $post_id = wp_insert_post($post_data);

    if ( $post_id ) {
        update_post_meta($post_id, 'player_id', $player_id);
        update_post_meta($post_id, 'team_id', $team_id);
        update_post_meta($post_id, 'league_id', $league_id);
        update_post_meta($post_id, 'salary_amount', $aav); // Store AAV for reference
        update_post_meta($post_id, 'target_year', $start_year); // Start year
        update_post_meta($post_id, 'multi_year_contract', $multi_contract); // The full structure
        update_post_meta($post_id, 'extension_war_basis', $war); // Store the WAR used for calc

        // Log the usage so they can't submit more
        add_row('extension_usage_log', [
            'team_id'     => $team_id,
            'league_year' => $current_year,
            'player_id'   => $player_id
        ], 'option');

        wp_send_json_success('Extension request submitted for approval!');
    } else {
        wp_send_json_error('Failed to create request.');
    }
}
add_action('wp_ajax_submit_extension_request', 'fod_submit_extension_request_handler');
