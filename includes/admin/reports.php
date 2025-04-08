<?php
/**
 * Reports
 *
 * @package   EDD-Software-Licensing
 * @copyright Copyright (c) 2020, Sandhills Development, LLC
 * @license   GPL2+
 */

/**
 * Adds "Renewals" to the log views
 *
 * @access      public
 * @since       2.2
 * @return      void
 */

function edd_sl_add_log_views( $views ) {
	$views['renewal_notices'] = __( 'License Renewal Notices', 'edd_sl' );
	return $views;
}
add_filter( 'edd_log_views', 'edd_sl_add_log_views' );

/**
 * Registers Software Licensing reports with the EDD3.0+ registry.
 *
 * @param EDD\Reports\Data\Report_Registry $reports
 *
 * @since 3.7
 */
function edd_sl_register_reports( $reports ) {

	try {
		$options = EDD\Reports\get_dates_filter_options();
		$dates   = EDD\Reports\get_filter_value( 'dates' );
		$label   = $options[ $dates['range'] ];

		/**
		 * Renewals
		 */
		$reports->add_report( 'software_licensing_renewals', array(
			'label'     => __( 'License Renewals', 'edd_sl' ),
			'icon'      => 'chart-area',
			'priority'  => 50,
			'endpoints' => array(
				'tiles' => array(
					'software_licensing_renewals_number',
					'software_licensing_renewal_earnings'
				),
				'charts' => array(
					'software_licensing_renewals_chart'
				)
			)
		) );

		$reports->register_endpoint( 'software_licensing_renewals_number', array(
			'label' => __( 'Number of Renewals', 'edd_sl' ),
			'views' => array(
				'tile' => array(
					'data_callback' => 'edd_sl_license_renewals_number_report_callback',
					'display_args'  => array(
						'comparison_label' => $label
					)
				)
			)
		) );

		$reports->register_endpoint( 'software_licensing_renewal_earnings', array(
			'label' => __( 'Renewal Earnings', 'edd_sl' ),
			'views' => array(
				'tile' => array(
					'data_callback' => 'edd_sl_license_renewal_earnings_report_callback',
					'display_args'  => array(
						'comparison_label' => $label
					)
				)
			)
		) );

		$reports->register_endpoint( 'software_licensing_renewals_chart', array(
			'label' => __( 'License Renewals', 'edd_sl' ),
			'views' => array(
				'chart' => array(
					'data_callback' => 'edd_sl_license_renewals_chart_callback',
					'type'          => 'line',
					'options'       => array(
						'datasets' => array(
							'number' => array(
								'label'                => __( 'Number of Renewals', 'edd_sl' ),
								'borderColor'          => 'rgb(252,108,18)',
								'backgroundColor'      => 'rgba(252,108,18,0.2)',
								'fill'                 => true,
								'borderDash'           => array( 2, 6 ),
								'borderCapStyle'       => 'round',
								'borderJoinStyle'      => 'round',
								'pointRadius'          => 4,
								'pointHoverRadius'     => 6,
								'pointBackgroundColor' => 'rgb(255,255,255)',
							),
							'amount' => array(
								'label'                => __( 'Earnings', 'edd_sl' ),
								'borderColor'          => 'rgb(24,126,244)',
								'backgroundColor'      => 'rgba(24,126,244,0.05)',
								'fill'                 => true,
								'borderWidth'          => 2,
								'type'                 => 'currency',
								'pointRadius'          => 4,
								'pointHoverRadius'     => 6,
								'pointBackgroundColor' => 'rgb(255,255,255)',
							),
						)
					)
				)
			)
		) );

		/**
		 * Upgrades
		 */
		$reports->add_report( 'software_licensing_upgrades', array(
			'label'     => __( 'License Upgrades', 'edd_sl' ),
			'icon'      => 'chart-area',
			'priority'  => 51,
			'endpoints' => array(
				'tiles' => array(
					'software_licensing_upgrades_number',
					'software_licensing_upgrade_earnings'
				),
				'charts' => array(
					'software_licensing_upgrades'
				)
			)
		) );

		$reports->register_endpoint( 'software_licensing_upgrades_number', array(
			'label' => __( 'Number of Upgrades', 'edd_sl' ),
			'views' => array(
				'tile' => array(
					'data_callback' => 'edd_sl_license_upgrades_number_report_callback',
					'display_args'  => array(
						'comparison_label' => $label
					)
				)
			)
		) );

		$reports->register_endpoint( 'software_licensing_upgrade_earnings', array(
			'label' => __( 'Upgrade Earnings', 'edd_sl' ),
			'views' => array(
				'tile' => array(
					'data_callback' => 'edd_sl_license_upgrade_earnings_report_callback',
					'display_args'  => array(
						'comparison_label' => $label
					)
				)
			)
		) );

		$reports->register_endpoint( 'software_licensing_upgrades', array(
			'label' => __( 'License Upgrades', 'edd_sl' ),
			'views' => array(
				'chart' => array(
					'data_callback' => 'edd_sl_license_upgrades_chart_callback',
					'type'          => 'line',
					'options'       => array(
						'datasets' => array(
							'number' => array(
								'label'                => __( 'Number of Upgrades', 'edd_sl' ),
								'borderColor'          => 'rgb(252,108,18)',
								'backgroundColor'      => 'rgba(252,108,18,0.2)',
								'fill'                 => true,
								'borderDash'           => array( 2, 6 ),
								'borderCapStyle'       => 'round',
								'borderJoinStyle'      => 'round',
								'pointRadius'          => 4,
								'pointHoverRadius'     => 6,
								'pointBackgroundColor' => 'rgb(255,255,255)',
							),
							'amount' => array(
								'label'                => __( 'Earnings', 'edd_sl' ),
								'borderColor'          => 'rgb(24,126,244)',
								'backgroundColor'      => 'rgba(24,126,244,0.05)',
								'fill'                 => true,
								'borderWidth'          => 2,
								'type'                 => 'currency',
								'pointRadius'          => 4,
								'pointHoverRadius'     => 6,
								'pointBackgroundColor' => 'rgb(255,255,255)',
							),
						)
					)
				)
			)
		) );
	} catch ( EDD_Exception $e ) {
		edd_debug_log_exception( $e );
	}

}
add_action( 'edd_reports_init', 'edd_sl_register_reports' );

