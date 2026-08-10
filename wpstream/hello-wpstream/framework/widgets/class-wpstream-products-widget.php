<?php
/**
 * Products widget
 *
 * Classic WP_Widget (via Wpstream_Widget_Base) that lists WooCommerce products.
 * Provides admin form settings (count, filter, ordering, hide-free, show-hidden),
 * builds the corresponding WP_Query, and renders each product with the theme's
 * video card partial. Only registered when WooCommerce (WC_Product) is present.
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define the widget only once, and only when WooCommerce is active.
if ( ! class_exists( 'Wpstream_Products_Widget' ) && class_exists( 'WC_Product' ) ) {
	/**
	 * Widget for displaying a list of products.
	 *
	 * @since 2.8.0
	 */
	class Wpstream_Products_Widget extends Wpstream_Widget_Base {

		/**
		 * Constructor.
		 *
		 * Declares the admin form settings schema and registers the widget.
		 *
		 * @return void
		 */
		public function __construct() {
			// Field schema consumed by Wpstream_Widget_Base to render the admin form.
			$this->settings = array(
				'title'       => array(
					'type'  => 'text',
					'std'   => __( 'Products', 'hello-wpstream' ),
					'label' => __( 'Title', 'hello-wpstream' ),
				),
				'number'      => array(
					'type'  => 'number',
					'step'  => 1,
					'min'   => 1,
					'max'   => '',
					'std'   => 5,
					'label' => __( 'Number of products to show', 'hello-wpstream' ),
				),
				'show'        => array(
					'type'    => 'select',
					'std'     => '',
					'label'   => __( 'Show', 'hello-wpstream' ),
					'options' => array(
						''         => __( 'All products', 'hello-wpstream' ),
						'featured' => __( 'Featured products', 'hello-wpstream' ),
						'onsale'   => __( 'On-sale products', 'hello-wpstream' ),
					),
				),
				'orderby'     => array(
					'type'    => 'select',
					'std'     => 'date',
					'label'   => __( 'Order by', 'hello-wpstream' ),
					'options' => array(
						'menu_order' => __( 'Menu order', 'hello-wpstream' ),
						'date'       => __( 'Date', 'hello-wpstream' ),
						'price'      => __( 'Price', 'hello-wpstream' ),
						'rand'       => __( 'Random', 'hello-wpstream' ),
						'sales'      => __( 'Sales', 'hello-wpstream' ),
					),
				),
				'order'       => array(
					'type'    => 'select',
					'std'     => 'desc',
					'label'   => _x( 'Order', 'Sorting order', 'hello-wpstream' ),
					'options' => array(
						'asc'  => __( 'ASC', 'hello-wpstream' ),
						'desc' => __( 'DESC', 'hello-wpstream' ),
					),
				),
				'hide_free'   => array(
					'type'  => 'checkbox',
					'std'   => 0,
					'label' => __( 'Hide free products', 'hello-wpstream' ),
				),
				'show_hidden' => array(
					'type'  => 'checkbox',
					'std'   => 0,
					'label' => __( 'Show hidden products', 'hello-wpstream' ),
				),
			);

			// Register the widget id base, admin title and description.
			parent::__construct(
				'wpstream-products',
				__( 'Wpstream Products list', 'hello-wpstream' ),
				array(
					'description' => __( "A list of your store's products.", 'hello-wpstream' ),
				)
			);
		}

		/**
		 * Query the products and return them.
		 *
		 * @param array $args Arguments.
		 * @param array $instance Widget instance.
		 *
		 * @return WP_Query
		 */
		public function get_products( $args, $instance ) {
			// WooCommerce must be available; bail otherwise.
			if(!function_exists('wc_get_product')) return;

			// Resolve each setting from the saved instance, falling back to its default.
			$number                      = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : $this->settings['number']['std'];
			$show                        = ! empty( $instance['show'] ) ? sanitize_title( $instance['show'] ) : $this->settings['show']['std'];
			$orderby                     = ! empty( $instance['orderby'] ) ? sanitize_title( $instance['orderby'] ) : $this->settings['orderby']['std'];
			$order                       = ! empty( $instance['order'] ) ? sanitize_title( $instance['order'] ) : $this->settings['order']['std'];
			// WooCommerce visibility term IDs (featured, exclude-from-catalog, outofstock, etc.).
			$product_visibility_term_ids = wc_get_product_visibility_term_ids();

			// Base query: published products, count/order applied, empty meta/tax filters to extend.
			$query_args = array(
				'posts_per_page' => $number,
				'post_status'    => 'publish',
				'post_type'      => 'product',
				'no_found_rows'  => 1,
				'order'          => $order,
				'meta_query'     => array(), //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'tax_query'      => array( //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'relation' => 'AND',
				),
			);

			// Unless "show hidden" is enabled, exclude catalog/search-hidden products and children.
			if ( empty( $instance['show_hidden'] ) ) {
				$query_args['tax_query'][] = array(
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => is_search() ? $product_visibility_term_ids['exclude-from-search'] : $product_visibility_term_ids['exclude-from-catalog'],
					'operator' => 'NOT IN',
				);
				$query_args['post_parent'] = 0;
			}

			// Optionally drop free products (price must be greater than 0).
			if ( ! empty( $instance['hide_free'] ) ) {
				$query_args['meta_query'][] = array(
					'key'     => '_price',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'DECIMAL',
				);
			}

			// Honor the store-wide "hide out of stock items" setting.
			if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
				$query_args['tax_query'][] = array(
					array(
						'taxonomy' => 'product_visibility',
						'field'    => 'term_taxonomy_id',
						'terms'    => $product_visibility_term_ids['outofstock'],
						'operator' => 'NOT IN',
					),
				);
			}

			// Apply the "Show" filter: featured products or on-sale products.
			switch ( $show ) {
				case 'featured':
					// Limit to products carrying the "featured" visibility term.
					$query_args['tax_query'][] = array(
						'taxonomy' => 'product_visibility',
						'field'    => 'term_taxonomy_id',
						'terms'    => $product_visibility_term_ids['featured'],
					);
					break;
				case 'onsale':
					// Limit to on-sale IDs (append 0 so an empty set still yields no matches).
					$product_ids_on_sale    = wc_get_product_ids_on_sale();
					$product_ids_on_sale[]  = 0;
					$query_args['post__in'] = $product_ids_on_sale;
					break;
			}

			// Translate the "Order by" setting into WP_Query orderby/meta_key.
			switch ( $orderby ) {
				case 'menu_order':
					// Manual menu order.
					$query_args['orderby'] = 'menu_order';
					break;
				case 'price':
					// Order numerically by the _price meta.
					$query_args['meta_key'] = '_price'; //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					$query_args['orderby']  = 'meta_value_num';
					break;
				case 'rand':
					// Random order.
					$query_args['orderby'] = 'rand';
					break;
				case 'sales':
					// Order numerically by total_sales meta (best sellers).
					$query_args['meta_key'] = 'total_sales'; //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					$query_args['orderby']  = 'meta_value_num';
					break;
				default:
					// Fall back to publish date.
					$query_args['orderby'] = 'date';
			}

			// Execute and return the assembled query.
			return new WP_Query( $query_args );
		}

		/**
		 * Output widget.
		 *
		 * @param array $args Arguments.
		 * @param array $instance Widget instance.
		 *
		 * @see WP_Widget
		 */
		public function widget( $args, $instance ) {
			// Tell WooCommerce loop templates they are rendering inside a widget.
			wc_set_loop_prop( 'name', 'widget' );

			// Run the configured product query.
			$products = $this->get_products( $args, $instance );

			// Only render when the query returned products.
			if ( $products && $products->have_posts() ) {
				// Output the theme's widget wrapper + title.
				$this->widget_start( $args, $instance );

				// Open the product list.
				echo '<ul class="wpstream-product-list-widget">';

				// Loop each product and render it via the selected card partial.
				while ( $products->have_posts() ) {
					echo '<li>';
					$products->the_post();
					// Pick the appropriate card template for the current item.
					$unit_card_type	= wpstream_video_item_card_selector();
					include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;
					echo '</li>';
				}

				// Close the product list.
				echo '</ul>';

				// Output the theme's widget closing wrapper.
				$this->widget_end( $args );
			}

			// Restore the main query's post data.
			wp_reset_postdata();
		}
	}
}
