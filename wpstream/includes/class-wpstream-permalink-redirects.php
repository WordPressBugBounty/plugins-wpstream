<?php
/**
 * Keeps shared links working when an item moves between its free and paid post types.
 *
 * Free content and WooCommerce products live under different URL bases, so the move changes
 * the address. The old path is remembered on the post and answered with a permanent redirect.
 *
 * @package wpstream
 */

defined( 'ABSPATH' ) || exit;

/** Remembers the previous paths of moved items and redirects visitors from them. */
class Wpstream_Permalink_Redirects {
	/** Post meta holding one previous path per row. */
	const META_KEY = '_wpstream_previous_path';

	/** Post types an item moves between. */
	const POST_TYPES = array( 'wpstream_product', 'wpstream_product_vod', 'product' );

	/** Register the hooks. */
	public function __construct() {
		add_action( 'post_updated', array( $this, 'remember_previous_path' ), 10, 3 );
		// Runs before WordPress guesses a similar page for the missing address.
		add_action( 'template_redirect', array( $this, 'redirect_previous_path' ), 9 );
	}

	/**
	 * Store the path a published item had before its post type changed.
	 *
	 * @param int     $post_id Post being updated.
	 * @param WP_Post $after   Post after the update.
	 * @param WP_Post $before  Post before the update.
	 * @return void
	 */
	public function remember_previous_path( $post_id, $after, $before ) {
		if ( $after->post_type === $before->post_type || 'publish' !== $before->post_status ) {
			return;
		}
		if ( ! in_array( $before->post_type, self::POST_TYPES, true ) || ! in_array( $after->post_type, self::POST_TYPES, true ) ) {
			return;
		}
		$url = wp_parse_url( get_permalink( $before ) );
		// Plain permalinks identify the item in the query string, so there is no path to remember.
		if ( empty( $url['path'] ) || ! empty( $url['query'] ) ) {
			return;
		}
		$path = trailingslashit( $url['path'] );
		if ( ! in_array( $path, get_post_meta( $post_id, self::META_KEY ), true ) ) {
			add_post_meta( $post_id, self::META_KEY, $path );
		}
	}

	/**
	 * Send a visitor from a remembered address to the item's current page.
	 *
	 * @return void
	 */
	public function redirect_previous_path() {
		if ( ! is_404() || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		global $wpdb;
		$path = trailingslashit( (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) );
		// The newest move wins when two items once used the same address.
		$post_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND m.meta_value = %s AND p.post_status = 'publish' ORDER BY m.meta_id DESC LIMIT 1",
				self::META_KEY,
				$path
			)
		);
		if ( $post_id ) {
			wp_safe_redirect( get_permalink( $post_id ), 301 );
			exit;
		}
	}
}