/**
 * Fetches the number of license renewals that were processed during this report period.
 *
 * @since 3.7
 * @return int
 */
function edd_sl_license_renewals_number_report_callback() {
	if ( ! function_exists( '\\EDD\\Reports\\get_dates_filter' ) ) {
		return 0;
	}

	global $wpdb;

	$dates    = EDD\Reports\get_dates_filter( 'objects' );
	$statuses = edd_get_complete_order_statuses();

	$number = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(edd_o.id) FROM {$wpdb->edd_orders} edd_o
			INNER JOIN {$wpdb->edd_ordermeta} edd_ometa ON( edd_o.id = edd_ometa.edd_order_id )
			WHERE edd_o.type = 'sale'
			AND edd_ometa.meta_key = '_edd_sl_is_renewal'
			AND edd_o.status IN( '" . implode( "', '", $wpdb->_escape( $statuses ) ) . "' )
			AND edd_o.date_created >= %s AND edd_o.date_created <= %s",
		$dates['start']->copy()->format( 'mysql' ),
		$dates['end']->copy()->format( 'mysql' )
	) );

	return absint( $number );
}

/**
 * Fetches the total earnings from license renewals that were processed during this report period.
 *
 * @since 3.7
 * @return string
 */
function edd_sl_license_renewal_earnings_report_callback() {
	if ( ! function_exists( '\\EDD\\Reports\\get_dates_filter' ) ) {
		return edd_currency_filter( edd_format_amount( 0 ) );
	}

	global $wpdb;

	$dates    = EDD\Reports\get_dates_filter( 'objects' );
	$column   = EDD\Reports\get_taxes_excluded_filter() ? 'total - tax' : 'total';
	$statuses = edd_get_complete_order_statuses();

	$earnings = $wpdb->get_var( $wpdb->prepare(
		"SELECT SUM({$column}) FROM {$wpdb->edd_orders} edd_o
			INNER JOIN {$wpdb->edd_ordermeta} edd_ometa ON( edd_o.id = edd_ometa.edd_order_id )
			WHERE edd_o.type = 'sale'
			AND edd_ometa.meta_key = '_edd_sl_is_renewal'
			AND edd_o.status IN( '" . implode( "', '", $wpdb->_escape( $statuses ) ) . "' )
			AND edd_o.date_created >= %s AND edd_o.date_created <= %s",
		$dates['start']->copy()->format( 'mysql' ),
		$dates['end']->copy()->format( 'mysql' )
	) );

	if ( is_null( $earnings ) ) {
		$earnings = 0;
	}

	return edd_currency_filter( edd_format_amount( $earnings ) );
}

/**
 * Fetches the data for the `software_licensing_renewals` report endpoint.
 *
 * @since 3.7
 * @return array
 */
