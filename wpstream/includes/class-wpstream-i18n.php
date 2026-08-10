<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       http://wpstream.net
 * @since      3.0.1
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      3.0.1
 * @package    Wpstream
 * @subpackage Wpstream/includes
 * @author     wpstream <office@wpstream.net>
 */
class Wpstream_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * Registers the 'wpstream' text domain so gettext calls resolve against the
	 * .mo files shipped in the plugin's languages/ directory.
	 *
	 * @since    3.0.1
	 */
	public function load_plugin_textdomain() {

		// Bind the 'wpstream' domain to the plugin's languages/ folder.
		load_plugin_textdomain(
			'wpstream',
			false,
			WPSTREAM_PLUGIN_PATH . 'languages/'
		);

	}



}
