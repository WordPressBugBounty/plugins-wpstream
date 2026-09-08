<?php
/**
 * Thin client for the WPStream channel registration API endpoints.
 *
 * Wraps the shared API connection object and exposes channel creation and
 * deletion, resolving the access token and site domain before issuing the
 * cURL call.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes/api
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wpstream_Channel_Service {
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
	 * Create a channel on the WPStream API.
	 *
	 * @param int         $channel_id Channel post ID.
	 * @param string|null $domain     Site domain. Defaults to the current site host.
	 * @return array|false Decoded API response, or false when no token is available.
	 */
	public function create_channel( $channel_id, $domain = null ) {
		return $this->registration_request( 'channel/create', $channel_id, $domain );
	}

	/**
	 * Delete a channel on the WPStream API.
	 *
	 * The service keys channels by the same (channel_id, domain) pair creation
	 * sent, so the request carries that exact pair. It only removes the
	 * channel's registration at the service; stored recordings are separate
	 * objects and are not touched.
	 *
	 * @param int         $channel_id Channel post ID.
	 * @param string|null $domain     Site domain. Defaults to the current site host.
	 * @return array|false Decoded API response, or false when the call could not be made.
	 */
	public function delete_channel( $channel_id, $domain = null ) {
		return $this->registration_request( 'channel/delete', $channel_id, $domain );
	}

	/**
	 * Issue one channel registration call: the (channel_id, domain) pair that
	 * identifies a channel at the service, POSTed to the given endpoint.
	 *
	 * @param string      $endpoint   API path, e.g. 'channel/create'.
	 * @param int         $channel_id Channel post ID.
	 * @param string|null $domain     Site domain. Defaults to the current site host.
	 * @return array|false Decoded API response, or false when the call could not be made.
	 */
	private function registration_request( $endpoint, $channel_id, $domain ) {
		// Default the domain to the current site's host when the caller omits it.
		if ( null === $domain ) {
			$parsed = parse_url( get_site_url() );
			$domain = isset( $parsed['host'] ) ? $parsed['host'] : '';
		}

		// Assemble the POST body the remote endpoint expects; the access token
		// is injected by the connection's authorized transport.
		$curl_post_fields = array(
			'channel_id' => intval( $channel_id ),
			'domain'     => $domain,
		);

		$response = $this->api_connection->authorized_request( $endpoint, $curl_post_fields, WPSTREAM_TIMEOUT_CONST );

		// Callers expect an array or false — not-connected and transport failures map to false.
		return is_wp_error( $response ) ? false : $response;
	}
}
