<?php
/**
 * Daily Rotation Submission System
 */

// 1. Register the Custom Post Type for 'rotation'
function fod_register_rotation_cpt() {
    $args = array(
        'label'                 => __( 'Rotations', 'text_domain' ),
        'description'           => __( 'Daily rotation submissions', 'text_domain' ),
        'labels'                => array(
            'name'          => _x( 'Rotations', 'Post Type General Name', 'text_domain' ),
            'singular_name' => _x( 'Rotation', 'Post Type Singular Name', 'text_domain' ),
            'menu_name'     => __( 'Rotations', 'text_domain' ),
        ),
        'supports'              => array( 'title', 'custom-fields', 'author' ),
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => 'edit.php?post_type=playerdata', // Sub-menu under Players
        'capability_type'       => 'post',
        'show_in_rest'          => true,
    );
    register_post_type( 'rotation', $args );
}
add_action( 'init', 'fod_register_rotation_cpt', 0 );

// 2. Shortcode to display the submission form
function display_rotation_submission_form_shortcode() {
    if ( ! is_user_logged_in() ) { return '<p>Please log in to submit your rotation.</p>'; }
    if ( ! function_exists('get_field') ) { return '<p>Error: ACF not active.</p>'; }

    $user_id = get_current_user_id();
    $user_key = 'user_' . $user_id;
    
    // Get MLB and AA teams only
    $mlb_teams = get_field('managed_teams', $user_key) ?: [];
    $allowed_leagues = ['MLB', 'AA'];
    $all_teams = array_filter($mlb_teams, function($team) use ($allowed_leagues) {
        return isset($team['league_id']) && in_array($team['league_id'], $allowed_leagues);
    });

    if ( empty($all_teams) ) {
        return '<p>You do not manage any teams in the MLB or AA leagues.</p>';
    }

    ob_start();

    // Handle success/error notices
    if (isset($_GET['rotation_success'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Rotation submitted successfully!</p></div>';
    }
    if (isset($_GET['rotation_error'])) {
        $error_msg = 'An unknown error occurred.';
        if ($_GET['rotation_error'] === 'missing_fields') {
            $error_msg = 'You must select a team and day.';
        } elseif ($_GET['rotation_error'] === 'missing_date') {
            $error_msg = 'You must enter a date for every selected pitcher.';
        }
        echo '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html($error_msg) . '</p></div>';
    }

    ?>
    <form id="rotation-submission-form" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
        <input type="hidden" name="action" value="process_rotation_submission">
        <?php wp_nonce_field( 'process_rotation_submission_nonce', 'rotation_nonce' ); ?>

        <p>
            <label for="rotation_team">Select Your Team:</label><br>
            <select name="rotation_team" id="rotation_team" required>
                <option value="">-- Select a Team --</option>
                <?php foreach ($all_teams as $team): 
                    $team_val = $team['league_id'] . '|' . $team['fantasy_team_id'];
                ?>
                    <option value="<?php echo esc_attr($team_val); ?>"><?php echo esc_html($team['fantasy_team_id'] . ' (' . $team['league_id'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="day_of_week">Select Day:</label><br>
            <select name="day_of_week" id="day_of_week" required>
                <option value="monday">Monday</option>
                <option value="tuesday">Tuesday</option>
                <option value="wednesday">Wednesday</option>
                <option value="thursday">Thursday</option>
                <option value="friday">Friday</option>
                <option value="saturday">Saturday</option>
                <option value="sunday">Sunday</option>
            </select>
        </p>

        <div id="pitcher-selection-area" style="display:none;">
            <div style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; margin-bottom: 10px;">
                <p>
                    <label for="pitcher_1"><strong>Starting Pitcher 1:</strong></label><br>
                    <select name="pitcher_1" id="pitcher_1" class="pitcher-select">
                        <option value="">-- None --</option>
                    </select>
                </p>
                <p>
                    <label for="pitcher_1_date">Date of Start:</label><br>
                    <input type="date" name="pitcher_1_date" id="pitcher_1_date" class="pitcher-date">
                </p>
            </div>

            <div style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; margin-bottom: 10px;">
                <p>
                    <label for="pitcher_2"><strong>Starting Pitcher 2:</strong></label><br>
                    <select name="pitcher_2" id="pitcher_2" class="pitcher-select">
                        <option value="">-- None --</option>
                    </select>
                </p>
                <p>
                    <label for="pitcher_2_date">Date of Start:</label><br>
                    <input type="date" name="pitcher_2_date" id="pitcher_2_date" class="pitcher-date">
                </p>
            </div>

            <div id="banked-starters-wrapper">
                <h4>Banked Starters (Optional)</h4>
                <div class="banked-starter-row" style="background: #eef; padding: 10px; border: 1px solid #ccd; margin-bottom: 10px;">
                    <p>
                        <label>Pitcher:</label><br>
                        <select name="banked_pitchers[]" class="pitcher-select dynamic-pitcher-select">
                            <option value="">-- None --</option>
                        </select>
                    </p>
                    <p>
                        <label>Date of Start:</label><br>
                        <input type="date" name="banked_dates[]" class="pitcher-date">
                    </p>
                </div>
            </div>
            <p><button type="button" id="add-banked-btn" class="button">Add Another Banked Starter</button></p>
        </div>
        <div id="pitcher-loading" style="display:none;">Loading pitchers...</div>

        <p><button type="submit" id="submit-rotation-button" disabled>Submit Rotation</button></p>
    </form>

    <script>
    jQuery(document).ready(function($) {
        var loadedPitchers = [];

        $('#rotation_team').on('change', function() {
            var selectedTeam = $(this).val();
            var pitcherSelects = $('#pitcher_1, #pitcher_2, .dynamic-pitcher-select');
            var selectionArea = $('#pitcher-selection-area');
            var loadingDiv = $('#pitcher-loading');
            var submitButton = $('#submit-rotation-button');

            pitcherSelects.html('<option value="">-- None --</option>');
            selectionArea.hide();
            submitButton.prop('disabled', true);
            
            // Reset banked rows to just one
            $('#banked-starters-wrapper .banked-starter-row').not(':first').remove();

            if (!selectedTeam) return;

            loadingDiv.show();
            var team_data = selectedTeam.split('|');

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'get_team_pitchers',
                    league_id: team_data[0],
                    team_id: team_data[1]
                },
                success: function(response) {
                    loadingDiv.hide();
                    if (response.success) {
                        loadedPitchers = response.data; // Store for dynamic rows
                        
                        if (loadedPitchers.length > 0) {
                            loadedPitchers.forEach(function(pitcher) {
                                pitcherSelects.append($('<option>', {
                                    value: pitcher.id,
                                    text: pitcher.name
                                }));
                            });
                        } else {
                            pitcherSelects.html('<option value="">No Starting Pitchers (SP) found on this roster.</option>');
                        }
                        selectionArea.show();
                        submitButton.prop('disabled', false);
                    } else {
                        pitcherSelects.html('<option value="">Could not load pitchers.</option>');
                        selectionArea.show();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX Error:', textStatus, errorThrown);
                    loadingDiv.hide();
                    pitcherSelects.html('<option value="">AJAX Error. Check console.</option>');
                    selectionArea.show();
                }
            });
        });

        // Add Banked Starter Row
        $('#add-banked-btn').on('click', function() {
            var row = $('<div class="banked-starter-row" style="background: #eef; padding: 10px; border: 1px solid #ccd; margin-bottom: 10px;">' +
                        '<p><label>Pitcher:</label><br><select name="banked_pitchers[]" class="pitcher-select dynamic-pitcher-select"><option value="">-- None --</option></select></p>' +
                        '<p><label>Date of Start:</label><br><input type="date" name="banked_dates[]" class="pitcher-date"></p>' +
                        '<button type="button" class="button remove-banked-row" style="margin-top:5px;">Remove</button>' +
                        '</div>');
            
            var select = row.find('select');
            if (loadedPitchers.length > 0) {
                loadedPitchers.forEach(function(p) {
                    select.append($('<option>', {value: p.id, text: p.name}));
                });
            }
            $('#banked-starters-wrapper').append(row);
        });

        // Remove Banked Starter Row
        $(document).on('click', '.remove-banked-row', function() {
            $(this).closest('.banked-starter-row').remove();
        });

        // Toggle required attribute for date fields
        $(document).on('change', '.pitcher-select', function() {
            var dateInput = $(this).closest('div').find('.pitcher-date');
            if ($(this).val()) {
                dateInput.prop('required', true);
            } else {
                dateInput.prop('required', false);
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('submit_rotation', 'display_rotation_submission_form_shortcode');

// 3. AJAX handler to get pitchers for a team
function ajax_get_team_pitchers() {
    $league_id = isset($_POST['league_id']) ? sanitize_text_field($_POST['league_id']) : '';
    $team_id = isset($_POST['team_id']) ? sanitize_text_field($_POST['team_id']) : '';

    if (empty($league_id) || empty($team_id)) {
        wp_send_json_error('Missing data.');
    }

    $args = array(
        'post_type' => 'playerdata',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'AND',
            array('key' => 'fantasy_team_id', 'value' => $team_id),
            array('key' => 'league_id', 'value' => $league_id),
            array('key' => 'position', 'value' => 'P', 'compare' => 'LIKE'),
            array('key' => 'status_26_man', 'value' => '1', 'compare' => '=')
        ),
        'orderby' => 'title',
        'order' => 'ASC'
    );
    $query = new WP_Query($args);
    $pitchers = [];
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $pos = get_field('position');
            $pitchers[] = ['id' => get_the_ID(), 'name' => get_the_title() . ' (' . $pos . ')'];
        }
    }
    wp_reset_postdata();
    wp_send_json_success($pitchers);
}
add_action('wp_ajax_get_team_pitchers', 'ajax_get_team_pitchers');
add_action('wp_ajax_nopriv_get_team_pitchers', 'ajax_get_team_pitchers');

// 4. Form handler for submission
function handle_rotation_submission() {
    if ( !isset($_POST['rotation_nonce']) || !wp_verify_nonce($_POST['rotation_nonce'], 'process_rotation_submission_nonce') ) {
        wp_die('Security check failed.');
    }

    $team_data = explode('|', $_POST['rotation_team']);
    $league_id = sanitize_text_field($team_data[0]);
    $team_id = sanitize_text_field($team_data[1]);
    $day_of_week = sanitize_key($_POST['day_of_week']);
    
    $pitcher1 = isset($_POST['pitcher_1']) ? absint($_POST['pitcher_1']) : 0;
    $pitcher2 = isset($_POST['pitcher_2']) ? absint($_POST['pitcher_2']) : 0;
    
    $pitcher1_date = isset($_POST['pitcher_1_date']) ? sanitize_text_field($_POST['pitcher_1_date']) : '';
    $pitcher2_date = isset($_POST['pitcher_2_date']) ? sanitize_text_field($_POST['pitcher_2_date']) : '';

    // Handle Banked Pitchers Array
    $banked_pitchers = isset($_POST['banked_pitchers']) ? $_POST['banked_pitchers'] : [];
    $banked_dates = isset($_POST['banked_dates']) ? $_POST['banked_dates'] : [];
    
    $banked_list = [];
    if (is_array($banked_pitchers)) {
        foreach ($banked_pitchers as $index => $pid) {
            $pid = absint($pid);
            if (!$pid) continue;
            
            $date = isset($banked_dates[$index]) ? sanitize_text_field($banked_dates[$index]) : '';
            if (empty($date)) {
                 wp_redirect( add_query_arg('rotation_error', 'missing_date', wp_get_referer()) ); exit;
            }
            $banked_list[] = ['id' => $pid, 'date' => $date];
        }
    }

    if (empty($team_id) || empty($day_of_week)) {
        wp_redirect( add_query_arg('rotation_error', 'missing_fields', wp_get_referer()) );
        exit;
    }
    
    if ( ($pitcher1 && empty($pitcher1_date)) || ($pitcher2 && empty($pitcher2_date)) ) {
        wp_redirect( add_query_arg('rotation_error', 'missing_date', wp_get_referer()) );
        exit;
    }

    // Find if a submission for this team, week, and day already exists
    $current_week = date('Y-W');
    $args = [
        'post_type' => 'rotation',
        'posts_per_page' => 1,
        'meta_query' => [
            'relation' => 'AND',
            ['key' => 'team_id', 'value' => $team_id],
            ['key' => 'league_id', 'value' => $league_id],
            ['key' => 'week_identifier', 'value' => $current_week],
            ['key' => 'day_of_week', 'value' => $day_of_week],
        ]
    ];
    $query = new WP_Query($args);
    $existing_post_id = $query->have_posts() ? $query->posts[0]->ID : 0;

    if ($existing_post_id) {
        $post_id = $existing_post_id;
    } else {
        $post_title = 'Rotation for ' . $team_id . ' - Week ' . $current_week . ' - ' . ucfirst($day_of_week);
        $post_id = wp_insert_post([
            'post_type' => 'rotation',
            'post_title' => $post_title,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);
    }

    if ($post_id) {
        update_post_meta($post_id, 'league_id', $league_id);
        update_post_meta($post_id, 'team_id', $team_id);
        update_post_meta($post_id, 'day_of_week', $day_of_week);
        update_post_meta($post_id, 'week_identifier', $current_week);
        
        update_post_meta($post_id, 'pitcher_1', $pitcher1);
        update_post_meta($post_id, 'pitcher_1_date', $pitcher1_date);
        
        update_post_meta($post_id, 'pitcher_2', $pitcher2);
        update_post_meta($post_id, 'pitcher_2_date', $pitcher2_date);
        
        update_post_meta($post_id, 'banked_starters_list', $banked_list);
    }

    wp_redirect( add_query_arg('rotation_success', 'true', wp_get_referer()) );
    exit;
}
add_action('admin_post_process_rotation_submission', 'handle_rotation_submission');

// 5. Shortcode to display the rotations
function display_view_rotations_shortcode() {
    ob_start();

    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $allowed_leagues = ['MLB', 'AA'];

    $selected_league = isset($_GET['rotations_league']) && in_array($_GET['rotations_league'], $allowed_leagues) ? $_GET['rotations_league'] : 'MLB';

    global $wpdb;
    $available_weeks = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_value DESC",
        'week_identifier'
    ));
    
    $current_week = date('Y-W');
    $selected_week = isset($_GET['rotations_week']) ? sanitize_text_field($_GET['rotations_week']) : $current_week;

    $teams = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT pm_team.meta_value 
         FROM {$wpdb->postmeta} pm_team
         INNER JOIN {$wpdb->postmeta} pm_league ON pm_team.post_id = pm_league.post_id
         WHERE pm_team.meta_key = %s 
           AND pm_league.meta_key = %s 
           AND pm_league.meta_value = %s
           AND pm_team.meta_value != ''",
        'fantasy_team_id',
        'league_id',
        $selected_league
    ));
    sort($teams);

    $args = [
        'post_type' => 'rotation',
        'posts_per_page' => -1,
        'meta_query' => [
            'relation' => 'AND',
            ['key' => 'week_identifier', 'value' => $selected_week],
            ['key' => 'league_id', 'value' => $selected_league]
        ]
    ];
    $query = new WP_Query($args);
    $submissions = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $team_id = get_post_meta(get_the_ID(), 'team_id', true);
            $day = get_post_meta(get_the_ID(), 'day_of_week', true);
            
            $p1_id = get_post_meta(get_the_ID(), 'pitcher_1', true);
            $p1_date = get_post_meta(get_the_ID(), 'pitcher_1_date', true);
            
            $p2_id = get_post_meta(get_the_ID(), 'pitcher_2', true);
            $p2_date = get_post_meta(get_the_ID(), 'pitcher_2_date', true);
            
            $banked_list = get_post_meta(get_the_ID(), 'banked_starters_list', true);
            
            $modified_time = get_the_modified_time('U');
            $dt = new DateTime("@$modified_time");
            $dt->setTimezone(new DateTimeZone('America/New_York'));
            $date_display = $dt->format('M j, g:i a');

            $format_pitcher = function($pid, $date) {
                if (!$pid) return '–';
                $name = get_the_title($pid);
                $extra = '';
                if ($date) {
                    $d = DateTime::createFromFormat('Y-m-d', $date);
                    $extra .= ' (' . ($d ? $d->format('m/d') : $date) . ')';
                }
                return $name . $extra;
            };
            
            $banked_html = '';
            if (!empty($banked_list) && is_array($banked_list)) {
                foreach($banked_list as $b) {
                    $banked_html .= '<div style="color: #0073aa; margin-top: 2px;"><strong>Bank:</strong> ' . $format_pitcher($b['id'], $b['date']) . '</div>';
                }
            }

            $submissions[$team_id][$day] = [
                'p1' => $format_pitcher($p1_id, $p1_date),
                'p2' => $format_pitcher($p2_id, $p2_date),
                'banked_html' => $banked_html,
                'date' => $date_display
            ];
        }
    }
    wp_reset_postdata();
    ?>
    
    <div class="rotation-filters" style="display: flex; gap: 20px; align-items: center; margin-bottom: 15px;">
        <div class="league-selector-ui">
            <strong>League:</strong> 
            <?php 
            $base_url = get_permalink();
            foreach ($allowed_leagues as $lid) {
                $url = add_query_arg(['rotations_league' => rawurlencode($lid), 'rotations_week' => $selected_week], $base_url);
                $sel = ($lid === $selected_league) ? ' class="is-selected"' : '';
                echo '<a href="'.esc_url($url).'"'.$sel.'>'.esc_html($lid).'</a> ';
            }
            ?>
        </div>

        <div class="week-selector-ui">
            <strong>Week:</strong>
            <select onchange="window.location.href=this.value">
                <?php foreach ($available_weeks as $week): 
                    $url = add_query_arg(['rotations_league' => $selected_league, 'rotations_week' => $week], $base_url);
                    $selected = ($week === $selected_week) ? 'selected' : '';
                    $week_label = $week === $current_week ? "$week (Current)" : $week;
                ?>
                    <option value="<?php echo esc_url($url); ?>" <?php echo $selected; ?>><?php echo esc_html($week_label); ?></option>
                <?php endforeach; ?>
                <?php if (!in_array($current_week, $available_weeks)): ?>
                    <option value="<?php echo esc_url(add_query_arg(['rotations_league' => $selected_league, 'rotations_week' => $current_week], $base_url)); ?>" selected><?php echo esc_html($current_week); ?> (Current)</option>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <h3>Weekly Rotations (<?php echo esc_html($selected_league); ?>)</h3>
    <div style="overflow-x: auto;">
        <table class="fantasy-table-base weekly-rotation-table compact-table">
            <thead>
                <tr>
                    <th>Team</th>
                    <?php foreach ($days as $day): ?>
                        <th><?php echo ucfirst(substr($day, 0, 3)); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teams)): ?>
                    <tr><td colspan="<?php echo count($days) + 1; ?>">No teams found in this league.</td></tr>
                <?php else: ?>
                    <?php foreach ($teams as $team_id): ?>
                        <tr>
                            <td><strong><?php echo esc_html($team_id); ?></strong></td>
                            <?php foreach ($days as $day): ?>
                                <td>
                                    <?php if (isset($submissions[$team_id][$day])): 
                                        $sub = $submissions[$team_id][$day];
                                    ?>
                                        <div class="pitcher-cell">
                                            <span><?php echo $sub['p1']; // Allowed HTML ?></span>
                                            <span><?php echo $sub['p2']; // Allowed HTML ?></span>
                                            <?php echo $sub['banked_html']; // Allowed HTML ?>
                                            <small class="timestamp">Updated: <?php echo esc_html($sub['date']); ?></small>
                                        </div>
                                    <?php else: ?>
                                        –
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <style>
        .weekly-rotation-table.compact-table th, 
        .weekly-rotation-table.compact-table td {
            padding: 5px 8px; /* Reduced padding */
            font-size: 13px;  /* Smaller font */
            white-space: nowrap; /* Prevent wrapping if possible */
        }
        .weekly-rotation-table.compact-table th {
            font-size: 14px;
        }
        .weekly-rotation-table .pitcher-cell {
            display: flex;
            flex-direction: column;
            font-size: 11px; /* Even smaller for player names */
            line-height: 1.2;
        }
        .weekly-rotation-table .timestamp {
            display: block; /* Show timestamp */
            font-size: 9px; /* Very small */
            color: #999;
            margin-top: 2px;
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('view_rotations', 'display_view_rotations_shortcode');
