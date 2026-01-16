<?php
/**
 * Bug Reporting System for Managers
 * Allows users to submit site errors for admin review.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the 'bug_report' Custom Post Type
 */
function fod_register_bug_report_cpt() {
    $args = array(
        'label'                 => __( 'Bug Reports', 'text_domain' ),
        'labels'                => array(
            'name'          => 'Bug Reports',
            'singular_name' => 'Bug Report',
            'menu_name'     => 'Bug Reports',
        ),
        'supports'              => array( 'title', 'editor', 'author', 'comments' ),
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_icon'             => 'dashicons-admin-tools',
        'capability_type'       => 'post',
        'has_archive'           => false,
    );
    register_post_type( 'bug_report', $args );
}
add_action( 'init', 'fod_register_bug_report_cpt' );

/**
 * [submit_bug_report] Shortcode
 */
function display_bug_report_form_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>Please log in to submit a bug report.</p>';
    }

    ob_start();
    
    if ( isset($_GET['bug_submitted']) ) {
        echo '<div class="notice notice-success" style="padding: 10px; background: #dff0d8; border-left: 4px solid #3c763d; margin-bottom: 20px;">Thank you! Your report has been submitted for review.</div>';
    }
    ?>
    <form id="bug-report-form" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
        <input type="hidden" name="action" value="submit_bug_report">
        <?php wp_nonce_field( 'submit_bug_report_nonce', 'bug_report_nonce_field' ); ?>

        <p>
            <label for="bug_title"><strong>Short Summary of Issue:</strong></label><br>
            <input type="text" name="bug_title" id="bug_title" style="width: 100%;" required placeholder="e.g. Salary not updating for Aaron Judge">
        </p>

        <p>
            <label for="bug_description"><strong>Detailed Description:</strong></label><br>
            <textarea name="bug_description" id="bug_description" rows="6" style="width: 100%;" required placeholder="Please describe exactly what is wrong..."></textarea>
        </p>

        <p>
            <button type="submit" class="button button-primary" style="background: #2E6DA4; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Submit Report</button>
        </p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'submit_bug_report', 'display_bug_report_form_shortcode' );

/**
 * Handle Bug Report Submission
 */
function handle_bug_report_submission() {
    if ( ! isset( $_POST['bug_report_nonce_field'] ) || ! wp_verify_nonce( $_POST['bug_report_nonce_field'], 'submit_bug_report_nonce' ) ) {
        wp_die('Security check failed');
    }

    if ( ! is_user_logged_in() ) {
        wp_die('You must be logged in.');
    }

    $title       = sanitize_text_field( $_POST['bug_title'] );
    $description = sanitize_textarea_field( $_POST['bug_description'] );
    $user_id     = get_current_user_id();

    $post_data = array(
        'post_title'   => $title,
        'post_content' => $description,
        'post_status'  => 'publish', // Stored as published so admins can see it easily
        'post_type'    => 'bug_report',
        'post_author'  => $user_id,
    );

    $post_id = wp_insert_post( $post_data );

    if ( $post_id ) {
        update_post_meta( $post_id, 'bug_status', 'pending' );
        
        // Redirect back with success message
        wp_redirect( add_query_arg( 'bug_submitted', '1', wp_get_referer() ) );
        exit;
    }
}
add_action( 'admin_post_submit_bug_report', 'handle_bug_report_submission' );

/**
 * Add columns to the Bug Report admin list
 */
function fod_set_bug_report_columns($columns) {
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = 'Issue';
    $new_columns['author'] = 'Submitted By';
    $new_columns['date'] = 'Date';
    return $new_columns;
}
add_filter('manage_bug_report_posts_columns', 'fod_set_bug_report_columns');