function edd_sl_license_renewals_chart_callback() {
	$data = array(
		'number' => array(),
		'amount' => array()
	);

	if ( ! function_exists( '\\EDD\\Reports\\get_dates_filter_day_by_day' ) ) {
		return $data;
	}

	global $wpdb;

	$dates          = EDD\Reports\get_dates_filter( 'objects' );
	$day_by_day     = EDD\Reports\get_dates_filter_day_by_day();
	$hour_by_hour   = EDD\Reports\get_dates_filter_hour_by_hour();
	$column         = EDD\Reports\get_taxes_excluded_filter() ? 'total - tax' : 'total';
	$statuses       = edd_get_complete_order_statuses();
	$converted_date = edd_sl_get_column_conversion( 'edd_o.date_created' );
	$date_format    = '%%Y-%%m';
	if ( $hour_by_hour ) {
		$date_format = '%%Y-%%m-%%d %%H:00:00';
	} elseif ( $day_by_day ) {
		$date_format = '%%Y-%%m-%%d';
	}

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT COUNT(edd_o.id) AS number, SUM({$column}) AS amount, DATE_FORMAT({$converted_date},\"{$date_format}\") AS date
			FROM {$wpdb->edd_orders} edd_o
			INNER JOIN {$wpdb->edd_ordermeta} edd_ometa ON( edd_o.id = edd_ometa.edd_order_id )
			WHERE edd_o.type = 'sale'
			AND edd_ometa.meta_key = '_edd_sl_is_renewal'
			AND edd_o.status IN( '" . implode( "', '", $wpdb->_escape( $statuses ) ) . "' )
			AND edd_o.date_created >= %s AND edd_o.date_created <= %s
			GROUP BY DATE(date)
			ORDER BY DATE(date)",
			$dates['start']->copy()->format( 'mysql' ),
			$dates['end']->copy()->format( 'mysql' )
		)
	);

	$number = $amount = array();

	try {
		// Initialise all arrays with timestamps and set values to 0.
		while ( strtotime( $dates['start']->copy()->format( 'mysql' ) ) <= strtotime( $dates['end']->copy()->format( 'mysql' ) ) ) {
			$timestamp = strtotime( $dates['start']->copy()->format( 'mysql' ) );

			$number[ $timestamp ][0] = $timestamp;
			$number[ $timestamp ][1] = 0;

			$amount[ $timestamp ][0] = $timestamp;
			$amount[ $timestamp ][1] = 0.00;

			// Loop through each date there were renewals, which we queried from the database.
			foreach ( $results as $result ) {

				$timezone         = new DateTimeZone( 'UTC' );
				$date_of_db_value = new DateTime( $result->date, $timezone );
				$date_on_chart    = new DateTime( $dates['start'], $timezone );

				// Add any renewals that happened during this hour.
				if ( $hour_by_hour ) {
					// If the date of this db value matches the date on this line graph/chart, set the y axis value for the chart to the number in the DB result.
					if ( $date_of_db_value->format( 'Y-m-d H' ) === $date_on_chart->format( 'Y-m-d H' ) ) {
						$number[ $timestamp ][1] += $result->number;
						$amount[ $timestamp ][1] += abs( $result->amount );
					}
					// Add any renewals that happened during this day.
				} elseif ( $day_by_day ) {
					// If the date of this db value matches the date on this line graph/chart, set the y axis value for the chart to the number in the DB result.
					if ( $date_of_db_value->format( 'Y-m-d' ) === $date_on_chart->format( 'Y-m-d' ) ) {
						$number[ $timestamp ][1] += $result->number;
						$amount[ $timestamp ][1] += abs( $result->amount );
					}
					// Add any renewals that happened during this month.
				} else {
					// If the date of this db value matches the date on this line graph/chart, set the y axis value for the chart to the number in the DB result.
					if ( $date_of_db_value->format( 'Y-m' ) === $date_on_chart->format( 'Y-m' ) ) {
						$number[ $timestamp ][1] += $result->number;
						$amount[ $timestamp ][1] += abs( $result->amount );
					}
				}
			}

			// Move the chart along to the next hour/day/month to get ready for the next loop.
			if ( $hour_by_hour ) {
				$dates['start']->addHour( 1 );
			} elseif ( $day_by_day ) {
				$dates['start']->addDays( 1 );
			} else {
				$dates['start']->addMonth( 1 );
			}
		}
	} catch ( \Exception $e ) {

	}

	return array(
		'number' => array_values( $number ),
		'amount' => array_values( $amount ),
	);
}

/**
 * Get the time zone converted dates for the query.
 * This is a copy of EDD\Reports\get_column_conversion() from EDD 3.1.1.4.
 *
 * @since 3.8.8
 * @param string $column
 * @return string
 */
