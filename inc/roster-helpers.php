<?php
/**
 * Roster-related helper functions to be used by shortcodes.
 * This helps reduce code duplication.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Retrieves the Luxury Tax limit for a specific year from the options page.
 *
 * @param int|string $year
 * @return float
 */
function fod_get_luxury_tax_limit( $year ) {
    $thresholds = get_field( 'luxury_tax_thresholds', 'option' );
    if ( is_array( $thresholds ) ) {
        foreach ( $thresholds as $row ) {
            if ( (int) $row['year'] === (int) $year ) {
                return (float) $row['limit'];
            }
        }
    }
    return 0.0;
}

/**
 * Calculates total payroll (Active + Dead Cap) for a team in a specific year.
 *
 * @param string $league_id
 * @param string $team_id
 * @param int    $year
 * @return float
 */
function fod_get_total_team_payroll( $league_id, $team_id, $year ) {
    $active_total = 0.0;

    $args = [
        'post_type'      => 'playerdata',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => 'league_id', 'value' => $league_id ],
            [ 'key' => 'fantasy_team_id', 'value' => $team_id ],
        ],
    ];

    $query = new WP_Query( $args );
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $salary = get_post_meta( get_the_ID(), 'contract_' . $year, true );
            if ( is_numeric( $salary ) ) {
                $active_total += (float) $salary;
            }
        }
    }
    wp_reset_postdata();

    $dead_cap_data = fod_calculate_dead_cap( $league_id, $team_id, [ $year ] );
    $dead_cap_total = $dead_cap_data['totals'][ $year ] ?? 0.0;

    return $active_total + $dead_cap_total;
}

/**
 * Helper function to sort player IDs by custom baseball position order.
 */
function fod_sort_players_by_position( $player_ids ) {
    if ( empty( $player_ids ) ) return [];

    $order_map = [ 'C'=>1, '1B'=>2, '2B'=>3, 'SS'=>4, '3B'=>5, 'OF'=>6, 'SP'=>7, 'RP'=>8 ];

    $players = [];
    foreach ( $player_ids as $pid ) {
        $pos = strtoupper( get_post_meta( $pid, 'position', true ) );
        $players[] = [
            'id'    => $pid,
            'pos'   => $pos,
            'name'  => get_the_title( $pid ),
            'order' => $order_map[ $pos ] ?? 99
        ];
    }

    usort( $players, function( $a, $b ) {
        if ( $a['order'] === $b['order'] ) {
            return strcasecmp( $a['name'], $b['name'] );
        }
        return $a['order'] <=> $b['order'];
    });

    return array_column( $players, 'id' );
}

/**
 * Renders a single player row for a roster table.
 *
 * @param int   $player_id The post ID of the player.
 * @param array $config Configuration array.
 * @param array $salary_totals Passed by reference to update with player salaries.
 * @return string The HTML for the <tr>.
 */
