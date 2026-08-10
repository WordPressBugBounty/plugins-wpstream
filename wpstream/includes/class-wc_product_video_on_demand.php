<?php
/**
 * Custom WooCommerce product type used by WpStream to sell Pay-Per-View
 * Video-On-Demand (VOD) items. Registering a dedicated product class lets a
 * WooCommerce product behave as a "Video On Demand" (its own type slug) so the
 * paywall and cart logic can treat VODs differently from ordinary products.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */

/**
 * WooCommerce product representing a Video-On-Demand (Pay-Per-View) item.
 */
class WC_Product_Video_On_Demand extends WC_Product {

    /**
     * Build the product and declare the features it supports.
     *
     * @param int|WC_Product $product Product ID or object handed to WC_Product.
     */
    public function __construct( $product ) {
        // Run the standard WC_Product setup (loads product data by ID/object).
        parent::__construct( $product );
          // Advertise AJAX add-to-cart support so the button works without a page reload.
          $this->supports[]   = 'ajax_add_to_cart';
    }

    /**
     * Return this product's unique type identifier.
     *
     * @return string The 'video_on_demand' product type slug.
     */
    public function get_type() {
        // WooCommerce keys product behaviour off this string.
        return 'video_on_demand';
    }
}