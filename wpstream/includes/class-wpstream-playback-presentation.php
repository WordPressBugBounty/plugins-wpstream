<?php
/**
 * Playback Presentation Module.
 *
 * Owns source resolution, engine selection, required assets, and the trusted
 * viewer-facing HTML for already-approved Streaming Content.
 *
 * @package Wpstream
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wpstream_Playback_Presentation {
	/** @var Wpstream Main plugin instance. */
	private $main;

	/** @var Wpstream_Playback_Session Playback-session owner used by hosted players. */
	private $playback_session;

	/** @param Wpstream $main Main plugin instance. */
	public function __construct( $main ) {
		$this->main = $main;
		$this->playback_session = new Wpstream_Playback_Session();
	}

	/**
	 * Render approved Streaming Content in the requested presentation mode.
	 *
	 * Entitlement is decided by the caller; this method only presents. Two
	 * filters and one action surround the render so themes and add-ons can
	 * change what is shown without forking the player templates.
	 *
	 * @param int    $content_id Streaming Content post ID.
	 * @param string $intent     auto, live, vod, or trailer-only.
	 * @return string Trusted player markup with dynamic values escaped by context.
	 */
	public function render( $content_id, $intent = 'auto' ) {
		$content_id = absint( $content_id );
		$intent     = strtolower( trim( (string) $intent ) );
		if ( $content_id && 'auto' === $intent ) {
			$intent = $this->classify( $content_id );
		}
		if ( $content_id ) {
			/**
			 * Re-route the resolved presentation intent for one piece of content.
			 *
			 * Runs after `auto` has been classified, so callbacks always see a
			 * concrete intent. Returning a value outside live / vod / trailer-only
			 * renders nothing. Typical use: a soft paywall that forces
			 * `trailer-only` for guests.
			 *
			 * @since 4.14.0
			 *
			 * @param string $intent     live, vod, or trailer-only.
			 * @param int    $content_id Streaming Content post ID.
			 */
			$intent = (string) apply_filters( 'wpstream_player_render_intent', $intent, $content_id );
		}
		if ( ! $content_id || ! in_array( $intent, array( 'live', 'vod', 'trailer-only' ), true ) ) {
			return '';
		}

		// Render the chosen presentation into a buffer.
		ob_start();
		if ( 'live' === $intent ) {
			$this->wpstream_live_event_player( $content_id );
		} elseif ( 'vod' === $intent ) {
			$this->wpstream_video_on_demand_player( $content_id );
		} else {
			$this->wpstream_video_on_demand_player_only_trailer( $content_id );
		}
		$html = (string) ob_get_clean();

		/**
		 * Fires once after the player markup for one piece of content was built.
		 *
		 * @since 4.14.0
		 *
		 * @param int    $content_id Streaming Content post ID.
		 * @param string $intent     live, vod, or trailer-only.
		 * @param string $engine     `iframe` (hosted player) or `videojs` (legacy in-page
		 *                           player) — a label derived from the embed-URL meta the
		 *                           renderers branch on, not a report of what was emitted.
		 */
		do_action( 'wpstream_player_rendered', $content_id, $intent, $this->engine( $content_id, $intent ) );

		/**
		 * Filter the complete player markup before it is returned to the caller.
		 *
		 * The markup is already escaped; callbacks own the escaping of anything
		 * they add. Entitlement was decided before this point — the filter cannot
		 * grant access, only change how approved content is presented.
		 *
		 * @since 4.14.0
		 *
		 * @param string $html       Trusted player markup.
		 * @param int    $content_id Streaming Content post ID.
		 * @param string $intent     live, vod, or trailer-only.
		 */
		return (string) apply_filters( 'wpstream_player_html', $html, $content_id, $intent );
	}

	/**
	 * Name the player engine the render used, derived from the same metadata
	 * the renderers branch on (an embed URL selects the hosted iframe player).
	 *
	 * @param int    $content_id Streaming Content post ID.
	 * @param string $intent     live, vod, or trailer-only.
	 * @return string iframe or videojs.
	 */
	private function engine( $content_id, $intent ) {
		if ( 'live' === $intent ) {
			return get_post_meta( $content_id, 'embedUrl', true ) ? 'iframe' : 'videojs';
		}
		if ( 'vod' === $intent ) {
			return get_post_meta( $content_id, 'wpstream_vod_embed_url', true ) ? 'iframe' : 'videojs';
		}
		return 'videojs';
	}

	/**
	 * Run the hosted-player iframe query through its filter without exposing
	 * the keys that are hashed into the embed key.
	 *
	 * `channel` / `video`, `embedKey`, `validatePlaybackSessionUrl`,
	 * `embedAncestor` and `encrypt` are signed together; a callback that changed
	 * one would produce a player that refuses to start. They are withheld from
	 * the filtered copy and re-applied afterwards, so callbacks can only touch
	 * presentation args (skin, poster, logo, autoplay, ...).
	 *
	 * @param string $hook       `wpstream_live_iframe_query_args` or `wpstream_vod_iframe_query_args`.
	 * @param array  $args       Assembled query args (already filtered of empties).
	 * @param int    $content_id Streaming Content post ID.
	 * @return array Query args with the signed keys restored.
	 */
	private function filter_iframe_query_args( $hook, array $args, $content_id ) {
		$signed_keys = array( 'channel', 'video', 'embedKey', 'validatePlaybackSessionUrl', 'embedAncestor', 'encrypt' );
		$signed      = array_intersect_key( $args, array_flip( $signed_keys ) );

		/**
		 * Filter the hosted player's iframe query args.
		 *
		 * The signed keys (`channel` / `video`, `embedKey`,
		 * `validatePlaybackSessionUrl`, `embedAncestor`, `encrypt`) are not in
		 * `$args` and cannot be set: the canonical values are re-applied after
		 * the filter.
		 *
		 * @since 4.14.0
		 *
		 * @param array $args       Presentation query args (skin, posterImage, logo*, autoplay, ...).
		 * @param int   $content_id Streaming Content post ID.
		 */
		$filtered = (array) apply_filters( $hook, array_diff_key( $args, $signed ), intval( $content_id ) );

		// Drop any signed key the callback set (including ones absent above) and
		// restore the canonical set, keeping the original key order.
		$filtered = array_diff_key( $filtered, array_flip( $signed_keys ) );
		$kept = $filtered + $signed;
		return array_replace( array_intersect_key( $args, $kept ), $kept );
	}

	/**
	 * Resolve the poster image of a Streaming Content item.
	 *
	 * The single place the poster is read, for the hosted iframe and the
	 * legacy in-page player alike.
	 *
	 * @param int    $content_id Streaming Content post ID.
	 * @param string $size       Image size (`full` for the iframe, `small` for the legacy player).
	 * @return string Poster URL or an empty string.
	 */
	public function poster_url( $content_id, $size = 'full' ) {
		$url = (string) get_the_post_thumbnail_url( $content_id, $size );

		/**
		 * Filter the player poster image URL.
		 *
		 * @since 4.14.0
		 *
		 * @param string $url        Poster URL (empty when the item has no featured image).
		 * @param int    $content_id Streaming Content post ID.
		 */
		return (string) apply_filters( 'wpstream_player_poster_url', $url, intval( $content_id ) );
	}

	/**
	 * Resolve the pre-roll trailer of a Streaming Content item.
	 *
	 * The single place the plugin's players read the trailer; their trailer
	 * controls key off the returned URL being non-empty. (The bundled theme
	 * framework still reads the `video_trailer` meta for its own controls.)
	 *
	 * @param int $content_id Streaming Content post ID.
	 * @return string Trailer URL or an empty string when there is none.
	 */
	public function trailer_url( $content_id ) {
		$attachment_id = intval( get_post_meta( $content_id, 'video_trailer', true ) );
		$url           = $attachment_id ? (string) wp_get_attachment_url( $attachment_id ) : '';

		/**
		 * Filter the pre-roll trailer URL.
		 *
		 * Returning an empty string removes the trailer; returning a URL adds
		 * one without a media-library attachment.
		 *
		 * @since 4.14.0
		 *
		 * @param string $url        Trailer URL or empty.
		 * @param int    $content_id Streaming Content post ID.
		 */
		return (string) apply_filters( 'wpstream_trailer_url', $url, intval( $content_id ) );
	}

	/** @return array Legacy source-result shape. */
	public function resolve_vod_source( $content_id ) {
		return $this->wpstream_video_on_demand_player_uri_request( absint( $content_id ) );
	}

	/**
	 * Classify Streaming Content by its post type, product term or subscription flag.
	 *
	 * The single copy of the live-vs-VOD rule; other surfaces (My Account lists)
	 * compare against its return instead of re-reading the term and flag.
	 *
	 * @param int $content_id Post ID.
	 * @return string live, vod, or an empty string for unknown content.
	 */
	public function classify( $content_id ) {
		$post_type = get_post_type( $content_id );
		if ( 'wpstream_product' === $post_type ) {
			return 'live';
		}
		if ( 'wpstream_product_vod' === $post_type ) {
			return 'vod';
		}
		if ( 'product' !== $post_type ) {
			return '';
		}

		$product_type = wpstream_get_product_type_slug( $content_id );
		if ( 'live_stream' === $product_type ) {
			return 'live';
		}
		if ( 'video_on_demand' === $product_type ) {
			return 'vod';
		}
		if ( 'subscription' === $product_type ) {
			$subscription_mode = get_post_meta( $content_id, '_subscript_live_event', true );
			if ( 'yes' === $subscription_mode ) {
				return 'live';
			}
			if ( 'no' === $subscription_mode ) {
				return 'vod';
			}
		}
		return '';
	}

	public function remove_http($url) {
	        // Schemes we want to remove from the front of the string.
	        $disallowed = array('http://', 'https://');
	        foreach($disallowed as $d) {
	           // Only strip when the scheme is at the very start of the URL.
	           if(strpos($url, $d) === 0) {
	              return str_replace($d, '', $url);
	           }
	        }
	        // No leading scheme matched; return the URL as-is.
	        return $url;
	    }

	private function wpstream_get_site_origin_for_embed() {
			// Break the site home URL into scheme/host/port parts.
			$parts = wp_parse_url( home_url() );
			// Without a host there is no origin to build.
			if ( empty( $parts['host'] ) ) {
				return '';
			}
			// Default to https when the scheme is missing.
			$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : 'https://';
			$host   = $parts['host'];
			// Include the port only when one is explicitly set.
			$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
			// Reassemble scheme://host[:port].
			return $scheme . $host . $port;
		}

	private function wpstream_get_player_embed_key_salt() {
			// Hardcoded fallback salt; must match the player's env.js fallback.
			$default_salt = 'EYjb84vNTXE85TfR';
			// A site can override the salt via option.
			$configured   = trim( (string) get_option( 'wpstream_player_embed_key_salt', '' ) );
			// Prefer the configured salt when present, else the default.
			$base_salt    = '' !== $configured ? $configured : $default_salt;

			// Let integrators filter the final salt.
			return (string) apply_filters( 'wpstream_player_embed_key_salt', $base_salt );
		}

	private function wpstream_generate_player_embed_key( $video, $validate_playback_session_url = '', $embed_ancestor = '', $encrypt_raw = '' ) {
			// An empty media path produces no key.
			$video = trim( (string) $video );
			if ( '' === $video ) {
				return '';
			}

			// Base input: "<video> <salt>". Optional parts are prepended in a fixed order.
			$hash_input = $video . ' ' . $this->wpstream_get_player_embed_key_salt();
			// Prepend the session-validation URL when supplied.
			$validate_playback_session_url = trim( (string) $validate_playback_session_url );
			if ( '' !== $validate_playback_session_url ) {
				$hash_input = $validate_playback_session_url . ' ' . $hash_input;
			}

			// Prepend the allowed frame-ancestor origin when supplied.
			$embed_ancestor = trim( (string) $embed_ancestor );
			if ( '' !== $embed_ancestor ) {
				$hash_input = $embed_ancestor . ' ' . $hash_input;
			}

			// Prepend the "crypt" marker when encryption is requested.
			$encrypt_raw = strtolower( trim( (string) $encrypt_raw ) );
			if ( in_array( $encrypt_raw, array( 'yes', 'true', '1' ), true ) ) {
				$hash_input = 'crypt ' . $hash_input;
			}

			// MD5 the assembled input, then base64url-encode it (matching embed-key.js).
			$binary_hash = md5( $hash_input, true );
			$base64      = base64_encode( $binary_hash );

			// URL-safe base64 without padding.
			return rtrim( strtr( $base64, '+/', '-_' ), '=' );
		}

	public function wpstream_return_event_settings($product_id){
			// Compatibility delegate: storage keys, legacy mode interpretation and
			// one-time default snapshots belong to the Channel Settings Module.
			$state = $this->main->channel_settings->read( (int) $product_id );
			return $state['options'];
	    }

	/**
	* Deprecated misspelled alias of wpstream_return_event_settings().
	*
	* Kept because the method is public and may be called by external code.
	*
	* @deprecated 4.13.4 Use wpstream_return_event_settings() instead.
	*/
	public function wpestream_return_event_settings($product_id){
			return $this->wpstream_return_event_settings( $product_id );
	    }

	public function wpstream_live_event_player($channel_id,$poster_show=''){
	        // Player dependencies (full stack plus the custom controls layer).
	        wpstream_enqueue_player_assets();
			wp_enqueue_script( 'wpstream-player-controls' );

		    // Resolve the configured player skin/theme for this channel.
		    $player_theme = $this->wpstream_get_player_theme( $channel_id );
	        // Unique-ish id used to disambiguate multiple players on one page.
	        $now                =   time().rand(0,1000000);
	        $overlay_video_div_id = "random_id_".$now;
	        // print '<div id="'.esc_attr($overlay_video_div_id).'" class="vjs-title-overlay wpstream-video-title-overlay">'.esc_html__('Playing:','wpstream').' '.get_the_title($channel_id).'</div>';


	        // Poster thumbnail, streaming username, and autoplay default.
	        $poster_thumb       =   $this->poster_url( $channel_id, 'small' );
	        $usernamestream     =   esc_html ( wpstream_get_api_username() );
	        $autoplay           =   true;

	        // Effective per-event / global settings (autoplay, mute, view count, encryption...).
	        $event_settings     =   $this->wpstream_return_event_settings($channel_id);

	        // Live status via the channel-state owner (45s cache, Baker on miss,
	        // fresh-data meta sync — all inside Wpstream_Channel_State).
	        $state = Wpstream_Channel_State::for_channel( $channel_id, 'wpstream_live_event_player_note' );

	        // Playback URL / stats / chat URIs, populated only when the channel is
	        // live (playable: status active AND a playback URL present).
	        $hls_playback_url   =   '';
	        $live_conect_views  =   '';

	        if ( $state->is_live() ) {
	            $hls_playback_url  = $state->playback_url();
	            $live_conect_views = $state->stats_url();
	            $chat_url          = $state->chat_url();
	        }

		    // Optional pre-roll trailer; when present it replaces the poster.
		    $video_trailer     = $this->trailer_url( $channel_id );
		    $has_trailer_class = '';
		    if ( '' !== $video_trailer ) {
			    $poster_data       = ''; // cancel poster for theme
			    $has_trailer_class = 'wpstream_theme_player_has_trailer';
		    }

	        // Honour the "autoplay off" event setting.
	        if(isset($event_settings['autoplay']) && intval($event_settings['autoplay'])==0){
	            $autoplay=false;
	        }

	        // Bootstrap data-* values consumed by wpstream-player-bootstrap.js.
	        $bootstrap_is_muted = ( isset( $event_settings['mute'] ) && intval( $event_settings['mute'] ) === 1 );
	        // Content / stats / chat URIs passed to the player as data attributes.
	        $live_content_uri = isset( $hls_playback_url ) ? trim( $hls_playback_url ) : '';
	        $live_stats_uri   = isset( $live_conect_views ) ? trim( $live_conect_views ) : '';
	        $live_chat_uri    = isset( $chat_url ) ? trim( $chat_url ) : '';
	        // Element ids for the trailer play/mute/unmute buttons.
	        $play_trailer_button_element_id   = 'wpstream_live_video_play_trailer_btn_' . $now;
	        $mute_trailer_button_element_id   = 'wpstream_live_video_mute_trailer_btn_' . $now;
	        $unmute_trailer_button_element_id = 'wpstream_live_video_unmute_trailer_btn_' . $now;



		    // Nonce for the status-poll endpoint (used by the legacy player path).
		    $player_nonce = wp_create_nonce( 'wpstream_player_check_status_nonce' );

			// New iframe player is keyed off the presence of an embedUrl meta value.
			$live_channel_embed_url = get_post_meta( $channel_id, 'embedUrl', true );

			// if embedUrl is set, we are using the new player
			if ( !$live_channel_embed_url ) {
				// Legacy Video.js path: emit the wrapper carrying all bootstrap data-* attributes.
				echo '<div class="wpstream_live_player_wrapper function_wpstream_live_event_player" data-now="' . $now . '" data-me="' . esc_attr( $usernamestream ) . '" data-product-id="' . $channel_id . '" id="wpstream_live_player_wrapper' . $now . '" data-nonce="' . $player_nonce . '" data-wpstream-bootstrap="live" data-instance-id="wpstream-live-' . esc_attr( $now ) . '" data-video-element-id="' . esc_attr( $now ) . '" data-title-overlay-element-id="' . esc_attr( $overlay_video_div_id ) . '" data-content-url="' . esc_attr( $live_content_uri ) . '" data-stats-uri="' . esc_attr( $live_stats_uri ) . '" data-chat-url="' . esc_attr( $live_chat_uri ) . '" data-trailer-url="' . esc_attr( $video_trailer ) . '" data-autoplay="' . ( $autoplay ? '1' : '0' ) . '" data-muted="' . ( $bootstrap_is_muted ? '1' : '0' ) . '" data-play-trailer-button-element-id="' . esc_attr( $play_trailer_button_element_id ) . '" data-mute-trailer-button-element-id="' . esc_attr( $mute_trailer_button_element_id ) . '" data-unmute-trailer-button-element-id="' . esc_attr( $unmute_trailer_button_element_id ) . '" > ';

				// Show the live viewer count unless the setting explicitly disables it.
				$show_viewer_count = (
					( isset( $event_settings['view_count'] ) && intval( $event_settings['view_count'] ) == 1 )
					|| ! isset( $event_settings['view_count'] )
				);

				// Container the JS fills with the live viewer count.
				echo '<div id="wpestream_live_counting" class="wpestream_live_counting" data-showviewercount="' . ( $show_viewer_count ? '1' : '0' ) . '"></div>';

				// Show the "not live" overlay only while there is no playback URL yet.
				$show_wpstream_not_live_mess = ' style="display:none;" ';
				if ( trim( $hls_playback_url ) == '' ) {

					$show_wpstream_not_live_mess = '';
				}

				// Default overlay carrying the configurable "we are not live" message.
				$not_live_html = '<div class="wpstream_not_live_mess" ' . $show_wpstream_not_live_mess . ' style="display: none">
						<div class="wpstream_not_live_mess_back"></div>
						<div class="wpstream_not_live_mess_mess">' . esc_html( wpstream_not_live_message( $channel_id ) ) . '</div>
					</div>';
				// One-release shim: themes that still define the legacy section function
				// keep winning until they move to the wpstream_not_live_html filter. The
				// bundled framework defines it AND registers on the filter, so its section
				// is built twice until the shim goes — harmless (state is cached).
				if ( function_exists( 'wpstream_theme_not_live_section' ) ) {
					$not_live_html = (string) wpstream_theme_not_live_section( $channel_id );
				}

				/**
				 * Filter the "not live" overlay rendered inside the in-page live player.
				 *
				 * The default markup is already escaped; callbacks own the escaping of
				 * anything they add. Replaces the `wpstream_theme_not_live_section()`
				 * theme sniff, which is honoured for one more release.
				 *
				 * @since 4.14.0
				 *
				 * @param string                 $html       Overlay markup.
				 * @param int                    $channel_id Channel post ID.
				 * @param Wpstream_Channel_State $state      Current channel state (status, playback URL).
				 */
				print apply_filters( 'wpstream_not_live_html', $not_live_html, intval( $channel_id ), $state );


				// Build the poster attribute; suppressed when caller passes 'no'.
				$poster_data = '';
				if ( '' !== $poster_thumb ) {
					$poster_data = ' poster="' . esc_url( $poster_thumb ) . '" ';
				}
				if ( $poster_show == 'no' ) {
					$poster_data = '';
				}

				// Start muted when the event setting requests it.
				$is_muted = false;
				if ( isset( $event_settings['mute'] ) && intval( $event_settings['mute'] ) == 1 ) {
					$is_muted = true;
				}
				// override $is_muted and $autoplay here - for testing
				// $autoplay = true;
				// $is_muted = false;

				// Translate the autoplay/muted booleans into HTML <video> attributes.
				$autoplay_str = $autoplay ? 'autoplay' : '';
				$is_muted_str = $is_muted ? 'muted' : '';

				// override trailer url here - for testing
				// $video_trailer = '';
				// $video_trailer = '/wp-content/uploads/2023/10/production-ID_4608975.mp4';
				// $video_trailer = '/wp-content/uploads/2023/10/ultrawide.mp4';


				// Watermark/logo placement classes applied to the <video> element.
				$player_logo_position_data       = $this->wpstream_get_player_logo_data( $channel_id );
				$player_logo_position            = $player_logo_position_data['player_logo_position'];
				$player_logo_position_class      = $player_logo_position_data['player_logo_position_class'];
				$player_logo_horizontal_position = $player_logo_position_data['player_logo_horizontal_position'];
	//			echo'
	//				<div class="wpstream-video-container">
	//					<div id="wpstream-pre-load-spinner" class="wpstream-pre-load-spinner"></div>
	//					<video id="wpstream-video'.$now.'"     '.$poster_data.'  class="video-js vjs-default-skin  vjs-fluid vjs-wpstream ' . esc_attr($has_trailer_class) . ' ' . $player_theme . ' ' . $player_logo_position_class . ' ' . $player_logo_horizontal_position . '" playsinline="true" '.$is_muted_str." ".$autoplay_str.'>
	//					</video>
	//				</div>';
				// Pre-load spinner shown until the player is ready.
				echo '<div class="wpstream-pre-load-spinner"></div>';
				// The Video.js element itself, with skin/logo classes and autoplay/muted attributes.
				echo '
						<video id="wpstream-video' . $now . '"     ' . $poster_data . '  class="video-js vjs-default-skin  vjs-fluid vjs-wpstream ' . esc_attr( $has_trailer_class ) . ' ' . $player_theme . ' ' . $player_logo_position_class . ' ' . $player_logo_horizontal_position . '" playsinline="true" ' . $is_muted_str . " " . $autoplay_str . '>

	                </video>';
				// When a trailer exists, render its play/mute/unmute button controls (SVG icons).
				if ( $video_trailer ) {
					print '<div class="wpstream_theme_trailer_wrapper">';
					print '<div id="' . esc_attr( $play_trailer_button_element_id ) . '" style="display: none;" class="wpstream_video_on_demand_play_trailer">
	                    <svg width="30" height="24" viewBox="0 0 30 24" fill="none" xmlns="http://www.w3.org/2000/svg">
	                        <path fill-rule="evenodd" clip-rule="evenodd" d="M26.6667 1.5H3.33337C2.50495 1.5 1.83337 2.17157 1.83337 3V21C1.83337 21.8284 2.50495 22.5 3.33338 22.5H26.6667C27.4951 22.5 28.1667 21.8284 28.1667 21V3C28.1667 2.17157 27.4951 1.5 26.6667 1.5ZM3.33337 0C1.67652 0 0.333374 1.34315 0.333374 3V21C0.333374 22.6569 1.67652 24 3.33338 24H26.6667C28.3236 24 29.6667 22.6569 29.6667 21V3C29.6667 1.34315 28.3236 0 26.6667 0H3.33337ZM4.83337 4C4.55723 4 4.33337 4.22386 4.33337 4.5V6.16667C4.33337 6.44281 4.55723 6.66667 4.83337 6.66667H6.50004C6.77618 6.66667 7.00004 6.44281 7.00004 6.16667V4.5C7.00004 4.22386 6.77618 4 6.50004 4H4.83337ZM23.5 4C23.2239 4 23 4.22386 23 4.5V6.16667C23 6.44281 23.2239 6.66667 23.5 6.66667H25.1667C25.4428 6.66667 25.6667 6.44281 25.6667 6.16667V4.5C25.6667 4.22386 25.4428 4 25.1667 4H23.5ZM4.33337 11.167C4.33337 10.8909 4.55723 10.667 4.83337 10.667H6.50004C6.77618 10.667 7.00004 10.8909 7.00004 11.167V12.8337C7.00004 13.1098 6.77618 13.3337 6.50004 13.3337H4.83337C4.55723 13.3337 4.33337 13.1098 4.33337 12.8337V11.167ZM23.5001 10.667C23.224 10.667 23.0001 10.8909 23.0001 11.167V12.8337C23.0001 13.1098 23.224 13.3337 23.5001 13.3337H25.1668C25.4429 13.3337 25.6668 13.1098 25.6668 12.8337V11.167C25.6668 10.8909 25.4429 10.667 25.1668 10.667H23.5001ZM4.33337 17.833C4.33337 17.5569 4.55723 17.333 4.83337 17.333H6.50004C6.77618 17.333 7.00004 17.5569 7.00004 17.833V19.4997C7.00004 19.7758 6.77618 19.9997 6.50004 19.9997H4.83337C4.55723 19.9997 4.33337 19.7758 4.33337 19.4997V17.833ZM23.5001 17.333C23.224 17.333 23.0001 17.5569 23.0001 17.833V19.4997C23.0001 19.7758 23.224 19.9997 23.5001 19.9997H25.1668C25.4429 19.9997 25.6668 19.7758 25.6668 19.4997V17.833C25.6668 17.5569 25.4429 17.333 25.1668 17.333H23.5001ZM19.0677 13.0997L13.4077 16.5087C13.0434 16.7281 12.6092 16.7094 12.2661 16.5091C11.9218 16.3081 11.6666 15.9224 11.6666 15.4086V8.59072C11.6666 8.07698 11.9218 7.69125 12.2661 7.49026C12.6092 7.28999 13.0434 7.27126 13.4077 7.49064L19.0677 10.8996C19.8663 11.3805 19.8663 12.6188 19.0677 13.0997Z"/>
	                    </svg>
	                    ' . esc_html__( 'Play Trailer', 'wpstream' ) . '</div>';
					print '<div id="' . esc_attr( $mute_trailer_button_element_id ) . '" style="display: none;" class="wpstream_video_on_demand_mute_trailer">
	                    <svg width="37" height="36" viewBox="0 0 37 36" fill="none" xmlns="http://www.w3.org/2000/svg">
	                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.32143 10.0789H8.69499L18.8964 0L21.1428 0.921053V35.1316L18.8964 36L8.69499 25.8684H1.32143L0 24.5526V11.3947L1.32143 10.0789ZM10.175 23.6842L18.5 31.9474V4.10526L10.175 12.3158L9.24999 12.7105H2.64286V23.2368H9.24999L10.175 23.6842ZM37 17.9737C37.0069 22.2216 35.5329 26.3401 32.8295 29.6263L30.9478 27.7579C33.1613 24.9734 34.3629 21.5249 34.3571 17.9737C34.3571 14.2895 33.0885 10.8974 30.9637 8.21053L32.8454 6.34211C35.5382 9.62494 37.0062 13.735 37 17.9737ZM31.7143 17.9737C31.7193 20.8255 30.7895 23.6011 29.0661 25.8789L27.1738 23.9947C28.4127 22.2295 29.0752 20.1272 29.0714 17.9737C29.0751 15.8287 28.4174 13.7344 27.1871 11.9737L29.0793 10.0895C30.7338 12.2868 31.7143 15.0158 31.7143 17.9737ZM26.4286 17.9737C26.4286 19.4842 26.0057 20.8947 25.2657 22.0947L23.3126 20.1526C23.6249 19.4729 23.7876 18.7345 23.7899 17.9869C23.7922 17.2394 23.634 16.5001 23.3258 15.8184L25.2789 13.8737C26.0083 15.0684 26.4286 16.4737 26.4286 17.9737Z" fill="white"/>
	                    </svg>

	                    </div>';
					print '<div id="' . esc_attr( $unmute_trailer_button_element_id ) . '" style="display: none;" class="wpstream_video_on_demand_unmute_trailer">
	                    <svg width="33" height="32" viewBox="0 0 33 32" fill="none" xmlns="http://www.w3.org/2000/svg">
	                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.15625 8.85688H7.60813L16.5344 0L18.5 0.809375V30.8719L16.5344 31.635L7.60813 22.7319H1.15625L0 21.5756V10.0131L1.15625 8.85688ZM8.90313 20.8125L16.1875 28.0738V3.6075L8.90313 10.8225L8.09375 11.1694H2.3125V20.4194H8.09375L8.90313 20.8125ZM30.5967 11.3127L32.2316 12.9477L28.2287 16.9506L32.2316 20.9559L30.5967 22.5908L26.5938 18.5856L22.5885 22.5908L20.9536 20.9559L24.9588 16.9506L20.9513 12.95L22.5862 11.3151L26.5938 15.3157L30.5967 11.3127Z" fill="white"/>
	                    </svg>
	                    </div>';
					print '</div>';
				}
				// Close the legacy player wrapper.
				print '</div>';


				// Live player bootstrap is handled by wpstream-player-bootstrap.js.
			} else {
				// New iframe player path: assemble the /player/live iframe URL and query args.
				$live_channel_frame_base               = WPSTREAM_PLAYER . "/player/live?";
				$live_channel_id                       = get_post_meta( $channel_id, 'channelId', true );

				// Global channel options drive session encryption, domain lock, and autoplay.
				$local_event_options                   = $this->wpstream_return_event_settings( $channel_id );
				$wpstream_session_encryption           = false;
				$wpstream_live_channel_lock_to_website = false;
				$wpstream_live_channel_autoplay        = false;

				// Session encryption on?
				if ( isset( $local_event_options['ses_encrypt'] ) && intval( $local_event_options['ses_encrypt'] ) == 1 ) {
					$wpstream_session_encryption = true;
				}
				// Lock playback to this website's origin?
				if ( isset( $local_event_options['domain_lock'] ) && intval( $local_event_options['domain_lock'] ) == 1 ) {
					$wpstream_live_channel_lock_to_website = true;
				}
				// Autoplay on?
				if ( isset( $local_event_options['autoplay'] ) && intval( $local_event_options['autoplay'] ) == 1 ) {
					$wpstream_live_channel_autoplay = true;
				}

				// When session encryption is on, build the validate-session URL and expose it to the controls JS.
				// The URL is bound to this channel's post ID so a session issued for another
				// product cannot pass verification here; no nonce is localized (the page may be
				// served from a cache, and issuance is entitlement-gated server-side instead).
				// Skipped entirely on non-publicly-routable hosts (local/dev sites): the
				// presence backend could never fetch the URL, and advertising it makes the
				// hosted player refuse to start (bootstrap 401 VALIDATION_UNAVAILABLE).
				$wpstream_live_channel_validate_url = '';
				if ( $wpstream_session_encryption && $this->playback_session->wpstream_playback_session_validation_available() ) {
					$wpstream_live_channel_validate_url = esc_url_raw(
						apply_filters(
							'wpstream_live_channel_validate_playback_session_url',
							$this->playback_session->wpstream_get_default_validate_playback_session_url( (int) $channel_id ),
							$live_channel_id
						)
					);
					wp_localize_script(
						'wpstream-player-controls',
						'wpstreamLiveIframeSessionApi',
						array(
							'requirePlaybackSession'            => true,
							'productId'                         => (int) $channel_id,
							'ajaxUrl'                           => admin_url( 'admin-ajax.php' ),
						)
					);
				}

				// Re-read the options and derive integer flags used in the iframe query args.
				$local_event_options                   = $this->wpstream_return_event_settings( $channel_id );
				$wpstream_live_channel_lock_to_website = 0;
				$wpstream_live_channel_encrypt         = 0;
				$wpstream_live_channel_abr             = 0;
				$wpstream_live_channel_muted           = 0;

				// Domain lock flag.
				if ( isset( $local_event_options['domain_lock'] ) && intval( $local_event_options['domain_lock'] ) == 1 ) {
					$wpstream_live_channel_lock_to_website = 1;
				}
				// HLS encryption flag.
				if ( isset( $local_event_options['encrypt'] ) && intval( $local_event_options['encrypt'] ) == 1 ) {
					$wpstream_live_channel_encrypt = 1;
				}
				// Adaptive bitrate flag.
				if ( isset( $local_event_options['adaptive_bitrate'] ) && intval( $local_event_options['adaptive_bitrate'] ) == 1 ) {
					$wpstream_live_channel_abr = 1;
				}
				// Start-muted flag.
				if ( isset( $local_event_options['mute'] ) && intval( $local_event_options['mute'] ) == 1 ) {
					$wpstream_live_channel_muted = 1;
				}

				// Frame-ancestor origin, only set when playback is locked to this website.
				$live_channel_embed_ancestor = '';
				if ( $wpstream_live_channel_lock_to_website ) {
					$live_channel_embed_ancestor = esc_url_raw(
						apply_filters(
							'wpstream_live_channel_embed_ancestor',
							$this->wpstream_get_site_origin_for_embed(),
							$live_channel_id
						)
					);
				}

				// Mint the embed key for the live channel from the same inputs as the iframe URL.
				$wpstream_live_channel_embed_key = $this->wpstream_generate_player_embed_key(
					$live_channel_id,
					$wpstream_live_channel_validate_url,
					$live_channel_embed_ancestor,
					$wpstream_live_channel_encrypt ? 'yes' : ''
				);
				// Fall back to a stored embed_key if the generated one is empty.
				if ( '' === $wpstream_live_channel_embed_key ) {
					$wpstream_live_channel_embed_key = (string) get_post_meta( $channel_id, 'embed_key', true );
				}
				// Separate embed key for the trailer iframe.
				$wpstream_live_channel_trailer_embed_key = $this->wpstream_generate_player_embed_key(
					$video_trailer,
					$wpstream_live_channel_validate_url,
					$live_channel_embed_ancestor,
					$wpstream_live_channel_encrypt ? 'yes' : ''
				);

				// Map the Video.js theme name to a bare skin slug the iframe player understands.
				$wpstream_player_iframe_skin_slug = '';
				if ( preg_match( '/^vjs-theme-(city|fantasy|forest|sea)$/', $player_theme, $vod_iframe_skin_m ) ) {
					$wpstream_player_iframe_skin_slug = $vod_iframe_skin_m[1];
				}
				// Viewer-count badge on unless the setting disables it.
				$wpstream_show_viewer_count = (
					( isset( $event_settings['view_count'] ) && intval( $event_settings['view_count'] ) == 1 )
					|| ! isset( $event_settings['view_count'] )
				);

				// Poster image and logo/watermark for the iframe query.
				$wpstream_poster_image = $this->poster_url( $channel_id, 'full' );

				$wpstream_player_logo_data       = $this->wpstream_get_player_logo_data( $channel_id );
				$wpstream_player_logo_image      = $wpstream_player_logo_data['player_logo_src'];

				// Assemble the live iframe query args, dropping null/empty values.
				$live_channel_query_args = array_filter(
					array(
						'channel'                    => $live_channel_id,
						'posterImage'                => $wpstream_poster_image,
						'embedKey'                   => $wpstream_live_channel_embed_key,
						'autoplay'                   => $wpstream_live_channel_autoplay ? true : '',
						'startMuted'                 => (bool) $wpstream_live_channel_muted,
						'viewerCountBadge'           => $wpstream_show_viewer_count ? true : '',
						'encrypt'                    => $wpstream_live_channel_encrypt ? true : '',
						'abr'                        => $wpstream_live_channel_abr ? true : '',
						'validatePlaybackSessionUrl' => $wpstream_live_channel_validate_url,
						'embedAncestor'              => $live_channel_embed_ancestor,
						'skin'                       => $wpstream_player_iframe_skin_slug,
						'logoImage'                  => $wpstream_player_logo_image,
						'logoPosition'               => $wpstream_player_logo_image ? $wpstream_player_logo_data['player_logo_position'] : '',
						'logoOpacity'                => $wpstream_player_logo_image ? $wpstream_player_logo_data['player_logo_opacity'] : '',
						'isThemeActive'              => get_template() == 'hello-wpstream',
					),
					static function ( $v ) {
						return $v !== null && $v !== '';
					}
				);
				/** This filter is documented in filter_iframe_query_args(). */
				$live_channel_query_args = $this->filter_iframe_query_args( 'wpstream_live_iframe_query_args', $live_channel_query_args, $channel_id );

				// Assemble the trailer iframe query args, dropping null/empty values.
				$live_channel_trailer_iframe_query_args = array_filter(
					array(
						'video'                      => $video_trailer,
						'embedKey'                   => $wpstream_live_channel_trailer_embed_key,
						'skin'                       => $wpstream_player_iframe_skin_slug,
						'startMuted'                 => (bool) $wpstream_live_channel_muted,
						'encrypt'                    => $wpstream_live_channel_encrypt ? true : '',
						'validatePlaybackSessionUrl' => $wpstream_live_channel_validate_url,
						'embedAncestor'              => $live_channel_embed_ancestor,
					),
					static function ( $v ) {
						return $v !== null && $v !== '';
					}
				);

				// Emit the content iframe pointing at /player/live with the assembled query.
				echo '<div class="wpstream_player_iframe_wrap">';
				echo '<iframe id="playerFrame"
								class="wpstream_live_channel_iframe"
								title="' . esc_attr__( 'Embedded content', 'wpstream' ) . '"
								src="' . esc_url( add_query_arg( $live_channel_query_args, $live_channel_frame_base ) ) . '"
								data-wpstream-frame-role="content"
								allowfullscreen
								allow="autoplay; fullscreen">
						</iframe>';
				// Add the hidden trailer iframe only when a trailer exists and the WpStream theme is active.
				if ( '' !== $video_trailer && get_template() === 'hello-wpstream' ) {
					echo '<iframe id="playerFrameTrailer"
								class="wpstream_live_channel_iframe wpstream_live_channel_iframe_trailer"
								title="' . esc_attr__( 'Embedded trailer content', 'wpstream' ) . '"
								src="' . esc_url( add_query_arg( $live_channel_trailer_iframe_query_args, WPSTREAM_PLAYER . "/player/vod?" ) ) . '"
								data-wpstream-frame-role="trailer"
								allowfullscreen
								allow="autoplay; fullscreen"
								style="display:none;"
								aria-hidden="true"
								tabindex="-1">
							</iframe>';
				}
				echo '</div>';

			}

	    }

	/**
	 * Resolve the HLS manifest URL for a VOD file.
	 *
	 * Baker's video/info answer is cached per video name for 5 minutes; hit or
	 * miss, the DRM key and embed data it carries are stored on the requesting
	 * product. Returns '' when nothing playable is available.
	 *
	 * @param string $video_name Storage name of the video.
	 * @param int    $product_id Post the video belongs to.
	 * @return string HLS URL or ''.
	 */
	public function  wpstream_request_video_on_demand_hls_player($video_name,$product_id){
	            // No video name means nothing to request.
	            if($video_name==''){
	                return '';
	            }

	            // Baker's video/info answer is cached per video for 5 minutes; Baker is asked only on a miss.
	            $transient_name        = 'wpstream_video_on_demand_' . $video_name;
	            $curl_response_decoded = get_transient( $transient_name );

	            if ( ! is_array( $curl_response_decoded ) ) {
	                // Endpoint for video info; the auth token is injected by the transport.
	                $url            =   'video/info';

	                // Resolve the canonical site origin. Request context is not
	                // authoritative here: wp-cli, cron and TLS-terminating proxies
	                // can make is_ssl() false for a site configured as HTTPS.
	                $local_event_options    =   get_option('wpstream_user_streaming_global_channel_options') ;
	                $domain                 =   parse_url ( get_site_url() );
	                $domain_scheme          =   isset( $domain['scheme'] ) ? $domain['scheme'] : 'http';

	                // With VOD domain-lock on, restrict CORS to this site's origin; otherwise allow any.
	                $wpstream_vod_domain_lock = intval( get_option('wpstream_vod_domain_lock','') ) ;
	                $corsorigin='*';
	                if($wpstream_vod_domain_lock !== 0 ){
	                    $corsorigin=$domain_scheme.'://'.$domain['host'];
	                }

	                // Request HLS encryption when the VOD encrypt option is enabled.
	                $is_encrypt="false";
	                $wpstream_vod_encrypt = intval( get_option('wpstream_vod_encrypt','') ) ;
	                if( intval( $wpstream_vod_encrypt ) ==1 ){
	                    $is_encrypt="true";
	                }

	                // Prefix the encryption-key requests route back through this site (?wpstream_voddrm=).
	                $hlsKeysUrlPrefix    =  get_site_url().'?wpstream_voddrm=';
	                $encrypt             =  $is_encrypt;
	                $debugDrm            =  false;

	                // Body of the API request.
	                $curl_post_fields=array(
	                    'name'              =>  $video_name,
	                    'corsOrigin'        =>  $corsorigin,
	                    'encryptHls'        =>  $encrypt,
	                    'hlsKeysUrlPrefix'  =>  $hlsKeysUrlPrefix,
	                    'debugDrm'          =>  $debugDrm,
		                'embed'             => true,
	                );


	                // Perform the API call; a WP_Error (not-connected included) means no playable URL.
	                $curl_response_decoded = $this->main->wpstream_live_connection->authorized_request( $url, $curl_post_fields );
	                if ( is_wp_error( $curl_response_decoded ) ) {
	                    $curl_response_decoded = array();
	                }

	                // API reported failure: nothing to cache, no URL to return.
	                if ( empty( $curl_response_decoded['success'] ) || empty( $curl_response_decoded['hlsUrl'] ) ) {
	                    return '';
	                }

	                /**
	                 * Filter how long a VOD's HLS answer from the cloud is cached, in seconds.
	                 *
	                 * Clamped to [1, 3600]. The transient name and payload are frozen.
	                 *
	                 * @since 4.14.0
	                 *
	                 * @param int $seconds Lifetime; default 300.
	                 */
	                $ttl = wpstream_tunable( 'wpstream_vod_hls_cache_ttl', 300, HOUR_IN_SECONDS );
	                // Cache the whole answer so a later hit can apply the same product meta below.
	                set_transient( $transient_name, $curl_response_decoded, $ttl );
	            }

	            // Store or clear the HLS decryption key/index on the requesting product.
	            if( isset($curl_response_decoded['hlsDecryptionKey']) && isset($curl_response_decoded['hlsDecryptionKeyIndex']) ){
	                update_post_meta($product_id,'hlsDecryptionKey',$curl_response_decoded['hlsDecryptionKey']);
	                update_post_meta($product_id,'hlsDecryptionKeyIndex',$curl_response_decoded['hlsDecryptionKeyIndex']);
	            }else{
	                delete_post_meta($product_id,'hlsDecryptionKey');
	                delete_post_meta($product_id,'hlsDecryptionKeyIndex');
	            }

	            // When the API returns new-player embed data, persist it as post meta.
	            if ( isset( $curl_response_decoded['video'] ) &&
	                isset( $curl_response_decoded['embedKey'] ) &&
	                isset( $curl_response_decoded['embedUrl'] )
	            ) {
	                update_post_meta( $product_id, 'wpstream_vod_video_data', trim( (string) $curl_response_decoded['video'] ) );
	                update_post_meta( $product_id, 'wpstream_vod_embed_key', trim( (string) $curl_response_decoded['embedKey'] ) );
	                update_post_meta( $product_id, 'wpstream_vod_embed_url', trim( (string) $curl_response_decoded['embedUrl'] ) );
	            }

	            return $curl_response_decoded['hlsUrl'];

	        }

	public function wpstream_get_player_theme( $channel_id = null ) {
				// Configured theme name and whether this is a streamify (basic) user.
				$player_theme = get_option('wpstream_video_player_theme');
				$is_streamify_user = $this->wpstream_is_streamify_user( $channel_id );
				// Apply the theme only for non-streamify users with a theme set.
				if ( !empty($player_theme) && !$is_streamify_user ) {
					$this->wpstream_enqueue_player_theme_style( $player_theme );
					return 'vjs-theme-' . $player_theme;
				}

				// No theme (default skin or streamify user).
				return '';
			}

	public function wpstream_enqueue_player_theme_style( $player_theme ) {
				// The 'default' skin ships with Video.js, so only load extra theme CSS.
				// Only skins we bundle may be enqueued; anything else falls back to default.
				$bundled_themes = array( 'city', 'forest', 'fantasy', 'sea' );
				if ( in_array( $player_theme, $bundled_themes, true ) ) {
					wp_enqueue_style( 'videojs-theme-' . $player_theme, plugin_dir_url( __DIR__ ) . 'public/css/vendor/videojs-theme-' . $player_theme . '.css', array(), '1.0.1' );
				}
			}

	private function wpstream_get_player_logo_data( $product_id ): array {
				// Configured logo corner, e.g. "top-left".
				$player_logo_position = (string) get_option( 'wpstream_player_logo_position', 'top-left' );

				// Logo image URL and its opacity (stored as a percentage, returned as 0-1).
				$player_logo_src = $this->wpstream_get_video_player_logo( $product_id );
				$player_logo_opacity = intval( esc_html( get_option('wpstream_player_logo_opacity','100') ) ) / 100;

				/**
				 * Filter the player logo placement.
				 *
				 * @since 4.14.0
				 *
				 * @param array $data {
				 *     @type string $player_logo_src      Logo URL (already through `wpstream_player_logo`).
				 *     @type string $player_logo_position Corner, e.g. `top-left`.
				 *     @type float  $player_logo_opacity  0..1.
				 * }
				 * @param int   $product_id Streaming Content post ID.
				 */
				$data = (array) apply_filters(
					'wpstream_player_logo_data',
					array(
						'player_logo_src'      => $player_logo_src,
						'player_logo_position' => $player_logo_position,
						'player_logo_opacity'  => $player_logo_opacity,
					),
					intval( $product_id )
				);

				// Derive the legacy player's corner classes from the (possibly filtered) position.
				$position_class = '';
				$horizontal     = '';
				$parts          = explode( '-', (string) ( $data['player_logo_position'] ?? '' ) );
				if ( isset( $parts[0], $parts[1] ) ) {
					$position_class = 'logo-' . $parts[0];
					$horizontal     = 'logo-' . $parts[1];
				}

				// Bundle the logo image, corner, derived classes, and opacity.
				return array(
					'player_logo_src'                 => (string) ( $data['player_logo_src'] ?? '' ),
					'player_logo_position'            => (string) ( $data['player_logo_position'] ?? '' ),
					'player_logo_position_class'      => $position_class,
					'player_logo_horizontal_position' => $horizontal,
					'player_logo_opacity'             => (float) ( $data['player_logo_opacity'] ?? 1 ),
				);
			}

	public function wpstream_video_on_demand_player_uri_request($product_id){
	                // Base Video.js data-setup attribute.
	                $wpstream_data_setup    =   '  data-setup="{}" ';

	                /* free_video_type
	                 * 1 - free live channel
	                 * 2 - free video encrypted
	                 * 3 - free video -not encrypted
	                 */

	                // Post type and the stored "free video type" classification.
	                $post_type              =   get_post_type($product_id);
	                $free_video_type        =   intval( get_post_meta($product_id, 'wpstream_product_type', true));
	                $video_path_final       =   '';
	                $video_type             =   '';
	                if( (  $post_type  =='wpstream_product_vod' && $free_video_type==2 ) || get_post_type($product_id)=='product' ){

	                    /*
	                    * IF vide is encrypted-  readed from vod,streaner
	                    *
	                    */

	                    // Encrypted / paid VOD: fetch an HLS manifest from the cloud API.
	                    $video_type         =   'application/x-mpegURL';
	                    $video_path         =   get_post_meta($product_id,'_movie_url',true);
	                    if(get_post_type($product_id)=='wpstream_product_vod'){
	                        // Free-VOD post type stores the source under a different meta key.
	                        $video_path =    esc_html(get_post_meta($product_id, 'wpstream_free_video', true));
	                    }
	                    $video_path_final = $this->wpstream_request_video_on_demand_hls_player($video_path,$product_id);


	                }else if(   $post_type =='wpstream_product_vod'  && $free_video_type==3 ){

	                    /* Video is unecrypted - read from local or youtube / vimeo
	                    */

	                    // Unencrypted VOD: use the stored external URL and load the YouTube tech.
	                    $video_type         =   'video/mp4';
	                    $video_path_final=esc_html(get_post_meta($product_id, 'wpstream_free_video_external', true));
	                    wp_enqueue_script('youtube.min');
	                }

	            // Package the resolved source details for the caller.
	            $return_array                       =   array();
	            $return_array['video_path_final']   =   $video_path_final;
	            $return_array['wpstream_data_setup']=   $wpstream_data_setup;
	            $return_array['video_type']         =   $video_type;
	            $return_array['free_video_type']    =   $free_video_type;
	            $return_array['post_type']          =   $post_type ;
	            return $return_array;
	 }

	public function wpstream_video_on_demand_player($product_id){
				// Player dependencies (full stack plus the custom controls layer).
				wpstream_enqueue_player_assets();
		        wp_enqueue_script( 'wpstream-player-controls' );

				// Resolve the configured skin/theme and the VOD source details.
				$player_theme = $this->wpstream_get_player_theme( $product_id );

	            $uri_details        =   $this->wpstream_video_on_demand_player_uri_request($product_id);
	            $video_path_final   =   $uri_details['video_path_final'];
	            $wpstream_data_setup =  $uri_details['wpstream_data_setup'];
	            $video_type          =  $uri_details['video_type'];
	            // Unique-ish id for this player instance.
	            $now                =   time().rand(0,1000000);

	            // Render the optional title overlay via the theme action hook.
	            $overlay_video_div_id = "random_id_".$now;
	            $this->wpstream_render_vod_title_overlay(
	                $overlay_video_div_id,
	                get_the_title($product_id)
	            );

	            // Poster thumbnail and streaming username.
	            $poster_thumb           =   $this->poster_url( $product_id, 'small' );
	            $usernamestream         =   esc_html ( wpstream_get_api_username() );

	            // DRM decryption key/index stored earlier by the HLS request.
	            $hlsDecryptionKey       =   get_post_meta($product_id,'hlsDecryptionKey',true);
	            $hlsDecryptionKeyIndex  =   get_post_meta($product_id,'hlsDecryptionKeyIndex',true);


				// Quota/pack data used below to decide whether streaming is allowed.
				$pack = $this->main->quota_manager->get_live_quota_data( 'wpstream_video_on_demand_player' );



	            // Optional pre-roll trailer (one accessor, filterable).
	            $video_trailer            = $this->trailer_url( $product_id );

	            // override trailer setup here (for testing)
	            // $video_trailer = '/wp-content/uploads/2023/10/production-ID_4608975.mp4';
	            // $video_trailer = '/wp-content/uploads/2023/10/ultrawide.mp4';

	            // If the video is self hosted or external, we should let the user see it
	            // (type 3 = unencrypted; bypasses the quota check below).
	            $video_type = intval( get_post_meta($product_id, 'wpstream_product_type', true));


	            // Render the player only when the quota allows VOD streaming, or the video is unencrypted (type 3).
	            if ( $this->main->quota_manager->can_stream_vod( $pack ) || $video_type === 3 ) {

	                if($video_path_final==''){
	                    // Empty source: show a "missing video" notice, except for unencrypted VODs.
	                    if( $uri_details['post_type']=='wpstream_product_vod'  && $uri_details['free_video_type']==3 ){
	                    }else{
	                        print '<div class="wpstream_vod_notice">This video does not exist or it has been deleted!</div>';
	                    }

	                }

	                // Autoplay/mute defaults, overridden by the VOD options below.
	                // TODO (crerem) populate these from VOD settings
	                $autoplay = false;
	                $muted = false;

	                // Start muted when the VOD "start muted" option is on.
	                $wpstream_vod_start_muted   =   intval ( get_option('wpstream_vod_start_muted','') );
	                if($wpstream_vod_start_muted===1){
	                    $muted=true;
	                }
	                // Autoplay when the VOD autoplay option is on.
	                $wpstream_vod_autoplay      =   intval  ( get_option('wpstream_vod_autoplay','') );
	                if($wpstream_vod_autoplay===1){
	                    $autoplay=true;
	                }

	                // Poster attribute; dropped in favour of a trailer when one exists.
	                $poster_data = 'poster="'.esc_url($poster_thumb).'"';
	                $has_trailer_class='';
	                if('' !== $video_trailer){
	                    $poster_data=''; // cancel poster for theme
	                    $has_trailer_class='wpstream_theme_player_has_trailer';
	                }

		            // Logo/watermark image, position, and opacity for the player.
		            $player_logo_data                = $this->wpstream_get_player_logo_data( $product_id );
		            $player_logo_image               = $player_logo_data['player_logo_src'];
		            $player_logo_position            = $player_logo_data['player_logo_position'];
		            $player_logo_position_class      = $player_logo_data['player_logo_position_class'];
		            $player_logo_horizontal_position = $player_logo_data['player_logo_horizontal_position'];
		            $player_logo_opacity			 = $player_logo_data['player_logo_opacity'];

					// Captions file and the trailer/video control button element ids.
					$captionsUrl = get_post_meta( $product_id, 'wpstream_closed_captions_file', true );
		            $play_trailer_button_element_id = '' !== $video_trailer ? 'wpstream_video_on_demand_play_trailer_btn_' . $now : '';
	                $mute_trailer_button_element_id = '' !== $video_trailer ? 'wpstream_video_on_demand_mute_trailer_btn_' . $now : '';
	                $unmute_trailer_button_element_id = '' !== $video_trailer ? 'wpstream_video_on_demand_unmute_trailer_btn_' . $now : '';
		            $play_video_button_element_id = '' !== $video_trailer ? 'wpstream_video_on_demand_play_video_btn_' . $now : '';

					// VOD encryption and domain-lock options.
					$wpstream_vod_encrypt = intval( get_option( 'wpstream_vod_encrypt', '' ) );
					$wpstream_vod_lock_to_website = intval( get_option( 'wpstream_vod_domain_lock', '' ) );

					// Session encryption flag from the global channel options.
					$wpstream_session_encryption = 0;
		            $local_event_options         =   get_option('wpstream_user_streaming_global_channel_options') ;
		            if(isset($local_event_options['ses_encrypt']) && intval($local_event_options['ses_encrypt'])==1 ) {
			            $wpstream_session_encryption = 1;
		            }

			            /**
					 * Presence bootstrap expects validatePlaybackSessionUrl as an absolute HTTP(S) URL that returns JSON { success: true } when the playback session is valid — not the literals "true"/"false".
					 * Embed keys must be minted with the same inputs as the iframe query (video, validatePlaybackSessionUrl, embedAncestor, encrypt) per player embed-key.js.
					 *
					 * @see WPStream player embed-key.js and session-validation.js (verifyPlaybackSessionUrl).
					 */
					// With session encryption on, build the validate-session URL and expose the session API to the controls JS.
					// The URL is bound to this product's ID so a session issued for another
					// product cannot pass verification here; no nonce is localized (the page may
					// be served from a cache, and issuance is entitlement-gated server-side instead).
					// Skipped entirely on non-publicly-routable hosts (local/dev sites): the
					// presence backend could never fetch the URL, and advertising it makes the
					// hosted player refuse to start (bootstrap 401 VALIDATION_UNAVAILABLE).
					$vod_validate_url = '';
					if ( $wpstream_session_encryption && $this->playback_session->wpstream_playback_session_validation_available() ) {
						$vod_validate_url = esc_url_raw(
							apply_filters(
								'wpstream_vod_validate_playback_session_url',
								$this->playback_session->wpstream_get_default_validate_playback_session_url( (int) $product_id ),
								$product_id
							)
						);
						wp_localize_script(
							'wpstream-player-controls',
							'wpstreamVodIframeSessionApi',
							array(
								'requirePlaybackSession' => true,
								'productId'                => (int) $product_id,
								'ajaxUrl'                  => admin_url( 'admin-ajax.php' ),
							)
						);
					}

					// Frame-ancestor origin, only when VOD playback is locked to this website.
					$vod_embed_ancestor = '';
					if ( $wpstream_vod_lock_to_website ) {
						$vod_embed_ancestor = esc_url_raw(
							apply_filters(
								'wpstream_vod_embed_ancestor',
								$this->wpstream_get_site_origin_for_embed(),
								$product_id
							)
						);
					}

					// if there's "wpstream_vod_embed_url" set, it means that we use the new player
					$wpstream_vod_embed_url       = get_post_meta( $product_id, 'wpstream_vod_embed_url', true );

					// New-player iframe base URL and the stored video identifier.
					$vod_iframe_base              = WPSTREAM_PLAYER . "/player/vod?";
					$vod_iframe_video_raw         = (string) get_post_meta( $product_id, 'wpstream_vod_video_data', true );
					$vod_iframe_video             = trim( $vod_iframe_video_raw );
					// Strip a surrounding pair of single/double quotes from the stored value.
					if ( strlen( $vod_iframe_video ) >= 2 ) {
						$first_char = $vod_iframe_video[0];
						$last_char  = substr( $vod_iframe_video, -1 );
						$is_wrapped_in_double_quotes = '"' === $first_char && '"' === $last_char;
						$is_wrapped_in_single_quotes = "'" === $first_char && "'" === $last_char;
						if ( $is_wrapped_in_double_quotes || $is_wrapped_in_single_quotes ) {
							$vod_iframe_video = substr( $vod_iframe_video, 1, -1 );
						}
					}
					// Mint the VOD embed key from the same inputs used in the iframe URL.
		            $vod_iframe_embed_key = $this->wpstream_generate_player_embed_key(
						$vod_iframe_video,
						$vod_validate_url,
						$vod_embed_ancestor,
						$wpstream_vod_encrypt ? 'yes' : ''
					);
					// Fall back to a stored embed key when the generated one is empty.
					if ( '' === $vod_iframe_embed_key ) {
						$vod_iframe_embed_key = (string) get_post_meta( $product_id, 'wpstream_vod_embed_key', true );
					}
					// Separate embed key for the trailer iframe.
					$vod_iframe_trailer_embed_key = $this->wpstream_generate_player_embed_key(
						$video_trailer,
						$vod_validate_url,
						$vod_embed_ancestor,
						$wpstream_vod_encrypt ? 'yes' : ''
					);

					// Poster image and active theme (used for the isThemeActive flag).
					$vod_poster_image = $this->poster_url( $product_id, 'full' );
					$current_active_theme = wp_get_theme();

					// Map the Video.js theme name to a bare skin slug for the iframe player.
					$vod_iframe_skin_slug = '';
					if ( preg_match( '/^vjs-theme-(city|fantasy|forest|sea)$/', $player_theme, $vod_iframe_skin_m ) ) {
						$vod_iframe_skin_slug = $vod_iframe_skin_m[1];
					}

					// Assemble the VOD iframe query args, dropping null/empty values.
					$vod_iframe_query_args = array_filter(
						array(
							'video'                      => $vod_iframe_video,
							'posterImage'                => $vod_poster_image,
							'embedKey'                   => $vod_iframe_embed_key,
							// Honour the VOD "autoplay" option in the hosted player (it starts muted, as browsers require).
							'autoplay'                   => $autoplay ? '1' : '',
							'startMuted'                 => $muted ? '1' : '',
							'skin'                       => $vod_iframe_skin_slug,
							'encrypt'                    => $wpstream_vod_encrypt ? 'yes' : '',
							'validatePlaybackSessionUrl' => $vod_validate_url,
							'embedAncestor'              => $vod_embed_ancestor,
							'logoImage'                  => $player_logo_image,
							'logoPosition'               => $player_logo_image ? $player_logo_position : '',
							'logoOpacity'                => $player_logo_image ? $player_logo_opacity : '',
							'isThemeActive'              => $current_active_theme->get('Name') === 'Hello WPStream',
						),
						static function ( $v ) {
							return $v !== null && $v !== '';
						}
					);
					/** This filter is documented in filter_iframe_query_args(). */
					$vod_iframe_query_args = $this->filter_iframe_query_args( 'wpstream_vod_iframe_query_args', $vod_iframe_query_args, $product_id );

					// Assemble the trailer iframe query args, dropping null/empty values.
					$vod_trailer_iframe_query_args = array_filter(
						array(
							'video'                      => $video_trailer,
							'embedKey'                   => $vod_iframe_trailer_embed_key,
							'skin'                       => $vod_iframe_skin_slug,
							'startMuted'                 => $muted ? '1' : '',
							'encrypt'                    => $wpstream_vod_encrypt ? 'yes' : '',
							'validatePlaybackSessionUrl' => $vod_validate_url,
							'embedAncestor'              => $vod_embed_ancestor,
						),
						static function ( $v ) {
							return $v !== null && $v !== '';
						}
					);

					// New iframe player when an embed URL exists; otherwise the legacy Video.js element.
					if ( $wpstream_vod_embed_url ) {
						// Emit the content iframe pointing at /player/vod with the assembled query.
						echo '<div class="wpstream_player_iframe_wrap">';
						echo '<iframe id="playerFrame"
								class="wpstream_video_on_demand_iframe"
								title="' . esc_attr__( 'Embedded content', 'wpstream' ) . '"
								src="' . esc_url( add_query_arg( $vod_iframe_query_args, $vod_iframe_base ) ) . '"
								data-wpstream-frame-role="content"
								allowfullscreen
								allow="autoplay; fullscreen">
						</iframe>';
						// Add the hidden trailer iframe only when a trailer exists and the WpStream theme is active.
						if ( '' !== $video_trailer && get_template() == 'hello-wpstream' ) {
							echo '<iframe id="playerFrameTrailer"
								class="wpstream_video_on_demand_iframe wpstream_video_on_demand_iframe_trailer"
								title="' . esc_attr__( 'Embedded trailer content', 'wpstream' ) . '"
								src="' . esc_url( add_query_arg( $vod_trailer_iframe_query_args, $vod_iframe_base ) ) . '"
								data-wpstream-frame-role="trailer"
								allowfullscreen
								allow="autoplay; fullscreen"
								style="display:none;"
								aria-hidden="true"
								tabindex="-1">
							</iframe>';
						}
						echo '</div>';
					} else {
						// Legacy Video.js <video> element carrying every bootstrap data-* attribute.
						echo '<video id="wpstream-video-vod-'.$now.'" class="'.esc_attr($has_trailer_class).' video-js vjs-default-skin  vjs-fluid kuk wpstream_video_on_demand vjs-wpstream ' . $player_theme .' ' . $player_logo_position_class . ' ' . $player_logo_horizontal_position . '"  data-me="'.esc_attr($usernamestream).'" data-product-id="'.$product_id.'" data-wpstream-bootstrap="vod" data-instance-id="wpstream-vod-'.esc_attr( $now ).'" data-video-element-id="wpstream-video-vod-'.esc_attr( $now ).'" data-title-overlay-element-id="'.esc_attr( $overlay_video_div_id ).'" data-video-url="'.esc_attr( $video_path_final ).'" data-trailer-url="'.esc_attr( $video_trailer ).'" data-autoplay="'. ( $autoplay ? '1' : '0' ) .'" data-muted="'. ( $muted ? '1' : '0' ) .'" data-captions-url="'.esc_attr( $captionsUrl ).'" data-play-trailer-button-element-id="'.esc_attr( $play_trailer_button_element_id ).'" data-mute-trailer-button-element-id="'.esc_attr( $mute_trailer_button_element_id ).'" data-unmute-trailer-button-element-id="'.esc_attr( $unmute_trailer_button_element_id ).'" data-play-video-button-element-id="'.esc_attr( $play_video_button_element_id ).'" data-player-logo-image="'.esc_attr( $player_logo_image ).'" data-player-logo-position="'.esc_attr( $player_logo_position ).'" data-player-logo-opacity="'.esc_attr( $player_logo_opacity ).'" data-player-logo-width="100" data-player-logo-height="auto" data-player-logo-padding="10" playsinline preload="auto"
	                  '. $poster_data.' '.$wpstream_data_setup.'>
	                        <p class="vjs-no-js">
	                          To view this video please enable JavaScript, and consider upgrading to a web browser that
	                          <a href="http://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>
	                        </p>
	                    </video>';

						// When a trailer exists, render its play-trailer / play-video / mute / unmute controls (SVG icons).
						if('' !== $video_trailer){
							print '<div class="wpstream_theme_trailer_wrapper">';
							print '<div id="'.esc_attr( $play_trailer_button_element_id ).'" class="wpstream_video_on_demand_play_trailer">
	                    <svg width="30" height="24" viewBox="0 0 30 24" fill="none" xmlns="http://www.w3.org/2000/svg">
	                        <path fill-rule="evenodd" clip-rule="evenodd" d="M26.6667 1.5H3.33337C2.50495 1.5 1.83337 2.17157 1.83337 3V21C1.83337 21.8284 2.50495 22.5 3.33338 22.5H26.6667C27.4951 22.5 28.1667 21.8284 28.1667 21V3C28.1667 2.17157 27.4951 1.5 26.6667 1.5ZM3.33337 0C1.67652 0 0.333374 1.34315 0.333374 3V21C0.333374 22.6569 1.67652 24 3.33338 24H26.6667C28.3236 24 29.6667 22.6569 29.6667 21V3C29.6667 1.34315 28.3236 0 26.6667 0H3.33337ZM4.83337 4C4.55723 4 4.33337 4.22386 4.33337 4.5V6.16667C4.33337 6.44281 4.55723 6.66667 4.83337 6.66667H6.50004C6.77618 6.66667 7.00004 6.44281 7.00004 6.16667V4.5C7.00004 4.22386 6.77618 4 6.50004 4H4.83337ZM23.5 4C23.2239 4 23 4.22386 23 4.5V6.16667C23 6.44281 23.2239 6.66667 23.5 6.66667H25.1667C25.4428 6.66667 25.6667 6.44281 25.6667 6.16667V4.5C25.6667 4.22386 25.4428 4 25.1667 4H23.5ZM4.33337 11.167C4.33337 10.8909 4.55723 10.667 4.83337 10.667H6.50004C6.77618 10.667 7.00004 10.8909 7.00004 11.167V12.8337C7.00004 13.1098 6.77618 13.3337 6.50004 13.3337H4.83337C4.55723 13.3337 4.33337 13.1098 4.33337 12.8337V11.167ZM23.5001 10.667C23.224 10.667 23.0001 10.8909 23.0001 11.167V12.8337C23.0001 13.1098 23.224 13.3337 23.5001 13.3337H25.1668C25.4429 13.3337 25.6668 13.1098 25.6668 12.8337V11.167C25.6668 10.8909 25.4429 10.667 25.1668 10.667H23.5001ZM4.33337 17.833C4.33337 17.5569 4.55723 17.333 4.83337 17.333H6.50004C6.77618 17.333 7.00004 17.5569 7.00004 17.833V19.4997C7.00004 19.7758 6.77618 19.9997 6.50004 19.9997H4.83337C4.55723 19.9997 4.33337 19.7758 4.33337 19.4997V17.833ZM23.5001 17.333C23.224 17.333 23.0001 17.5569 23.0001 17.833V19.4997C23.0001 19.7758 23.224 19.9997 23.5001 19.9997H25.1668C25.4429 19.9997 25.6668 19.7758 25.6668 19.4997V17.833C25.6668 17.5569 25.4429 17.333 25.1668 17.333H23.5001ZM19.0677 13.0997L13.4077 16.5087C13.0434 16.7281 12.6092 16.7094 12.2661 16.5091C11.9218 16.3081 11.6666 15.9224 11.6666 15.4086V8.59072C11.6666 8.07698 11.9218 7.69125 12.2661 7.49026C12.6092 7.28999 13.0434 7.27126 13.4077 7.49064L19.0677 10.8996C19.8663 11.3805 19.8663 12.6188 19.0677 13.0997Z"/>
	                    </svg>
	                    '.esc_html__('Play Trailer','wpstream').'</div>';

							print '<div class="wpstream_video_on_demand_play_video_wrapper" id="'.esc_attr( $play_video_button_element_id ).'" >
	                    <div class="wpstream_video_on_demand_play_video">
	                        <svg width="29" height="30" viewBox="0 0 29 30" fill="none" xmlns="http://www.w3.org/2000/svg">
	                            <path fill-rule="evenodd" clip-rule="evenodd" d="M6.1808 28.9035L26.274 18.1652C29.1087 16.6503 29.1087 12.7497 26.274 11.2348L6.1808 0.496557C4.88769 -0.194506 3.34623 -0.1355 2.1283 0.495357C0.906043 1.12846 1.0095e-06 2.34351 9.38766e-07 3.96179L0 25.4382C-7.07369e-08 27.0565 0.906042 28.2715 2.1283 28.9046C3.34622 29.5355 4.88769 29.5945 6.1808 28.9035ZM24.8221 13.8026C25.5742 14.2045 25.5742 15.1955 24.8221 15.5974L4.72891 26.3356C3.94628 26.7539 3.01386 26.2165 3.01386 25.4382L3.01386 3.96179C3.01386 3.18347 3.94628 2.6461 4.72891 3.06436L24.8221 13.8026Z" fill="#F1F1F1"/>
	                        </svg>
	                    </div>
	                    '.esc_html__('Play Video','wpstream').'
	                    </div>';



							print '<div id="'.esc_attr( $mute_trailer_button_element_id ).'" class="wpstream_video_on_demand_mute_trailer">

	                    <svg width="37" height="36" viewBox="0 0 37 36" fill="none" xmlns="http://www.w3.org/2000/svg">
	                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.32143 10.0789H8.69499L18.8964 0L21.1428 0.921053V35.1316L18.8964 36L8.69499 25.8684H1.32143L0 24.5526V11.3947L1.32143 10.0789ZM10.175 23.6842L18.5 31.9474V4.10526L10.175 12.3158L9.24999 12.7105H2.64286V23.2368H9.24999L10.175 23.6842ZM37 17.9737C37.0069 22.2216 35.5329 26.3401 32.8295 29.6263L30.9478 27.7579C33.1613 24.9734 34.3629 21.5249 34.3571 17.9737C34.3571 14.2895 33.0885 10.8974 30.9637 8.21053L32.8454 6.34211C35.5382 9.62494 37.0062 13.735 37 17.9737ZM31.7143 17.9737C31.7193 20.8255 30.7895 23.6011 29.0661 25.8789L27.1738 23.9947C28.4127 22.2295 29.0752 20.1272 29.0714 17.9737C29.0751 15.8287 28.4174 13.7344 27.1871 11.9737L29.0793 10.0895C30.7338 12.2868 31.7143 15.0158 31.7143 17.9737ZM26.4286 17.9737C26.4286 19.4842 26.0057 20.8947 25.2657 22.0947L23.3126 20.1526C23.6249 19.4729 23.7876 18.7345 23.7899 17.9869C23.7922 17.2394 23.634 16.5001 23.3258 15.8184L25.2789 13.8737C26.0083 15.0684 26.4286 16.4737 26.4286 17.9737Z" fill="white"/>
	                    </svg>
	                    </div>';
							print '<div id="'.esc_attr( $unmute_trailer_button_element_id ).'" class="wpstream_video_on_demand_unmute_trailer">
	                    <svg width="33" height="32" viewBox="0 0 33 32" fill="none" xmlns="http://www.w3.org/2000/svg">
	                    <path fill-rule="evenodd" clip-rule="evenodd" d="M1.15625 8.85688H7.60813L16.5344 0L18.5 0.809375V30.8719L16.5344 31.635L7.60813 22.7319H1.15625L0 21.5756V10.0131L1.15625 8.85688ZM8.90313 20.8125L16.1875 28.0738V3.6075L8.90313 10.8225L8.09375 11.1694H2.3125V20.4194H8.09375L8.90313 20.8125ZM30.5967 11.3127L32.2316 12.9477L28.2287 16.9506L32.2316 20.9559L30.5967 22.5908L26.5938 18.5856L22.5885 22.5908L20.9536 20.9559L24.9588 16.9506L20.9513 12.95L22.5862 11.3151L26.5938 15.3157L30.5967 11.3127Z" fill="white"/>
	                    </svg>

	                    </div>';
							print '</div>';
						}
					}
	            }else{
	                // Quota exhausted for an encrypted VOD: show an "insufficient resources" notice.
	                print '<div class="wpstream_insuficent_res">'.esc_html__('Insufficient resources to stream this title','wpstream').'</div>';
	            }

	        }

	public function wpstream_video_on_demand_player_only_trailer($product_id){
	            // Player dependencies (scripts + styles).
	            wpstream_enqueue_player_assets();

		        // Skin/theme, unique id, and the optional title overlay.
		        $player_theme = $this->wpstream_get_player_theme();
	            $now                =   time().rand(0,1000000);
	            $overlay_video_div_id = "random_id_".$now;
	            $this->wpstream_render_vod_title_overlay(
	                $overlay_video_div_id,
	                get_the_title($product_id)
	            );



	            // Poster thumbnail and streaming username.
	            $poster_thumb           =   $this->poster_url( $product_id, 'small' );
	            $usernamestream         =   esc_html ( wpstream_get_api_username() );


	            // Optional pre-roll trailer (one accessor, filterable).
	            $video_trailer            = $this->trailer_url( $product_id );


	            // Autoplay/mute from the VOD options.
	            $autoplay = false;
	            $muted = false;

				if( intval ( get_option('wpstream_vod_start_muted','') ) === 1){
					$muted = true;
				}
				if( intval  ( get_option('wpstream_vod_autoplay','') ) === 1 ){
					$autoplay = true;
				}
	                 // No main video source (trailer only); captions and control ids.
	                 $video_path_final='';
	                 $has_trailer_class='wpstream_theme_player_has_trailer';
	                 $captions_url = get_post_meta( $product_id, 'wpstream_closed_captions_file', true );
	                 $play_trailer_button_element_id = '' !== $video_trailer ? 'wpstream_video_on_demand_play_trailer_btn_' . $now : '';
	                 $mute_trailer_button_element_id = '' !== $video_trailer ? 'wpstream_video_on_demand_mute_trailer_btn_' . $now : '';
	                 $unmute_trailer_button_element_id = '' !== $video_trailer ? 'wpstream_video_on_demand_unmute_trailer_btn_' . $now : '';

	                 // Video.js element with an empty data-video-url (trailer supplied via data-trailer-url).
	                 echo '<video id="wpstream-video-vod-'.$now.'" class="video-js vjs-default-skin  vjs-fluid kuk wpstream_video_on_demand vjs-wpstream '.esc_attr(  $has_trailer_class ).' ' . $player_theme . '"  data-me="'.esc_attr($usernamestream).'" data-product-id="'.$product_id.'" data-wpstream-bootstrap="vod" data-instance-id="wpstream-vod-trailer-'.esc_attr( $now ).'" data-video-element-id="wpstream-video-vod-'.esc_attr( $now ).'" data-title-overlay-element-id="'.esc_attr( $overlay_video_div_id ).'" data-video-url="" data-trailer-url="'.esc_attr( $video_trailer ).'" data-autoplay="'. ( $autoplay ? '1' : '0' ) .'" data-muted="'. ( $muted ? '1' : '0' ) .'" data-captions-url="'.esc_attr( $captions_url ).'" data-play-trailer-button-element-id="'.esc_attr( $play_trailer_button_element_id ).'" data-mute-trailer-button-element-id="'.esc_attr( $mute_trailer_button_element_id ).'" data-unmute-trailer-button-element-id="'.esc_attr( $unmute_trailer_button_element_id ).'"  playsinline preload="auto"
	                   >
	                    <p class="vjs-no-js">
	                      To view this video please enable JavaScript, and consider upgrading to a web browser that
	                      <a href="http://videojs.com/html5-video-support/" target="_blank">supports HTML5 video</a>
	                    </p>
	                </video>';

	            // Render the trailer play/mute/unmute controls (SVG icons) when a trailer exists.
	            if('' !== $video_trailer){
	                print '<div class="wpstream_theme_trailer_wrapper">';
	                print '<div id="'.esc_attr( $play_trailer_button_element_id ).'" class="wpstream_video_on_demand_play_trailer">
	                     <svg width="30" height="24" viewBox="0 0 30 24" fill="none" xmlns="http://www.w3.org/2000/svg">
	                                <path fill-rule="evenodd" clip-rule="evenodd" d="M26.6667 1.5H3.33337C2.50495 1.5 1.83337 2.17157 1.83337 3V21C1.83337 21.8284 2.50495 22.5 3.33338 22.5H26.6667C27.4951 22.5 28.1667 21.8284 28.1667 21V3C28.1667 2.17157 27.4951 1.5 26.6667 1.5ZM3.33337 0C1.67652 0 0.333374 1.34315 0.333374 3V21C0.333374 22.6569 1.67652 24 3.33338 24H26.6667C28.3236 24 29.6667 22.6569 29.6667 21V3C29.6667 1.34315 28.3236 0 26.6667 0H3.33337ZM4.83337 4C4.55723 4 4.33337 4.22386 4.33337 4.5V6.16667C4.33337 6.44281 4.55723 6.66667 4.83337 6.66667H6.50004C6.77618 6.66667 7.00004 6.44281 7.00004 6.16667V4.5C7.00004 4.22386 6.77618 4 6.50004 4H4.83337ZM23.5 4C23.2239 4 23 4.22386 23 4.5V6.16667C23 6.44281 23.2239 6.66667 23.5 6.66667H25.1667C25.4428 6.66667 25.6667 6.44281 25.6667 6.16667V4.5C25.6667 4.22386 25.4428 4 25.1667 4H23.5ZM4.33337 11.167C4.33337 10.8909 4.55723 10.667 4.83337 10.667H6.50004C6.77618 10.667 7.00004 10.8909 7.00004 11.167V12.8337C7.00004 13.1098 6.77618 13.3337 6.50004 13.3337H4.83337C4.55723 13.3337 4.33337 13.1098 4.33337 12.8337V11.167ZM23.5001 10.667C23.224 10.667 23.0001 10.8909 23.0001 11.167V12.8337C23.0001 13.1098 23.224 13.3337 23.5001 13.3337H25.1668C25.4429 13.3337 25.6668 13.1098 25.6668 12.8337V11.167C25.6668 10.8909 25.4429 10.667 25.1668 10.667H23.5001ZM4.33337 17.833C4.33337 17.5569 4.55723 17.333 4.83337 17.333H6.50004C6.77618 17.333 7.00004 17.5569 7.00004 17.833V19.4997C7.00004 19.7758 6.77618 19.9997 6.50004 19.9997H4.83337C4.55723 19.9997 4.33337 19.7758 4.33337 19.4997V17.833ZM23.5001 17.333C23.224 17.333 23.0001 17.5569 23.0001 17.833V19.4997C23.0001 19.7758 23.224 19.9997 23.5001 19.9997H25.1668C25.4429 19.9997 25.6668 19.7758 25.6668 19.4997V17.833C25.6668 17.5569 25.4429 17.333 25.1668 17.333H23.5001ZM19.0677 13.0997L13.4077 16.5087C13.0434 16.7281 12.6092 16.7094 12.2661 16.5091C11.9218 16.3081 11.6666 15.9224 11.6666 15.4086V8.59072C11.6666 8.07698 11.9218 7.69125 12.2661 7.49026C12.6092 7.28999 13.0434 7.27126 13.4077 7.49064L19.0677 10.8996C19.8663 11.3805 19.8663 12.6188 19.0677 13.0997Z"/>
	                            </svg>
	                            '.esc_html__('Play Trailer','wpstream').'
	                </div>';
	                print '<div id="'.esc_attr( $mute_trailer_button_element_id ).'" class="wpstream_video_on_demand_mute_trailer">
	                <svg width="37" height="36" viewBox="0 0 37 36" fill="none" xmlns="http://www.w3.org/2000/svg">
	                <path fill-rule="evenodd" clip-rule="evenodd" d="M1.32143 10.0789H8.69499L18.8964 0L21.1428 0.921053V35.1316L18.8964 36L8.69499 25.8684H1.32143L0 24.5526V11.3947L1.32143 10.0789ZM10.175 23.6842L18.5 31.9474V4.10526L10.175 12.3158L9.24999 12.7105H2.64286V23.2368H9.24999L10.175 23.6842ZM37 17.9737C37.0069 22.2216 35.5329 26.3401 32.8295 29.6263L30.9478 27.7579C33.1613 24.9734 34.3629 21.5249 34.3571 17.9737C34.3571 14.2895 33.0885 10.8974 30.9637 8.21053L32.8454 6.34211C35.5382 9.62494 37.0062 13.735 37 17.9737ZM31.7143 17.9737C31.7193 20.8255 30.7895 23.6011 29.0661 25.8789L27.1738 23.9947C28.4127 22.2295 29.0752 20.1272 29.0714 17.9737C29.0751 15.8287 28.4174 13.7344 27.1871 11.9737L29.0793 10.0895C30.7338 12.2868 31.7143 15.0158 31.7143 17.9737ZM26.4286 17.9737C26.4286 19.4842 26.0057 20.8947 25.2657 22.0947L23.3126 20.1526C23.6249 19.4729 23.7876 18.7345 23.7899 17.9869C23.7922 17.2394 23.634 16.5001 23.3258 15.8184L25.2789 13.8737C26.0083 15.0684 26.4286 16.4737 26.4286 17.9737Z" fill="white"/>
	                </svg>


	                </div>';
	                print '<div id="'.esc_attr( $unmute_trailer_button_element_id ).'" class="wpstream_video_on_demand_unmute_trailer">
	                <svg width="33" height="32" viewBox="0 0 33 32" fill="none" xmlns="http://www.w3.org/2000/svg">
	                <path fill-rule="evenodd" clip-rule="evenodd" d="M1.15625 8.85688H7.60813L16.5344 0L18.5 0.809375V30.8719L16.5344 31.635L7.60813 22.7319H1.15625L0 21.5756V10.0131L1.15625 8.85688ZM8.90313 20.8125L16.1875 28.0738V3.6075L8.90313 10.8225L8.09375 11.1694H2.3125V20.4194H8.09375L8.90313 20.8125ZM30.5967 11.3127L32.2316 12.9477L28.2287 16.9506L32.2316 20.9559L30.5967 22.5908L26.5938 18.5856L22.5885 22.5908L20.9536 20.9559L24.9588 16.9506L20.9513 12.95L22.5862 11.3151L26.5938 15.3157L30.5967 11.3127Z" fill="white"/>
	                </svg>

	                </div>';
	                print '</div>';
	            }
	            else {
	                //just show the poster or don't show anything; no player needed
	            }

	        }

	private function wpstream_render_vod_title_overlay( $overlay_id, $title_text ) {
	                // Only fire when a theme/plugin has registered the overlay renderer.
	                if ( has_action( 'wpstream_vod_title_overlay' ) ) {
	                    do_action(
	                        'wpstream_vod_title_overlay',
	                        $overlay_id,
	                        $title_text,
	                        esc_html__( 'Playing:', 'wpstream' )
	                    );
	                }
	            }

	public function wpstream_get_video_player_logo( $product_id ) {
			// Streamify (basic) users always get the bundled WpStream watermark.
			$is_streamify_user = $this->wpstream_is_streamify_user( $product_id );
			if ( $is_streamify_user ) {
				return WPSTREAM_PLUGIN_DIR_URL . 'img/wpstream-symbol-large.png';
			}

			// Otherwise the site-configured logo, which an integration may replace.
			$logo = (string) get_option( 'wpstream_player_logo', '' );

			/**
			 * Filter the player logo / watermark image URL.
			 *
			 * Not applied for basic-plan (streamify) channels, whose bundled
			 * watermark is part of the plan.
			 *
			 * @since 4.14.0
			 *
			 * @param string $logo       Logo URL or empty for none.
			 * @param int    $product_id Streaming Content post ID.
			 */
			return (string) apply_filters( 'wpstream_player_logo', $logo, intval( $product_id ) );
		}

	public function wpstream_is_streamify_user( $channel_id ) {
			// The 'basicStreaming' meta flag marks streamify (basic) channels.
			$is_basic_streaming = get_post_meta( $channel_id, 'basicStreaming', true );
			if ( $is_basic_streaming === '1' ) {
				return true;
			}
			return false;
		}
}
