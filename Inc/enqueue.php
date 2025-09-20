<?php
/**
 * Enqueue styles and scripts for the theme.
 */

function twentytwentytwo_child_enqueue_styles() {
    // Parent + child styles
    $parent_handle = 'twentytwentytwo-style';
    wp_enqueue_style( $parent_handle, get_template_directory_uri() . '/style.css' );
    wp_enqueue_style(
        'twentytwentytwo-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( $parent_handle ),
        wp_get_theme()->get('Version')
    );

    // Resolve page IDs from ACF option fields
    $trade_url       = function_exists('get_field') ? ( get_field('trade_page', 'option') ?: '' ) : '';
    $fa_url          = function_exists('get_field') ? ( get_field('free_agent_page', 'option') ?: '' ) : '';
    $view_trades_url = function_exists('get_field') ? ( get_field('view_pending_trades_page', 'option') ?: '' ) : '';
    $roster_url      = function_exists('get_field') ? ( get_field('roster_page', 'option') ?: '' ) : '';
    $waiver_url      = function_exists('get_field') ? ( get_field('waiver_wire_page', 'option') ?: '' ) : '';

    $trade_page_id       = $trade_url       ? url_to_postid($trade_url)       : 0;
    $free_agent_page_id  = $fa_url          ? url_to_postid($fa_url)          : 0;
    $view_trades_page_id = $view_trades_url ? url_to_postid($view_trades_url) : 0;
    $roster_page_id      = $roster_url      ? url_to_postid($roster_url)      : 0;
    $waiver_page_id      = $waiver_url      ? url_to_postid($waiver_url)      : 0;


    // Trade form AJAX JS (only on trade page)
    if ( $trade_page_id && is_page( $trade_page_id ) ) {
        wp_enqueue_script('trade-form-ajax-script', get_stylesheet_directory_uri() . '/js/trade-form-ajax.js', array('jquery'), '1.3', true);
        wp_localize_script(
            'trade-form-ajax-script', 
            'tradeFormAjax', 
            array(
                'ajax_url' => admin_url('admin-ajax.php'), 
                'nonce' => wp_create_nonce('trade_form_nonce')
            )
        );
    }

    // Main AJAX JS for modals, roster moves, waivers, etc.
    // Loaded on FA, Roster, AND Waiver pages.
    if ( ($free_agent_page_id && is_page($free_agent_page_id)) || ($roster_page_id && is_page($roster_page_id)) || ($waiver_page_id && is_page($waiver_page_id)) ) {
        wp_enqueue_script('fa-modal-script', get_stylesheet_directory_uri() . '/js/fa-modal.js', array(), '1.4', true);
        wp_localize_script(
            'fa-modal-script',
            'faModalData',
            array(
                'ajax_url'           => admin_url('admin-ajax.php'),
                'nonce'              => wp_create_nonce('fa_modal_nonce_action'),
                'fa_search_nonce'    => wp_create_nonce('fa_search_nonce'),
                'drop_player_nonce'  => wp_create_nonce('drop_player_nonce'), // Used for waive & claim actions
                'roster_move_nonce'  => wp_create_nonce('roster_move_nonce'),
            )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'twentytwentytwo_child_enqueue_styles' );