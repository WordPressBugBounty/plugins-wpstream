<?php
/**
 * Thin client for the WPStream "user/quota" API endpoint.
 *
 * Fetch-only: asks the API for the account's request-pack/quota data and
 * records the resolved username as an option. Caching the payload is the
 * job of WpStream_Quota_Manager, the transient's single owner — this class
 * never touches the cache.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes/api
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wpstream_User_Quota_Service {
	/** @var string Option key storing the username resolved from the access token. */
	const USERNAME_OPTION_KEY = 'wpstream_api_username_from_token';

	/** @var object Shared API connection providing the token and cURL helpers. */
	private $api_connection;

	/**
	 * Store the injected API connection dependency.
	 *
	 * @param object $api_connection Connection exposing authorized_request().
	 */
	public function __construct( $api_connection ) {
		$this->api_connection = $api_connection;
	}

	/**
	 * Fetch user quota/pack data from the WPStream API.
	 *
	 * @param string $context Caller context for API logging/analytics.
	 * @return array|false Decoded API response on success, or false when no token or request fails.
	 */
	public function request_pack_data_per_user( $context = '' ) {
		// Build the POST body: caller context and the running plugin version.
		// The access token is injected by the connection's authorized transport.
		$curl_post_fields = array(
			'context'        => $context,
			'plugin_version' => WPSTREAM_PLUGIN_VERSION,
		);

		// Issue the request to the user/quota endpoint; not-connected and
		// transport failures both surface as WP_Error and map to false.
		// Short timeout: this fetch runs on admin page loads, where a slow API
		// must cost seconds at most — failures are cached by the quota manager.
		$response = $this->api_connection->authorized_request( 'user/quota', $curl_post_fields, 5 );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		// On a successful response, remember the resolved username.
		if ( isset( $response['success'] ) && $response['success'] === true ) {
			// Persist the username when the response carries one.
			if ( isset( $response['username'] ) ) {
				update_option( self::USERNAME_OPTION_KEY, $response['username'] );
			}

			return $response;
		}

		// Missing token handled above; any non-success response falls through to false.
		return false;
	}
}
