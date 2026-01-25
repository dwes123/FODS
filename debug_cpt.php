<?php
require_once('wp-load.php');

if ( ! current_user_can('manage_options') ) {
    wp_die('Admin only.');
}

echo '<h1>Registered Post Types</h1>';
$post_types = get_post_types([], 'objects');

$found = false;
foreach ($post_types as $pt) {
    echo '<strong>' . $pt->name . '</strong> (' . $pt->label . ')<br>';
    if ($pt->name === 'playerdata') {
        $found = true;
    }
}

echo '<hr>';
if ($found) {
    echo '<h2 style="color:green;">SUCCESS: "playerdata" is registered.</h2>';
} else {
    echo '<h2 style="color:red;">FAILURE: "playerdata" is NOT registered.</h2>';
    echo '<p>This is likely the issue. If you use a plugin like <strong>CPT UI</strong> or <strong>Custom Post Type UI</strong>, check if the "playerdata" post type was deleted.</p>';
}
