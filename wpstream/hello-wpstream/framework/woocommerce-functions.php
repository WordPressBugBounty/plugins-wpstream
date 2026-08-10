<?php
/**
 * Woocommerce functions.
 *
 * Theme-side WooCommerce integration: registers the custom "Video Collection"
 * (wpstream_bundle) product type, tweaks account/checkout fields, blocks
 * re-purchasing already-owned streaming products, and renders the LIVE / VIDEO
 * / COLLECTION / SUBSCRIPTION badges shown on product imagery.
 *
 * @package wpstream-theme
 */

// Require class wc product bundle.
// The bundle product-type class depends on WooCommerce, so only load it when active.
if ( class_exists( 'WooCommerce' ) ) {
	require_once 'class-wc-product-wpstream-bundle.php';
}

/**
 * Remove the "screen-reader-text" class from WooCommerce form field labels.
 *
 * @param array  $args  The form field arguments.
 * @param string $key   The field key.
 * @param mixed  $value The field value.
 * @return array        The filtered field arguments.
 */
add_filter( 'woocommerce_form_field_args', 'wpstream_theme_custom_remove_class_from_labels', 10, 3 );function wpstream_theme_custom_remove_class_from_labels( $args, $key, $value ) {
    // Specify the class you want to remove
    $class_to_remove = 'screen-reader-text';

    // Check if the label_class is set and contains the class we want to remove
    // Locate the class in the label_class array (assignment inside the condition is intentional).
    if ( isset( $args['label_class'] ) && ($key = array_search($class_to_remove, $args['label_class'])) !== false ) {
        // Drop the screen-reader-only class so the label becomes visible.
        unset($args['label_class'][$key]);
    }

    return $args;
}


// Print custom copy just above the related-products block on single product pages.
add_action( 'woocommerce_before_related_products', 'wpstream_before_related_products' );
/**
 * Output custom content before the related products section.
 */
function wpstream_before_related_products() {
	// Placeholder text rendered before the related products list.
	echo '<p>This is some custom text before the related products:</p>';
}

/**
 * Save extra fields on account save.
 *
 * @param int $user_id The user ID.
 */
function wpstream_save_extra_fields_on_account_save( $user_id ) {
	// Verify nonce.
	// Abort unless the WooCommerce account-details nonce is present and valid.
	if ( ! isset( $_POST['woocommerce-save-account-details-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-save-account-details-nonce'] ) ), 'save_account_details' ) ) {
		return;
	}

	// Persist the custom profile picture reference, when submitted.
	if ( isset( $_POST['custom_picture'] ) ) {
		update_user_meta( $user_id, 'custom_picture', sanitize_text_field( wp_unslash( $_POST['custom_picture'] ) ) );
	}
	// Persist the small/thumbnail profile picture reference, when submitted.
	if ( isset( $_POST['custom_picture_small'] ) ) {
		update_user_meta( $user_id, 'custom_picture_small', sanitize_text_field( wp_unslash( $_POST['custom_picture_small'] ) ) );
	}
}
// Hook the extra-field save into WooCommerce's account-save action.
add_action( 'woocommerce_save_account_details', 'wpstream_save_extra_fields_on_account_save' );


/**
 * Remove "Add to Cart" button if product has already been purchased by the customer.
 *
 * @param bool       $purchasable Whether the product is purchasable.
 * @param WC_Product $product The product object.
 * @return bool Whether the product is purchasable.
 */
function wpstream_hide_add_to_cart_if_purchased( $purchasable, $product ) {
	// Check if the product is already purchased by the current customer.
	$product_type = $product->get_type();

	// Only guard the streaming/subscription product types (one-off ownership).
	if (
		'video_on_demand' === $product_type ||   // paid VOD
		'live_stream' === $product_type ||       // paid live channel
		'wpstream_bundle' === $product_type ||   // video collection bundle
		'subscription' === $product_type         // recurring subscription
	) {
		// Identify the logged-in customer.
		$current_user = wp_get_current_user();

		// If they already bought this product, mark it non-purchasable (hides Add to Cart).
		if ( is_user_logged_in() && wc_customer_bought_product( $current_user->user_email, get_current_user_id(), $product->get_id() ) ) {
			$purchasable = false;
		}
	}

	return $purchasable;
}

