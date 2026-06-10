<?php
/**
 * VOD playback session: validatePlaybackSessionUrl, REST verification, AJAX issuance.
 *
 * @package wpstream
 */

/**
 * Handles wpstream/v1/playback-session-verify and wpstream_issue_playback_session.
 */
class Wpstream_Playback_Session {

	/**
	 * Wpstream_Playback_Session constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'wpstream_register_playback_session_rest_routes' ) );
		add_action( 'wp_ajax_wpstream_issue_playback_session', array( $this, 'wpstream_ajax_issue_playback_session' ) );
		// Logged-out viewers may still be entitled (e.g. free wpstream_product_vod); nonce + entitlement gate the call.
		add_action( 'wp_ajax_nopriv_wpstream_issue_playback_session', array( $this, 'wpstream_ajax_issue_playback_session' ) );
	}

	/**
	 * Base URL embedded as validatePlaybackSessionUrl (presence server verifies via GET).
	 *
	 * @return string
	 */
	public function wpstream_get_default_validate_playback_session_url() {
		return esc_url_raw( rest_url( 'wpstream/v1/playback-session-verify' ) );
	}

	/**
	 * @param string $token Opaque playback session token.
	 * @return string
	 */
	private function wpstream_playback_session_transient_name( $token ) {
		return 'wpstream_pbs_' . hash( 'sha256', wp_salt( 'auth' ) . $token );
	}

	/**
	 * @return int TTL in seconds for issued playback sessions.
	 */
	public function wpstream_get_playback_session_ttl() {
		return (int) apply_filters( 'wpstream_playback_session_ttl_seconds', 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Mirrors entitlement used when showing the VOD iframe (woo purchase / bundle / free channel types).
	 *
	 * @param int $product_id Product or wpstream_* post ID.
	 * @return bool
	 */
	private function wpstream_user_entitled_for_vod_product( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return false;
		}

		$post_type = get_post_type( $product_id );
		if ( in_array( $post_type, array( 'wpstream_product', 'wpstream_product_vod' ), true ) ) {
			return true;
		}

		if ( 'product' !== $post_type ) {
			return false;
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );
		if ( ! in_array( 'woocommerce/woocommerce.php', $plugins, true ) ) {
			return false;
		}

		$user            = wp_get_current_user();
		$possible_bundle = (int) get_post_meta( $product_id, 'wpstream_part_of_bundle', true );

		return (bool) ( wc_customer_bought_product( $user->user_email, $user->ID, $product_id )
			|| ( 0 !== $possible_bundle && wc_customer_bought_product( $user->user_email, $user->ID, $possible_bundle ) ) );
	}

	public function wpstream_register_playback_session_rest_routes() {
		register_rest_route(
			'wpstream/v1',
			'/playback-session-verify',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'wpstream_rest_verify_playback_session' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'playbackSession' => array(
						'type' => 'string',
					),
				),
			)
		);
	}

	public function wpstream_rest_verify_playback_session( WP_REST_Request $request ) {
		$session = trim( (string) $request->get_param( 'playbackSession' ) );
		if ( '' === $session && ! empty( $_SERVER['HTTP_X_WPSTREAM_PLAYBACK_SESSION'] ) ) {
			$session = trim( (string) wp_unslash( $_SERVER['HTTP_X_WPSTREAM_PLAYBACK_SESSION'] ) );
		}

		if ( '' === $session ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'invalid_playback_session',
				),
				401
			);
		}

		$data = get_transient( $this->wpstream_playback_session_transient_name( $session ) );
		if ( ! is_array( $data ) || empty( $data['product_id'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'playback_session_invalid',
				),
				401
			);
		}

		return new WP_REST_Response( array( 'success' => true ) );
	}

	public function wpstream_ajax_issue_playback_session() {
		check_ajax_referer( 'wpstream_playback_session_issue', 'nonce' );

		$product_id = isset( $_POST['productId'] ) ? intval( wp_unslash( $_POST['productId'] ) ) : 0;

		if ( ! $this->wpstream_user_entitled_for_vod_product( $product_id ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$token = wp_generate_password( 56, false, false );
		$ttl   = $this->wpstream_get_playback_session_ttl();
		set_transient(
			$this->wpstream_playback_session_transient_name( $token ),
			array(
				'product_id' => $product_id,
				'user_id'    => get_current_user_id(),
				'issued_at'  => time(),
			),
			$ttl
		);

		wp_send_json_success(
			array(
				'playbackSession' => $token,
			)
		);
	}
}
