<?php
/**
 * My Account > Event list.
 *
 * Lists the current customer's purchased live-stream and subscription live
 * events. Prefers the theme's own renderer when present, otherwise rebuilds
 * the list from WooCommerce orders. Template slots fire around both branches;
 * the fallback list and its cards are filterable.
 *
 * @package Wpstream
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpstream_account_user_id = get_current_user_id();
// On global-subscription sites the tab is the customer's subscription, not a list of events.
$wpstream_account_global_sub = function_exists( 'wpstream_is_global_subscription' ) && wpstream_is_global_subscription();
?>
<h3><?php echo $wpstream_account_global_sub ? esc_html__( 'Your Subscription', 'wpstream' ) : esc_html__( 'Event list', 'wpstream' ); ?></h3>
<?php
/**
 * Fires before the My Account event list, in the theme-renderer and the fallback branch.
 *
 * @since 4.14.0
 *
 * @param int $user_id Current customer ID.
 */
do_action( 'wpstream_before_account_event_list', $wpstream_account_user_id );

// Prefer the theme's own purchased-event renderer when it is available.
if ( function_exists( 'wpstream_theme_purchased_event_list' ) ) {
	wpstream_theme_purchased_event_list();
} else {
	// Fallback: the shared collector walks the customer's orders and keeps
	// only the live events (or live subscriptions) the entitlement rule allows.
	$wpstream_account_products = wpstream_account_list_products( 'event_list', $wpstream_account_user_id );

	// Render one card per product.
	foreach ( $wpstream_account_products as $product_id ) {
		$url  = get_permalink( $product_id );
		$html = '<div class="wpstream_product_front">'
			. '<a class="wpstream_product_image_wrapper" href="' . esc_url( $url ) . '">' . get_the_post_thumbnail( $product_id, 'thumb' ) . '</a>'
			. '<div class="wpstream_product_wrapper">'
			. '<a class="wpstream_product_name_front" href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $product_id ) ) . '</a>';
		// On global-subscription sites, omit the per-event "see the event" link.
		if ( ! $wpstream_account_global_sub ) {
			$html .= '<a class="wpstream_product_see" href="' . esc_url( $url ) . '">' . esc_html__( 'see the event', 'wpstream' ) . '</a>';
		}
		$html .= '</div></div>';
		/** This filter is documented in templates/myaccount/video_list.php */
		echo apply_filters( 'wpstream_account_list_item_html', $html, $product_id, 'event_list' );
	}
}

/**
 * Fires after the My Account event list, in the theme-renderer and the fallback branch.
 *
 * @since 4.14.0
 *
 * @param int $user_id Current customer ID.
 */
do_action( 'wpstream_after_account_event_list', $wpstream_account_user_id );
