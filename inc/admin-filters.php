<?php
/**
 * Admin Filters for Player Data
 * Adds League ID and Fantasy Team ID filter dropdowns to the Players list in WP Admin.
 */

// 1. Add columns to the admin list
function fod_add_league_column($columns) {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['league_id'] = 'League';
            $new_columns['fantasy_team'] = 'Fantasy Team';
            $new_columns['fa_status'] = 'FA Status';
        }
    }
    return $new_columns;
}
add_filter('manage_playerdata_posts_columns', 'fod_add_league_column');

// 2. Populate the custom columns
function fod_populate_league_column($column, $post_id) {
    if ($column === 'league_id') {
        echo esc_html(get_post_meta($post_id, 'league_id', true));
    }
    if ($column === 'fantasy_team') {
        echo esc_html(get_post_meta($post_id, 'fantasy_team_id', true));
    }
    if ($column === 'fa_status') {
        $status = get_post_meta($post_id, 'fa_status', true);
        $style = ($status === 'on_waivers') ? 'style="color:red; font-weight:bold;"' : '';
        echo '<span ' . $style . '>' . esc_html($status) . '</span>';
    }
}
add_action('manage_playerdata_posts_custom_column', 'fod_populate_league_column', 10, 2);

// 3. Make columns sortable
function fod_sortable_league_column($columns) {
    $columns['league_id'] = 'league_id';
    $columns['fantasy_team'] = 'fantasy_team_id';
    $columns['fa_status'] = 'fa_status';
    return $columns;
}
add_filter('manage_edit-playerdata_sortable_columns', 'fod_sortable_league_column');

// 4. Add the Filter Dropdowns (League, Team, FA Status)
function fod_add_admin_filters($post_type) {
    if ($post_type !== 'playerdata') {
        return;
    }

    global $wpdb;

    // --- League Filter ---
    $leagues = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_value ASC",
        'league_id'
    ));

    $current_league = isset($_GET['filter_league']) ? $_GET['filter_league'] : '';

    echo '<select name="filter_league">';
    echo '<option value="">All Leagues</option>';
    foreach ($leagues as $league) {
        if (empty($league)) continue;
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($league),
            selected($current_league, $league, false),
            esc_html($league)
        );
    }
    echo '</select>';

    // --- Team Filter ---
    $teams = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != '' ORDER BY meta_value ASC",
        'fantasy_team_id'
    ));

    $current_team = isset($_GET['filter_team']) ? $_GET['filter_team'] : '';

    echo '<select name="filter_team">';
    echo '<option value="">All Teams</option>';
    foreach ($teams as $team) {
        if (empty($team)) continue;
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($team),
            selected($current_team, $team, false),
            esc_html($team)
        );
    }
    echo '</select>';

    // --- FA Status Filter ---
    $statuses = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_value ASC",
        'fa_status'
    ));

    $current_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

    echo '<select name="filter_status">';
    echo '<option value="">All Statuses</option>';
    foreach ($statuses as $status) {
        if (empty($status)) continue;
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($status),
            selected($current_status, $status, false),
            esc_html($status)
        );
    }
    echo '</select>';
}
add_action('restrict_manage_posts', 'fod_add_admin_filters');

// 5. Filter the Query
function fod_filter_players_query($query) {
    global $pagenow;

    if (is_admin() && $pagenow === 'edit.php' && $query->get('post_type') === 'playerdata') {
        
        $meta_query = $query->get('meta_query') ?: [];

        // Handle League Filter
        if (isset($_GET['filter_league']) && !empty($_GET['filter_league'])) {
            $meta_query[] = [
                'key' => 'league_id',
                'value' => sanitize_text_field($_GET['filter_league']),
                'compare' => '='
            ];
        }

        // Handle Team Filter
        if (isset($_GET['filter_team']) && !empty($_GET['filter_team'])) {
            $meta_query[] = [
                'key' => 'fantasy_team_id',
                'value' => sanitize_text_field($_GET['filter_team']),
                'compare' => '='
            ];
        }

        // Handle Status Filter
        if (isset($_GET['filter_status']) && !empty($_GET['filter_status'])) {
            $meta_query[] = [
                'key' => 'fa_status',
                'value' => sanitize_text_field($_GET['filter_status']),
                'compare' => '='
            ];
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }

        // Handle Column Sorting
        $orderby = $query->get('orderby');
        if ($orderby === 'league_id') {
            $query->set('meta_key', 'league_id');
            $query->set('orderby', 'meta_value');
        }
        if ($orderby === 'fantasy_team_id') {
            $query->set('meta_key', 'fantasy_team_id');
            $query->set('orderby', 'meta_value');
        }
        if ($orderby === 'fa_status') {
            $query->set('meta_key', 'fa_status');
            $query->set('orderby', 'meta_value');
        }
    }
}
add_action('pre_get_posts', 'fod_filter_players_query');
