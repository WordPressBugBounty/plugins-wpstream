<?php
/**
 * My Account list collector shared by the event-list and video-list templates.
 *
 * The templates only render; the decision of which products a customer sees
 * lives here so both lists apply the same rule and expose the same filter.
 *
 * @package Wpstream
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wpstream_account_list_products' ) ) {
	/**
	 * Collect the product IDs listed on one My Account tab for a customer.
	 *
	 * Walks the customer's WooCommerce orders newest first, expands a bundle or
	 * subscription plan line into the content it covers, keeps each product
	 * once, drops what the shared entitlement rule denies (unpaid orders,
	 * lapsed subscriptions) and keeps the products of the tab's kind: live
	 * streams (or live-flagged subscriptions) for `event_list`, videos on
	 * demand (or VOD-flagged subscriptions) for `video_list`.
	 *
	 * `$user_id` must be the current viewer: the entitlement rule inside the
	 * walk always judges the current user, so a foreign ID would list one
	 * customer's purchases against another's entitlements.
	 *
	 * @since 4.14.0
	 *
	 * @param string $context `event_list` or `video_list`.
	 * @param int    $user_id Customer ID (the current viewer).
	 * @return int[] Product IDs in display order.
	 */
	function wpstream_account_list_products( $context, $user_id ) {
		global $wpstream_plugin;
		$product_ids = array();
		$user_id     = intval( $user_id );

		// Without WooCommerce there are no orders to read; the filter can still add products.
		if ( $user_id > 0 && function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'customer_id' => $user_id,
					'limit'       => -1,
					'orderby'     => 'date',
					'order'       => 'DESC',
					'status'      => array_keys( wc_get_order_statuses() ),
				)
			);

			foreach ( $orders as $order ) {
				foreach ( $order->get_items() as $item ) {
					// A line grants its own product plus, for a bundle or a plan,
					// the content that bundle holds or that plan covers.
					foreach ( wpstream_account_list_granted_ids( intval( $item['product_id'] ) ) as $product_id ) {
						// Each product is listed once, newest purchase first.
						if ( in_array( $product_id, $product_ids, true ) ) {
							continue;
						}

						// The list shows only what the customer can actually watch.
						if ( ! wpstream_user_entitled_for_product( $product_id ) ) {
							continue;
						}

						// Keep the products of this tab's kind, using the presentation
						// module's single live-vs-VOD rule.
						$kind = $wpstream_plugin->playback_presentation->classify( $product_id );
						if ( ( 'event_list' === $context ? 'live' : 'vod' ) === $kind ) {
							$product_ids[] = $product_id;
						}
					}
				}
			}
		}

		/**
		 * Filter the products listed on a My Account tab.
		 *
		 * This widens what is *listed*, not what is *playable*: opening a
		 * product added here still goes through the `wpstream_user_is_entitled`
		 * playback rule. Typical use: an LMS lists course-granted videos.
		 * Callbacks own the visibility of what they add (only published
		 * products belong here).
		 *
		 * @since 4.14.0
		 *
		 * @param int[]  $product_ids Product IDs in display order.
		 * @param string $context     `event_list` or `video_list`.
		 * @param int    $user_id     Customer ID.
		 */
		$product_ids = apply_filters( 'wpstream_account_list_products', $product_ids, $context, $user_id );

		return array_values( array_unique( array_filter( array_map( 'intval', (array) $product_ids ) ) ) );
	}
}

if ( ! function_exists( 'wpstream_account_list_granted_ids' ) ) {
	/**
	 * Product IDs one bought product can give access to.
	 *
	 * The product itself, then the published products that name it as their
	 * bundle (`wpstream_part_of_bundle`, one row per bundle) or as a linked
	 * subscription plan (`_wpstream_parent_sub`, a serialized ID list). The
	 * caller still applies the entitlement rule to each one.
	 *
	 * @since 4.14.0
	 *
	 * @param int $product_id Product on an order line.
	 * @return int[]
	 */
	function wpstream_account_list_granted_ids( $product_id ) {
		global $wpdb;
		$product_id = intval( $product_id );

		// Bundle members store the bundle ID as the plain meta value; linked
		// content stores the plan ID inside a serialized array, matched loosely
		// here and confirmed below.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_status = 'publish'
				 WHERE ( pm.meta_key = 'wpstream_part_of_bundle' AND pm.meta_value = %s )
				    OR ( pm.meta_key = '_wpstream_parent_sub' AND ( pm.meta_value LIKE %s OR pm.meta_value LIKE %s ) )
				 ORDER BY p.post_date DESC",
				(string) $product_id,
				'%i:' . $product_id . ';%',
				'%"' . $product_id . '"%'
			)
		);

		$ids = array( $product_id );
		foreach ( $rows as $row ) {
			// A serialized match could be an array index; keep only real plan IDs.
			if ( '_wpstream_parent_sub' === $row->meta_key && ! in_array( $product_id, array_map( 'absint', (array) maybe_unserialize( $row->meta_value ) ), true ) ) {
				continue;
			}
			$ids[] = intval( $row->post_id );
		}
		return array_values( array_unique( $ids ) );
	}
}
