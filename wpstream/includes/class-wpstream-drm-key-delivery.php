<?php
/**
 * Deep Module for live and VOD DRM Key delivery.
 *
 * @package Wpstream
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Wpstream_Drm_Key_Remote_Fetch_Port {
	/**
	 * @param string $url Remote DRM Key retrieval URL.
	 * @return array{success:bool,status:int,body:string}
	 */
	public function fetch( $url );
}

class Wpstream_WordPress_Drm_Key_Remote_Fetch implements Wpstream_Drm_Key_Remote_Fetch_Port {
	public function fetch( $url ) {
		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'status' => 0, 'body' => '' );
		}

		$status = intval( wp_remote_retrieve_response_code( $response ) );
		$body   = (string) wp_remote_retrieve_body( $response );
		return array(
			'success' => 200 <= $status && 300 > $status && '' !== $body,
			'status'  => $status,
			'body'    => $body,
		);
	}
}

class Wpstream_Drm_Key_Delivery {
	/** @var Wpstream_Channel_Settings */
	private $channel_settings;
	/** @var Wpstream_Drm_Key_Remote_Fetch_Port */
	private $remote_fetch;

	public function __construct( Wpstream_Channel_Settings $channel_settings, Wpstream_Drm_Key_Remote_Fetch_Port $remote_fetch ) {
		$this->channel_settings = $channel_settings;
		$this->remote_fetch     = $remote_fetch;
	}

	/**
	 * Resolve one normalized DRM Key request into an adapter-ready result.
	 *
	 * @param array<string,mixed> $request Request kind, identifier, and session state.
	 * @return array<string,mixed>
	 */
	public function deliver( $request ) {
		if ( ! is_array( $request ) ) {
			return array( 'handled' => false );
		}

		$kind       = isset( $request['kind'] ) ? (string) $request['kind'] : '';
		$identifier = isset( $request['identifier'] ) && is_scalar( $request['identifier'] )
			? trim( (string) $request['identifier'] )
			: '';
		if ( ! in_array( $kind, array( 'live', 'vod' ), true ) || '' === $identifier ) {
			return array( 'handled' => false );
		}

		if ( $this->channel_settings->session_encryption_enabled() && empty( $request['has_session'] ) ) {
			return $this->denied( 'no session', $kind, 0, 'no_session' );
		}

		if ( 'vod' === $kind ) {
			$free_result = $this->deliver_free_vod( $identifier );
			if ( ! empty( $free_result['handled'] ) ) {
				return $free_result;
			}
			return $this->deliver_paid_vod( $identifier );
		}

		return $this->deliver_live( $identifier );
	}

	/**
	 * Invalidate cached VOD ownership for one post's DRM Key index.
	 *
	 * @param int $post_id VOD post ID.
	 * @return void
	 */
	public function invalidate_vod_lookup( $post_id ) {
		$identifier = (string) get_post_meta( intval( $post_id ), 'hlsDecryptionKeyIndex', true );
		if ( '' === $identifier ) {
			return;
		}

		delete_transient( 'wpstream_vod_decryption_key_index_' . $identifier );
		delete_transient( 'wpstream_paid_vod_key_index_' . $identifier );
	}

	private function deliver_live( $identifier ) {
		$parts       = explode( '-', $identifier );
		$stream_name = $parts[0];
		$cache_key   = 'wpstream_free_event_streamName_' . $stream_name;
		$post_ids    = get_transient( $cache_key );

		if ( false === $post_ids ) {
			$query = new WP_Query(
				array(
					'posts_per_page'         => -1,
					'cache_results'          => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'post_type'              => 'wpstream_product',
					'post_status'            => 'publish',
					'meta_query'             => array(
						array(
							'key'     => 'stream_name',
							'value'   => $stream_name,
							'compare' => '=',
						),
					),
					'fields'                  => 'ids',
				)
			);
			$post_ids = $query->posts;
			$this->cache( $cache_key, $post_ids, 60 );
		}

		if ( empty( $post_ids ) ) {
			return $this->deliver_paid_live( $identifier, $stream_name );
		}

		$key = $this->fetch_live_key( intval( $post_ids[0] ), $identifier );
		if ( '' === $key ) {
			return $this->empty_key_result();
		}

		return $this->key_result( $key, 301, false, 'live', intval( $post_ids[0] ) );
	}

