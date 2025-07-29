<?php
/**
 * Woocommerce functions
 *
 * @package wpstream-theme
 */

// Require class wc product bundle.
if ( class_exists( 'WooCommerce' ) ) {
	require_once 'class-wc-product-wpstream-bundle.php';
}

add_filter( 'woocommerce_form_field_args', 'wpstream_theme_custom_remove_class_from_labels', 10, 3 );function wpstream_theme_custom_remove_class_from_labels( $args, $key, $value ) {
    // Specify the class you want to remove
    $class_to_remove = 'screen-reader-text';

    // Check if the label_class is set and contains the class we want to remove
    if ( isset( $args['label_class'] ) && ($key = array_search($class_to_remove, $args['label_class'])) !== false ) {
        unset($args['label_class'][$key]);
    }

    return $args;
}


add_action( 'woocommerce_before_related_products', 'wpstream_before_related_products' );
/**
 * Output custom content before the related products section.
 */
function wpstream_before_related_products() {
	echo '<p>This is some custom text before the related products:</p>';
}

/**
 * Save extra fields on account save.
 *
 * @param int $user_id The user ID.
 */
function wpstream_save_extra_fields_on_account_save( $user_id ) {
	// Verify nonce.
	if ( ! isset( $_POST['woocommerce-save-account-details-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-save-account-details-nonce'] ) ), 'save_account_details' ) ) {
		return;
	}

	if ( isset( $_POST['custom_picture'] ) ) {
		update_user_meta( $user_id, 'custom_picture', sanitize_text_field( wp_unslash( $_POST['custom_picture'] ) ) );
	}
	if ( isset( $_POST['custom_picture_small'] ) ) {
		update_user_meta( $user_id, 'custom_picture_small', sanitize_text_field( wp_unslash( $_POST['custom_picture_small'] ) ) );
	}
}
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

	if (
		'video_on_demand' === $product_type ||
		'live_stream' === $product_type ||
		'wpstream_bundle' === $product_type ||
		'subscription' === $product_type
	) {
		$current_user = wp_get_current_user();

		if ( is_user_logged_in() && wc_customer_bought_product( get_current_user_id(), $current_user->user_email, $product->get_id() ) ) {
			$purchasable = false;
		}
	}

	return $purchasable;
}

add_filter( 'woocommerce_is_purchasable', 'wpstream_hide_add_to_cart_if_purchased', 10, 2 );



add_filter( 'product_type_selector', 'wpstream_theme_add_products_type' );
/**
 * Add custom product type to WooCommerce.
 *
 * @param array $types List of existing product types.
 * @return array List of product types with the custom one added.
 */
function wpstream_theme_add_products_type( $types ) {
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
	$tabs['general']['class'][] = 'show_if_live_stream  show_if_video_on_demand show_if_wpstream_bundle';
	return $tabs;
}

add_filter( 'woocommerce_my_account_my_orders_query', 'custom_my_orders_query' );

/**
 * Customizes the query for displaying customer orders.
 *
 * @param array $args Query arguments.
 * @return array Modified query arguments.
 */
function custom_my_orders_query( $args ) {
	$args['posts_per_page'] = 5;
	return $args;
}



function wpstream_theme_products_tag($product_id,$product){
	if ( get_post_type( $product_id ) === 'product') : 
			
		$product_type = $product->get_type();
		
		switch ($product_type){
			case "live_stream":
				?>
				<div class="wpstream_featured_image_live_tag wpstream_hide_on_trailer wpstream_featured_image_live_tag--on-description"><?php echo esc_html__( 'LIVE', 'hello-wpstream' ); ?></div>
				<?php
			break;
			case "video_on_demand":
				?>
				<div class="wpstream_featured_image_vod_tag wpstream_hide_on_trailer  wpstream_featured_image_live_tag--on-description"><?php echo esc_html__( 'VIDEO', 'hello-wpstream' ); ?></div>
				<?php
			break;
			case "wpstream_bundle":
				?>
				<div class="wpstream_featured_image_collection_tag wpstream_hide_on_trailer  wpstream_featured_image_live_tag--on-description"><?php echo esc_html__( 'COLLECTION', 'hello-wpstream' ); ?></div>
				<?php
			break;
			case "subscription":
				?>
				<div class="wpstream_featured_image_subscription_tag wpstream_hide_on_trailer  wpstream_featured_image_live_tag--on-description"><?php echo esc_html__( 'SUBSCRIPTION', 'hello-wpstream' ); ?></div>
				<?php
			break;
		}
	endif;
		
}