<?php
/**
 * Upload this file to your WordPress root directory (where wp-config.php is).
 * Access it via your browser: https://yourdomain.com/debug_roster_check.php
 * Delete it after use.
 */

// Load WordPress
require_once('wp-load.php');

if ( ! current_user_can('manage_options') ) {
    wp_die('You must be an admin to view this debug script.');
}

echo '<style>body{font-family:sans-serif; padding:20px;} pre{background:#eee; padding:10px; border:1px solid #ddd;}</style>';
echo '<h1>Roster Discovery Debugger</h1>';

// 1. Check Post Types
echo '<h2>1. Post Type Counts</h2>';
$counts = wp_count_posts('playerdata');
echo '<pre>'; print_r($counts); echo '</pre>';

if ( $counts->publish < 1 ) {
    echo '<p style="color:red;">CRITICAL: No published "playerdata" posts found. Did the import run on Production?</p>';
}

// 2. Check Meta Values (Direct SQL)
global $wpdb;
echo '<h2>2. Raw Database Values (Distinct)</h2>';

// Check Leagues
$leagues = $wpdb->get_col("SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = 'league_id' AND meta_value != ''");
echo '<strong>Leagues Found:</strong> <pre>' . implode(', ', $leagues) . '</pre>';

// Check Teams
$teams = $wpdb->get_col("SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = 'fantasy_team_id' AND meta_value != ''");
echo '<strong>Teams Found:</strong> <pre>' . implode(', ', $teams) . '</pre>';

// 3. Test the "Shortcode Logic" Query
echo '<h2>3. Simulating Shortcode Query</h2>';

$target_league = !empty($leagues) ? $leagues[0] : 'MLB'; // Default to first found or MLB
echo '<p>Testing Query for League ID: <strong>' . esc_html($target_league) . '</strong></p>';

$args = [
    'post_type'      => 'playerdata',
    'posts_per_page' => 5,
    'meta_query'     => [
        [
            'key'   => 'league_id',
            'value' => $target_league,
        ]
    ]
];

$query = new WP_Query($args);

echo '<p><strong>Found Posts:</strong> ' . $query->found_posts . '</p>';
echo '<p><strong>SQL Request:</strong> <code>' . esc_html($query->request) . '</code></p>';

if ( $query->have_posts() ) {
    echo '<ul>';
    while ( $query->have_posts() ) {
        $query->the_post();
        echo '<li>';
        echo '<strong>' . get_the_title() . '</strong> (ID: ' . get_the_ID() . ')<br>';
        echo 'League ID: ' . get_post_meta(get_the_ID(), 'league_id', true) . '<br>';
        echo 'Team ID: ' . get_post_meta(get_the_ID(), 'fantasy_team_id', true) . '<br>';
        echo '</li>';
    }
    echo '</ul>';
} else {
    echo '<p style="color:red;">Query returned no results. This matches the empty roster page symptom.</p>';
    echo '<p><strong>Potential Cause:</strong> Meta Key "league_id" might be missing or stored differently in Production.</p>';
}

// 4. Check one random player
echo '<h2>4. Random Player Inspection</h2>';
$random_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_type = 'playerdata' AND post_status = 'publish' ORDER BY RAND() LIMIT 1");

if ( $random_id ) {
    echo '<p>Inspecting Player ID: ' . $random_id . ' (' . get_the_title($random_id) . ')</p>';
    $meta = get_post_meta($random_id);
    echo '<pre>'; 
    // Filter relevant keys
    $relevant = array_intersect_key($meta, array_flip(['league_id', 'fantasy_team_id', 'position', 'status_40_man', 'contract_2026']));
    print_r($relevant); 
    echo '</pre>';
} else {
    echo 'No players found to inspect.';
}
