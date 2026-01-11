<?php
/**
 * Smart CSV Importer for Player Data
 * Final version using direct meta updates for reliability.
 * UPDATED: Supports separate player records per league.
 */

function fod_player_importer_menu() {
    add_submenu_page(
        'tools.php',
        'Import Players',
        'Import Players',
        'manage_options',
        'fod-player-importer',
        'fod_render_player_importer_page'
    );
}
add_action('admin_menu', 'fod_player_importer_menu');

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
                if ( empty($player_name) ) continue;

                // Determine the target league for this row
                // It should be in the CSV, usually 'league_id'
                $target_league = trim($data['league_id'] ?? '');

                // --- NEW LOGIC: Find player by Name AND League ---
                $existing_player_id = 0;
                
                $args = [
                    'post_type' => 'playerdata',
                    'title'     => $player_name,
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'fields' => 'ids' // Just get IDs for speed
                ];
                
                // If we have a league, try to find a match in that league
                if ( ! empty($target_league) ) {
                    $args['meta_query'] = [
                        [
                            'key' => 'league_id',
                            'value' => $target_league,
                            'compare' => '='
                        ]
                    ];
                }

                $query = new WP_Query($args);
                
                if ( $query->have_posts() ) {
                    // Found a match! Update this specific player.
                    $existing_player_id = $query->posts[0];
                    $updated_count++;
                } else {
                    // No match found in this league (or at all). Create NEW.
                    $post_args = [
                        'post_title'  => $player_name,
                        'post_type'   => 'playerdata',
                        'post_status' => 'publish',
                    ];
                    $existing_player_id = wp_insert_post($post_args);
                    $imported_count++;
                }

                if ( $existing_player_id && ! is_wp_error($existing_player_id) ) {
                    // --- MAP ALL FIELDS ---
                    foreach ($data as $key => $value) {
                        if ($key === 'name') continue;
                        
                        if ( ! empty($key) ) {
                            update_post_meta($existing_player_id, $key, $value);
                        }
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
