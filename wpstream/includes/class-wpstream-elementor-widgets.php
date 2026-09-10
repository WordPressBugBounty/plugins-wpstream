<?php
/**
 * Elementor integration for the WpStream plugin.
 *
 * One class owns the whole Elementor layer: it verifies on plugins_loaded that
 * Elementor is active and meets the minimum Elementor/PHP versions, and then
 * loads the plugin's widget classes from widgets/ and registers them with
 * Elementor under the "WpStream Widgets" category.
 *
 * Elementor is optional. A site without it stays silent: no widgets, no
 * notice. Only a site that does run Elementor is warned when its Elementor or
 * PHP version is too old, because there the warning is actionable.
 *
 * The widget files are required here rather than autoloaded because they
 * extend Elementor\Widget_Base, which only exists once Elementor has loaded.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */

// Block direct access: ABSPATH is only defined when running inside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Requirement gate and widget registrar for Elementor.
 */
final class Wpstream_Elementor_Widgets {

	/**
	 * Elementor must be at least this version. 3.5.0 introduced the
	 * `elementor/widgets/register` hook and `Widgets_Manager::register()`
	 * that this class relies on.
	 *
	 * @var string
	 */
	const MINIMUM_ELEMENTOR_VERSION = '3.5.0';

	/**
	 * PHP must be at least this version (matches the plugin header).
	 *
	 * @var string
	 */
	const MINIMUM_PHP_VERSION = '7.1';

	/**
	 * Defer the requirement checks until every plugin has had a chance to load.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Check the requirements; schedule a notice on failure, wire the widgets on success.
	 *
	 * @return void
	 */
	public function init() {
		// Elementor not active: 'elementor/loaded' never fired. Elementor is an
		// optional integration, so a site without it gets no widgets and no notice.
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		// Elementor too old for the registration API used below.
		if ( ! version_compare( ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_minimum_elementor_version' ) );
			return;
		}

		// PHP too old.
		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_minimum_php_version' ) );
			return;
		}

		// Requirements met: add the panel category and register the widgets when Elementor asks for them.
		add_action( 'elementor/elements/categories_registered', array( $this, 'add_widget_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Add the "WpStream Widgets" category to the Elementor editor panel.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor's category registry.
	 * @return void
	 */
	public function add_widget_category( $elements_manager ) {
		$elements_manager->add_category(
			'wpstream',
			array(
				'title' => __( 'WpStream Widgets', 'wpstream' ),
				'icon'  => 'fa fa-home',
			)
		);
	}

	/**
	 * Load the widget classes and hand one instance of each to Elementor.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor's widget registry.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ) {
		$widgets_dir = dirname( __DIR__ ) . '/widgets/';

		// Widget files extend Elementor\Widget_Base, so they are only safe to include here.
		require_once $widgets_dir . 'player.php';
		require_once $widgets_dir . 'start_streaming.php';
		require_once $widgets_dir . 'media_list.php';

		$widgets_manager->register( new \ElementorWpStream\Widgets\Wpstream_Player_Base() );
		$widgets_manager->register( new \ElementorWpStream\Widgets\Wpstream_Start_Streaming_Base() );
		$widgets_manager->register( new \ElementorWpStream\Widgets\Wpstream_Media_List_Channel() );
		$widgets_manager->register( new \ElementorWpStream\Widgets\Wpstream_Media_List_Vod() );
	}

	/**
	 * Admin notice: the installed Elementor is older than the minimum.
	 *
	 * @return void
	 */
	public function admin_notice_minimum_elementor_version() {
		$message = sprintf(
			/* translators: 1: Plugin name 2: Elementor 3: Required Elementor version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'wpstream' ),
			'<strong>' . esc_html__( 'WpStream Elementor', 'wpstream' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor', 'wpstream' ) . '</strong>',
			esc_html( self::MINIMUM_ELEMENTOR_VERSION )
		);
		$this->print_notice( $message );
	}

	/**
	 * Admin notice: the running PHP is older than the minimum.
	 *
	 * @return void
	 */
	public function admin_notice_minimum_php_version() {
		$message = sprintf(
			/* translators: 1: Plugin name 2: PHP 3: Required PHP version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'wpstream' ),
			'<strong>' . esc_html__( 'WpStream Elementor', 'wpstream' ) . '</strong>',
			'<strong>' . esc_html__( 'PHP', 'wpstream' ) . '</strong>',
			esc_html( self::MINIMUM_PHP_VERSION )
		);
		$this->print_notice( $message );
	}

	/**
	 * Print an already-escaped message as a dismissible warning notice.
	 *
	 * @param string $message Escaped HTML message.
	 * @return void
	 */
	private function print_notice( $message ) {
		// Hide WordPress's "Plugin activated" message so it is not shown beside the warning.
		unset( $_GET['activate'] );
		printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built.
	}
}
