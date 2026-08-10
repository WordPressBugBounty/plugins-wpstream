<?php
/**
 * Widget manager
 *
 * Bootstraps the theme's classic widgets. On `widgets_init` it auto-discovers
 * every `*-widget.php` file in this directory, derives the class name from the
 * file name, and registers each class that extends Wpstream_Widget_Base. A
 * singleton instance is created at the bottom of the file. Also tweaks the
 * WooCommerce product-list widget markup.
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class is used to register widgets
 */
if ( ! class_exists( 'Wpstream_Widget_Manager' ) ) {
	/**
	 * Class for managing custom widgets.
	 */
	class Wpstream_Widget_Manager {
		/**
		 * Constructor function.
		 */
		public function __construct() {
			// Register all theme widgets once WordPress initializes widgets.
			add_action( 'widgets_init', array( $this, 'register_widgets' ) );

			// Strip the default classes from WooCommerce's product-list <ul>.
			add_filter( 'woocommerce_before_widget_product_list', array( $this, 'remove_class' ) );
		}

		/**
		 * Remove class from ul.
		 *
		 * @return string The modified ul tag.
		 */
		public function remove_class() {
			// Replace the opening tag with a class-less <ul>.
			return "<ul class=''>";
		}

		/**
		 * Register custom widgets.
		 */
		public function register_widgets() {
			// Load the shared base class the widgets extend.
			require_once 'class-wpstream-widget-base.php';

			// Discover every widget file in this directory.
			$widgets = glob( __DIR__ . '/*-widget.php' );

			// Nothing to do when no widget files are present.
			if ( ! empty( $widgets ) ) {
				// Process each discovered widget file in turn.
				foreach ( $widgets as $file ) {
					// Guard against a stale glob entry.
					if ( file_exists( $file ) ) {
						// Base file name without extension, e.g. "class-wpstream-social-media-widget".
						$file_name  = pathinfo( $file, PATHINFO_FILENAME );
						// Derive the class name: drop the leading "class" segment, TitleCase the rest, join with "_".
						$class_name = implode( '_', array_map( 'ucwords', array_slice( explode( '-', $file_name ), 1 ) ) );
						// Load the widget class definition.
						require_once $file;

						// Only register real subclasses of the widget base.
						if ( class_exists( $class_name ) && is_a( $class_name, 'Wpstream_Widget_Base', true ) ) {
							// Unregister widgets.
							foreach ( $class_name::get_widgets_for_unregister() as $widget ) {
								unregister_widget( $widget );
							}
							// Register this widget with WordPress.
							register_widget( $class_name );
						}
					}
				}
			}
		}
	}

}
// Instantiate the manager so its hooks are wired on load.
( new Wpstream_Widget_Manager() );
