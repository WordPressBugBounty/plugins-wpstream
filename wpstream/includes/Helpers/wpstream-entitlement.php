<?php
/**
 * Viewing-entitlement helper.
 *
 * Central place for deciding whether the current viewer may watch a piece of
 * content. This is the single entitlement rule — every access gate (player
 * render, shortcode, status poll, DRM key delivery, playback-session issuance,
 * My-Account media lists) must call this instead of re-implementing purchase /
 * subscription / bundle checks. Sibling of the channel-management rule in
 * wpstream-authorization.php: that file answers "may this user MANAGE the
 * channel", this one answers "may this user WATCH it".
 *
 * The rule (see docs/adr/0001-unified-entitlement-rule.md):
 *  - free WpStream post types are watchable by anyone, guests included;
 *  - whoever may manage a paid item (its author, or an admin/editor, per
 *    wpstream_can_manage_channel) may watch it without buying it;
 *  - subscription-type products need a PAID subscription (active, or cancelled
 *    but paid to the end of the term) — the completed order a subscription
 *    purchase leaves behind never grants by itself, so once the subscription
 *    ends access is revoked everywhere;
 *  - other WooCommerce products are unlocked by an active subscription
 *    covering them, a completed direct purchase, or a completed purchase of
 *    the bundle they belong to;
 *  - Netflix mode adds a "qualifying global subscription" leg on top — it
 *    never disables the purchase legs (money paid = access kept).
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes/Helpers
 */

// Block direct file access outside of WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wpstream_user_has_paid_subscription' ) ) {
	/**
	 * Whether a user holds a subscription to a product that is still paid for.
	 *
	 * Paid means active, or cancelled but paid to the end of the term
	 * (pending-cancel). Once WooCommerce ends it (cancelled, expired, on-hold)
	 * it no longer counts.
	 *
	 * @param int $user_id    User to check.
	 * @param int $product_id Subscription product ID.
	 * @return bool True when such a subscription exists.
	 */
	function wpstream_user_has_paid_subscription( $user_id, $product_id ) {
		// Without WooCommerce Subscriptions there are no subscriptions to hold.
		if ( ! function_exists( 'wcs_user_has_subscription' ) ) {
			return false;
		}

		return wcs_user_has_subscription( $user_id, $product_id, array( 'active', 'pending-cancel' ) );
	}
}

if ( ! function_exists( 'wpstream_user_entitled_for_product' ) ) {
	/**
	 * Decide whether the current viewer is entitled to watch a product.
	 *
	 * Always evaluates against the current user (every gate in the plugin
	 * checks the viewer of the running request). Returns a plain boolean;
	 * callers keep their own logged-in checks for messaging purposes
	 * ("please log in" vs "please purchase").
	 *
	 * The built-in verdict passes through one filter, which is therefore the
	 * single extension point for membership plugins: whatever it returns is
	 * what every gate listed in the file header enforces.
	 *
	 * @param int $product_id Product or wpstream_* post ID.
	 * @return bool True when the current viewer may watch this content.
	 */
	function wpstream_user_entitled_for_product( $product_id ) {
		// Normalise and reject non-positive IDs up front.
		$product_id = intval( $product_id );
		if ( $product_id <= 0 ) {
			return false;
		}

		/**
		 * Filters whether the current viewer may watch a product.
		 *
		 * Gate-widening by design: this one verdict covers the player render,
		 * the live-status poll, DRM key delivery, playback-session issuance
		 * and the My Account media lists, so returning true grants all of
		 * them at once. Replaces the former theme-defined global-subscription
		 * sniff (wpstream_check_global_subscription_model).
		 *
		 * @since 4.14.0
		 *
		 * @param bool $entitled   The built-in verdict (free type / purchase /
		 *                         active subscription / bundle / Netflix mode).
		 * @param int  $product_id Product or wpstream_* post ID.
		 * @param int  $user_id    Current user ID, 0 for guests.
		 */
		return (bool) apply_filters(
			'wpstream_user_is_entitled',
			wpstream_user_entitled_for_product_rule( $product_id ),
			$product_id,
			get_current_user_id()
		);
	}
}

