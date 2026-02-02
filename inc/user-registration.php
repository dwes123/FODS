<?php
/**
 * Custom User Registration with Admin Approval System (Version 6.0 - Guaranteed Team Lists)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * [fod_register_form] Shortcode
 */
function fod_display_registration_form() {
    if ( is_user_logged_in() ) {
        return '<p>You are already logged in. <a href="' . esc_url( home_url() ) . '">Return to Home</a></p>';
    }

    $mlb_teams = [
        "Arizona Diamondbacks", "Atlanta Braves", "Baltimore Orioles", "Boston Red Sox", "Chicago Cubs", 
        "Chicago White Sox", "Cincinnati Reds", "Cleveland Guardians", "Colorado Rockies", "Detroit Tigers", 
        "Houston Astros", "Kansas City Royals", "Los Angeles Angels", "Los Angeles Dodgers", "Miami Marlins", 
        "Milwaukee Brewers", "Minnesota Twins", "New York Mets", "New York Yankees", "Oakland Athletics", 
        "Philadelphia Phillies", "Pittsburgh Pirates", "San Diego Padres", "San Francisco Giants", "Seattle Mariners", 
        "St. Louis Cardinals", "Tampa Bay Rays", "Texas Rangers", "Toronto Blue Jays", "Washington Nationals"
    ];

    $aaa_teams = [
        "Albuquerque Isotopes", "Buffalo Bisons", "Charlotte Knights", "Columbus Clippers", "Durham Bulls", 
        "El Paso Chihuahuas", "Gwinnett Stripers", "Indianapolis Indians", "Iowa Cubs", "Jacksonville Jumbo Shrimp", 
        "Las Vegas Aviators", "Lehigh Valley IronPigs", "Louisville Bats", "Memphis Redbirds", "Nashville Sounds", 
        "Norfolk Tides", "Oklahoma City Comets", "Omaha Storm Chasers", "Reno Aces", "Rochester Red Wings", 
        "Round Rock Express", "Sacramento River Cats", "Salt Lake Bees", "Scranton/Wilkes-Barre RailRiders", 
        "St. Paul Saints", "Sugar Land Space Cowboys", "Syracuse Mets", "Tacoma Rainiers", "Toledo Mud Hens", "Worcester Red Sox"
    ];

    $aa_teams = [
        "Akron RubberDucks", "Altoona Curve", "Amarillo Sod Poodles", "Arkansas Travelers", "Biloxi Shuckers", 
        "Binghamton Rumble Ponies", "Birmingham Barons", "Chattanooga Lookouts", "Chesapeake Baysox", "Columbus Clingstones", 
        "Corpus Christi Hooks", "Erie SeaWolves", "Frisco RoughRiders", "Harrisburg Senators", "Hartford Yard Goats", 
        "Knoxville Smokies", "Midland RockHounds", "Montgomery Biscuits", "New Hampshire Fisher Cats", "Northwest Arkansas Naturals", 
        "Pensacola Blue Wahoos", "Portland Sea Dogs", "Reading Fightin Phils", "Richmond Flying Squirrels", "Rocket City Trash Pandas", 
        "San Antonio Missions", "Somerset Patriots", "Springfield Cardinals", "Tulsa Drillers", "Wichita Wind Surge"
    ];

    $high_a_teams = [
        "Asheville Tourists", "Beloit Sky Carp", "Bowling Green Hot Rods", "Brooklyn Cyclones", "Cedar Rapids Kernels", 
        "Dayton Dragons", "Eugene Emeralds", "Everett AquaSox", "Fort Wayne TinCaps", "Frederick Keys", 
        "Great Lakes Loons", "Greensboro Grasshoppers", "Greenville Drive", "Hillsboro Hops", "Hub City Spartanburgers", 
        "Hudson Valley Renegades", "Jersey Shore BlueClaws", "Lake County Captains", "Lansing Lugnuts", "Peoria Chiefs", 
        "Quad Cities River Bandits", "Rome Emperors", "South Bend Cubs", "Spokane Indians", "Tri-City Dust Devils", 
        "Vancouver Canadians", "West Michigan Whitecaps", "Wilmington Blue Rocks", "Winston-Salem Dash", "Wisconsin Timber Rattlers"
    ];

    sort($mlb_teams);
    sort($aaa_teams);
    sort($aa_teams);
    sort($high_a_teams);

    ob_start();
    
    if ( isset($_GET['reg_success']) ) {
        echo '<div class="notice notice-success" style="padding:15px; background:#dff0d8; border-left:4px solid #3c763d; margin-bottom:20px;"><strong>Success!</strong> Your request has been sent to the commissioner team. We will review your application for the team requested.</div>';
    }

    if ( isset($_GET['reg_errors']) ) {
        echo '<div class="notice notice-error" style="color:red; margin-bottom:10px; font-weight:bold;">Error: ' . esc_html($_GET['reg_errors']) . '</div>';
    }
    ?>
    <div class="fod-registration-wrapper" style="max-width: 550px; margin: 20px auto; padding: 30px; background: #fff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
        <h2 style="margin-top:0; text-align:center;">Request a Manager Account</h2>
        
        <form id="fod-registration-form" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
            <input type="hidden" name="action" value="fod_process_registration">
            <?php wp_nonce_field( 'fod_new_user_nonce', 'fod_reg_nonce' ); ?>

            <div style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #eee;">
                <h3 style="margin-top:0; font-size: 1.1em;">1. Account Details</h3>
                <p>
                    <label for="reg_username"><strong>Desired Username:</strong></label><br>
                    <input type="text" name="reg_username" id="reg_username" style="width:100%; padding:10px;" required>
                </p>
                <p>
                    <label for="reg_email"><strong>Email Address:</strong></label><br>
                    <input type="email" name="reg_email" id="reg_email" style="width:100%; padding:10px;" required>
                </p>
                <p>
                    <label for="reg_password"><strong>Choose Password:</strong></label><br>
                    <input type="password" name="reg_password" id="reg_password" style="width:100%; padding:10px;" required minlength="8">
                </p>
            </div>

            <div style="background: #fff4e5; padding: 15px; border-radius: 6px; border: 1px solid #ffd8a8;">
                <h3 style="margin-top:0; font-size: 1.1em; color: #d9480f;">2. League Placement</h3>
                <p>
                    <label for="reg_league"><strong>League Joining:</strong></label><br>
                    <select name="reg_league" id="reg_league" style="width:100%; padding:10px;" required>
                        <option value="">-- Select League --</option>
                        <option value="MLB">MLB</option>
                        <option value="AAA">AAA</option>
                        <option value="AA">AA</option>
                        <option value="High A">High A</option>
                    </select>
                </p>

                <div id="team-selection-box" style="display:none; margin-top:15px;">
                    <label for="reg_team_selection"><strong>Select Team:</strong></label><br>
                    <select name="reg_team_final" id="reg_team_selection" style="width:100%; padding:10px;" required>
                        <option value="">-- Select League First --</option>
                    </select>
                </div>
            </div>

            <p style="margin-top:25px;">
                <button type="submit" id="reg-submit-btn" class="button button-primary" style="width:100%; padding:12px; font-size:1.1em; background-color: #E87426; border:none; color:white; cursor:pointer; font-weight:bold; border-radius:4px;" disabled>Submit Registration Request</button>
            </p>
        </form>
    </div>

    <script type="text/javascript">
    var LEAGUE_MAP = {
        'MLB': <?php echo json_encode($mlb_teams); ?>,
        'AAA': <?php echo json_encode($aaa_teams); ?>,
        'AA': <?php echo json_encode($aa_teams); ?>,
        'High A': <?php echo json_encode($high_a_teams); ?>
    };
    
    document.addEventListener('DOMContentLoaded', function() {
        var leagueSelect = document.getElementById('reg_league');
        var teamSelect   = document.getElementById('reg_team_selection');
        var teamBox      = document.getElementById('team-selection-box');
        var submitBtn    = document.getElementById('reg-submit-btn');

        if (leagueSelect) {
            leagueSelect.addEventListener('change', function() {
                var league = this.value;
                teamSelect.innerHTML = '<option value="">-- Choose Team --</option>';
                
                if (league && LEAGUE_MAP[league]) {
                    teamBox.style.display = 'block';
                    LEAGUE_MAP[league].forEach(function(t) {
                        var opt = document.createElement('option');
                        opt.value = t; opt.text = t;
                        teamSelect.appendChild(opt);
                    });
                    submitBtn.disabled = false;
                } else {
                    teamBox.style.display = 'none';
                    submitBtn.disabled = true;
                }
            });
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'fod_register_form', 'fod_display_registration_form' );

/**
 * Handle Registration Submission
 */
function fod_handle_registration_submission() {
    if ( ! isset( $_POST['fod_reg_nonce'] ) || ! wp_verify_nonce( $_POST['fod_reg_nonce'], 'fod_new_user_nonce' ) ) {
        wp_die('Security check failed');
    }

    $username = sanitize_user( $_POST['reg_username'] );
    $email    = sanitize_email( $_POST['reg_email'] );
    $password = $_POST['reg_password'];
    $league   = sanitize_text_field( $_POST['reg_league'] );
    
    // Determine which team field to use
    $team = ($league === 'High A') ? sanitize_text_field($_POST['reg_team_higha']) : sanitize_text_field($_POST['reg_team_final']);

    if ( username_exists($username) ) {
        wp_redirect( add_query_arg( 'reg_errors', 'This username is already taken. Please choose another.', wp_get_referer() ) );
        exit;
    }

    if ( email_exists($email) ) {
        wp_redirect( add_query_arg( 'reg_errors', 'This email address is already registered. If you forgot your password, please use the login screen.', wp_get_referer() ) );
        exit;
    }

    $post_id = wp_insert_post(array(
        'post_title'  => $username,
        'post_type'   => 'reg_request',
        'post_status' => 'publish',
    ));

    if ( $post_id ) {
        update_post_meta($post_id, 'req_email', $email);
        update_post_meta($post_id, 'req_password', wp_hash_password($password));
        update_post_meta($post_id, 'req_league', $league);
        update_post_meta($post_id, 'req_team', $team);
        wp_redirect( add_query_arg( 'reg_success', '1', wp_get_referer() ) );
        exit;
    }
}
add_action( 'admin_post_nopriv_fod_process_registration', 'fod_handle_registration_submission' );
add_action( 'admin_post_fod_process_registration', 'fod_handle_registration_submission' );

/**
 * Admin Approval Page
 */
function fod_render_account_approval_page() {
    if ( ! current_user_can('manage_options') ) return;

    if ( isset($_GET['action'], $_GET['id'], $_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'reg_action_' . $_GET['id']) ) {
        $post_id = absint($_GET['id']);
        if ( $_GET['action'] === 'approve' ) {
            $username = get_the_title($post_id);
            $email = get_post_meta($post_id, 'req_email', true);
            $hashed_pass = get_post_meta($post_id, 'req_password', true);
            $league = get_post_meta($post_id, 'req_league', true);
            $team = get_post_meta($post_id, 'req_team', true);

            $user_id = wp_create_user($username, wp_generate_password(), $email);
            if ( !is_wp_error($user_id) ) {
                global $wpdb;
                $wpdb->update($wpdb->users, ['user_pass' => $hashed_pass], ['ID' => $user_id]);
                
                if ( function_exists('add_row') ) {
                    add_row('managed_teams', [
                        'league_id' => $league,
                        'fantasy_team_id' => $team
                    ], 'user_' . $user_id);
                }

                wp_delete_post($post_id, true);
                echo '<div class="notice notice-success"><p>User approved and assigned to ' . esc_html($team) . '!</p></div>';
            }
        } elseif ( $_GET['action'] === 'deny' ) {
            wp_delete_post($post_id, true);
            echo '<div class="notice notice-warning"><p>Request denied and deleted.</p></div>';
        }
    }

    $requests = new WP_Query(['post_type' => 'reg_request', 'posts_per_page' => -1]);
    ?>
    <div class="wrap">
        <h1>Pending Account Requests</h1>
        <?php if ( $requests->have_posts() ) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Username</th><th>Email</th><th>League</th><th>Team</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php while ( $requests->have_posts() ) : $requests->the_post(); 
                        $id = get_the_ID();
                        $app_url = wp_nonce_url(admin_url('admin.php?page=fod-account-approvals&action=approve&id='.$id), 'reg_action_'.$id);
                        $den_url = wp_nonce_url(admin_url('admin.php?page=fod-account-approvals&action=deny&id='.$id), 'reg_action_'.$id);
                    ?>
                        <tr>
                            <td><strong><?php the_title(); ?></strong></td>
                            <td><?php echo get_post_meta($id, 'req_email', true); ?></td>
                            <td><?php echo get_post_meta($id, 'req_league', true); ?></td>
                            <td><?php echo get_post_meta($id, 'req_team', true); ?></td>
                            <td>
                                <a href="<?php echo $app_url; ?>" class="button button-primary">Approve & Assign</a>
                                <a href="<?php echo $den_url; ?>" class="button" style="color:red;">Deny</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No pending requests.</p>
        <?php endif; wp_reset_postdata(); ?>
    </div>
    <?php
}