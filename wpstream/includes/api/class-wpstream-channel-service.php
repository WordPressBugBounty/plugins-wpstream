<?php

class Wpstream_Channel_Service {
	private $api_connection;

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
		$access_token = $this->api_connection->wpstream_get_token();

		if ( ! $access_token ) {
			return false;
		}

		if ( null === $domain ) {
			$parsed = parse_url( get_site_url() );
			$domain = isset( $parsed['host'] ) ? $parsed['host'] : '';
		}

		$curl_post_fields = array(
			'access_token' => $access_token,
			'channel_id'   => intval( $channel_id ),
			'domain'       => $domain,
		);

		$curl_response = $this->api_connection->wpstream_baker_do_curl_base(
			'channel/create',
			$curl_post_fields,
			true,
			false,
			WPSTREAM_TIMEOUT_CONST
		);

		return json_decode( $curl_response, JSON_OBJECT_AS_ARRAY );
	}
}
