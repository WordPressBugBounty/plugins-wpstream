<?php

/**
 * Fired during plugin activation
 *
 * @link       http://wpstream.net
 * @since      3.0.1
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      3.0.1
 * @package    Wpstream
 * @subpackage Wpstream/includes
 * @author     wpstream <office@wpstream.net>
 */
class Wpstream_Activator {

	/**
	 * Run the one-time activation routine.
	 *
	 * Registers the plugin's custom post types and flushes the rewrite rules so
	 * their permalinks work immediately after the plugin is switched on.
	 *
	 * @since    3.0.1
	 */
	public static function activate() {

            // Load the class that declares WpStream's custom post types.
            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wpstream_product.php';
            // Instantiate the post-type registrar.
            $plugin_post_types = new Wpstream_Product();
            // Register the custom post types (channels, VODs, etc.).
            $plugin_post_types->create_custom_post_type();

            // Rebuild rewrite rules now that new post types exist, so their URLs resolve.
            flush_rewrite_rules();

//

	}

}
