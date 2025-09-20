<?php
/* ------------------------------------------------------------------------
   ACF Options Page
------------------------------------------------------------------------ */

if ( function_exists('acf_add_options_page') ) {
    acf_add_options_page(array(
        'page_title' => 'Site Custom Settings',
        'menu_title' => 'Site Settings',
        'menu_slug'  => 'site-custom-settings',
        'capability' => 'manage_options',
        'redirect'   => false,
    ));
}
