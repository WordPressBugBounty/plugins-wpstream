<?php
/**
 * Widget manager
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
			add_action( 'widgets_init', array( $this, 'register_widgets' ) );

			add_filter( 'woocommerce_before_widget_product_list', array( $this, 'remove_class' ) );
		}

		/**
		 * Remove class from ul.
		 *
		 * @return string The modified ul tag.
		 */
		public function remove_class() {
			return "<ul class=''>";
		}

		/**
		 * Register custom widgets.
		 */
		public function register_widgets() {
			require_once 'class-wpstream-widget-base.php';

			$widgets = glob( __DIR__ . '/*-widget.php' );

			if ( ! empty( $widgets ) ) {
				foreach ( $widgets as $file ) {
					if ( file_exists( $file ) ) {
						$file_name  = pathinfo( $file, PATHINFO_FILENAME );
						$class_name = implode( '_', array_map( 'ucwords', array_slice( explode( '-', $file_name ), 1 ) ) );
						require_once $file;

						if ( class_exists( $class_name ) && is_a( $class_name, 'Wpstream_Widget_Base', true ) ) {
							// Unregister widgets.
							foreach ( $class_name::get_widgets_for_unregister() as $widget ) {
								unregister_widget( $widget );
							}
							register_widget( $class_name );
						}
					}
				}
			}
		}
	}

}
( new Wpstream_Widget_Manager() );
