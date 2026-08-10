<?php
/**
 * Thin client for the WPStream "user/quota" API endpoint.
 *
 * Fetches the account's request-pack/quota data, caches a successful response
 * in a short-lived transient, and records the resolved username as an option.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes/api
 */

class Wpstream_User_Quota_Service {
	/** @var string Transient key caching the last successful quota response. */
	const TRANSIENT_KEY = 'wpstream_request_pack_data_per_user_transient';
	/** @var string Option key storing the username resolved from the access token. */
	const USERNAME_OPTION_KEY = 'wpstream_api_username_from_token';

	/** @var object Shared API connection providing the token and cURL helpers. */
	private $api_connection;

	/**
	 * Store the injected API connection dependency.
	 *
	 * @param object $api_connection Connection exposing wpstream_get_token() and wpstream_baker_do_curl_base().
	 */
	public function __construct( $api_connection ) {
		$this->api_connection = $api_connection;
	}

	/**
	 * Fetch user quota/pack data from the WPStream API and cache successful responses.
	 *
	 * @param string $context Caller context for API logging/analytics.
	 * @return array|false Decoded API response on success, or false when no token or request fails.
	 */
	public function request_pack_data_per_user( $context = '' ) {
		// Target endpoint and the account access token used to authenticate the call.
		$url          = 'user/quota';
		$access_token = $this->api_connection->wpstream_get_token();

		// do not make the call if no token is available
		if ( ! $access_token ) {
			return false;
		}

		// Build the POST body: credentials, caller context, and the running plugin version.
		$curl_post_fields = array(
			'access_token'   => $access_token,
			'context'        => $context,
			'plugin_version' => WPSTREAM_PLUGIN_VERSION,
		);

		// Issue the request to the user/quota endpoint through the shared cURL helper.
		$curl_response = $this->api_connection->wpstream_baker_do_curl_base(
			$url,
			$curl_post_fields,
			true,
			false,
			WPSTREAM_TIMEOUT_CONST
		);

		// Decode the JSON response into an associative array.
		$curl_response_decoded = json_decode( $curl_response, JSON_OBJECT_AS_ARRAY );

		// On a successful response, cache it briefly and remember the resolved username.
		if ( isset( $curl_response_decoded['success'] ) && $curl_response_decoded['success'] === true ) {
			// Cache the payload for 60 seconds to avoid hammering the API.
			set_transient( self::TRANSIENT_KEY, $curl_response_decoded, 60 );
			// Persist the username when the response carries one.
			if ( isset( $curl_response_decoded['username'] ) ) {
				update_option( self::USERNAME_OPTION_KEY, $curl_response_decoded['username'] );
			}

			return $curl_response_decoded;
		}

		// Missing token handled above; any non-success response falls through to false.
		return false;
	}
}
