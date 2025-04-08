<?php
/**
 * Deprecated Admin Functions
 *
 * @package   EDD-Software-Licensing
 * @copyright Copyright (c) 2020, Sandhills Development, LLC
 * @license   GPL2+
 */

/**
 * Registers deprecated report hooks if not on EDD 3.0+.
 */
if ( ! function_exists( 'edd_add_order' ) ) {
	add_filter( 'edd_report_views', 'edd_sl_add_renewals_view' );
	add_action( 'edd_reports_view_renewals', 'edd_sl_show_renewals_graph' );
	add_action( 'edd_reports_view_upgrades', 'edd_sl_show_upgrades_graph' );
}

/**
 * Adds "Renewals" to the report views
 *
 * @deprecated 3.7
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

/**
 * Show Renewals Graph
 *
 * @deprecated 3.7
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
			$day_by_day  = true;
			break;
		case 'last_year' :
			$day_by_day  = false;
			break;
		case 'this_year' :
			$day_by_day  = false;
			break;
		case 'last_quarter' :
			$day_by_day  = false;
			break;
		case 'this_quarter' :
			$day_by_day  = false;
			break;
		case 'other' :
			if ( ( $dates['m_end'] - $dates['m_start'] ) >= 2 ) {
				$day_by_day  = false;
			} else {
				$day_by_day  = true;
			}
			break;
		default:
			$day_by_day  = true;
			break;
	endswitch;

	$totals      = (float) 0.00; // Total renewal earnings for time period shown

	ob_start(); ?>
	<div class="tablenav top">
		<div class="alignleft actions"><?php edd_report_views(); ?></div>
	</div>
	<?php
	$data = array();
	if ( 'today' === $dates['range'] ) {
		// Hour by hour
		$hour  = 1;
		$month = date( 'n' );
		while ( $hour <= 23 ) :
			$renewals = edd_sl_get_renewals_by_date( $dates['day'], $month, $dates['year'], $hour );
			$totals  += $renewals['earnings'];
			$date     = mktime( $hour, 0, 0, $month, $dates['day'], $dates['year'] );
			$data[]   = array( $date * 1000, (int) $renewals['count'] );
			$hour++;
		endwhile;
	} elseif ( 'this_week' === $dates['range'] || 'last_week' === $dates['range'] ) {

		//Day by day
		$day     = $dates['day'];
		$day_end = $dates['day_end'];
		$month   = $dates['m_start'];
		while ( $day <= $day_end ) :
			$renewals = edd_sl_get_renewals_by_date( $day, $month, $dates['year'] );
			$totals  += $renewals['earnings'];
			$date     = mktime( 0, 0, 0, $month, $day, $dates['year'] );
			$data[]   = array( $date * 1000, (int) $renewals['count'] );
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
						$totals  += $renewals['earnings'];
						$data[]   = array( $date * 1000, (int) $renewals['count'] );
						$d++;
					endwhile;
				else :
					$date     = mktime( 0, 0, 0, $i, 1, $y );
					$renewals = edd_sl_get_renewals_by_date( null, $i, $y );
					$totals  += $renewals['earnings'];
					$data[]   = array( $date * 1000, (int) $renewals['count'] );
				endif;
				$i++;
			endwhile;
			$y++;
		endwhile;
	}
	$data = array(
		__( 'License Renewals', 'edd_sl' ) => $data,
	);
	?>
	<div class="metabox-holder" class="edd-sl-graph-controls">
		<div class="postbox">
			<h3><span><?php esc_html_e( 'License Renewals Over Time', 'edd_sl' ); ?></span></h3>

			<div class="inside">
				<?php
				edd_reports_graph_controls();
				$graph = new EDD_Graph( $data );
				$graph->set( 'x_mode', 'time' );
				$graph->display();
				?>
				<p id="edd_graph_totals">
					<strong>
						<?php esc_html_e( 'Total renewal earnings for period shown: ', 'edd_sl' ); echo esc_html( edd_currency_filter( edd_format_amount( $totals ) ) ); ?>
					</strong>
				</p>
			</div>
		</div>
	</div>
	<?php
	echo ob_get_clean();
}

/**
 * Show license upgrades
 *
 * @deprecated 3.7
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