function fod_render_player_roster_row( $player_id, $config, & $salary_totals ) {
    $is_readonly        = $config['is_readonly'] ?? false;
    $years_to_process   = $config['years_to_process'] ?? [];
    $selected_league_id = $config['selected_league_id'] ?? '';
    $selected_team_id   = $config['selected_team_id'] ?? '';

    $name         = get_the_title( $player_id );
    $is_on_40_man = get_post_meta( $player_id, 'status_40_man', true ) === 'X';
    $is_on_26_man = get_post_meta( $player_id, 'status_26_man', true ) == '1';
    $is_dfa_only  = get_post_meta( $player_id, 'dfa_only', true );
    $rule_5_year  = get_post_meta( $player_id, 'rule_5_eligibility_year', true );
    $position     = get_post_meta( $player_id, 'position', true );
    $mlb_team     = get_post_meta( $player_id, 'mlb_team', true );
    $status_il    = get_post_meta( $player_id, 'status_il', true );

    // Calculate options used
    $moves_log                = get_field( 'roster_moves_log', $player_id );
    $options_used_this_season = 0;
    $current_year_string      = date( 'Y' );
    if ( is_array( $moves_log ) ) {
        foreach ( $moves_log as $move ) {
            if ( isset( $move['move_year'], $move['move_type'] ) && $move['move_year'] === $current_year_string && $move['move_type'] === 'Optioned' ) {
                $options_used_this_season++;
            }
        }
    }
    $total_option_years_used = get_post_meta( $player_id, 'option_years_used', true );
    $option_years_display    = ! empty( $total_option_years_used ) ? (int) $total_option_years_used : '–';

    $tr = '<tr>';

    // Action column
    if ( ! $is_readonly ) {
        $tr                  .= '<td class="player-action-cell">';
        $common_data_attrs = 'data-playerid="' . esc_attr( $player_id ) . '" data-playername="' . esc_attr( $name ) . '" data-leagueid="' . esc_attr( $selected_league_id ) . '" data-teamid="' . esc_attr( $selected_team_id ) . '"';
        $on_il               = ! empty( $status_il );

        if ( $on_il ) {
            $tr .= '<button type="button" class="button activate-from-il-button" ' . $common_data_attrs . '>Activate from IL</button>';
        } else {
            $tr .= '<button type="button" class="button move-to-il-button" ' . $common_data_attrs . '>Move to IL</button>';
            if ( $is_on_40_man ) {
                if ( (int) $total_option_years_used < 3 ) {
                    $tr .= '<button type="button" class="button option-minors-button" ' . $common_data_attrs . '>Option to Minors</button>';
                }
                if ( ! $is_on_26_man ) {
                    $tr .= '<button type="button" class="button promote-26-button" ' . $common_data_attrs . '>Promote to 26-Man</button>';
                }
            } else {
                $tr .= '<button type="button" class="button promote-40-button" ' . $common_data_attrs . '>Move to 40-Man</button>';
            }
            $tr .= '<button type="button" class="button dfa-player-button" ' . $common_data_attrs . '>DFA</button>';
        }
        $tr .= '</td>';
    }

    $tr .= '<td>' . esc_html( $name ) . '</td>';
    $tr .= '<td>' . esc_html( $position ?: 'N/A' ) . '</td>';
    $tr .= '<td>' . esc_html( $mlb_team ?: 'N/A' ) . '</td>';
    $tr .= '<td>' . esc_html( $status_il ?: '–' ) . '</td>';
    $tr .= '<td>' . ( $is_on_40_man ? 'X' : '–' ) . '</td>';
    $tr .= '<td>' . ( $is_on_26_man ? 'X' : '–' ) . '</td>';
    $tr .= '<td>' . ( $is_dfa_only ? 'Yes' : '–' ) . '</td>';
    $tr .= '<td>' . esc_html( $options_used_this_season ) . ' / 5</td>';
    $tr .= '<td>' . esc_html( $option_years_display ) . '</td>';
    $tr .= '<td>' . esc_html( $rule_5_year ?: '–' ) . '</td>';

    // Salary columns
    foreach ( $years_to_process as $yr ) {
        $v    = get_post_meta( $player_id, 'contract_' . $yr, true );
        $cell = ( is_numeric( $v ) ) ? '$' . number_format( (float) $v, 0 ) : ( ( $v === '' || $v === null ) ? '–' : esc_html( $v ) );
        $tr   .= '<td>' . $cell . '</td>';
        if ( $v !== '' && $v !== null ) {
            $clean = str_replace( ',', '', (string) $v );
            if ( is_numeric( $clean ) ) {
                $salary_totals[ $yr ] += (float) $clean;
            }
        }
    }

    $tr .= '</tr>';
    return $tr;
}

/**
 * Calculates dead cap totals and groups them by year.
 *
 * @param string $league_id
 * @param string $team_id
 * @param array $years_to_process
 * @return array An array with 'totals' and 'grouped' dead cap data.
 */