function edd_sl_get_column_conversion( $column = 'date_created' ) {
	if ( function_exists( 'EDD\Reports\get_column_conversion' ) ) {
		return EDD\Reports\get_column_conversion( $column );
	}

	$date       = EDD()->utils->date( 'now', edd_get_timezone_id(), false );
	$gmt_offset = $date->getOffset();
	if ( empty( $gmt_offset ) ) {
		return $column;
	}

	// Output the offset in the proper format.
	$hours   = abs( floor( $gmt_offset / HOUR_IN_SECONDS ) );
	$minutes = abs( floor( ( $gmt_offset / MINUTE_IN_SECONDS ) % MINUTE_IN_SECONDS ) );
	$math    = ( $gmt_offset >= 0 ) ? '+' : '-';

	$formatted_offset = ! empty( $minutes ) ? "{$hours}:{$minutes}" : $hours . ':00';

	return "CONVERT_TZ({$column}, '+00:00', '{$math}{$formatted_offset}')";
}

/**
 * Fetches the number of license upgrades that were processed during this report period.
 *
 * @since 3.7
 * @return int
 */
function edd_sl_license_upgrades_number_report_callback() {
	if ( ! function_exists( '\\EDD\\Reports\\get_dates_filter' ) ) {
		return 0;
	}

	global $wpdb;

	$dates = EDD\Reports\get_dates_filter( 'objects' );

	$number = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(edd_o.id) FROM {$wpdb->edd_orders} edd_o
			INNER JOIN {$wpdb->edd_ordermeta} edd_ometa ON( edd_o.id = edd_ometa.edd_order_id )
			WHERE edd_o.type = 'sale'
			AND edd_ometa.meta_key = '_edd_sl_upgraded_payment_id'
			AND edd_o.status IN( 'complete', 'revoked' )
			AND edd_o.date_created >= %s AND edd_o.date_created <= %s",
		$dates['start']->copy()->format( 'mysql' ),
		$dates['end']->copy()->format( 'mysql' )
	) );

	return absint( $number );
}

/**
 * Fetches the total earnings from license upgrades that were processed during this report period.
 *
 * @since 3.7
 * @return string
 */
function edd_sl_license_upgrade_earnings_report_callback() {
	if ( ! function_exists( '\\EDD\\Reports\\get_dates_filter' ) ) {
		return edd_currency_filter( edd_format_amount( 0 ) );
	}

	global $wpdb;

	$dates  = EDD\Reports\get_dates_filter( 'objects' );
	$column = EDD\Reports\get_taxes_excluded_filter() ? 'total - tax' : 'total';

	$earnings = $wpdb->get_var( $wpdb->prepare(
		"SELECT SUM({$column}) FROM {$wpdb->edd_orders} edd_o
			INNER JOIN {$wpdb->edd_ordermeta} edd_ometa ON( edd_o.id = edd_ometa.edd_order_id )
			WHERE edd_o.type = 'sale'
			AND edd_ometa.meta_key = '_edd_sl_upgraded_payment_id'
			AND edd_o.status IN( 'complete', 'revoked' )
			AND edd_o.date_created >= %s AND edd_o.date_created <= %s",
		$dates['start']->copy()->format( 'mysql' ),
		$dates['end']->copy()->format( 'mysql' )
	) );

	if ( is_null( $earnings ) ) {
		$earnings = 0;
	}

	return edd_currency_filter( edd_format_amount( $earnings ) );
}

/**
 * Fetches the data for the `software_licensing_upgrades` report endpoint.
 *
 * @since 3.7
 * @return array
 */
