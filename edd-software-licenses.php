<?php
/**
 * Plugin Name: Easy Digital Downloads - Software Licensing
 * Plugin URI: https://easydigitaldownloads.com/downloads/software-licensing/
 * Description: Adds a software licensing system to Easy Digital Downloads.
 * Version: 3.8.14
 * Requires PHP: 7.1
 * Requires at least: 5.4
 * Author: Easy Digital Downloads
 * Author URI: https://easydigitaldownloads.com/
 * Contributors: easydigitaldownloads, mordauk, cklosows
 * Text Domain: edd_sl
 * Domain Path: languages
 */

if ( ! defined( 'EDD_SL_PLUGIN_DIR' ) ) {
	define( 'EDD_SL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'EDD_SL_PLUGIN_URL' ) ) {
	define( 'EDD_SL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'EDD_SL_PLUGIN_FILE' ) ) {
	define( 'EDD_SL_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'EDD_SL_VERSION' ) ) {
	define( 'EDD_SL_VERSION', '3.8.14' );
}

require_once EDD_SL_PLUGIN_DIR . 'vendor/autoload.php';
require_once EDD_SL_PLUGIN_DIR . 'includes/classes/class-sl-requirements.php';

/**
 * Class EDD_SL_Requirements_Check
 *
 * @since 3.8
 */
final class EDD_SL_Requirements_Check {

	/**
	 * Plugin file
	 *
	 * @var string
	 * @since 3.8
	 */
	private $file;

	/**
	 * Plugin basename
	 *
	 * @var string
	 * @since 3.8
	 */
	private $plugin_base;

	/**
	 * Platform versions required to load Software Licensing.
	 *
	 * @var array[]
	 * @since 3.8
	 */
	private $current_requirements = array(
		'php'                    => array(
			'minimum' => '7.1',
			'name'    => 'PHP',
			'local'   => true,
		),
		'wp'                     => array(
			'minimum' => '5.4',
			'name'    => 'WordPress',
			'local'   => true,
		),
		'easy-digital-downloads' => array(
			'minimum' => '2.11',
			'name'    => 'Easy Digital Downloads',
			'local'   => true,
		),
	);

	/**
	 * @var EDD_SL_Requirements
	 */
	private $requirements;

	/**
	 * EDD_SL_Requirements_Check constructor.
	 *
	 * @param string $plugin_file
	 */
	public function __construct( $plugin_file ) {
		$this->file         = $plugin_file;
		$this->plugin_base  = plugin_basename( $this->file );
		$this->requirements = new EDD_SL_Requirements( $this->current_requirements );
	}

	/**
	 * Loads the plugin if requirements have been met, otherwise
	 * displays "plugin not fully active" UI and exists.
	 *
	 * @since 3.8
	 */
	public function maybe_load() {
		$this->requirements->met() ? $this->load() : $this->quit();
	}

	/**
	 * Loads Software Licensing
	 *
	 * @since 3.8
	 */
	private function load() {
		if ( ! class_exists( 'EDD_Software_Licensing' ) ) {
			require_once EDD_SL_PLUGIN_DIR . 'includes/classes/class-edd-software-licensing.php';
		}

		$this->maybe_install();

		// Get Software Licensing running.
		edd_software_licensing();
	}

	/**
	 * Installs Software Licensing if needed.
	 *
	 * @since 3.8
	 */
	private function maybe_install() {
		if ( ! function_exists( 'edd_sl_install' ) ) {
			require_once EDD_SL_PLUGIN_DIR . 'includes/install.php';
		}

		if ( get_option( 'edd_sl_run_install' ) ) {
			// Install Software Licensing.
			edd_sl_install();

			// Delete this option so we don't run the install again.
			delete_option( 'edd_sl_run_install' );
		}
	}

	/**
	 * Adds action hooks to inject our "plugin not fully loaded" UI / text.
	 *
	 * @since 3.8
	 */
	private function quit() {
		add_action( 'admin_head', array( $this, 'admin_head' ) );
		add_action( "after_plugin_row_{$this->plugin_base}", array( $this, 'plugin_row_notice' ) );
	}

	/**
	 * Adds CSS to the admin head for our "plugin not fully loaded" UI.
	 *
	 * @since 3.8
	 */
	public function admin_head() {
		$name = $this->unmet_requirements_name();
		?>
		<style id="<?php echo esc_attr( $name ); ?>">
			.plugins tr[data-plugin="<?php echo esc_html( $this->plugin_base ); ?>"] th,
			.plugins tr[data-plugin="<?php echo esc_html( $this->plugin_base ); ?>"] td,
			.plugins .<?php echo esc_html( $name ); ?>-row th,
			.plugins .<?php echo esc_html( $name ); ?>-row td {
				background: #fff5f5;
			}

			.plugins tr[data-plugin="<?php echo esc_html( $this->plugin_base ); ?>"] th {
				box-shadow: none;
			}

			.plugins .<?php echo esc_html( $name ); ?>-row th span {
				margin-left: 6px;
				color: #dc3232;
			}

			.plugins tr[data-plugin="<?php echo esc_html( $this->plugin_base ); ?>"] th,
			.plugins .<?php echo esc_html( $name ); ?>-row th.check-column {
				border-left: 4px solid #dc3232 !important;
			}

			.plugins .<?php echo esc_html( $name ); ?>-row .column-description p {
				margin: 0;
				padding: 0;
			}

			.plugins .<?php echo esc_html( $name ); ?>-row .column-description p:not(:last-of-type) {
				margin-bottom: 8px;
			}
		</style>
		<?php
	}

	/**
	 * Displays a notice on the plugin row about missing requirements.
	 *
	 * @since 3.8
	 */
	public function plugin_row_notice() {
		$colspan = function_exists( 'wp_is_auto_update_enabled_for_type' ) && wp_is_auto_update_enabled_for_type( 'plugin' ) ? 2 : 1;
		?>
		<tr class="active <?php echo esc_attr( $this->unmet_requirements_name() ); ?>-row">
			<th class="check-column">
				<span class="dashicons dashicons-warning"></span>
			</th>
			<td class="column-primary">
				<?php $this->unmet_requirements_text(); ?>
			</td>
			<td class="column-description" colspan="<?php echo esc_attr( $colspan ); ?>">
				<?php $this->unmet_requirements_description(); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Plugin specific text used in CSS to identify attribute IDs and classes.
	 *
	 * @since 3.8
	 * @return string
	 */
	private function unmet_requirements_name() {
		return 'edd-sl-requirements';
	}

	/**
	 * Outputs a message about the plugin not being fully active.
	 *
	 * @since 3.8
	 */
	private function unmet_requirements_text() {
		esc_html_e( 'This plugin is not fully active.', 'edd_sl' );
	}

	/**
	 * Displays error messages for all unmet requirements.
	 *
	 * @since 3.8
	 */
	private function unmet_requirements_description() {
		foreach ( $this->requirements->get_errors()->get_error_messages() as $message ) {
			echo wpautop( wp_kses_post( $message ) );
		}
	}

}

/**
 * Run the requirements check.
 *
 * This needs to be delayed until `plugins_loaded`, otherwise we won't be able to detect
 * EDD install/version.
 */
add_action( 'plugins_loaded', function() {
	$requirements_checker = new EDD_SL_Requirements_Check( EDD_SL_PLUGIN_FILE );
	$requirements_checker->maybe_load();
} );

/**
 * Adds an option flag if the installation has not yet run, to designate that we
 * should run it later. The reason we do this is because when installation happens
 * the requirements haven't yet been checked, and we only actually want to run the
 * full installation once we have.
 *
 * @see edd_sl_install()
 */
register_activation_hook( EDD_SL_PLUGIN_FILE, function() {
	$current_version = get_option( 'edd_sl_version' );
	if ( ! $current_version ) {
		update_option( 'edd_sl_run_install', time() );
	}

	update_option( 'edd_sl_version', EDD_SL_VERSION, false );
} );
