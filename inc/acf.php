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

/* ------------------------------------------------------------------------
   Manually Register ACF Field Groups
   (Fallback if JSON sync fails)
------------------------------------------------------------------------ */
if( function_exists('acf_add_local_field_group') ) {

    // League Key Dates Field Group
    acf_add_local_field_group(array(
        'key' => 'group_league_dates',
        'title' => 'League Key Dates',
        'fields' => array(
            array(
                'key' => 'field_dates_mlb',
                'label' => 'MLB Key Dates',
                'name' => 'dates_mlb',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Add Date',
                'sub_fields' => array(
                    array(
                        'key' => 'field_date_mlb_date',
                        'label' => 'Date',
                        'name' => 'date',
                        'type' => 'text',
                        'placeholder' => 'e.g. March 28, 2026',
                    ),
                    array(
                        'key' => 'field_date_mlb_event',
                        'label' => 'Event',
                        'name' => 'event',
                        'type' => 'text',
                        'placeholder' => 'e.g. Opening Day',
                    ),
                ),
            ),
            array(
                'key' => 'field_dates_aaa',
                'label' => 'AAA Key Dates',
                'name' => 'dates_aaa',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Add Date',
                'sub_fields' => array(
                    array(
                        'key' => 'field_date_aaa_date',
                        'label' => 'Date',
                        'name' => 'date',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_date_aaa_event',
                        'label' => 'Event',
                        'name' => 'event',
                        'type' => 'text',
                    ),
                ),
            ),
            array(
                'key' => 'field_dates_aa',
                'label' => 'AA Key Dates',
                'name' => 'dates_aa',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Add Date',
                'sub_fields' => array(
                    array(
                        'key' => 'field_date_aa_date',
                        'label' => 'Date',
                        'name' => 'date',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_date_aa_event',
                        'label' => 'Event',
                        'name' => 'event',
                        'type' => 'text',
                    ),
                ),
            ),
            array(
                'key' => 'field_dates_nba',
                'label' => 'NBA Key Dates',
                'name' => 'dates_nba',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Add Date',
                'sub_fields' => array(
                    array(
                        'key' => 'field_date_nba_date',
                        'label' => 'Date',
                        'name' => 'date',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_date_nba_event',
                        'label' => 'Event',
                        'name' => 'event',
                        'type' => 'text',
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'site-custom-settings',
                ),
            ),
        ),
        'menu_order' => 10,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => 'Manage key dates for each league.',
    ));

    // League Thresholds (Luxury Tax, etc.)
    acf_add_local_field_group(array(
        'key' => 'group_league_thresholds',
        'title' => 'League Thresholds & Deadlines',
        'fields' => array(
            array(
                'key' => 'field_luxury_tax_thresholds',
                'label' => 'Luxury Tax Thresholds',
                'name' => 'luxury_tax_thresholds',
                'type' => 'repeater',
                'instructions' => 'Set the luxury tax limit for each year.',
                'layout' => 'table',
                'button_label' => 'Add Year',
                'sub_fields' => array(
                    array(
                        'key' => 'field_tax_year',
                        'label' => 'Year',
                        'name' => 'year',
                        'type' => 'number',
                        'placeholder' => 'e.g. 2026',
                    ),
                    array(
                        'key' => 'field_tax_limit',
                        'label' => 'Tax Limit',
                        'name' => 'limit',
                        'type' => 'number',
                        'prepend' => '$',
                        'default_value' => 241000000,
                    ),
                ),
            ),
            array(
                'key' => 'field_trade_deadlines',
                'label' => 'Trade Deadlines',
                'name' => 'trade_deadlines',
                'type' => 'repeater',
                'instructions' => 'Set the trade deadline for each league and year.',
                'layout' => 'table',
                'button_label' => 'Add Deadline',
                'sub_fields' => array(
                    array(
                        'key' => 'field_trade_deadline_year',
                        'label' => 'Year',
                        'name' => 'year',
                        'type' => 'number',
                        'placeholder' => '2026',
                    ),
                    array(
                        'key' => 'field_trade_deadline_league',
                        'label' => 'League',
                        'name' => 'league_id',
                        'type' => 'select',
                        'choices' => array(
                            'MLB' => 'MLB',
                            'AAA' => 'AAA',
                            'AA'  => 'AA',
                        ),
                    ),
                    array(
                        'key' => 'field_trade_deadline_date',
                        'label' => 'Deadline Date',
                        'name' => 'deadline_date',
                        'type' => 'date_picker',
                        'display_format' => 'F j, Y',
                        'return_format' => 'Ymd',
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'site-custom-settings',
                ),
            ),
        ),
        'menu_order' => 12, // Between Key Dates and Financials
        'active' => true,
    ));

    // Team Financials (ISBP Balances)
    acf_add_local_field_group(array(
        'key' => 'group_team_financials',
        'title' => 'Team Financials (ISBP)',
        'fields' => array(
            array(
                'key' => 'field_isbp_mlb',
                'label' => 'MLB ISBP Balances',
                'name' => 'isbp_mlb',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Add Team',
                'sub_fields' => array(
                    array(
                        'key' => 'field_isbp_mlb_team',
                        'label' => 'Team ID',
                        'name' => 'team_id',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_isbp_mlb_balance',
                        'label' => 'ISBP Balance',
                        'name' => 'balance',
                        'type' => 'number',
                        'prepend' => '$',
                        'default_value' => 0,
                    ),
                ),
            ),
            array(
                'key' => 'field_isbp_aaa',
                'label' => 'AAA ISBP Balances',
                'name' => 'isbp_aaa',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Add Team',
                'sub_fields' => array(
                    array(
                        'key' => 'field_isbp_aaa_team',
                        'label' => 'Team ID',
                        'name' => 'team_id',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_isbp_aaa_balance',
                        'label' => 'ISBP Balance',
                        'name' => 'balance',
                        'type' => 'number',
                        'prepend' => '$',
                        'default_value' => 0,
                    ),
                ),
            ),
            array(
                'key' => 'field_isbp_aa',
                'label' => 'AA ISBP Balances',
                'name' => 'isbp_aa',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Add Team',
                'sub_fields' => array(
                    array(
                        'key' => 'field_isbp_aa_team',
                        'label' => 'Team ID',
                        'name' => 'team_id',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_isbp_aa_balance',
                        'label' => 'ISBP Balance',
                        'name' => 'balance',
                        'type' => 'number',
                        'prepend' => '$',
                        'default_value' => 0,
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'site-custom-settings',
                ),
            ),
        ),
        'menu_order' => 15,
        'active' => true,
    ));

    // Team Financials (MILB Allowance)
    acf_add_local_field_group(array(
        'key' => 'group_team_financials_milb',
        'title' => 'Team Financials (MILB)',
        'fields' => array(
            array(
                'key' => 'field_milb_mlb',
                'label' => 'MLB MILB Balances',
                'name' => 'milb_mlb',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Add Team',
                'sub_fields' => array(
                    array(
                        'key' => 'field_milb_mlb_team',
                        'label' => 'Team ID',
                        'name' => 'team_id',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_milb_mlb_balance',
                        'label' => 'MILB Balance',
                        'name' => 'balance',
                        'type' => 'number',
                        'prepend' => '$',
                        'default_value' => 0,
                    ),
                ),
            ),
            array(
                'key' => 'field_milb_aaa',
                'label' => 'AAA MILB Balances',
                'name' => 'milb_aaa',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Add Team',
                'sub_fields' => array(
                    array(
                        'key' => 'field_milb_aaa_team',
                        'label' => 'Team ID',
                        'name' => 'team_id',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_milb_aaa_balance',
                        'label' => 'MILB Balance',
                        'name' => 'balance',
                        'type' => 'number',
                        'prepend' => '$',
                        'default_value' => 0,
                    ),
                ),
            ),
            array(
                'key' => 'field_milb_aa',
                'label' => 'AA MILB Balances',
                'name' => 'milb_aa',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Add Team',
                'sub_fields' => array(
                    array(
                        'key' => 'field_milb_aa_team',
                        'label' => 'Team ID',
                        'name' => 'team_id',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_milb_aa_balance',
                        'label' => 'MILB Balance',
                        'name' => 'balance',
                        'type' => 'number',
                        'prepend' => '$',
                        'default_value' => 0,
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'options_page',
                    'operator' => '==',
                    'value' => 'site-custom-settings',
                ),
            ),
        ),
        'menu_order' => 16, // After ISBP
        'active' => true,
    ));

    // Bid History Field Group
    acf_add_local_field_group(array(
        'key' => 'group_bid_history',
        'title' => 'Bid History',
        'fields' => array(
            array(
                'key' => 'field_bid_history',
                'label' => 'Bid History',
                'name' => 'bid_history',
                'type' => 'repeater',
                'instructions' => 'This field stores the history of bids for a player.',
                'layout' => 'table',
                'button_label' => 'Add Bid',
                'sub_fields' => array(
                    array(
                        'key' => 'field_history_team_id',
                        'label' => 'Team ID',
                        'name' => 'history_team_id',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_history_bid_amount',
                        'label' => 'Bid Amount',
                        'name' => 'history_bid_amount',
                        'type' => 'number',
                    ),
                    array(
                        'key' => 'field_history_bid_years',
                        'label' => 'Bid Years',
                        'name' => 'history_bid_years',
                        'type' => 'number',
                    ),
                    array(
                        'key' => 'field_history_bid_aav',
                        'label' => 'Bid AAV',
                        'name' => 'history_bid_aav',
                        'type' => 'number',
                    ),
                    array(
                        'key' => 'field_history_timestamp',
                        'label' => 'Timestamp',
                        'name' => 'history_timestamp',
                        'type' => 'date_time_picker',
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'playerdata',
                ),
            ),
        ),
        'menu_order' => 20,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => 'Stores the bidding history for free agents.',
    ));

}

