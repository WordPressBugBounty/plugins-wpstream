<?php
/**
 * Elementor integration bootstrap for the WpStream widgets.
 *
 * This file is required only after WpStream_Elementor_Base::init() has confirmed
 * that Elementor is present and meets the minimum version. It defines the
 * singleton that loads each WpStream Elementor widget, registers those widget
 * types with Elementor, and adds the "WpStream Widgets" category to the editor
 * panel.
 *
 * @package    Wpstream
 */

namespace ElementorWpStream;

/**
 * Class Plugin
 *
 * Main Plugin class
 * @since 1.2.0
 */
class Plugin_Base {

	/**
	 * Instance
	 *
	 * @since 1.2.0
	 * @access private
	 * @static
	 *
	 * @var Plugin The single instance of the class.
	 */
	// @var Plugin_Base|null Holds the one and only instance; null until first requested.
	private static $_instance = null;

	/**
	 * Instance
	 *
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @since 1.2.0
	 * @access public
	 *
	 * @return Plugin An instance of the class.
	 */
	public static function instance() {
		// Lazily construct the singleton the first time it is asked for.
		if ( is_null( self::$_instance ) ) {
			// No instance yet, so create one (the constructor wires up the hooks).
			self::$_instance = new self();
		}
		// Return the shared instance to every caller.
		return self::$_instance;
	}

	/**
	 * widget_scripts
	 *
	 * Load required plugin core files.
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function widget_scripts() {
		// Hooked to elementor/frontend/after_register_scripts; no scripts are
		// registered here at present (intentional no-op placeholder).
	}

	/**
	 * Include Widgets files
	 *
	 * Load widgets files
	 *
	 * @since 1.2.0
	 * @access private
	 */
	private function include_widgets_files() {
            // Pull in the standard (Video.js) player widget definition.
            require_once( __DIR__ . '/widgets/player.php' );
            // Pull in the live chat widget definition.
            require_once( __DIR__ . '/widgets/wpstream_chat.php' );
            // Pull in the low-latency player widget definition.
            require_once( __DIR__ . '/widgets/player-lowlatency.php' );
            // Pull in the "start streaming" / go-live widget definition.
            require_once( __DIR__ . '/widgets/start_streaming.php' );
            // Pull in the widget that lists live channels.
            require_once( __DIR__ . '/widgets/media_list_channels.php' );
            // Pull in the widget that lists VOD entries.
            require_once( __DIR__ . '/widgets/media_list_vod.php' );
	}

	/**
	 * Register Widgets
	 *
	 * Register new Elementor widgets.
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function register_widgets() {
            // Its is now safe to include Widgets files
            // Load each widget's class file before instantiating it below.
            $this->include_widgets_files();

            // Register Widgets
            // Hand a fresh instance of each widget to Elementor's widgets manager
            // so they appear in the editor and can render on the front end.

            // Register the standard player widget.
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new Widgets\Wpstream_Player_Base() );
            // Register the live chat widget.
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new Widgets\Wpstream_Chat_Base() );
            // Register the low-latency player widget (note: class name spelled "LowLatecy").
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new Widgets\Wpstream_Player_LowLatecy_Base() );
            // Register the go-live / start streaming widget.
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new Widgets\Wpstream_Start_Streaming_Base() );
            // Register the live channel listing widget.
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new Widgets\Wpstream_Media_List_Channel() );
            // Register the VOD listing widget.
            \Elementor\Plugin::instance()->widgets_manager->register_widget_type( new Widgets\Wpstream_Media_List_Vod() );

        }

      
        
	/**
	 * Add the WpStream category to the Elementor editor panel.
	 *
	 * Hooked to elementor/elements/categories_registered so that all WpStream
	 * widgets are grouped together under a "WpStream Widgets" heading.
	 *
	 * @since 1.2.0
	 * @access public
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor's category registry.
	 */
        public function add_elementor_widget_categories($elements_manager){
            // Declare a new widget category keyed 'wpstream' with a label and icon.
            $elements_manager->add_category(
		'wpstream',
		[
			// Human-readable heading shown in the editor's widget panel.
			'title' => __( 'WpStream Widgets', 'hello-wpstream' ),
			// Font Awesome icon used for the category.
			'icon'  => 'fa fa-home',
		]
            );


        }
	/**
	 * Plugin class constructor.
	 *
	 * Registers the Elementor action hooks that load scripts, register the
	 * widgets, and add the widget category.
	 *
	 * @since 1.2.0
	 * @access public
	 */
	public function __construct() {

		// Register widget scripts
		// When Elementor registers front-end scripts, give us a chance to enqueue ours.
		add_action( 'elementor/frontend/after_register_scripts', [ $this, 'widget_scripts' ] );

		// Register widgets
		// When Elementor collects widget types, register the WpStream widgets.
		add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_widgets' ] );

		        // When Elementor builds its category list, add the WpStream category.
                add_action( 'elementor/elements/categories_registered',  [ $this, 'add_elementor_widget_categories' ]  );
	}
}

// Instantiate Plugin Class
// Kick off the singleton, which registers all Elementor hooks on construction.
Plugin_Base::instance();