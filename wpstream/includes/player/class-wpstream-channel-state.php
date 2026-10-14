<?php
/**
 * Wpstream_Channel_State — the single owner of "is this channel live?".
 *
 * One value object answers every liveness question for a live channel: it owns
 * the status cache (the `event_data_to_return_{id}` transient), its 45-second
 * lifetime, the allowlist of fields a Baker response may contribute, the Baker
 * fetch on a cache miss, and the stream-name/key-URL post-meta sync that a
 * fresh live payload triggers. No other plugin code reads or writes the
 * transient, calls Baker for channel status, or re-derives liveness.
 *
 * Liveness rule (see docs/adr/0002-channel-state-single-owner.md): a channel
 * is live only when the upstream status is `active` AND a non-empty playback
 * URL exists. The status string alone is for viewer-facing messaging only.
 *
 * Compatibility contract: customer sites run older standalone hello-wpstream
 * themes that read the transient directly by name, so the transient's name,
 * TTL, and field names are frozen — the module is its only writer, but the
 * slot itself stays byte-compatible for those readers.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wpstream_Channel_State {

	/** Frozen transient name prefix — older standalone themes read it directly. */
	const TRANSIENT_PREFIX = 'event_data_to_return_';

	/** Default status-cache lifetime in seconds; see `wpstream_channel_state_ttl`. */
	const TTL = 45;

	/** Upper bound for the filtered lifetime: past this a stopped channel looks live for too long. */
	const MAX_TTL = 300;

	/**
	 * Only these Baker response fields may enter the cache (and, via
	 * api20_wpstream_update_event, post meta): every key the plugin later
	 * reads back. A response can never plant arbitrary keys.
	 *
	 * Deliberately not filterable: every cached key is written to post meta,
	 * so a filter would turn an upstream response into arbitrary meta writes.
	 *
	 * @var string[]
	 */
	private static $allowed_event_keys = array(
		'success',
		'status',
		'error',
		'server_id',
		'stream_name',
		'hls_playback_url',
		'hls_key_retrieval_url',
		'stats_url',
		'chat_url',
		'whipUrl',
		'broadcast_url',
	);

	/** @var int Channel/post id this state describes. */
	private $channel_id;

	/** @var array Cached (allowlist-shaped) status payload; empty array when unknown. */
	private $data;

	/**
	 * Private: build instances through for_channel().
	 *
	 * @param int   $channel_id Channel/post id.
	 * @param array $data       Status payload backing this state.
	 */
	private function __construct( $channel_id, array $data ) {
		$this->channel_id = intval( $channel_id );
		$this->data       = $data;
	}

	/**
	 * Return the current state for a channel: served from the 45s cache, or
	 * fetched fresh from Baker on a miss (which also syncs the stream-name /
	 * key-URL post meta when the payload is live).
	 *
	 * @param int    $channel_id Channel/post id.
	 * @param string $notes      Caller context passed through to the Baker call.
	 * @return Wpstream_Channel_State
	 */
	public static function for_channel( $channel_id, $notes = 'wpstream_channel_state' ) {
		$channel_id = intval( $channel_id );
		$data       = get_transient( self::TRANSIENT_PREFIX . $channel_id );

		// Anything that is not a non-empty array counts as a cache miss.
		if ( ! is_array( $data ) || array() === $data ) {
			$data = self::refresh( $channel_id, $notes );
		}

		return new self( $channel_id, is_array( $data ) ? $data : array() );
	}

	/**
	 * Single write path into the status cache: filter a Baker response down to
	 * the allowlisted fields and store it for TTL seconds.
	 *
	 * @param int   $channel_id Channel/post id.
	 * @param mixed $response   Decoded Baker response.
	 * @return array|false The stored (filtered) payload, or false when $response is not an array.
	 */
	public static function store( $channel_id, $response ) {
		// Only arrays carry event fields; anything else stores nothing.
		if ( ! is_array( $response ) ) {
			return false;
		}

		// Keep allowlisted fields only — never the raw upstream payload.
		$filtered = array();
		foreach ( $response as $key => $value ) {
			if ( in_array( $key, self::$allowed_event_keys, true ) ) {
				$filtered[ $key ] = $value;
			}
		}

		/**
		 * Filter how long a channel's status snapshot is cached, in seconds.
		 *
		 * Clamped to [1, 300]. The transient name and its fields are frozen.
		 *
		 * @since 4.14.0
		 *
		 * @param int $seconds    Lifetime; default 45.
		 * @param int $channel_id Channel/post id.
		 */
		$ttl = wpstream_tunable( 'wpstream_channel_state_ttl', self::TTL, self::MAX_TTL, array( intval( $channel_id ) ) );
		set_transient( self::TRANSIENT_PREFIX . intval( $channel_id ), $filtered, $ttl );
		return $filtered;
	}

	/**
	 * Cache miss: fetch the channel status from Baker, cache it, and — when a
	 * live payload just arrived — sync the stream name / HLS key retrieval URL
	 * onto the channel post and drop the free-event stream-name lookup cache.
	 * Fresh-data-only: cache-served reads never write meta.
	 *
	 * @param int    $channel_id Channel/post id.
	 * @param string $notes      Caller context for the Baker call.
	 * @return array The freshly cached payload; empty array when Baker is unreachable.
	 */
	private static function refresh( $channel_id, $notes ) {
		global $wpstream_plugin;

		// No live connection service (e.g. plugin still booting): unknown state.
		if ( ! isset( $wpstream_plugin->wpstream_live_connection ) ) {
			return array();
		}

		$response = $wpstream_plugin->wpstream_live_connection->wpstream_check_event_status_api_call( $channel_id, $notes );
		$data     = self::store( $channel_id, $response );
		if ( ! is_array( $data ) ) {
			return array();
		}

		// A live payload carries the stream identity the DRM/key code reads
		// from post meta — persist it once per cache refresh.
		if ( isset( $data['hls_playback_url'] ) && '' !== $data['hls_playback_url'] && isset( $data['stream_name'] ) ) {
			update_post_meta( $channel_id, 'stream_name', $data['stream_name'] );
			if ( isset( $data['hls_key_retrieval_url'] ) ) {
				update_post_meta( $channel_id, 'hls_key_retrieval_url', $data['hls_key_retrieval_url'] );
			}
			// Invalidate the cached stream-name lookup so the fresh name is used.
			delete_transient( 'wpstream_free_event_streamName_' . $data['stream_name'] );
		}

		return $data;
	}

	/**
	 * Whether a viewer can actually watch this second: upstream status is
	 * `active` AND a non-empty playback URL exists. Neither signal alone is
	 * "live" — see the Live entry in CONTEXT.md.
	 *
	 * @return bool
	 */
	public function is_live() {
		return 'active' === $this->status() && '' !== $this->playback_url();
	}

	/**
	 * Upstream lifecycle word (`starting`, `active`, `stopping`, `stopped`, …).
	 * For choosing viewer-facing messages only — never for playability.
	 *
	 * @return string Empty string when unknown.
	 */
	public function status() {
		return $this->field( 'status' );
	}

	/**
	 * Upstream error string (e.g. `NO_SUCH_CHANNEL`), empty when none.
	 *
	 * @return string
	 */
	public function error() {
		return $this->field( 'error' );
	}

	/**
	 * HLS playback URL, empty when the channel is not streaming.
	 *
	 * @return string
	 */
	public function playback_url() {
		return $this->field( 'hls_playback_url' );
	}

	/**
	 * Live-connect stats URI: the explicit stats_url when Baker provides one,
	 * otherwise derived from a playback URL on the known streamer host.
	 *
	 * @return string
	 */
	public function stats_url() {
		return self::derive_stats_url( $this->data );
	}

	/**
	 * Chat endpoint URL, empty when none.
	 *
	 * @return string
	 */
	public function chat_url() {
		return $this->field( 'chat_url' );
	}

	/**
	 * Derive the live-connect stats URI from a status payload (shared with the
	 * player's legacy public wpstream_get_live_connect_uri delegate).
	 *
	 * @param array $payload Status payload.
	 * @return string Stats URL, a host derived from the playback URL, or ''.
	 */
	public static function derive_stats_url( $payload ) {
		// Prefer an explicit stats_url when the API provides one.
		if ( isset( $payload['stats_url'] ) && '' !== trim( $payload['stats_url'] ) ) {
			return trim( $payload['stats_url'] );
		}

		// Otherwise, if the playback URL points at the known streamer host, derive it.
		if (
			isset( $payload['hls_playback_url'] ) &&
			'' !== trim( $payload['hls_playback_url'] ) &&
			false !== strpos( $payload['hls_playback_url'], 'live.streamer.wpstream.net' )
		) {
			// Keep everything up to and including the streamer host, then strip the scheme.
			$parts = explode( 'live.streamer.wpstream.net', $payload['hls_playback_url'] );
			return preg_replace( '#^https?://#', '', $parts[0] . 'live.streamer.wpstream.net' );
		}

		// No stats URL available.
		return '';
	}

	/**
	 * Read one payload field as a trimmed string ('' when absent or non-scalar).
	 *
	 * @param string $key Payload key.
	 * @return string
	 */
	private function field( $key ) {
		if ( ! isset( $this->data[ $key ] ) || ! is_scalar( $this->data[ $key ] ) ) {
			return '';
		}
		return trim( (string) $this->data[ $key ] );
	}
}

if ( ! function_exists( 'wpstream_channel_state' ) ) {
	/**
	 * Guarded helper for themes/templates: current state for a channel.
	 * Themes call this behind function_exists() so they degrade gracefully
	 * against plugin versions that predate the module.
	 *
	 * @param int $channel_id Channel/post id.
	 * @return Wpstream_Channel_State
	 */
	function wpstream_channel_state( $channel_id ) {
		return Wpstream_Channel_State::for_channel( $channel_id );
	}
}
