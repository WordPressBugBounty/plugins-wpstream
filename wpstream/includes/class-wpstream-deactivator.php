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


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * Clean up regenerable caches on deactivation.
	 *
	 * Deactivation is not uninstallation: the stored WpStream account
	 * credentials and tokens (wpstream_api_* / wpstream_* options) must
	 * survive so a temporary deactivation (conflict testing, WordPress
	 * auto-deactivation on a fatal, staging clones) does not sever the
	 * account connection. Permanent data removal lives in uninstall.php.
	 * Only the transient-cached auth token — regenerable from the stored
	 * credentials on the next API call — is cleared here, plus a rewrite
	 * flush so plugin-registered permalinks are dropped. (issue #112)
	 *
	 * @since    3.0.1
	 */
	public static function deactivate() {
		// Drop the transient-cached auth token and its per-30s request cache;
		// both are regenerable from the stored credentials, which stay untouched.
		delete_transient( 'wpstream_token_api' );
		delete_transient( 'wpstream_token_request_30' );
		// Flush rewrite rules so plugin-registered permalinks are cleared.
		flush_rewrite_rules();
	}

}
