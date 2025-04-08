<?php
/**
 * Edit Renewal Notice
 *
 * @package     EDD Software Licensing
 * @copyright   Copyright (c) 2014, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $_GET['notice'] ) || ! is_numeric( $_GET['notice'] ) ) {
	//wp_die( __( 'Something went wrong.', 'edd_sl' ), __( 'Error', 'edd_sl' ) );
}
edd_email_tags_inserter_media_button();
edd_email_tags_inserter_thickbox_content( 'license' );
edd_email_tags_inserter_enqueue_scripts( 'license' );
?>
<div class="wrap wrap-general">
<?php
	$notice_id = absint( $_GET['notice'] );
	$notice    = edd_sl_get_renewal_notice( $notice_id );
?>
	<h1><?php esc_html_e( 'Edit Renewal Notice', 'edd_sl' ); ?></h1>
	<?php
	if ( ! empty( $_GET['edd-message'] ) && ! empty( $_GET['edd-result'] ) ) {
		?>
		<div class="notice notice-<?php echo esc_attr( $_GET['edd-result'] ); ?>">
			<p><?php echo esc_html( urldecode( $_GET['edd-message'] ) ); ?></p>
		</div>
		<?php
	}
	$settings_page_url  = add_query_arg(
		array(
			'post_type' => 'download',
			'page'      => 'edd-settings',
			'tab'       => 'emails',
			'section'   => 'software-licensing',
		),
		admin_url( 'edit.php' )
	);
	$settings_page_text = __( 'Return to Settings', 'edd_sl' );
	$preview_url        = add_query_arg(
		array(
			'edd-action' => 'edd_sl_preview_notice',
			'notice-id'  => urlencode( $notice_id ),
		),
		home_url()
	);
	?>
	<a href="<?php echo esc_url( $settings_page_url ); ?>"><?php echo esc_html( $settings_page_text ); ?></a> | <a href="<?php echo esc_url( wp_nonce_url( $preview_url ) ); ?>" class="edd-sl-preview-renewal-notice" data-key="<?php echo esc_attr( $notice_id ); ?>" target="_blank"><?php esc_html_e( 'Preview', 'edd_sl' ); ?></a>
	<form id="edd-edit-renewal-notice" action="" method="post">
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-notice-subject"><?php esc_html_e( 'Email Subject', 'edd_sl' ); ?></label>
					</th>
					<td>
						<input name="subject" id="edd-notice-subject" class="edd-notice-subject regular-text" type="text" value="<?php echo esc_attr( stripslashes( $notice['subject'] ) ); ?>" />
						<p class="description"><?php esc_html_e( 'The subject line of the renewal notice email', 'edd_sl' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-notice-period"><?php esc_html_e( 'Email Period', 'edd_sl' ); ?></label>
					</th>
					<td>
						<select name="period" id="edd-notice-period">
							<?php foreach ( edd_sl_get_renewal_notice_periods() as $period => $label ) : ?>
								<option value="<?php echo esc_attr( $period ); ?>"<?php selected( $period, $notice['send_period'] ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'When should this email be sent?', 'edd_sl' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top">
						<label for="edd-notice-message"><?php esc_html_e( 'Email Message', 'edd_sl' ); ?></label>
					</th>
					<td>
						<?php wp_editor( wp_kses_post( wptexturize( $notice['message'] ) ), 'message', array( 'textarea_name' => 'message' ) ); ?>
						<p class="description"><?php esc_html_e( 'The email message to be sent with the renewal notice. The following template tags can be used in the message:', 'edd_sl' ); ?></p>
						<?php do_action( 'edd_sl_after_renewal_notice_form' ); ?>
					</td>
				</tr>

			</tbody>
		</table>
		<p class="submit">
			<input type="hidden" name="edd-action" value="edit_renewal_notice"/>
			<input type="hidden" name="notice-id" value="<?php echo esc_attr( $notice_id ); ?>"/>
			<input type="hidden" name="edd-renewal-notice-nonce" value="<?php echo esc_attr( wp_create_nonce( 'edd_renewal_nonce' ) ); ?>"/>
			<input type="submit" value="<?php esc_attr_e( 'Update Renewal Notice', 'edd_sl' ); ?>" class="button-primary"/>
		</p>
	</form>
</div>
