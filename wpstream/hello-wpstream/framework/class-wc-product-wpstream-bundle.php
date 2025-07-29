<?php
/**
 * WC product bundle
 *
 * @package wpstream-theme
 */

/**
 * Wc product bundle class.
 */
class WC_Product_Wpstream_Bundle extends WC_Product {
	/**
	 * Constructor
	 *
	 * @param mixed $product Объект продукта, который будет использоваться для инициализации.
	 */
	public function __construct( $product ) {
		parent::__construct( $product );
			$this->supports[] = 'ajax_add_to_cart';
	}

	/**
	 * Get type
	 */
	public function get_type() {
		return 'wpstream_bundle';
	}
}
