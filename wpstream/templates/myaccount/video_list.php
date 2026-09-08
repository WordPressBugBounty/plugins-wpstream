<?php
/**
 * My Account > Video list.
 *
 * Lists the current customer's purchased video-on-demand items (and VOD
 * subscriptions). Prefers the theme's own renderer when present, otherwise
 * rebuilds the list from WooCommerce orders. Template slots fire around both
 * branches; the fallback list and its cards are filterable.
 *
 * @package Wpstream
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpstream_account_user_id = get_current_user_id();
?>
<h3><?php esc_html_e( 'Video list', 'wpstream' ); ?></h3>
<?php
/**
 * Fires before the My Account video list, in the theme-renderer and the fallback branch.
 *
 * @since 4.14.0
 *
 * @param int $user_id Current customer ID.
 */
do_action( 'wpstream_before_account_video_list', $wpstream_account_user_id );

// Prefer the theme's own purchased-video renderer when it is available.
if ( function_exists( 'wpstream_theme_purchased_video_list' ) ) {
	wpstream_theme_purchased_video_list();
} else {
	// Fallback: the shared collector walks the customer's orders and keeps
	// only the VODs (or VOD subscriptions) the entitlement rule allows.
	$wpstream_account_products = wpstream_account_list_products( 'video_list', $wpstream_account_user_id );

	// Render one card per product.
	foreach ( $wpstream_account_products as $product_id ) {
		$url  = get_permalink( $product_id );
		$html = '<div class="wpstream_product_front">'
			. '<a class="wpstream_product_image_wrapper" href="' . esc_url( $url ) . '">' . get_the_post_thumbnail( $product_id, 'thumb' ) . '</a>'
			. '<div class="wpstream_product_wrapper">'
			. '<a class="wpstream_product_name_front" href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $product_id ) ) . '</a>'
			. '<a class="wpstream_product_see" href="' . esc_url( $url ) . '" >' . esc_html__( 'watch the video', 'wpstream' ) . '</a>'
			. '</div>'
			. '</div>';
		/**
		 * Filter one card of a My Account list.
		 *
		 * The default markup is already escaped; callbacks own the escaping of
		 * anything they add.
		 *
		 * @since 4.14.0
		 *
		 * @param string $html       Card markup.
		 * @param int    $product_id Product the card is for.
		 * @param string $context    `event_list` or `video_list`.
		 */
		echo apply_filters( 'wpstream_account_list_item_html', $html, $product_id, 'video_list' );
	}
}

/**
 * Fires after the My Account video list, in the theme-renderer and the fallback branch.
 *
 * @since 4.14.0
 *
 * @param int $user_id Current customer ID.
 */
do_action( 'wpstream_after_account_video_list', $wpstream_account_user_id );