	private function deliver_paid_live( $identifier, $stream_name ) {
		if ( ! is_user_logged_in() || 0 === intval( get_current_user_id() ) ) {
			return $this->denied( 'live - user not log or anwser', 'live', 0, 'not_logged_in' );
		}

		$cache_key = 'wpstream_paid_event_streamName_' . $stream_name;
		$post_ids  = get_transient( $cache_key );
		if ( false === $post_ids ) {
			$query = new WP_Query(
				array(
					'posts_per_page' => -1,
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'meta_query'     => array(
						array(
							'key'     => 'stream_name',
							'value'   => $stream_name,
							'compare' => '=',
						),
					),
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_type',
							'field'    => 'slug',
							'terms'    => array( 'live_stream', 'subscription' ),
						),
					),
					'fields'         => 'ids',
				)
			);
			$post_ids = $query->posts;
			$this->cache( $cache_key, $post_ids, 60 );
		}

		if ( empty( $post_ids ) ) {
			return $this->denied( 'live - no event', 'live', 0, 'unknown_item' );
		}

		$product_id = intval( $post_ids[0] );
		if ( ! wpstream_user_entitled_for_product( $product_id ) ) {
			return $this->denied( 'live - no ticket ', 'live', $product_id, 'not_entitled' );
		}

		$key = $this->fetch_live_key( $product_id, $identifier );
		if ( '' === $key ) {
			return $this->empty_key_result();
		}

		return $this->key_result( $key, 302, true, 'live', $product_id );
	}

	/**
	 * Store a lookup result (post ids, or the live key) under its frozen cache key.
	 *
	 * @param string $cache_key Transient name.
	 * @param mixed  $value     Value to cache.
	 * @param int    $seconds   Today's lifetime for this cache.
	 * @param int    $max       Upper bound for the filtered lifetime.
	 */
	private function cache( $cache_key, $value, $seconds, $max = HOUR_IN_SECONDS ) {
		/**
		 * Filter how long a DRM lookup result is cached, in seconds.
		 *
		 * Clamped to [1, 3600] for the id lookups and [1, 300] for the live
		 * key, which the cloud may rotate. Only the lifetime is filterable:
		 * the cache key and the cached value never pass through the filter.
		 *
		 * @since 4.14.0
		 *
		 * @param int    $seconds   Lifetime; 60 for index/stream-name lookups, 30 for the live key.
		 * @param string $cache_key Transient name being written.
		 */
		$ttl = wpstream_tunable( 'wpstream_drm_lookup_cache_ttl', $seconds, $max, array( (string) $cache_key ) );
		set_transient( $cache_key, $value, $ttl );
	}

	private function fetch_live_key( $channel_id, $identifier ) {
		$cache_key = $channel_id . '_api20_streamName';
		$key       = get_transient( $cache_key );
		if ( false !== $key ) {
			return (string) $key;
		}

		$base_url = (string) get_post_meta( $channel_id, 'hls_key_retrieval_url', true );
		$response = $this->remote_fetch->fetch( rtrim( $base_url, '/' ) . '/' . $identifier );
		if ( ! is_array( $response ) || empty( $response['success'] ) || empty( $response['body'] ) ) {
			return '';
		}

		$key = (string) $response['body'];
		$this->cache( $cache_key, $key, 30, 5 * MINUTE_IN_SECONDS );
		return $key;
	}

	private function deliver_paid_vod( $identifier ) {
		if ( ! is_user_logged_in() || 0 === intval( get_current_user_id() ) ) {
			return $this->denied( 'vod - user not log or no answer', 'vod', 0, 'not_logged_in' );
		}

		$cache_key = 'wpstream_paid_vod_key_index_' . $identifier;
		$post_ids  = get_transient( $cache_key );
		if ( false === $post_ids ) {
			$query = new WP_Query(
				array(
					'posts_per_page' => -1,
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'meta_query'     => array(
						array(
							'key'     => 'hlsDecryptionKeyIndex',
							'value'   => $identifier,
							'compare' => '=',
						),
					),
					'tax_query'      => array(
						array(
							'taxonomy' => 'product_type',
							'field'    => 'slug',
							'terms'    => array( 'video_on_demand', 'subscription' ),
						),
					),
					'fields'         => 'ids',
				)
			);
			$post_ids = $query->posts;
			$this->cache( $cache_key, $post_ids, 60 );
		}

		if ( empty( $post_ids ) ) {
			return $this->denied( 'vod - no item', 'vod', 0, 'unknown_item' );
		}

		$product_id = intval( $post_ids[0] );
		if ( ! wpstream_user_entitled_for_product( $product_id ) ) {
			return $this->denied( 'vod - no ticket ', 'vod', $product_id, 'not_entitled' );
		}

		$encoded = get_post_meta( $product_id, 'hlsDecryptionKey', true );
		$key     = base64_decode( (string) $encoded, true );
		if ( false === $key || '' === $key ) {
			return $this->empty_key_result();
		}

		return $this->key_result( $key, 302, true, 'vod', $product_id );
	}

	private function deliver_free_vod( $identifier ) {
		$cache_key = 'wpstream_vod_decryption_key_index_' . $identifier;
		$post_ids  = get_transient( $cache_key );

		if ( false === $post_ids ) {
			$query = new WP_Query(
				array(
					'posts_per_page'         => -1,
					'cache_results'          => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'post_type'              => 'wpstream_product_vod',
					'post_status'            => 'publish',
					'meta_query'             => array(
						array(
							'key'     => 'hlsDecryptionKeyIndex',
							'value'   => $identifier,
							'compare' => '=',
						),
					),
					'fields'                  => 'ids',
				)
			);
			$post_ids = $query->posts;
			$this->cache( $cache_key, $post_ids, 60 );
		}

		if ( empty( $post_ids ) ) {
			return array( 'handled' => false );
		}

		$encoded = get_post_meta( intval( $post_ids[0] ), 'hlsDecryptionKey', true );
		$key     = base64_decode( (string) $encoded, true );
		if ( false === $key || '' === $key ) {
			return $this->empty_key_result();
		}

		return $this->key_result( $key, 301, false, 'vod', intval( $post_ids[0] ) );
	}

	/**
	 * Announce a refused key request and return the plain-text refusal.
	 *
	 * @param string $body       Legacy refusal text.
	 * @param string $kind       `live` or `vod`.
	 * @param int    $product_id Product the key belongs to (0 when not resolved yet).
	 * @param string $reason     `no_session`, `not_logged_in`, `unknown_item` or `not_entitled`.
	 * @return array Delivery result.
	 */
	private function denied( $body, $kind, $product_id, $reason ) {
		/**
		 * Fires when a DRM key request was refused.
		 *
		 * @since 4.14.0
		 *
		 * @param string $kind       `live` or `vod`.
		 * @param int    $product_id Product the key belongs to (0 when the item could not be resolved).
		 * @param int    $user_id    Requesting user (0 for guests).
		 * @param string $reason     `no_session`, `not_logged_in`, `unknown_item` or `not_entitled`.
		 */
		do_action( 'wpstream_drm_key_denied', $kind, intval( $product_id ), get_current_user_id(), $reason );

		return $this->text_result( $body );
	}

	/**
	 * Build the binary key response and announce the delivery.
	 *
	 * Stays a plain method on purpose: the key body is never passed through a
	 * filter, so no callback can read or replace key material.
	 *
	 * @param string $key        Raw key bytes.
	 * @param int    $seconds    Cache lifetime.
	 * @param bool   $private    Whether the cache entry is per-user.
	 * @param string $kind       `live` or `vod`.
	 * @param int    $product_id Product the key belongs to.
	 * @return array Delivery result.
	 */
	private function key_result( $key, $seconds, $private, $kind, $product_id ) {
		/**
		 * Fires after a DRM key was delivered. No key material is passed.
		 *
		 * @since 4.14.0
		 *
		 * @param string $kind       `live` or `vod`.
		 * @param int    $product_id Product the key belongs to.
		 * @param int    $user_id    Requesting user (0 for guests).
		 */
		do_action( 'wpstream_drm_key_delivered', $kind, intval( $product_id ), get_current_user_id() );

		$cache_control = ( $private ? 'private, ' : '' ) . 'max-age=' . intval( $seconds );
		return array(
			'handled' => true,
			'status'  => 200,
			'body'    => $key,
			'headers' => array(
				'Content-Type'           => 'application/octet-stream',
				'X-Content-Type-Options' => 'nosniff',
				'Expires'                => gmdate( 'D, d M Y H:i:s', time() + intval( $seconds ) ) . ' GMT',
				'Pragma'                 => 'cache',
				'Cache-Control'          => $cache_control,
			),
		);
	}

	private function empty_key_result() {
		return array(
			'handled' => true,
			'status'  => 200,
			'body'    => '',
			'headers' => array(
				'Content-Type'           => 'application/octet-stream',
				'X-Content-Type-Options' => 'nosniff',
				'Cache-Control'          => 'no-store',
			),
		);
	}

	private function text_result( $body ) {
		return array(
			'handled' => true,
			'status'  => 200,
			'body'    => (string) $body,
			'headers' => array(
				'Content-Type'           => 'text/plain; charset=UTF-8',
				'X-Content-Type-Options' => 'nosniff',
			),
		);
	}
}
