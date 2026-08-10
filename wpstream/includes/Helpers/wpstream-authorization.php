<?php
/**
 * Channel-ownership authorization helpers.
 *
 * Central place for deciding whether a given user is allowed to manage a
 * specific WpStream channel (start/stop it, or read its RTMP/WHIP publishing
 * credentials). Every state-changing or credential-returning channel handler
 * must gate on this so a streaming-capable user cannot control, or steal the
 * stream key of, a channel they do not own. This enforces the multi-broadcaster
 * isolation the product advertises (see TASK-01 / audit SEC-03, SEC-04).
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes/Helpers
 */

// Block direct file access outside of WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wpstream_can_manage_channel' ) ) {
	/**
	 * Decide whether a user may manage a specific channel.
	 *
	 * The single rule: a site admin/editor (anyone who can edit posts they do
	 * not author) may manage any channel; every other user may only manage a
	 * channel they authored. The target must also be a real channel post — a
	 * free `wpstream_product` or a paid WooCommerce `product`.
	 *
	 * "Manage" covers starting/stopping the channel and receiving its RTMP/WHIP
	 * ingest credentials; the same ownership rule guards all of those actions,
	 * so no per-action branching is needed here.
	 *
	 * @param int $user_id    The acting user's ID (0/guest is always denied).
	 * @param int $channel_id The channel post ID being acted upon.
	 * @return bool True if the user may manage the channel, false otherwise.
	 */
	function wpstream_can_manage_channel( $user_id, $channel_id ) {
		// Normalise inputs; a missing user or channel can never be authorized.
		$user_id    = intval( $user_id );
		$channel_id = intval( $channel_id );
		if ( $user_id <= 0 || $channel_id <= 0 ) {
			return false;
		}

		// The target must exist and be a WpStream channel post type.
		$channel = get_post( $channel_id );
		if ( ! $channel || ! in_array( $channel->post_type, array( 'wpstream_product', 'product' ), true ) ) {
			return false;
		}

		// Admins/editors (can edit posts they do not own) may manage any channel.
		if ( user_can( $user_id, 'edit_others_posts' ) ) {
			return true;
		}

		// Everyone else: only the author of the channel may manage it.
		return intval( $channel->post_author ) === $user_id;
	}
}
