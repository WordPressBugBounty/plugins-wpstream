<?php

class Wpstream_User_Quota_Service {
	const TRANSIENT_KEY = 'wpstream_request_pack_data_per_user_transient';
	const USERNAME_OPTION_KEY = 'wpstream_api_username_from_token';

	private $api_connection;

	public function __construct( $api_connection ) {
		$this->api_connection = $api_connection;
	}

	public function request_pack_data_per_user( $context = '' ) {
		$url          = 'user/quota';
		$access_token = $this->api_connection->wpstream_get_token();

		// do not make the call if no token is available
		if ( ! $access_token ) {
			return false;
		}

		$curl_post_fields = array(
			'access_token'   => $access_token,
			'context'        => $context,
			'plugin_version' => WPSTREAM_PLUGIN_VERSION,
		);

		$curl_response = $this->api_connection->wpstream_baker_do_curl_base(
			$url,
			$curl_post_fields,
			true,
			false,
			WPSTREAM_TIMEOUT_CONST
		);

		$curl_response_decoded = json_decode( $curl_response, JSON_OBJECT_AS_ARRAY );

		if ( isset( $curl_response_decoded['success'] ) && $curl_response_decoded['success'] === true ) {
			set_transient( self::TRANSIENT_KEY, $curl_response_decoded, 60 );
			if ( isset( $curl_response_decoded['username'] ) ) {
				update_option( self::USERNAME_OPTION_KEY, $curl_response_decoded['username'] );
			}

			return $curl_response_decoded;
		}

		return false;
	}
}