function edd_sl_license_upgrades_chart_callback() {
	$data = array(
		'number' => array(),
		'amount' => array()
	);

	if ( ! function_exists( '\\EDD\\Reports\\get_dates_filter_day_by_day' ) ) {
		return $data;
	}

	global $wpdb;

	$dates          = EDD\Reports\get_dates_filter( 'objects' );
	$day_by_day     = EDD\Reports\get_dates_filter_day_by_day();
	$hour_by_hour   = EDD\Reports\get_dates_filter_hour_by_hour();
	$column         = EDD\Reports\get_taxes_excluded_filter() ? 'total - tax' : 'total';
	$statuses       = edd_get_complete_order_statuses();
	$converted_date = edd_sl_get_column_conversion( 'edd_o.date_created' );
	$date_format    = '%%Y-%%m';
	if ( $hour_by_hour ) {
		$date_format = '%%Y-%%m-%%d %%H:00:00';
	} elseif ( $day_by_day ) {
		$date_format = '%%Y-%%m-%%d';
	}

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT COUNT(edd_o.id) AS number, SUM({$column}) AS amount, DATE_FORMAT({$converted_date},\"{$date_format}\") AS date
			FROM {$wpdb->edd_orders} edd_o
			INNER JOIN {$wpdb->edd_ordermeta} edd_ometa ON( edd_o.id = edd_ometa.edd_order_id )
			WHERE edd_o.type = 'sale'
			AND edd_ometa.meta_key = '_edd_sl_upgraded_payment_id'
			AND edd_o.status IN( '" . implode( "', '", $wpdb->_escape( $statuses ) ) . "' )
			AND edd_o.date_created >= %s AND edd_o.date_created <= %s
			GROUP BY DATE(date)
			ORDER BY DATE(date)",
			$dates['start']->copy()->format( 'mysql' ),
			$dates['end']->copy()->format( 'mysql' )
		)
	);

	$number = $amount = array();

	try {
		// Initialise all arrays with timestamps and set values to 0.
		while ( strtotime( $dates['start']->copy()->format( 'mysql' ) ) <= strtotime( $dates['end']->copy()->format( 'mysql' ) ) ) {
			$timestamp = strtotime( $dates['start']->copy()->format( 'mysql' ) );

			$number[ $timestamp ][0] = $timestamp;
			$number[ $timestamp ][1] = 0;

			$amount[ $timestamp ][0] = $timestamp;
			$amount[ $timestamp ][1] = 0.00;

			// Loop through each date there were renewals, which we queried from the database.
			foreach ( $results as $result ) {

				$timezone         = new DateTimeZone( 'UTC' );
				$date_of_db_value = new DateTime( $result->date, $timezone );
				$date_on_chart    = new DateTime( $dates['start'], $timezone );

				// Add any renewals that happened during this hour.
				if ( $hour_by_hour ) {
					// If the date of this db value matches the date on this line graph/chart, set the y axis value for the chart to the number in the DB result.
					if ( $date_of_db_value->format( 'Y-m-d H' ) === $date_on_chart->format( 'Y-m-d H' ) ) {
						$number[ $timestamp ][1] += $result->number;
						$amount[ $timestamp ][1] += abs( $result->amount );
					}
					// Add any renewals that happened during this day.
				} elseif ( $day_by_day ) {
					// If the date of this db value matches the date on this line graph/chart, set the y axis value for the chart to the number in the DB result.
					if ( $date_of_db_value->format( 'Y-m-d' ) === $date_on_chart->format( 'Y-m-d' ) ) {
						$number[ $timestamp ][1] += $result->number;
						$amount[ $timestamp ][1] += abs( $result->amount );
					}
					// Add any renewals that happened during this month.
				} else {
					// If the date of this db value matches the date on this line graph/chart, set the y axis value for the chart to the number in the DB result.
					if ( $date_of_db_value->format( 'Y-m' ) === $date_on_chart->format( 'Y-m' ) ) {
						$number[ $timestamp ][1] += $result->number;
						$amount[ $timestamp ][1] += abs( $result->amount );
					}
				}
			}

			// Move the chart along to the next hour/day/month to get ready for the next loop.
			if ( $hour_by_hour ) {
				$dates['start']->addHour( 1 );
			} elseif ( $day_by_day ) {
				$dates['start']->addDays( 1 );
			} else {
				$dates['start']->addMonth( 1 );
			}
		}
	} catch ( \Exception $e ) {

	}

	return array(
		'number' => array_values( $number ),
		'amount' => array_values( $amount ),
	);
}

function edd_sl_show_renewal_notices_table() {
	include EDD_SL_PLUGIN_DIR . 'includes/admin/classes/class-sl-renewal-notice-logs.php';

	$logs_table = new EDD_SL_Renewal_Notice_Logs();
	$logs_table->prepare_items();
	?>
	<div class="wrap">
		<?php do_action( 'edd_logs_renewal_notices_top' ); ?>
		<form id="edd-logs-filter" method="get" action="<?php echo admin_url( 'edit.php?post_type=download&page=edd-reports&tab=logs' ); ?>">
			<?php
			$logs_table->display();
			?>
			<input type="hidden" name="post_type" value="download" />
			<input type="hidden" name="page" value="edd-reports" />
			<input type="hidden" name="tab" value="logs" />
		</form>
		<?php do_action( 'edd_logs_renewal_notices_bottom' ); ?>
	</div>
	<?php
}
add_action('edd_logs_view_renewal_notices', 'edd_sl_show_renewal_notices_table');
