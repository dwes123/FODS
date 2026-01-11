<?php
/**
 * Admin User List Enhancements
 * Adds a "Managed Teams" column to the Users list.
 */

// 1. Add "Managed Teams" Column
function fod_add_user_teams_column($columns) {
    $columns['managed_teams'] = 'Managed Teams';
    return $columns;
}
add_filter('manage_users_columns', 'fod_add_user_teams_column');

// 2. Populate the Column
function fod_show_user_teams_column($value, $column_name, $user_id) {
    if ( 'managed_teams' !== $column_name ) {
        return $value;
    }

    $output = [];

    // Check MLB/MiLB Teams
    if ( function_exists('get_field') ) {
        $mlb_teams = get_field('managed_teams', 'user_' . $user_id);
        if ( is_array($mlb_teams) ) {
            foreach ( $mlb_teams as $team ) {
                if ( !empty($team['fantasy_team_id']) ) {
                    $league = $team['league_id'] ?? 'Unknown';
                    $output[] = sprintf('<strong>%s:</strong> %s', esc_html($league), esc_html($team['fantasy_team_id']));
                }
            }
        }

        // Check NBA Teams
        $nba_teams = get_field('managed_nba_teams', 'user_' . $user_id);
        if ( is_array($nba_teams) ) {
            foreach ( $nba_teams as $team ) {
                if ( !empty($team['fantasy_team_id']) ) {
                    $league = $team['league_id'] ?? 'NBA';
                    $output[] = sprintf('<strong>%s:</strong> %s', esc_html($league), esc_html($team['fantasy_team_id']));
                }
            }
        }
    }

    return implode('<br>', $output);
}
add_filter('manage_users_custom_column', 'fod_show_user_teams_column', 10, 3);
