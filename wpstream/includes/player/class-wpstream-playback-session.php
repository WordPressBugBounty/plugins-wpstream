<?php
/**
 * VOD playback session: validatePlaybackSessionUrl, REST verification, AJAX issuance.
 *
 * @package wpstream
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles wpstream/v1/playback-session-verify and wpstream_issue_playback_session.
 */
class Wpstream_Playback_Session {

	/**
	 * Wpstream_Playback_Session constructor.
	 */
	public function __construct() {
		// Register the REST route used by the presence server to verify a session token.
		add_action( 'rest_api_init', array( $this, 'wpstream_register_playback_session_rest_routes' ) );
		// AJAX endpoint that issues a session token to a logged-in viewer.
		add_action( 'wp_ajax_wpstream_issue_playback_session', array( $this, 'wpstream_ajax_issue_playback_session' ) );
		// Logged-out viewers may still be entitled (e.g. free wpstream_product_vod); the entitlement check gates the call.
		add_action( 'wp_ajax_nopriv_wpstream_issue_playback_session', array( $this, 'wpstream_ajax_issue_playback_session' ) );
	}

	/**
	 * Base URL embedded as validatePlaybackSessionUrl (presence server verifies via GET).
	 *
	 * The product ID travels as a path segment so verification is bound to the exact
	 * video the URL was rendered for; the URL is hashed into the embed key, so it
	 * cannot be swapped for another product's without invalidating the key.
	 *
	 * @param int $product_id Product/channel post ID to bind verification to (0 = unbound legacy URL).
	 * @return string
	 */
	public function wpstream_get_default_validate_playback_session_url( $product_id = 0 ) {
		// Absolute, escaped URL of the verification REST route embedded in the player.
		$path = 'wpstream/v1/playback-session-verify';
		if ( (int) $product_id > 0 ) {
			$path .= '/' . (int) $product_id;
		}
		return esc_url_raw( rest_url( $path ) );
	}

	/**
	 * Whether a host name is plausibly reachable from the public internet.
	 *
	 * Loopback/private/reserved IPs, reserved development TLDs (.test, .local,
	 * .localhost, .example, .invalid) and bare single-label intranet names can
	 * never be resolved or reached by an external service.
	 *
	 * @param string $host Host name or IP literal (no scheme/port).
	 * @return bool True when the host could be reached from outside.
	 */
	public function wpstream_is_host_publicly_routable( $host ) {
		// Normalize: lowercase, trimmed, IPv6 brackets removed.
		$host = strtolower( trim( (string) $host ) );
		$host = trim( $host, '[]' );
		if ( '' === $host || 'localhost' === $host ) {
			return false;
		}

		// IP literal: private (10/8, 172.16/12, 192.168/16) and reserved
		// (loopback, link-local, ::1, ...) ranges are unreachable from outside.
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return (bool) filter_var(
				$host,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			);
		}

		// Reserved / conventional development TLDs never resolve publicly.
		$tld = substr( (string) strrchr( $host, '.' ), 1 );
		if ( in_array( $tld, array( 'localhost', 'test', 'local', 'example', 'invalid', 'internal' ), true ) ) {
			return false;
		}

