<?php

/**
 * Fired during plugin deactivation
 *
 * @link       http://wpstream.net
 * @since      3.0.1
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      3.0.1
 * @package    Wpstream
 * @subpackage Wpstream/includes
 * @author     wpstream <office@wpstream.net>
 */
class Wpstream_Deactivator {

	/**
	 * Clean up the plugin's cached API credentials and tokens on deactivation.
	 *
	 * Removes the stored WpStream API key/secret/username/password options and
	 * the cached API auth tokens, then flushes rewrite rules so any custom
	 * permalink structure registered by the plugin is dropped.
	 *
	 * @since    3.0.1
	 */
	public static function deactivate() {
            // Remove the cached API token and its expiry timestamp.
            delete_option('wp_estate_token_expire');
            delete_option('wp_estate_curent_token');
            // Remove the stored WpStream cloud API credentials.
            delete_option('wpstream_api_key');
            delete_option('wpstream_api_secret_key');
            delete_option('wpstream_api_username');
            delete_option('wpstream_api_password');
			// Drop the transient-cached auth token and its per-30s request cache.
			delete_transient( 'wpstream_token_api');
			delete_transient('wpstream_token_request_30');
            // Flush rewrite rules so plugin-registered permalinks are cleared.
            flush_rewrite_rules();
	}

}
