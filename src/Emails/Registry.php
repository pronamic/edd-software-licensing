<?php

namespace EDD\SoftwareLicensing\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Class Registry
 *
 * @since 3.8.12
 * @package EDD\SoftwareLicensing\Emails\Templates
 */
class Registry {

	/**
	 * The notices.
	 *
	 * @since 3.8.12
	 * @var array
	 */
	private $notices;

	/**
	 * Registry constructor.
	 *
	 * @since 3.8.12
	 */
	public function __construct() {
		add_filter( 'edd_email_registered_templates', array( $this, 'register_email_templates' ) );
		add_filter( 'edd_email_registered_types', array( $this, 'register_email_types' ) );
		add_filter( 'edd_email_senders', array( $this, 'register_email_senders' ) );
		add_filter( 'edd_email_contexts', array( $this, 'register_email_contexts' ) );
		add_action( 'edd_email_add_new_actions', array( $this, 'get_add_action' ) );
		add_action( 'edd_email_editor_form', array( $this, 'add_email_period' ) );
		add_filter( 'edd_email_manager_save_email_id', array( $this, 'save_id' ), 10, 3 );
		add_action( 'edd_email_added', array( $this, 'update_email_meta' ), 10, 2 );
		add_action( 'edd_email_updated', array( $this, 'update_email_meta' ), 10, 2 );
		add_filter( 'edd_emails_logs_table_object', array( $this, 'update_logs_table_object' ), 10, 2 );
	}

	/**
	 * Registers the email templates.
	 *
	 * @since 3.8.12
	 * @param array $emails
	 * @return array
	 */
	public function register_email_templates( $emails ) {

		$notices = edd_software_licensing()->notices->get_notices();
		if ( ! empty( $notices ) ) {
			foreach ( $notices as $notice ) {
				$email_id            = edd_software_licensing()->notices->get_notice_id( $notice );
				$emails[ $email_id ] = Templates\Notices::class;
			}
		}
		$emails['license_new'] = Templates\Notices::class;

		return $emails;
	}

	/**
	 * Registers the email types.
	 *
	 * @since 3.8.12
	 * @param array $types
	 * @return array
	 */
	public function register_email_types( $types ) {
		$notices = edd_software_licensing()->notices->get_notices();
		if ( empty( $notices ) ) {
			return $types;
		}

		foreach ( $notices as $notice ) {
			$email_id           = edd_software_licensing()->notices->get_notice_id( $notice );
			$types[ $email_id ] = Types\Notices::class;
		}

		return $types;
	}

	/**
	 * Registers the email senders.
	 *
	 * @since 3.8.12
	 * @param array $senders
	 * @return array
	 */
	public function register_email_senders( $senders ) {
		$senders['software_licensing'] = __( 'Software Licensing', 'edd_sl' );

		return $senders;
	}

	/**
	 * Registers the email contexts.
	 *
	 * @since 3.8.12
	 * @param array $contexts
	 * @return array
	 */
	public function register_email_contexts( $contexts ) {
		$contexts['license'] = __( 'License', 'edd_sl' );

		return $contexts;
	}

	/**
	 * Gets the action to add a new email.
	 *
	 * @since 3.8.12
	 * @param array $actions The array of "add new" actions.
	 */
	public function get_add_action( $actions ) {
		$actions['license_new'] = __( 'Add License Renewal Notice', 'edd_sl' );

		return $actions;
	}

	/**
	 * Adds the email period field.
	 *
	 * @since 3.8.12
	 * @param \EDD\Emails\Templates\EmailTemplate $email
	 */
	public function add_email_period( $email ) {
		if ( 'software_licensing' !== $email->sender ) {
			return;
		}
		if ( empty( $email->meta['type'] ) || 'notice' !== $email->meta['type'] ) {
			return;
		}
		$meta_period = $email->get_metadata( 'period' );
		?>
		<div class="edd-form-group">
			<label for="edd-notice-period" class="edd-form-group__label"><?php esc_html_e( 'When to Send Email', 'edd_sl' ); ?></label>
			<div class="edd-form-group__control">
				<select name="period" id="edd-notice-period">
					<?php foreach ( edd_sl_get_renewal_notice_periods() as $period => $label ) : ?>
						<option value="<?php echo esc_attr( $period ); ?>"<?php selected( $period, $meta_period ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<input type="hidden" name="type" value="notice">
		</div>
		<?php
	}

	/**
	 * Saves the email ID. This only runs if the emails are registered with Berlin.
	 * This generates a unique ID for the new email.
	 *
	 * @since 3.8.12
	 * @param string                    $email_id
	 * @param \EDD\Emails\EmailTemplate $email_template
	 * @param array                     $data
	 * @return string|null
	 */
	public function save_id( $email_id, $email_template, $data ) {
		if ( 'software_licensing' !== $email_template->sender ) {
			return $email_id;
		}

		if ( empty( $data['type'] ) || 'notice' !== $data['type'] ) {
			return;
		}

		if ( 'license_new' === $email_id ) {
			return \EDD\Admin\Emails\Manager::get_new_id( 'license', $email_id );
		}

		return $email_id;
	}

	/**
	 * Updates the email meta when emails are registered with Berlin.
	 *
	 * @since 3.8.12
	 * @param int|string $id
	 * @param array      $data
	 */
	public function update_email_meta( $id, $data = array() ) {
		if ( empty( $data['sender'] ) || 'software_licensing' !== $data['sender'] ) {
			return;
		}
		if ( empty( $data['type'] ) || 'notice' !== $data['type'] ) {
			return;
		}
		edd_update_email_meta( $id, 'type', 'notice' );
		$period = array_key_exists( $data['period'], edd_sl_get_renewal_notice_periods() ) ? $data['period'] : '+1month';
		edd_update_email_meta( $id, 'period', $period );
	}

	/**
	 * Updates the email logs table object output.
	 *
	 * @since 3.8.12
	 * @param string              $object_id The object ID of the email.
	 * @param EDD\Emails\LogEmail $item      The log item.
	 * @return string
	 */
	public function update_logs_table_object( $object_id, $item ) {
		if ( 'license' !== $item->object_type ) {
			return $object_id;
		}

		return sprintf(
			'<a href="%s">%s</a>',
			add_query_arg(
				array(
					'page'       => 'edd-licenses',
					'view'       => 'overview',
					'license_id' => absint( $item->object_id ),
				),
				edd_get_admin_url()
			),
			/* translators: %s: License ID */
			sprintf( __( 'License %s', 'edd_sl' ), $item->object_id )
		);
	}
}
