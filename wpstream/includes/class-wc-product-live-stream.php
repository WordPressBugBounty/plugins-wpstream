<?php
/**
 * Custom WooCommerce product type used by WpStream to sell Pay-Per-View live
 * channels. Registering a dedicated product class lets a WooCommerce product
 * behave as a "Live Channel" (its own type slug) so the paywall and cart logic
 * can treat live streams differently from ordinary products.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce product representing a live-stream (Pay-Per-View) channel.
 */
 class WC_Product_Live_Stream extends WC_Product{

    /**
     * Build the product and declare the features it supports.
     *
     * The default mirrors WC_Product's own signature: narrowing it would make
     * an argument-less `new WC_Product_Live_Stream()` a fatal, even though the
     * parent contract allows it.
     *
     * @param int|WC_Product $product Product ID or object handed to WC_Product.
     */
    public function __construct( $product = 0 ) {
        // Run the standard WC_Product setup (loads product data by ID/object).
        parent::__construct( $product );
          // Advertise AJAX add-to-cart support so the button works without a page reload.
          $this->supports[]   = 'ajax_add_to_cart';

          /**
           * Filter the WooCommerce features a WpStream product type supports.
           *
           * @since 4.14.0
           *
           * @param string[] $supports Feature slugs (see WC_Product::supports()).
           * @param string   $type     `live_stream` or `video_on_demand`.
           */
          $this->supports = (array) apply_filters( 'wpstream_product_supports', $this->supports, 'live_stream' );
    }

    // Needed since Woocommerce version 3
    /**
     * Return this product's unique type identifier.
     *
     * @return string The 'live_stream' product type slug.
     */
    public function get_type() {
        // WooCommerce keys product behaviour off this string.
        return 'live_stream';
    }
}