if ( ! function_exists( 'wpstream_user_entitled_for_product_rule' ) ) {
	/**
	 * The built-in entitlement rule, before the wpstream_user_is_entitled filter.
	 *
	 * Gates must call wpstream_user_entitled_for_product(), never this.
	 *
	 * @param int $product_id Positive product or wpstream_* post ID.
	 * @return bool True when the rule itself grants access.
	 */
	function wpstream_user_entitled_for_product_rule( $product_id ) {

		// Free WpStream live/VOD post types are watchable by anyone, guests included.
		$post_type = get_post_type( $product_id );
		if ( in_array( $post_type, array( 'wpstream_product', 'wpstream_product_vod' ), true ) ) {
			return true;
		}

		// Anything that is not a WooCommerce product cannot be paid content.
		if ( 'product' !== $post_type ) {
			return false;
		}

		// Paid content needs a logged-in user to check entitlement against,
		// and WooCommerce loaded for the lookup helpers to exist.
		// class_exists (not the active_plugins option) so network-activated
		// WooCommerce on multisite is detected too.
		if ( ! is_user_logged_in() || ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		$current_user = wp_get_current_user();

		// Whoever may manage this item (its author, or an admin/editor) may
		// watch it without buying it — same rule that guards start/stop.
		if ( wpstream_can_manage_channel( $current_user->ID, $product_id ) ) {
			return true;
		}

		// Leg 1: an ACTIVE subscription covering this product grants in any mode.
		// For subscription-type products this is the ONLY per-product leg — the
		// completed order the subscription purchase left behind must never grant,
		// otherwise cancelling would not revoke access.
		if ( wpstream_user_has_paid_subscription( $current_user->ID, $product_id ) ) {
			return true;
		}

		// Explicitly linked plans cover this video/channel in either access mode.
		// The global mode still controls only the site-wide fallback plan below.
		if ( function_exists( 'wcs_user_has_subscription' ) ) {
			foreach ( (array) get_post_meta( $product_id, '_wpstream_parent_sub', true ) as $parent_id ) {
				if ( absint( $parent_id ) && wpstream_user_has_paid_subscription( $current_user->ID, absint( $parent_id ) ) ) {
					return true;
				}
			}
		}

		// Legs 2+3 apply only to non-subscription products: completed direct
		// purchase, or completed purchase of the bundle this product belongs to.
		if ( 'subscription' !== $product->get_type() ) {
			if ( wc_customer_bought_product( $current_user->user_email, $current_user->ID, $product_id ) ) {
				return true;
			}

			// The product keeps one row per bundle it belongs to; buying any of them grants.
			foreach ( (array) get_post_meta( $product_id, 'wpstream_part_of_bundle', false ) as $possible_bundle ) {
				$possible_bundle = intval( $possible_bundle );
				if ( 0 !== $possible_bundle
					&& wc_customer_bought_product( $current_user->user_email, $current_user->ID, $possible_bundle ) ) {
					return true;
				}
			}
		}

		// Leg 4 (Netflix mode, additive): a qualifying global subscription —
		// per-product parent subscription(s), or the site-wide main one.
		if ( wpstream_user_has_qualifying_global_subscription( $product_id ) ) {
			return true;
		}

		// No leg matched: not entitled.
		return false;
	}
}

if ( ! function_exists( 'wpstream_user_has_qualifying_global_subscription' ) ) {
	/**
	 * Netflix-mode leg: does the current user hold a qualifying global subscription?
	 *
	 * Only ever true when the site runs in Netflix mode (wpstream_global_sub = 1).
	 * A product may point at one or more parent subscriptions via the
	 * _wpstream_parent_sub meta (per-product), or the site may define a single
	 * main subscription (wpstream_global_sub_id); an active subscription to any
	 * of those qualifies. Logic moved verbatim from the player's
	 * wpstream_in_plugin_check_global_subscription_model(), which now delegates here.
	 *
	 * @param int $product_id Product ID whose parent subscription(s) to check.
	 * @return bool True when the user holds a qualifying active subscription.
	 */
	function wpstream_user_has_qualifying_global_subscription( $product_id ) {
		// Requires a logged-in user and WooCommerce Subscriptions.
		if ( ! is_user_logged_in() || ! function_exists( 'wcs_user_has_subscription' ) ) {
			return false;
		}

		// Only meaningful when the site runs the Netflix (global subscription) mode.
		if ( 1 !== intval( get_option( 'wpstream_global_sub', '' ) ) ) {
			return false;
		}

		$current_user      = wp_get_current_user();
		$main_subscription = intval( get_option( 'wpstream_global_sub_id', '' ) );

		// Subscription id(s) this product is tied to (per-product parents win).
		$selected_sub = get_post_meta( $product_id, '_wpstream_parent_sub', true );

		if ( is_array( $selected_sub ) ) {
			// Entitled if the user actively subscribes to any linked parent subscription.
			foreach ( $selected_sub as $subscription_id ) {
				if ( wpstream_user_has_paid_subscription( $current_user->ID, $subscription_id ) ) {
					return true;
				}
			}
		} elseif ( 0 !== $main_subscription ) {
			// No per-product parents: entitled via the single site-wide main subscription.
			if ( wpstream_user_has_paid_subscription( $current_user->ID, $main_subscription ) ) {
				return true;
			}
		}

		// Logged in but no qualifying subscription.
		return false;
	}
}
