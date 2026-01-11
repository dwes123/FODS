<?php

/* ------------------------------------------------------------------------
   Dead Cap Admin (Undo Drop)
------------------------------------------------------------------------ */
function dead_cap_admin_menu() {
    add_menu_page(
        'Dead Cap Admin',
        'Dead Cap Admin',
        'manage_options',
        'dead-cap-admin',
        'render_dead_cap_admin_page',
        'dashicons-money',
        58
    );
}
add_action('admin_menu', 'dead_cap_admin_menu');

function render_dead_cap_admin_page() {
    if ( ! current_user_can('manage_options') ) { wp_die('Not allowed'); }

    $q = new WP_Query(array(
        'post_type'      => 'playerdata',
        'posts_per_page' => 200,
        'meta_query'     => array( array('key'=>'drop_backups','compare'=>'EXISTS') ),
        'no_found_rows'  => true,
        'fields'         => 'ids',
    ));

    echo '<div class="wrap"><h1>Dead Cap Admin</h1>';
    if (isset($_GET['undo_status'])) {
        $msg = sanitize_text_field($_GET['undo_status']);
        echo '<div class="notice notice-info"><p>'.esc_html($msg).'</p></div>';
    }

    if ( ! $q->have_posts() ) {
        echo '<p>No drop backups found.</p></div>';
        return;
    }

    echo '<table class="widefat striped"><thead><tr>'
        .'<th>Player</th><th>Backup Time</th><th>Team</th><th>Years Restored</th><th>Contracts Sum</th><th>Action</th>'
        .'</tr></thead><tbody>';

    foreach ($q->posts as $pid) {
        $name = get_the_title($pid);
        $backups = get_post_meta($pid, 'drop_backups', true);
        if (!is_array($backups) || empty($backups)) continue;

        usort($backups, function($a,$b){ return ($b['ts']??0) <=> ($a['ts']??0); });

        foreach ($backups as $bk) {
            $ts  = isset($bk['ts']) ? (int)$bk['ts'] : 0;
            $tid = $bk['team_id'] ?? '';
            $contracts = is_array($bk['contracts'] ?? null) ? $bk['contracts'] : [];
            $years = implode(', ', array_map('intval', array_keys($contracts)));
            $sum = array_sum($contracts);

            $undo_url = wp_nonce_url(
                add_query_arg(array(
                    'action'    => 'undo_player_drop',
                    'player_id' => $pid,
                    'backup_id' => $bk['id'] ?? '',
                ), admin_url('admin-post.php')),
                'undo_drop_'.$pid.'_'.$bk['id']
            );

            echo '<tr><td>'.esc_html($name).'</td>'
                .'<td>'.($ts ? esc_html(date('Y-m-d H:i:s', $ts)) : '—').'</td>'
                .'<td>'.esc_html($tid ?: '—').'</td>'
                .'<td>'.esc_html($years ?: '—').'</td>'
                .'<td>$'.esc_html(number_format($sum,0)).'</td>'
                .'<td><a href="'.esc_url($undo_url).'" class="button">Undo Drop</a></td></tr>';
        }
    }

    echo '</tbody></table></div>';
}

function undo_player_drop_handler() {
    if ( ! current_user_can('manage_options') ) { wp_die('Not allowed'); }

    $player_id = isset($_GET['player_id']) ? absint($_GET['player_id']) : 0;
    $backup_id = isset($_GET['backup_id']) ? sanitize_text_field($_GET['backup_id']) : '';
    if (!$player_id || !$backup_id) { wp_safe_redirect( admin_url('admin.php?page=dead-cap-admin&undo_status=Missing+parameters') ); exit; }

    check_admin_referer('undo_drop_'.$player_id.'_'.$backup_id);

    $backups = get_post_meta($player_id, 'drop_backups', true);
    if (!is_array($backups)) $backups = [];

    $idx = null; $bk = null;
    foreach ($backups as $i => $b) {
        if (isset($b['id']) && $b['id'] === $backup_id) { $idx = $i; $bk = $b; break; }
    }
    if ($bk === null) {
        wp_safe_redirect( admin_url('admin.php?page=dead-cap-admin&undo_status=Backup+not+found') ); exit;
    }

    $team_id  = $bk['team_id'] ?? '';
    $prev_team= $bk['prev_team'] ?? '';
    $prev_stat= $bk['prev_status'] ?? 'rostered';
    $contracts= is_array($bk['contracts'] ?? null) ? $bk['contracts'] : [];
    $cur_year = isset($bk['current_year']) ? (int)$bk['current_year'] : (int)date('Y');

    // remove added dead-cap rows (match by team/year/type and expected amount)
    $rows = get_field('dead_cap_penalties', $player_id);
    if (is_array($rows)) {
        $filtered = [];
        foreach ($rows as $r) {
            $yr = (int)($r['penalty_year'] ?? 0);
            $tid = $r['dead_cap_team_id'] ?? '';
            $ptype = $r['penalty_type'] ?? '';

            $salary = (float)($contracts[$yr] ?? 0);
            $rate   = ($yr === $cur_year) ? 0.75 : 0.50;
            $expected = round($salary * $rate, 0);

            // PHP 7 safe "starts with"
            $ptype_starts_drop = (substr((string)$ptype, 0, 11) === 'Player Drop');

            $is_match = ($tid === $team_id)
                        && isset($contracts[$yr])
                        && $ptype_starts_drop
                        && ((float)($r['penalty_amount'] ?? -1) == $expected);

            if (!$is_match) { $filtered[] = $r; }
        }
        update_field('dead_cap_penalties', $filtered, $player_id);
    }

    // restore contracts
    foreach ($contracts as $yr => $amt) {
        update_field('contract_' . (int)$yr, (float)$amt, $player_id);
    }

    // restore owner + status
    if (!empty($prev_team)) {
        update_field('fantasy_team_id', $prev_team, $player_id);
    }
    update_field('fa_status', $prev_stat ?: 'rostered', $player_id);

    // remove backup entry
    array_splice($backups, $idx, 1);
    update_post_meta($player_id, 'drop_backups', $backups);

    wp_safe_redirect( admin_url('admin.php?page=dead-cap-admin&undo_status=Drop+undone+for+player+'.rawurlencode(get_the_title($player_id))) );
    exit;
}
add_action('admin_post_undo_player_drop', 'undo_player_drop_handler');
