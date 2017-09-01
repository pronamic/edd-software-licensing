<?php


/**
 * Adds "Renewals" to the report views
 *
 * @access      public
 * @since       2.2
 * @return      void
*/

function edd_sl_add_renewals_view( $views ) {
	$views['renewals'] = __( 'License Renewals', 'edd_sl' );
	$views['upgrades'] = __( 'License Upgrades', 'edd_sl' );
	return $views;
}
add_filter( 'edd_report_views', 'edd_sl_add_renewals_view' );

/**
 * Adds "Renewals" to the report views
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
 * Show Commissions Graph
 *
 * @access      public
 * @since       2.2
 * @return      void
*/

function edd_sl_show_renewals_graph() {

	if ( ! current_user_can( 'view_shop_reports' ) ) {
		wp_die( __( 'You do not have permission to view this data', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
	}

	// retrieve the queried dates
	$dates = edd_get_report_dates();

	// Determine graph options
	switch( $dates['range'] ) :
		case 'today' :
			$time_format = '%d/%b';
			$tick_size   = 'hour';
			$day_by_day  = true;
			break;
		case 'last_year' :
			$time_format = '%b';
			$tick_size   = 'month';
			$day_by_day  = false;
			break;
		case 'this_year' :
			$time_format = '%b';
			$tick_size   = 'month';
			$day_by_day  = false;
			break;
		case 'last_quarter' :
			$time_format = '%b';
			$tick_size   = 'month';
			$day_by_day  = false;
			break;
		case 'this_quarter' :
			$time_format = '%b';
			$tick_size   = 'month';
			$day_by_day  = false;
			break;
		case 'other' :
			if( ( $dates['m_end'] - $dates['m_start'] ) >= 2 ) {
				$time_format = '%b';
				$tick_size   = 'month';
				$day_by_day  = false;
			} else {
				$time_format = '%d/%b';
				$tick_size   = 'day';
				$day_by_day  = true;
			}
			break;
		default:
			$time_format = '%d/%b'; 	// Show days by default
			$tick_size   = 'day'; 	// Default graph interval
			$day_by_day  = true;
			break;
	endswitch;

	$time_format = apply_filters( 'edd_graph_timeformat', $time_format );
	$tick_size   = apply_filters( 'edd_graph_ticksize', $tick_size );
	$totals      = (float) 0.00; // Total renewal earnings for time period shown

	ob_start(); ?>
	<div class="tablenav top">
		<div class="alignleft actions"><?php edd_report_views(); ?></div>
	</div>
	<script type="text/javascript">
	   jQuery( document ).ready( function($) {
			$.plot(
				$("#renewals_chart_div"),
				[{
					data: [
						<?php

						if( $dates['range'] == 'today' ) {

							// Hour by hour
							$hour  = 1;
							$month = date( 'n' );
							while ( $hour <= 23 ) :
								$renewals = edd_sl_get_renewals_by_date( $dates['day'], $month, $dates['year'], $hour );
								$totals   += $renewals['earnings'];
								$date     = mktime( $hour, 0, 0, $month, $dates['day'], $dates['year'] ); ?>
								[<?php echo $date * 1000; ?>, <?php echo $renewals['count']; ?>],
								<?php
								$hour++;
							endwhile;

						} elseif( $dates['range'] == 'this_week' || $dates['range'] == 'last_week' ) {

							//Day by day
							$day     = $dates['day'];
							$day_end = $dates['day_end'];
							$month   = $dates['m_start'];
							while ( $day <= $day_end ) :
								$renewals = edd_sl_get_renewals_by_date( $day, $month, $dates['year'] );
								$totals += $renewals['earnings'];
								$date = mktime( 0, 0, 0, $month, $day, $dates['year'] ); ?>
								[<?php echo $date * 1000; ?>, <?php echo $renewals['count']; ?>],
								<?php
								$day++;
							endwhile;

						} else {

							$y = $dates['year'];
							while ( $y <= $dates['year_end'] ) :
								$i = $dates['m_start'];
								while ( $i <= $dates['m_end'] ) :
									if ( $day_by_day ) :
										$num_of_days = $i == $dates['m_end'] ? $dates['day_end'] : cal_days_in_month( CAL_GREGORIAN, $i, $y );
										$d           = $i == $dates['m_start'] && $dates['day'] ? $dates['day'] : 1;
										while ( $d <= $num_of_days ) :
											$date     = mktime( 0, 0, 0, $i, $d, $y );
											$renewals = edd_sl_get_renewals_by_date( $d, $i, $y );
											$totals   += $renewals['earnings']; ?>
											[<?php echo $date * 1000; ?>, <?php echo $renewals['count']; ?>],
										<?php $d++; endwhile;
									else :
										$date     = mktime( 0, 0, 0, $i, 1, $y );
										$renewals = edd_sl_get_renewals_by_date( null, $i, $y );
										$totals   += $renewals['earnings'];
										?>
										[<?php echo $date * 1000; ?>, <?php echo $renewals['count']; ?>],
									<?php
									endif;
									$i++;
								endwhile;
								$y++;
							endwhile;
						}
						?>,
					],
					label: "<?php _e( 'Renewals', 'eddc' ); ?>",
					id: 'renewals'
				}],
			{
				series: {
				   lines: { show: true },
				   points: { show: true }
				},
				grid: {
					show: true,
					aboveData: false,
					color: '#ccc',
					backgroundColor: '#fff',
					borderWidth: 2,
					borderColor: '#ccc',
					clickable: false,
					hoverable: true
				},
				xaxis: {
					mode: "time",
					timeFormat: "<?php echo $time_format; ?>",
					minTickSize: [1, "<?php echo $tick_size; ?>"],
				},
				yaxis: {
					min: 0
				}
			});

			function edd_flot_tooltip(x, y, contents) {
				$('<div id="edd-flot-tooltip">' + contents + '</div>').css( {
					position: 'absolute',
					display: 'none',
					top: y + 5,
					left: x + 5,
					border: '1px solid #fdd',
					padding: '2px',
					'background-color': '#fee',
					opacity: 0.80
				}).appendTo("body").fadeIn(200);
			}

			var previousPoint = null;
			$("#renewals_chart_div").bind("plothover", function (event, pos, item) {
				$("#x").text(pos.x.toFixed(2));
				$("#y").text(pos.y.toFixed(2));
				if (item) {
					if (previousPoint != item.dataIndex) {
						previousPoint = item.dataIndex;
						$("#edd-flot-tooltip").remove();
						var x = item.datapoint[0].toFixed(2),
							y = item.datapoint[1].toFixed(2);
						if( item.series.id == 'commissions' ) {
							if( edd_vars.currency_pos == 'before' ) {
								edd_flot_tooltip( item.pageX, item.pageY, item.series.label + ' ' + edd_vars.currency_sign + y );
							} else {
								edd_flot_tooltip( item.pageX, item.pageY, item.series.label + ' ' + y + edd_vars.currency_sign );
							}
						} else {
							edd_flot_tooltip( item.pageX, item.pageY, item.series.label + ' ' + y.replace( '.00', '' ) );
						}
					}
				} else {
					$("#edd-flot-tooltip").remove();
					previousPoint = null;
				}
			});
	   });
	</script>
	<div class="metabox-holder" class="edd-sl-graph-controls">
		<div class="postbox">
			<h3><span><?php _e('License Renewals Over Time', 'edd_sl'); ?></span></h3>

			<div class="inside">
				<?php edd_reports_graph_controls(); ?>
				 <div id="renewals_chart_div"></div>
			</div>
		</div>
	</div>
	<p id="edd_graph_totals"><strong><?php _e( 'Total renewal earnings for period shown: ', 'edd_sl' ); echo edd_currency_filter( edd_format_amount( $totals ) ); ?></strong></p>
	<?php
	echo ob_get_clean();
}
add_action('edd_reports_view_renewals', 'edd_sl_show_renewals_graph');

/**
 * Show license upgrades
 *
 * @access      public
 * @since       3.3
 * @return      void
*/
function edd_sl_show_upgrades_graph() {

	if ( ! current_user_can( 'view_shop_reports' ) ) {
		wp_die( __( 'You do not have permission to view this data', 'edd_sl' ), __( 'Error', 'edd_sl' ), array( 'response' => 401 ) );
	}

	$dates      = edd_get_report_dates();
	$day_by_day = true;

	// Determine graph options
	switch( $dates['range'] ) :
		case 'last_year' :
		case 'this_year' :
		case 'last_quarter' :
		case 'this_quarter' :
			$day_by_day = false;
			break;
		case 'other' :
			if( ( $dates['m_end'] - $dates['m_start'] ) >= 2 ) {
				$day_by_day = false;
			}
			break;
	endswitch;

	$total = (float) 0.00; // Total upgrades value for time period shown

	ob_start(); ?>
	<div class="tablenav top">
		<div class="alignleft actions"><?php edd_report_views(); ?></div>
	</div>
	<?php
	$data = array();

	if( $dates['range'] == 'today' ) {
		// Hour by hour
		$hour  = 1;
		$month = date( 'n' );

		while ( $hour <= 23 ) :

			$upgrades    = edd_sl_get_upgrades_by_date( $dates['day'], $month, $dates['year'], $hour );
			$total      += $upgrades['earnings'];
			$date        = mktime( $hour, 0, 0, $month, $dates['day'], $dates['year'] );
			$data[]      = array( $date * 1000, (int) $upgrades['count'] );
			$hour++;

		endwhile;

	} elseif( $dates['range'] == 'this_week' || $dates['range'] == 'last_week' ) {

		//Day by day
		$day     = $dates['day'];
		$day_end = $dates['day_end'];
		$month   = $dates['m_start'];

		while ( $day <= $day_end ) :

			$upgrades    = edd_sl_get_upgrades_by_date( $day, $month, $dates['year'], null );
			$total      += $upgrades['earnings'];
			$date        = mktime( 0, 0, 0, $month, $day, $dates['year'] );
			$data[]      = array( $date * 1000, (int) $upgrades['count'] );
			$day++;

		endwhile;

	} else {

		$y = $dates['year'];
		while ( $y <= $dates['year_end'] ) :
			$i = $dates['m_start'];

			while ( $i <= $dates['m_end'] ) :

				if ( $day_by_day ) :

					$num_of_days = $i == $dates['m_end'] ? $dates['day_end'] : cal_days_in_month( CAL_GREGORIAN, $i, $y );
					$d           = $i == $dates['m_start'] && $dates['day'] ? $dates['day'] : 1;

					while ( $d <= $num_of_days ) :

						$date        = mktime( 0, 0, 0, $i, $d, $y );
						$upgrades    = edd_sl_get_upgrades_by_date( $d, $i, $y, null );
						$total      += $upgrades['earnings'];
						$data[]      = array( $date * 1000, (int) $upgrades['count'] );
						$d++;

					endwhile;

				else :

					$date        = mktime( 0, 0, 0, $i, 1, $y );
					$upgrades    = edd_sl_get_upgrades_by_date( null, $i, $y, null );
					$total      += $upgrades['earnings'];
					$data[]      = array( $date * 1000, (int) $upgrades['count'] );

				endif;

				$i++;

			endwhile;
			$y++;
		endwhile;
	}

	$data = array(
		__( 'License Upgrades', 'edd_sl' ) => $data
	);
	?>

	<div class="metabox-holder" style="padding-top: 0;">
		<div class="postbox">
			<h3><span><?php _e( 'License Upgrades', 'edd_sl' ); ?></span></h3>

			<div class="inside">
				<?php
					edd_reports_graph_controls();
					$graph = new EDD_Graph( $data );
					$graph->set( 'x_mode', 'time' );
					$graph->display();
				?>
				<p id="edd_graph_totals">
					<strong><?php _e( 'Total earnings from upgrades period shown: ', 'edd_sl' ); echo edd_currency_filter( edd_format_amount( $total ) ); ?></strong>
				</p>
			</div>
		</div>
	</div>
	<?php
	echo ob_get_clean();
}
add_action( 'edd_reports_view_upgrades', 'edd_sl_show_upgrades_graph' );

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