		// A bare single-label name (intranet host) is not publicly routable.
		if ( false === strpos( $host, '.' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether playback-session validation can work for this site at all.
	 *
	 * The presence backend has to fetch this site's REST verify URL; when the
	 * site lives on a non-publicly-routable host (local dev, intranet) that
	 * fetch can never succeed and the hosted player refuses to start
	 * (presence bootstrap 401 VALIDATION_UNAVAILABLE). Such sites must not
	 * advertise a validate URL — the gate protects nothing there, while
	 * sending it guarantees a dead player.
	 *
	 * Overridable via the wpstream_playback_session_validation_available
	 * filter (e.g. a tunnel setup where an unroutable-looking host IS exposed).
	 *
	 * @return bool True when the verify URL is worth advertising.
	 */
	public function wpstream_playback_session_validation_available() {
		$host      = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$available = $this->wpstream_is_host_publicly_routable( $host );
		return (bool) apply_filters( 'wpstream_playback_session_validation_available', $available, $host );
	}

	/**
	 * Derive the transient key that stores a given session token's data.
	 *
	 * @param string $token Opaque playback session token.
	 * @return string
	 */
	private function wpstream_playback_session_transient_name( $token ) {
		// Salted SHA-256 hash so the raw token is never used directly as a storage key.
		return 'wpstream_pbs_' . hash( 'sha256', wp_salt( 'auth' ) . $token );
	}

	/**
	 * Time-to-live applied to each issued playback session.
	 *
	 * @return int TTL in seconds for issued playback sessions.
	 */
	public function wpstream_get_playback_session_ttl() {
		// Default 10 minutes, overridable via the wpstream_playback_session_ttl_seconds filter.
		return (int) apply_filters( 'wpstream_playback_session_ttl_seconds', 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Whether the current viewer is entitled to this product.
	 *
	 * Delegates to the single shared entitlement rule, so session issuance
	 * grants exactly what the player render does — including active
	 * subscriptions and Netflix mode, which the old inline copy missed.
	 *
	 * @param int $product_id Product or wpstream_* post ID.
	 * @return bool
	 */
	private function wpstream_user_entitled_for_vod_product( $product_id ) {
		// The single entitlement rule (includes/Helpers/wpstream-entitlement.php).
		return wpstream_user_entitled_for_product( $product_id );
	}

	/**
	 * Register the GET REST route the presence server calls to verify a session.
	 *
	 * The product-bound form (…/playback-session-verify/123) is what the player embeds;
	 * the bare form stays registered for embeds rendered before the URL carried a product.
	 */
	public function wpstream_register_playback_session_rest_routes() {
		// Public read-only route; the token itself is the credential (permission_callback returns true).
		// The trailing product segment is optional and carried as a path segment (not a query
		// param) so it survives any token-appending style the remote player uses.
		register_rest_route(
			'wpstream/v1',
			'/playback-session-verify(?:/(?P<productId>\d+))?',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'wpstream_rest_verify_playback_session' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'playbackSession' => array(
						'type' => 'string',
					),
					'productId'       => array(
						'type' => 'integer',
					),
				),
			)
		);
	}

	/**
	 * REST callback: confirm a playback session token is present and still valid.
	 *
	 * @param WP_REST_Request $request Incoming verification request.
	 * @return WP_REST_Response Success payload, or a 401 error response.
	 */
	public function wpstream_rest_verify_playback_session( WP_REST_Request $request ) {
		// Prefer the token from the query param; fall back to the custom request header.
		$session = trim( (string) $request->get_param( 'playbackSession' ) );
		if ( '' === $session && ! empty( $_SERVER['HTTP_X_WPSTREAM_PLAYBACK_SESSION'] ) ) {
			$session = trim( (string) wp_unslash( $_SERVER['HTTP_X_WPSTREAM_PLAYBACK_SESSION'] ) );
		}

		// No token supplied at all: reject as unauthorized.
		if ( '' === $session ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'invalid_playback_session',
				),
				401
			);
		}

		// Look up the token's stored data; missing/expired or malformed data is a rejection.
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

		// When the route carries a product context, the token must have been issued for
		// that exact product — a token minted for one video is worthless for any other.
		$requested_product = (int) $request->get_param( 'productId' );
		if ( $requested_product > 0 && (int) $data['product_id'] !== $requested_product ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'playback_session_scope_mismatch',
				),
				401
			);
		}

		// Token resolved to a valid session: report success and name the bound product
		// so the response is never a scope-less generic success.
		return new WP_REST_Response(
			array(
				'success'   => true,
				'productId' => (int) $data['product_id'],
			)
		);
	}

	/**
	 * AJAX callback: issue a fresh playback session token to an entitled viewer.
	 *
	 * Deliberately nonce-free: player pages are served from page caches/CDNs, so a
	 * rendered nonce goes stale and silently breaks issuance. The entitlement check
	 * below is the gate; issuance is non-destructive and the token cannot be read
	 * cross-origin, so CSRF adds no protection here.
	 */
	public function wpstream_ajax_issue_playback_session() {
		// Read and sanitize the requested product ID from the POST body.
		$product_id = isset( $_POST['productId'] ) ? intval( wp_unslash( $_POST['productId'] ) ) : 0;

		// Deny issuance unless the current viewer is entitled to this product.
		if ( ! $this->wpstream_user_entitled_for_vod_product( $product_id ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		// Generate an opaque token and its TTL, then persist the session data under the hashed key.
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

		/**
		 * Fires after a playback session was issued to a viewer.
		 *
		 * An action, never a filter, and the token is deliberately not passed:
		 * it is the viewer's credential for the presence server.
		 *
		 * @since 4.14.0
		 *
		 * @param int $product_id Product the session was issued for.
		 * @param int $user_id    Viewer, 0 for guests.
		 * @param int $ttl        Session lifetime in seconds.
		 */
		do_action( 'wpstream_playback_session_issued', $product_id, get_current_user_id(), intval( $ttl ) );

		// Return the token to the player, which will present it to the presence server.
		wp_send_json_success(
			array(
				'playbackSession' => $token,
			)
		);
	}
}
