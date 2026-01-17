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
    $fn_exists = function_exists('get_field');
    $trade_page_id       = $fn_exists ? (int) ( get_field('trade_page', 'option') ?: 0 ) : 0;
    $free_agent_page_id  = $fn_exists ? (int) ( get_field('free_agent_page', 'option') ?: 0 ) : 0;
    $view_trades_page_id = $fn_exists ? (int) ( get_field('view_pending_trades_page', 'option') ?: 0 ) : 0;
    $roster_page_id      = $fn_exists ? (int) ( get_field('roster_page', 'option') ?: 0 ) : 0;
    $waiver_page_id      = $fn_exists ? (int) ( get_field('waiver_wire_page', 'option') ?: 0 ) : 0;

    // Trade form AJAX JS (only on trade page)
    $load_trade_js = ( $trade_page_id && is_page( $trade_page_id ) );
    if ( ! $load_trade_js && function_exists('get_post') ) {
        $post = get_post();
        if ( $post && has_shortcode( $post->post_content, 'trade_proposal_form' ) ) {
            $load_trade_js = true;
        }
    }

    if ( $load_trade_js ) {
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
    $load_fa_modal_js = false;
    if ( ($free_agent_page_id && is_page($free_agent_page_id)) || ($roster_page_id && is_page($roster_page_id)) || ($waiver_page_id && is_page($waiver_page_id)) ) {
        $load_fa_modal_js = true;
    }
    if ( function_exists('get_post') ) {
        $post = get_post();
        if ( $post && ( 
            has_shortcode( $post->post_content, 'fa_bidding_history' ) || 
            has_shortcode( $post->post_content, 'fa_bid_calculator' ) || 
            has_shortcode( $post->post_content, 'my_team_roster' ) 
        ) ) {
            $load_fa_modal_js = true;
        }
    }

    if ( $load_fa_modal_js ) {
        wp_enqueue_script('fa-modal-script', get_stylesheet_directory_uri() . '/js/fa-modal.js', array('jquery'), '1.8', true);
        wp_localize_script(
            'fa-modal-script',
            'faModalData',
            array(
                'ajax_url'                  => admin_url('admin-ajax.php'),
                'nonce'                     => wp_create_nonce('fa_modal_nonce_action'),
                'roster_move_nonce'         => wp_create_nonce('roster_move_nonce'),
                'get_fa_sign_nonce'         => wp_create_nonce('get_fa_sign_nonce'),
                'move_to_il_nonce'          => wp_create_nonce('move_to_il_nonce'),
                'activate_from_il_nonce'    => wp_create_nonce('activate_from_il_nonce'),
                'promote_to_26man_nonce'    => wp_create_nonce('promote_to_26man_nonce'),
                'option_to_minors_nonce'    => wp_create_nonce('option_to_minors_nonce'),
                'promote_to_40man_nonce'    => wp_create_nonce('promote_to_40man_nonce'),
                'dfa_player_nonce'          => wp_create_nonce('dfa_player_nonce'),
                'claim_player_nonce'        => wp_create_nonce('claim_player_nonce'),
            )
        );

        // Enqueue Extension Calculator (for Roster Page)
        wp_enqueue_script('fod-extension-js', get_stylesheet_directory_uri() . '/js/extension-calculator.js', array('jquery'), '1.0', true);
        wp_localize_script('fod-extension-js', 'fodExtData', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('extension_calc_nonce')
        ));
    }

    // --- NEW: Load jQuery for Rotation & Arbitration Submission Pages ---
    if ( function_exists('get_post') ) {
        $post = get_post();
        if ( $post && (has_shortcode( $post->post_content, 'submit_rotation' ) || has_shortcode( $post->post_content, 'arbitration_acceptance_form' )) ) {
            wp_enqueue_script('jquery');
        }
    }
}
add_action( 'wp_enqueue_scripts', 'twentytwentytwo_child_enqueue_styles' );