function fod_calculate_dead_cap( $league_id, $team_id, $years_to_process ) {
    $dead_cap_totals  = array_fill_keys( $years_to_process, 0.0 );
    $grouped_dead_cap = [];

    $dc_args = [
        'post_type'      => 'playerdata',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => 'league_id', 'value' => $league_id ],
            [ 'key' => 'dead_cap_penalties', 'compare' => 'EXISTS' ],
        ],
    ];

    $dc_q = new WP_Query( $dc_args );

    if ( $dc_q->have_posts() ) {
        while ( $dc_q->have_posts() ) {
            $dc_q->the_post();
            $p_id      = get_the_ID();
            $penalties = get_field( 'dead_cap_penalties', $p_id );
            if ( $penalties ) {
                foreach ( $penalties as $row ) {
                    $yr  = isset( $row['penalty_year'] ) ? (int) $row['penalty_year'] : 0;
                    $amt = isset( $row['penalty_amount'] ) ? (float) $row['penalty_amount'] : 0.0;
                    $tid = isset( $row['dead_cap_team_id'] ) ? trim(strtoupper($row['dead_cap_team_id'])) : '';
                    $typ = $row['penalty_type'] ?? '';
                    
                    if ( $tid === strtoupper(trim($team_id)) && $yr && isset( $dead_cap_totals[ $yr ] ) ) {
                        $dead_cap_totals[ $yr ] += $amt;
                        $grouped_dead_cap[ $yr ][] = [
                            'player' => get_the_title( $p_id ),
                            'amount' => $amt,
                            'type'   => $typ ?: 'Dead Cap',
                        ];
                    }
                }
            }
        }
    }
    wp_reset_postdata();

    return [
        'totals'  => $dead_cap_totals,
        'grouped' => $grouped_dead_cap,
    ];
}

/**
 * Renders the salary summary table HTML.
 *
 * @param array $salary_totals
 * @param array $dead_cap_totals
 * @param array $years_to_process
 * @return string The HTML for the salary table.
 */
function fod_render_salary_summary_table( $salary_totals, $dead_cap_totals, $years_to_process ) {
    ob_start();
    ?>
    <h4>Team Salary Totals</h4>
    <table class="fantasy-table-base">
        <thead>
            <tr>
                <th>Year</th>
                <?php foreach ( $years_to_process as $y ) : ?>
                    <th><?php echo esc_html( $y ); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Active Payroll</td>
                <?php foreach ( $years_to_process as $y ) : ?>
                    <td>$<?php echo esc_html( number_format( $salary_totals[ $y ] ?? 0, 0 ) ); ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Dead Cap</td>
                <?php foreach ( $years_to_process as $y ) : ?>
                    <td>$<?php echo esc_html( number_format( $dead_cap_totals[ $y ] ?? 0, 0 ) ); ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Total Payroll</td>
                <?php foreach ( $years_to_process as $y ) : ?>
                    <?php $tot = ( $salary_totals[ $y ] ?? 0 ) + ( $dead_cap_totals[ $y ] ?? 0 ); ?>
                    <td>$<?php echo esc_html( number_format( $tot, 0 ) ); ?></td>
                <?php endforeach; ?>
            </tr>
            <tr class="luxury-tax-row" style="border-top: 2px solid #ccc; font-weight: bold;">
                <td>Luxury Tax Limit</td>
                <?php foreach ( $years_to_process as $y ) : ?>
                    <?php $limit = fod_get_luxury_tax_limit( $y ); ?>
                    <td>$<?php echo ( $limit > 0 ) ? esc_html( number_format( $limit, 0 ) ) : '–'; ?></td>
                <?php endforeach; ?>
            </tr>
            <tr>
                <td>Tax Space (+/-)</td>
                <?php foreach ( $years_to_process as $y ) : ?>
                    <?php 
                        $tot = ( $salary_totals[ $y ] ?? 0 ) + ( $dead_cap_totals[ $y ] ?? 0 );
                        $limit = fod_get_luxury_tax_limit( $y );
                        if ( $limit > 0 ) {
                            $diff = $limit - $tot;
                            $color = ( $diff < 0 ) ? 'red' : 'green';
                            echo '<td style="color:' . $color . ';">$' . number_format( $diff, 0 ) . '</td>';
                        } else {
                            echo '<td>–</td>';
                        }
                    ?>
                <?php endforeach; ?>
            </tr>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}
