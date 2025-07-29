<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin
 *
 * @package wpstream-theme
 */

namespace elementor;

use ElementorWpStreamTheme\Widgets;

print 'loaded pluginelementor ';


/**
 * Class Plugin
 *
 * Main Plugin class
 *
 * @since 1.2.0
 */
class Plugin {
	/**
	 * Instance
	 *
	 * @since 1.2.0
	 * @access private
	 * @static
	 *
	 * @var Plugin The single instance of the class.
	 */
	private static $instance = null;

	/**
	 * Instance
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @return Plugin An instance of the class.
	 * @since 1.2.0
	 * @access public
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Widget_scripts
	 * Load required plugin core files.
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function widget_scripts() {
	}

	/**
	 * Include Widgets files
	 * Load widgets files
	 *
	 * @since 1.2.0
	 * @access private
	 */
	private function include_widgets_files() {
		require_once __DIR__ . '/elementor/widgets/recent-items.php';
	}

	/**
	 * Register Widgets
	 * Register new Elementor widgets.
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function register_widgets() {
		// Its is now safe to include Widgets files.
		$this->include_widgets_files();

		// Register Widgets.
		\Elementor\Plugin::instance()->widgets_manager->register( new Widgets\WpStreamTheme_Recent_Items() );
	}

	/**
	 * Plugin class constructor.
	 *
	 * Register plugin action hooks and filters.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager The Elementor elements manager instance.
	 * @since 1.2.0
	 * @access public
	 */
	public function add_elementor_widget_categories( $elements_manager ) {
		$elements_manager->add_category(
			'hello-wpstream',
			array(
				'title' => __( 'Hello WpStream Widgets', 'hello-wpstream' ),
				'icon'  => 'fa fa-home',
			)
		);
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register widget scripts.
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'widget_scripts' ) );

		// Register widgets.
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		add_action( 'elementor/elements/categories_registered', array( $this, 'add_elementor_widget_categories' ) );
	}
}

// Instantiate Plugin Class.
Plugin::instance();
