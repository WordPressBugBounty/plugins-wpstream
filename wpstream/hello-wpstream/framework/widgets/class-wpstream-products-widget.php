<?php
/**
 * Products widget
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wpstream_Products_Widget' ) && class_exists( 'WC_Product' ) ) {
	/**
	 * Widget for displaying a list of products.
	 *
	 * @since 2.8.0
	 */
	class Wpstream_Products_Widget extends Wpstream_Widget_Base {

		/**
		 * Constructor.
		 */
		public function __construct() {
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
			if(!function_exists('wc_get_product')) return;

			$number                      = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : $this->settings['number']['std'];
			$show                        = ! empty( $instance['show'] ) ? sanitize_title( $instance['show'] ) : $this->settings['show']['std'];
			$orderby                     = ! empty( $instance['orderby'] ) ? sanitize_title( $instance['orderby'] ) : $this->settings['orderby']['std'];
			$order                       = ! empty( $instance['order'] ) ? sanitize_title( $instance['order'] ) : $this->settings['order']['std'];
			$product_visibility_term_ids = wc_get_product_visibility_term_ids();

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

			if ( empty( $instance['show_hidden'] ) ) {
				$query_args['tax_query'][] = array(
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => is_search() ? $product_visibility_term_ids['exclude-from-search'] : $product_visibility_term_ids['exclude-from-catalog'],
					'operator' => 'NOT IN',
				);
				$query_args['post_parent'] = 0;
			}

			if ( ! empty( $instance['hide_free'] ) ) {
				$query_args['meta_query'][] = array(
					'key'     => '_price',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'DECIMAL',
				);
			}

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

			switch ( $show ) {
				case 'featured':
					$query_args['tax_query'][] = array(
						'taxonomy' => 'product_visibility',
						'field'    => 'term_taxonomy_id',
						'terms'    => $product_visibility_term_ids['featured'],
					);
					break;
				case 'onsale':
					$product_ids_on_sale    = wc_get_product_ids_on_sale();
					$product_ids_on_sale[]  = 0;
					$query_args['post__in'] = $product_ids_on_sale;
					break;
			}

			switch ( $orderby ) {
				case 'menu_order':
					$query_args['orderby'] = 'menu_order';
					break;
				case 'price':
					$query_args['meta_key'] = '_price'; //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					$query_args['orderby']  = 'meta_value_num';
					break;
				case 'rand':
					$query_args['orderby'] = 'rand';
					break;
				case 'sales':
					$query_args['meta_key'] = 'total_sales'; //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					$query_args['orderby']  = 'meta_value_num';
					break;
				default:
					$query_args['orderby'] = 'date';
			}

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
			wc_set_loop_prop( 'name', 'widget' );

			$products = $this->get_products( $args, $instance );

			if ( $products && $products->have_posts() ) {
				$this->widget_start( $args, $instance );

				echo '<ul class="wpstream-product-list-widget">';

				while ( $products->have_posts() ) {
					echo '<li>';
					$products->the_post();
					$unit_card_type	= wpstream_video_item_card_selector();
					include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;
					echo '</li>';
				}

				echo '</ul>';

				$this->widget_end( $args );
			}

			wp_reset_postdata();
		}
	}
}
