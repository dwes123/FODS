<?php
/**
 * Twenty Twenty-Two Child Theme Functions
 *
 * This file acts as a loader for organizing theme functions from the /inc/ directory.
 * All new functionality should be added to the appropriate file in that folder.
 */

// Load ACF Options Page setup
require_once get_stylesheet_directory() . '/inc/acf.php';

// Load custom cron jobs
require_once get_stylesheet_directory() . '/inc/cron.php';

// Load script and style enqueueing
require_once get_stylesheet_directory() . '/inc/enqueue.php';

// Load admin-specific functionality (like the "Dead Cap Admin" page)
require_once get_stylesheet_directory() . '/inc/admin.php';

// Load all theme shortcodes
require_once get_stylesheet_directory() . '/inc/shortcodes.php';

// Load all AJAX and form submission handlers
require_once get_stylesheet_directory() . '/inc/ajax-handlers.php';