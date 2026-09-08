<?php
/**
 * WC product bundle
 *
 * Defines the `wpstream_bundle` WooCommerce product type used to sell a
 * collection ("bundle") of WpStream video items as a single purchasable
 * product. Extends the base WC_Product so WooCommerce treats bundles like any
 * other product while reporting a distinct product type slug.
 *
 * @package wpstream-theme
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wc product bundle class.
 *
 * Custom WooCommerce product class for WpStream video collections/bundles.
 */
class WC_Product_Wpstream_Bundle extends WC_Product {
	/**
	 * Constructor
	 *
	 * Initializes the product via the parent WC_Product constructor and flags
	 * it as supporting AJAX add-to-cart so bundles can be added without a page
	 * reload.
	 *
	 * The default mirrors WC_Product's own signature: narrowing it would make an
	 * argument-less `new WC_Product_Wpstream_Bundle()` a fatal, even though the
	 * parent contract allows it.
	 *
	 * @param mixed $product Product object/ID used to initialize this instance.
	 */
	public function __construct( $product = 0 ) {
		// Let the base WC_Product set up all standard product data first.
		parent::__construct( $product );
			// Advertise AJAX add-to-cart support for this bundle product type.
			$this->supports[] = 'ajax_add_to_cart';
	}

	/**
	 * Get type
	 *
	 * @return string The product type slug WooCommerce uses to identify bundles.
	 */
	public function get_type() {
		// Report this product's custom WooCommerce type identifier.
		return 'wpstream_bundle';
	}
}
