<?php
/**
 * Thin client for the WPStream "channel/create" API endpoint.
 *
 * Wraps the shared API connection object and exposes channel creation,
 * resolving the access token and site domain before issuing the cURL call.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes/api
 */

class Wpstream_Channel_Service {
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
	 * Create a channel on the WPStream API.
	 *
	 * @param int         $channel_id Channel post ID.
	 * @param string|null $domain     Site domain. Defaults to the current site host.
	 * @return array|false Decoded API response, or false when no token is available.
	 */
	public function create_channel( $channel_id, $domain = null ) {
		// Retrieve the WPStream account access token via the connection.
		$access_token = $this->api_connection->wpstream_get_token();

		// Without a token the account is not linked, so there is nothing to call.
		if ( ! $access_token ) {
			return false;
		}

		// Default the domain to the current site's host when the caller omits it.
		if ( null === $domain ) {
			$parsed = parse_url( get_site_url() );
			$domain = isset( $parsed['host'] ) ? $parsed['host'] : '';
		}

		// Assemble the POST body the remote endpoint expects.
		$curl_post_fields = array(
			'access_token' => $access_token,
			'channel_id'   => intval( $channel_id ),
			'domain'       => $domain,
		);

		// Issue the request to the channel/create endpoint through the shared cURL helper.
		$curl_response = $this->api_connection->wpstream_baker_do_curl_base(
			'channel/create',
			$curl_post_fields,
			true,
			false,
			WPSTREAM_TIMEOUT_CONST
		);

		// Decode the JSON response into an associative array for the caller.
		return json_decode( $curl_response, JSON_OBJECT_AS_ARRAY );
	}
}