// Filter WooCommerce's purchasable check with the already-owned guard.
add_filter( 'woocommerce_is_purchasable', 'wpstream_hide_add_to_cart_if_purchased', 10, 2 );



// Register the custom bundle product type in the product-type dropdown.
add_filter( 'product_type_selector', 'wpstream_theme_add_products_type' );
/**
 * Add custom product type to WooCommerce.
 *
 * @param array $types List of existing product types.
 * @return array List of product types with the custom one added.
 */
function wpstream_theme_add_products_type( $types ) {
	// Expose the bundle type under a friendly "Video Collection" label.
	$types['wpstream_bundle'] = __( 'Video Collection', 'hello-wpstream' );
	return $types;
}

/**
 * Show the general tab for custom product types in WooCommerce.
 *
 * @param array $tabs List of product tabs.
 * @return array Modified list of product tabs.
 */
function wpstream_show_general_tab_for_custom_product( $tabs ) {
	// Make the General tab visible for our streaming/bundle product types.
	$tabs['general']['class'][] = 'show_if_live_stream  show_if_video_on_demand show_if_wpstream_bundle';
	return $tabs;
}

// Limit the number of orders shown on the My Account orders screen.
add_filter( 'woocommerce_my_account_my_orders_query', 'custom_my_orders_query' );

/**
 * Customizes the query for displaying customer orders.
 *
 * @param array $args Query arguments.
 * @return array Modified query arguments.
 */
function custom_my_orders_query( $args ) {
	// Show at most 5 orders per page.
	$args['posts_per_page'] = 5;
	return $args;
}



/**
 * Echo the media type badge (LIVE / VIDEO / COLLECTION / SUBSCRIPTION) for a product.
 *
 * Only runs for the 'product' post type and switches on the WooCommerce product
 * type to output the matching overlay badge markup.
 *
 * @param int        $product_id The product post ID.
 * @param WC_Product $product    The product object.
 * @return void
 */
function wpstream_theme_products_tag($product_id,$product){
	if ( get_post_type( $product_id ) === 'product') : 
			
		// Resolve the WooCommerce product type to decide which badge to show.
		$product_type = $product->get_type();
		
		// Pick the badge markup that matches the product type.
		switch ($product_type){
			// Live channel product: red "LIVE" badge.
			case "live_stream":
				?>
				<!-- LIVE badge overlay -->
				<div class="wpstream_featured_image_live_tag wpstream_hide_on_trailer wpstream_featured_image_live_tag--on-description"><?php echo esc_html__( 'LIVE', 'hello-wpstream' ); ?></div>
				<?php
			break;
			// On-demand video product: "VIDEO" badge.
			case "video_on_demand":
				?>
				<!-- VIDEO (VOD) badge overlay -->
				<div class="wpstream_featured_image_vod_tag wpstream_hide_on_trailer  wpstream_featured_image_live_tag--on-description"><?php echo esc_html__( 'VIDEO', 'hello-wpstream' ); ?></div>
				<?php
			break;
			// Bundle product: "COLLECTION" badge.
			case "wpstream_bundle":
				?>
				<!-- COLLECTION (bundle) badge overlay -->
				<div class="wpstream_featured_image_collection_tag wpstream_hide_on_trailer  wpstream_featured_image_live_tag--on-description"><?php echo esc_html__( 'COLLECTION', 'hello-wpstream' ); ?></div>
				<?php
			break;
			// Subscription product: "SUBSCRIPTION" badge.
			case "subscription":
				?>
				<!-- SUBSCRIPTION badge overlay -->
				<div class="wpstream_featured_image_subscription_tag wpstream_hide_on_trailer  wpstream_featured_image_live_tag--on-description"><?php echo esc_html__( 'SUBSCRIPTION', 'hello-wpstream' ); ?></div>
				<?php
			break;
		}
	endif;
		
}