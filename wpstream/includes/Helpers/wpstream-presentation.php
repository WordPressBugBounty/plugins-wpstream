<?php
/**
 * Viewer-facing presentation strings shared by the player, the public JS
 * config and the bundled theme framework.
 *
 * One accessor per string keeps the filter in a single place, so every
 * surface that shows the message agrees with what a theme decided.
 *
 * @package Wpstream
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wpstream_not_live_message' ) ) {
	/**
	 * The "we are not live" message shown while a channel is offline.
	 *
	 * Reads the configurable option and passes it through one filter. The
	 * return is plain text — callers escape it for their context.
	 *
	 * @since 4.14.0
	 *
	 * @param int $channel_id Channel post ID, or 0 when the message is not tied
	 *                        to one channel (the player's shared JS config).
	 * @return string Plain-text message.
	 */
	function wpstream_not_live_message( $channel_id = 0 ) {
		// The option holds a site-wide custom message; the literal is the translated default.
		$message = (string) get_option( 'wpstream_you_are_not_live', __( 'We are not live at this moment', 'wpstream' ) );

		/**
		 * Filter the "we are not live" message.
		 *
		 * @since 4.14.0
		 *
		 * @param string $message    Plain-text message (not yet escaped).
		 * @param int    $channel_id Channel post ID, or 0 for the shared player config.
		 */
		return (string) apply_filters( 'wpstream_not_live_message', $message, intval( $channel_id ) );
	}
}
