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
                'key' => 'field_dates_higha',
                'label' => 'High A Key Dates',
                'name' => 'dates_high_a',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => 'Add Date',
                'sub_fields' => array(
                    array(
                        'key' => 'field_date_higha_date',
                        'label' => 'Date',
                        'name' => 'date',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_date_higha_event',
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
                            'MLB'    => 'MLB',
                            'AAA'    => 'AAA',
                            'AA'     => 'AA',
                            'High A' => 'High A',
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
            array(
                'key' => 'field_opening_days',
                'label' => 'Opening Days',
                'name' => 'opening_days',
                'type' => 'repeater',
                'instructions' => 'Set the Opening Day for each league and year.',
                'layout' => 'table',
                'button_label' => 'Add Opening Day',
                'sub_fields' => array(
                    array(
                        'key' => 'field_opening_day_year',
                        'label' => 'Year',
                        'name' => 'year',
                        'type' => 'number',
                        'placeholder' => '2026',
                    ),
                    array(
                        'key' => 'field_opening_day_league',
                        'label' => 'League',
                        'name' => 'league_id',
                        'type' => 'select',
                        'choices' => array(
                            'MLB'    => 'MLB',
                            'AAA'    => 'AAA',
                            'AA'     => 'AA',
                            'High A' => 'High A',
                        ),
                    ),
                    array(
                        'key' => 'field_opening_day_date',
                        'label' => 'Opening Day Date',
                        'name' => 'opening_date',
                        'type' => 'date_picker',
                        'display_format' => 'F j, Y',
                        'return_format' => 'Ymd',
                    ),
                ),
            ),
            array(
                'key' => 'field_slack_settings_tab',
                'label' => 'Slack Integration',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ),
            array(
                'key' => 'field_slack_channels_repeater',
                'label' => 'League Slack Configurations',
                'name' => 'league_slack_channels',
                'type' => 'repeater',
                'instructions' => 'Add the specific Token and Channel ID for each league workspace.',
                'required' => 0,
                'layout' => 'block',
                'button_label' => 'Add League Config',
                'sub_fields' => array(
                    array(
                        'key' => 'field_slack_league_id',
                        'label' => 'League ID',
                        'name' => 'league_id',
                        'type' => 'select',
                        'choices' => array(
                            'MLB'    => 'MLB',
                            'AAA'    => 'AAA',
                            'AA'     => 'AA',
                            'High A' => 'High A',
                            'NBA'    => 'NBA',
                        ),
                        'required' => 1,
                        'wrapper' => array('width' => '20'),
                    ),
                    array(
                        'key' => 'field_slack_bot_token',
                        'label' => 'Bot OAuth Token',
                        'name' => 'bot_token',
                        'type' => 'text',
                        'instructions' => 'starts with xoxb-',
                        'required' => 1,
                        'placeholder' => 'xoxb-...',
                        'wrapper' => array('width' => '40'),
                    ),
                    array(
                        'key' => 'field_slack_channel_id',
                        'label' => 'Channel ID (Trade Block)',
                        'name' => 'channel_id',
                        'type' => 'text',
                        'instructions' => 'Channel for Trade Block alerts.',
                        'required' => 1,
                        'placeholder' => 'C12345678',
                        'wrapper' => array('width' => '30'),
                    ),
                    array(
                        'key' => 'field_slack_completed_trades_channel_id',
                        'label' => 'Channel ID (Completed Trades)',
                        'name' => 'completed_trades_channel_id',
                        'type' => 'text',
                        'instructions' => 'Channel for accepted/completed trades.',
                        'required' => 0,
                        'placeholder' => 'C0ACWU5NR7B',
                        'wrapper' => array('width' => '30'),
                    ),
                    array(
                        'key' => 'field_slack_stat_alerts_channel_id',
                        'label' => 'Stat Alerts Channel ID',
                        'name' => 'stat_alerts_channel_id',
                        'type' => 'text',
                        'instructions' => 'Optional: For HR alerts, etc.',
                        'required' => 0,
                        'placeholder' => 'C04N19DR6QJ',
                        'wrapper' => array('width' => '30'),
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

    // Contract Restructure Tracking (Players)
    acf_add_local_field_group(array(
        'key' => 'group_player_restructure_status',
        'title' => 'Restructure Status',
        'fields' => array(
            array(
                'key' => 'field_has_been_restructured',
                'label' => 'Has Been Restructured?',
                'name' => 'has_been_restructured',
                'type' => 'true_false',
                'instructions' => 'Indicates if this contract has already been restructured.',
                'ui' => 1,
            ),
            // NEW: Bid Type Tracking
            array(
                'key' => 'field_bid_type',
                'label' => 'Current Bid Type',
                'name' => 'bid_type',
                'type' => 'select',
                'choices' => array(
                    'standard' => 'Standard (Free Agent)',
                    'milb' => 'Minor League Contract',
                ),
                'default_value' => 'standard',
                'ui' => 1,
            ),
            array(
                'key' => 'field_milb_qualifying_stat',
                'label' => 'MiLB Qualification',
                'name' => 'milb_qualifying_stat',
                'type' => 'text', // e.g. "25 IP"
                'instructions' => 'Stats entered to qualify for MiLB deal.',
            ),
            // NEW: International Free Agent Flag
            array(
                'key' => 'field_is_international_free_agent',
                'label' => 'Is International Free Agent?',
                'name' => 'is_international_free_agent',
                'type' => 'true_false',
                'instructions' => 'If checked, this player must be signed using ISBP funds.',
                'ui' => 1,
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
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'nbaplayer',
                ),
            ),
        ),
    ));

    // Contract Restructure Tracking (Team usage per year)
    acf_add_local_field_group(array(
        'key' => 'group_team_restructure_tracker',
        'title' => 'Team Restructure Tracker',
        'fields' => array(
            array(
                'key' => 'field_restructure_usage_log',
                'label' => 'Restructure Usage Log',
                'name' => 'restructure_usage_log',
                'type' => 'repeater',
                'layout' => 'table',
                'sub_fields' => array(
                    array(
                        'key' => 'field_restructure_log_team',
                        'label' => 'Team ID',
                        'name' => 'team_id',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_restructure_log_year',
                        'label' => 'League Year',
                        'name' => 'league_year',
                        'type' => 'number',
                    ),
                    array(
                        'key' => 'field_restructure_log_player',
                        'label' => 'Player ID',
                        'name' => 'player_id',
                        'type' => 'number',
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
    ));

    // Contract Extension Tracking (Team usage per year)
    acf_add_local_field_group(array(
        'key' => 'group_team_extension_tracker',
        'title' => 'Team Extension Tracker',
        'fields' => array(
            array(
                'key' => 'field_extension_usage_log',
                'label' => 'Extension Usage Log',
                'name' => 'extension_usage_log',
                'type' => 'repeater',
                'layout' => 'table',
                'instructions' => 'Tracks which teams have used their 2 allowed contract extensions per year.',
                'sub_fields' => array(
                    array(
                        'key' => 'field_extension_log_team',
                        'label' => 'Team ID',
                        'name' => 'team_id',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_extension_log_year',
                        'label' => 'League Year',
                        'name' => 'league_year',
                        'type' => 'number',
                    ),
                    array(
                        'key' => 'field_extension_log_player',
                        'label' => 'Player ID',
                        'name' => 'player_id',
                        'type' => 'number',
                    ),
                    array(
                        'key' => 'field_extension_log_player_name',
                        'label' => 'Player Name',
                        'name' => 'player_name',
                        'type' => 'text',
                        'readonly' => 1,
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
    ));

    // Team Options Field
    acf_add_local_field_group(array(
        'key' => 'group_player_contract_options',
        'title' => 'Team Options',
        'fields' => array(
            array(
                'key' => 'field_contract_option_years',
                'label' => 'Team Option Years',
                'name' => 'contract_option_years',
                'type' => 'select',
                'instructions' => 'Select years that are Team Options.',
                'choices' => array(
                    '2026' => '2026',
                    '2027' => '2027',
                    '2028' => '2028',
                    '2029' => '2029',
                    '2030' => '2030',
                    '2031' => '2031',
                    '2032' => '2032',
                    '2033' => '2033',
                    '2034' => '2034',
                    '2035' => '2035',
                ),
                'allow_null' => 1,
                'multiple' => 1,
                'ui' => 1,
                'ajax' => 0,
                'return_format' => 'value',
                'placeholder' => '',
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
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'nbaplayer',
                ),
            ),
        ),
    ));

    // Trade Retention Field
    acf_add_local_field_group(array(
        'key' => 'group_trade_retention',
        'title' => 'Trade Retention Details',
        'fields' => array(
            array(
                'key' => 'field_retained_salary_players',
                'label' => 'Players with Retained Salary (50%)',
                'name' => 'retained_salary_players',
                'type' => 'text',
                'instructions' => 'Comma-separated list of Player IDs who have 50% salary retained by the sending team.',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'trade_proposal',
                ),
            ),
        ),
    ));

    // Manual Team Financials (IFA/MiLB Dashboard)
    acf_add_local_field_group(array(
        'key' => 'group_manual_team_financials',
        'title' => 'Manual Team Financials (IFA/MiLB)',
        'fields' => array(
            array(
                'key' => 'field_manual_financials_repeater',
                'label' => 'Team Financials',
                'name' => 'manual_team_financials',
                'type' => 'repeater',
                'layout' => 'row', // 'row' layout makes it easier to edit many fields
                'button_label' => 'Add Team Data',
                'sub_fields' => array(
                    array(
                        'key' => 'field_fin_team_id',
                        'label' => 'Team ID',
                        'name' => 'team_id',
                        'type' => 'text', // e.g. "COL"
                        'wrapper' => array('width' => '10'),
                    ),
                    // IFA Column
                    array(
                        'key' => 'field_fin_ifa_allotment',
                        'label' => 'IFA Allotment',
                        'name' => 'ifa_allotment',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    array(
                        'key' => 'field_fin_ifa_deductions',
                        'label' => 'IFA Deductions',
                        'name' => 'ifa_deductions',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    array(
                        'key' => 'field_fin_ifa_max_trade',
                        'label' => 'IFA Max Trade',
                        'name' => 'ifa_max_trade_acquisition',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    array(
                        'key' => 'field_fin_ifa_traded_away',
                        'label' => 'IFA Traded Away',
                        'name' => 'ifa_traded_away',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    array(
                        'key' => 'field_fin_ifa_traded_for',
                        'label' => 'IFA Traded For',
                        'name' => 'ifa_traded_for',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    array(
                        'key' => 'field_fin_ifa_spent',
                        'label' => 'IFA Spent',
                        'name' => 'ifa_spent',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    array(
                        'key' => 'field_fin_ifa_signings',
                        'label' => 'IFA Signings',
                        'name' => 'ifa_signings',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    array(
                        'key' => 'field_fin_ifa_remaining',
                        'label' => 'IFA Remaining',
                        'name' => 'ifa_remaining',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    // MiLB Column
                    array(
                        'key' => 'field_fin_milb_allotment',
                        'label' => 'MiLB Allotment',
                        'name' => 'milb_allotment',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    array(
                        'key' => 'field_fin_rule_v_picks',
                        'label' => 'Rule V Picks',
                        'name' => 'rule_v_picks',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    array(
                        'key' => 'field_fin_purchased',
                        'label' => 'Purchased',
                        'name' => 'purchased',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    array(
                        'key' => 'field_fin_milb_spent',
                        'label' => 'MiLB Spent',
                        'name' => 'milb_spent',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    array(
                        'key' => 'field_fin_milb_signings',
                        'label' => 'MiLB Signings',
                        'name' => 'milb_signings',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
                    ),
                    array(
                        'key' => 'field_fin_milb_remaining',
                        'label' => 'MiLB Remaining',
                        'name' => 'milb_remaining',
                        'type' => 'number',
                        'wrapper' => array('width' => '10'),
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
        'menu_order' => 30,
        'active' => true,
    ));

}

