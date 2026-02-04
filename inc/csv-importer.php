<?php
/**
 * Smart CSV Importer for Player Data
 * Final version using direct meta updates for reliability.
 * UPDATED: Supports separate player records per league.
 */

function fod_player_importer_menu() {
    add_submenu_page(
        'commissioner-tools', // Parent Slug
        'Import Players',
        'Import Players',
        'manage_options',
        'fod-player-importer',
        'fod_render_player_importer_page'
    );
}
add_action('admin_menu', 'fod_player_importer_menu', 20);

function fod_render_player_importer_page() {
    if ( ! current_user_can('manage_options') ) {
        return;
    }

    $message = '';
    $imported_count = 0;
    $updated_count = 0;
    $errors = [];

    if ( isset($_POST['fod_import_nonce']) && wp_verify_nonce($_POST['fod_import_nonce'], 'fod_import_players') ) {
        if ( ! empty($_FILES['csv_file']['tmp_name']) ) {
            
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            
            $headers = array_map('trim', fgetcsv($file));
            
            while ( ($row = fgetcsv($file)) !== false ) {
                // Handle potential empty rows in the CSV
                if (count($row) < count($headers)) {
                    $row = array_pad($row, count($headers), '');
                }
                $data = array_combine($headers, $row);
                
                $player_name = trim($data['name'] ?? '');
                $fod_id      = trim($data['fod_id'] ?? '');
                
                if ( empty($player_name) && empty($fod_id) ) continue;

                // Determine the target league for this row
                $target_league = trim($data['league_id'] ?? '');

                $existing_player_id = 0;

                // --- 1. TRY MATCHING BY FOD_ID (Highest Reliability) ---
                if ( ! empty($fod_id) ) {
                    $args = [
                        'post_type' => ['playerdata', 'nbaplayer'],
                        'meta_query' => [
                            ['key' => 'fod_id', 'value' => $fod_id, 'compare' => '=']
                        ],
                        'posts_per_page' => 1,
                        'fields' => 'ids'
                    ];
                    $query_id = new WP_Query($args);
                    if ( $query_id->have_posts() ) {
                        $existing_player_id = $query_id->posts[0];
                    }
                }

                // --- 2. FALLBACK: MATCH BY NAME AND LEAGUE ---
                if ( ! $existing_player_id && ! empty($player_name) ) {
                    $args = [
                        'post_type' => ['playerdata', 'nbaplayer'],
                        'title'     => $player_name,
                        'posts_per_page' => 1,
                        'post_status' => 'publish',
                        'fields' => 'ids'
                    ];
                    
                    if ( ! empty($target_league) ) {
                        $args['meta_query'] = [
                            ['key' => 'league_id', 'value' => $target_league, 'compare' => '=']
                        ];
                    }

                    $query_name = new WP_Query($args);
                    if ( $query_name->have_posts() ) {
                        $existing_player_id = $query_name->posts[0];
                    }
                }

                // --- 3. CREATE NEW IF NO MATCH FOUND ---
                if ( $existing_player_id ) {
                    $updated_count++;
                } else {
                    $post_args = [
                        'post_title'  => $player_name ?: $fod_id,
                        'post_type'   => (strpos($target_league, 'NBA') !== false) ? 'nbaplayer' : 'playerdata',
                        'post_status' => 'publish',
                    ];
                    $existing_player_id = wp_insert_post($post_args);
                    $imported_count++;
                }

                if ( $existing_player_id && ! is_wp_error($existing_player_id) ) {
                    // --- MAP ALL FIELDS ---
                    foreach ($data as $key => $value) {
                        $key = trim($key);
                        if ($key === 'name' || empty($key)) continue;
                        
                        // Special handling for boolean/checkbox fields
                        if ($key === 'is_international_free_agent' || $key === 'on_trade_block') {
                            $value = ($value == '1' || strtolower($value) === 'true' || strtolower($value) === 'x') ? '1' : '0';
                        }

                        update_post_meta($existing_player_id, $key, $value);
                    }

                    // --- SET DEFAULT FA STATUS IF MISSING ---
                    $current_status = get_post_meta($existing_player_id, 'fa_status', true);
                    $current_team = get_post_meta($existing_player_id, 'fantasy_team_id', true);
                    
                    if ( empty($current_status) ) {
                        $new_status = !empty($current_team) ? 'rostered' : 'available';
                        update_post_meta($existing_player_id, 'fa_status', $new_status);
                    }
                } else {
                    $errors[] = "Failed to import: " . $player_name;
                }
            }
            fclose($file);
            $message = "Import Complete! Created: $imported_count, Updated: $updated_count.";
        } else {
            $errors[] = "Please upload a valid CSV file.";
        }
    }

    ?>
    <div class="wrap">
        <h1>Player Data Importer</h1>
        
        <?php if ($message): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="notice notice-error is-dismissible">
                <?php foreach($errors as $err) echo "<p>".esc_html($err)."</p>"; ?>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
            <h3>Instructions</h3>
            <p>This tool is designed to work with the output from the <code>normalize_rosters.py</code> script.</p>
            <p><strong>Important:</strong> This importer now supports multiple leagues. It will check if a player exists <em>in the specific league</em> defined in your CSV. If not, it creates a new player record for that league.</p>
            <ol>
                <li>Place your raw roster files in the <code>Python/raw_rosters</code> folder.</li>
                <li>Run the Python script and enter the desired League ID.</li>
                <li>Take the generated files from the <code>Python/clean_rosters</code> folder.</li>
                <li>Upload one of those clean CSV files below.</li>
            </ol>
            
            <hr>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('fod_import_players', 'fod_import_nonce'); ?>
                <p>
                    <label for="csv_file"><strong>Choose Clean CSV File:</strong></label><br>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                </p>
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Run Import">
                </p>
            </form>
        </div>
    </div>
    <?php
}
