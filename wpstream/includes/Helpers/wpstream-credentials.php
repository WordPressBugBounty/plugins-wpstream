<?php
/**
 * Accessors for the WpStream account credentials used to authenticate
 * against the backend API.
 *
 * Credentials can be supplied two ways, in priority order:
 *  1. wp-config.php constants WPSTREAM_API_USERNAME / WPSTREAM_API_PASSWORD,
 *     which keep the secret out of the database entirely, or
 *  2. the wpstream_api_username / wpstream_api_password options saved from
 *     the Credentials admin screen or the onboarding wizard.
 *
 * Every read of the account credentials must go through these helpers so the
 * constant override is honoured everywhere.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes/Helpers
 */

// Block direct file access outside of WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether both credentials are supplied as wp-config.php constants.
 *
 * Used by the admin screens to disable the credential inputs, since values
 * saved there would be ignored while the constants are defined.
 *
 * @return bool True when WPSTREAM_API_USERNAME and WPSTREAM_API_PASSWORD are both defined and non-empty.
 */
function wpstream_api_credentials_from_constants() {
	return defined( 'WPSTREAM_API_USERNAME' ) && WPSTREAM_API_USERNAME !== ''
		&& defined( 'WPSTREAM_API_PASSWORD' ) && WPSTREAM_API_PASSWORD !== '';
}

/**
 * The WpStream account username/email: constant first, option as fallback.
 *
 * @return string Username, or '' when not configured.
 */
function wpstream_get_api_username() {
	if ( wpstream_api_credentials_from_constants() ) {
		return WPSTREAM_API_USERNAME;
	}
	return get_option( 'wpstream_api_username', '' );
}

/**
 * The WpStream account password: constant first, option as fallback.
 *
 * Never echo this value into markup or send it to a client — it is only for
 * server-side authentication against the backend API.
 *
 * @return string Password, or '' when not configured.
 */
function wpstream_get_api_password() {
	if ( wpstream_api_credentials_from_constants() ) {
		return WPSTREAM_API_PASSWORD;
	}
	return get_option( 'wpstream_api_password', '' );
}
