<?php
/**
 * Elementor compatibility gate for the WpStream plugin.
 *
 * This file is required from the main wpstream.php bootstrap. It defines the
 * WpStream_Elementor_Base class, whose job is to verify that Elementor is
 * installed and meets the minimum Elementor/PHP versions before loading the
 * actual widget integration (wpstream-elementor-base.php). If any requirement
 * fails it schedules a dismissible admin notice instead. It also sets an
 * Elementor spacing option on activation.
 *
 * @package    Wpstream
 */

// Block direct access: ABSPATH is only defined when running inside WordPress.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
// On plugin activation, run the one-time Elementor default setup below.
register_activation_hook( __FILE__, 'wpestate_stream_elementor_activate_functionality_base' );

/**
 * Activation callback: apply WpStream's preferred Elementor defaults.
 *
 * @return void
 */
function wpestate_stream_elementor_activate_functionality_base(){
//update_option('elementor_container_width','1170');
    // Set the default gap between Elementor widgets to 10px.
    update_option('elementor_space_between_widgets','10');
}

/**
 * Requirement checker that guards the Elementor widget integration.
 */
final class WpStream_Elementor_Base {

	/**
	 * Plugin Version
	 *
	 * @since 1.2.0
	 * @var string The plugin version.
	 */
	// @var string This gate's own version string (not the WpStream plugin version).
	const VERSION = '1.0.0';

	/**
	 * Minimum Elementor Version
	 *
	 * @since 1.2.0
	 * @var string Minimum Elementor version required to run the plugin.
	 */
	// @var string Elementor must be at least this version for the integration to load.
	const MINIMUM_ELEMENTOR_VERSION = '2.0.0';

	/**
	 * Minimum PHP Version
	 *
	 * @since 1.2.0
	 * @var string Minimum PHP version required to run the plugin.
	 */
	// @var string PHP must be at least this version for the integration to load.
	const MINIMUM_PHP_VERSION = '7.0';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function __construct() {

		// Load translation
		// (i18n loading is currently disabled/commented out.)
//		add_action( 'init', array( $this, 'i18n' ) );

		// Init Plugin
		// Run the requirement checks once all plugins have loaded.
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Load Textdomain
	 *
	 * Load plugin localization files.
	 * Fired by `init` action hook.
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function i18n() {
		// Load the plugin's translations for the 'wpstream' text domain.
		load_plugin_textdomain( 'wpstream' );
	}

	/**
	 * Initialize the plugin
	 *
	 * Validates that Elementor is already loaded.
	 * Checks for basic plugin requirements, if one check fail don't continue,
	 * if all check have passed include the plugin class.
	 *
	 * Fired by `plugins_loaded` action hook.
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function init() {

		// Check if Elementor installed and activated
		// did_action() returns 0 if the 'elementor/loaded' hook never fired,
		// meaning Elementor is not active — queue a notice and stop.
		if ( ! did_action( 'elementor/loaded' ) ) {
			// Elementor missing: schedule the "requires Elementor" admin notice.
			add_action( 'admin_notices', array( $this, 'admin_notice_missing_main_plugin' ) );
			// Abort loading the integration.
			return;
		}

		// Check for required Elementor version
		// Bail if the installed Elementor is older than our minimum.
		if ( ! version_compare( ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=' ) ) {
			// Too old: schedule the "minimum Elementor version" admin notice.
			add_action( 'admin_notices', array( $this, 'admin_notice_minimum_elementor_version' ) );
			// Abort loading the integration.
			return;
		}

		// Check for required PHP version
		// Bail if the running PHP is older than our minimum.
		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			// Too old: schedule the "minimum PHP version" admin notice.
			add_action( 'admin_notices', array( $this, 'admin_notice_minimum_php_version' ) );
			// Abort loading the integration.
			return;
		}

		// Once we get here, We have passed all validation checks so we can safely include our plugin
		// All requirements satisfied: load the widget integration bootstrap.
		require_once( 'wpstream-elementor-base.php' );
	}

	/**
	 * Admin notice
	 *
	 * Warning when the site doesn't have Elementor installed or activated.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function admin_notice_missing_main_plugin() {
            // NOTE: This unconditional return short-circuits the method, so the
            // notice below is effectively disabled (dead code left in place).
            return;
		// Hide the "Plugin activated" admin message so it isn't shown alongside our warning.
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		// Build the translated "requires Elementor to be installed" message.
		$message = sprintf(
			/* translators: 1: Plugin name 2: Elementor */
			esc_html__( '"%1$s" requires "%2$s" to be installed and activated.', 'wpstream' ),
			// %1$s — this plugin's name.
			'<strong>' . esc_html__( 'WpStream Elementor', 'wpstream' ) . '</strong>',
			// %2$s — the required Elementor plugin's name.
			'<strong>' . esc_html__( 'Elementor', 'wpstream' ) . '</strong>'
		);

		// Output the message inside a dismissible warning notice.
		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
	}

	/**
	 * Admin notice
	 *
	 * Warning when the site doesn't have a minimum required Elementor version.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function admin_notice_minimum_elementor_version() {
            // NOTE: This unconditional return short-circuits the method, so the
            // notice below is effectively disabled (dead code left in place).
            return;
		// Hide the "Plugin activated" admin message so it isn't shown alongside our warning.
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		// Build the translated "requires a newer Elementor version" message.
		$message = sprintf(
			/* translators: 1: Plugin name 2: Elementor 3: Required Elementor version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'wpstream' ),
			// %1$s — this plugin's name.
			'<strong>' . esc_html__( 'WpStream Elementor ', 'wpstream' ) . '</strong>',
			// %2$s — the required Elementor plugin's name.
			'<strong>' . esc_html__( 'Elementor', 'wpstream' ) . '</strong>',
			// %3$s — the minimum Elementor version we require.
			self::MINIMUM_ELEMENTOR_VERSION
		);

		// Output the message inside a dismissible warning notice.
		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
	}

	/**
	 * Admin notice
	 *
	 * Warning when the site doesn't have a minimum required PHP version.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function admin_notice_minimum_php_version() {
            // NOTE: This unconditional return short-circuits the method, so the
            // notice below is effectively disabled (dead code left in place).
            return;
		// Hide the "Plugin activated" admin message so it isn't shown alongside our warning.
		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}

		// Build the translated "requires a newer PHP version" message.
		$message = sprintf(
			/* translators: 1: Plugin name 2: PHP 3: Required PHP version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'wpstream' ),
			// %1$s — this plugin's name.
			'<strong>' . esc_html__( 'WpStream Elementor', 'wpstream' ) . '</strong>',
			// %2$s — the "PHP" label.
			'<strong>' . esc_html__( 'PHP', 'wpstream' ) . '</strong>',
			// %3$s — the minimum PHP version we require.
			self::MINIMUM_PHP_VERSION
		);

		// Output the message inside a dismissible warning notice.
		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message );
	}
}

// Instantiate Wpstream elementor.
// Construct immediately so the plugins_loaded requirement check is registered.
new WpStream_Elementor_Base();