<?php
/**
 * Deep Module owning Media List option meaning and rendered output.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Wpstream_Media_List_Active_Catalog_Port {
	/** @return array|false Active Channel post IDs, or false when unavailable. */
	public function active_channel_ids();
}

class Wpstream_Baker_Media_List_Active_Catalog implements Wpstream_Media_List_Active_Catalog_Port {
	private $connection;

	public function __construct( $connection ) {
		$this->connection = $connection;
	}

	public function active_channel_ids() {
		return $this->connection->api20_wpstream_request_live_stream_for_user_for_shortcode( 'shortcode' );
	}
}

class Wpstream_Media_List {
	private $active_catalog;

	public function __construct( Wpstream_Media_List_Active_Catalog_Port $active_catalog = null ) {
		$this->active_catalog = $active_catalog;
	}

	/**
	 * Return the canonical choices and defaults used by builder adapters.
	 *
	 * @param string $kind `live_channel` or `vod`.
	 * @return array
	 */
	public function catalog( $kind ) {
		return array(
			'defaults'             => array(
				'access'         => 'free',
				'active_only'    => false,
				'items_per_page' => 3,
				'columns'        => 3,
				'free_label'     => '',
				'order'          => 'date_asc',
			),
			'builder_defaults'      => array(
				'free_label' => esc_html__( 'Watch now!', 'wpstream' ),
			),
			'choices'              => array(
				'access' => array(
					'free' => esc_html__( 'Free', 'wpstream' ),
					'paid' => esc_html__( 'Paid', 'wpstream' ),
				),
				'order'  => array(
					'date_asc'   => esc_html__( 'By date - ASC', 'wpstream' ),
					'date_desc'  => esc_html__( 'By date - DESC', 'wpstream' ),
					'title_asc'  => esc_html__( 'By title - ASC', 'wpstream' ),
					'title_desc' => esc_html__( 'By title - DESC', 'wpstream' ),
				),
				'active_only' => array(
					false => esc_html__( 'no', 'wpstream' ),
					true  => esc_html__( 'yes', 'wpstream' ),
				),
			),
			'supports_active_only' => 'live_channel' === $kind,
		);
	}

	/**
	 * Render a Media List as a complete HTML string.
	 *
	 * @param string $kind    `live_channel` or `vod`.
	 * @param array  $options Builder or shortcode options.
	 * @return string
	 */
	public function render( $kind, array $options ) {
		$catalog      = $this->catalog( $kind );
		$defaults     = $catalog['defaults'];
		$media_raw    = isset( $options['items_per_page'] ) ? $options['items_per_page'] : ( $options['media_number'] ?? $defaults['items_per_page'] );
		$media_value  = is_scalar( $media_raw ) ? trim( (string) $media_raw ) : '';
		$media_number = preg_match( '/^-?\d+$/', $media_value ) ? intval( $media_value ) : $defaults['items_per_page'];
		if ( -1 !== $media_number && $media_number < 1 ) {
			$media_number = $defaults['items_per_page'];
		}
		$columns_raw   = isset( $options['columns'] ) ? $options['columns'] : ( $options['row_number'] ?? $defaults['columns'] );
		$columns_value = is_scalar( $columns_raw ) ? trim( (string) $columns_raw ) : '';
		$columns       = preg_match( '/^\d+$/', $columns_value ) ? intval( $columns_value ) : $defaults['columns'];
		if ( $columns < 1 || $columns > 4 ) {
			$columns = $defaults['columns'];
		}
		$label_raw    = $options['free_label'] ?? $defaults['free_label'];
		$free_label   = is_scalar( $label_raw ) ? wp_kses_post( (string) $label_raw ) : '';
		$access_raw   = isset( $options['access'] ) ? $options['access'] : ( $options['product_type_free_paid'] ?? $defaults['access'] );
		$access_value = is_scalar( $access_raw ) ? strtolower( trim( (string) $access_raw ) ) : '';
		$is_paid      = in_array( $access_value, array( '1', 'paid', strtolower( __( 'Paid', 'wpstream' ) ) ), true );
		$order_raw    = isset( $options['order'] ) ? $options['order'] : ( $options['order_by'] ?? $defaults['order'] );
		$order_value  = is_scalar( $order_raw ) ? strtolower( trim( (string) $order_raw ) ) : '';
		$order_map    = array(
			'0'                                                  => array( 'date', 'ASC' ),
			'date_asc'                                           => array( 'date', 'ASC' ),
			strtolower( __( 'By date - ASC', 'wpstream' ) )       => array( 'date', 'ASC' ),
			'1'                                                  => array( 'date', 'DESC' ),
			'date_desc'                                          => array( 'date', 'DESC' ),
			strtolower( __( 'By date - DESC', 'wpstream' ) )      => array( 'date', 'DESC' ),
			'2'                                                  => array( 'title', 'ASC' ),
			'title_asc'                                          => array( 'title', 'ASC' ),
			strtolower( __( 'By title - ASC', 'wpstream' ) )      => array( 'title', 'ASC' ),
			'3'                                                  => array( 'title', 'DESC' ),
			'title_desc'                                         => array( 'title', 'DESC' ),
			strtolower( __( 'By title - DESC', 'wpstream' ) )     => array( 'title', 'DESC' ),
		);
		$order_rule   = $order_map[ $order_value ] ?? array( 'date', 'ASC' );
		$current_page = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : 1;
		if ( is_front_page() ) {
			$current_page = get_query_var( 'page' ) ? absint( get_query_var( 'page' ) ) : 1;
		}
		$query_args   = array(
			'post_type'      => 'live_channel' === $kind ? 'wpstream_product' : 'wpstream_product_vod',
			'post_status'    => 'publish',
			'posts_per_page' => $media_number,
			'paged'          => max( 1, $current_page ),
			'orderby'        => $order_rule[0],
			'order'          => $order_rule[1],
		);

		if ( $is_paid ) {
			if ( ! function_exists( 'wc_get_template_part' ) ) {
				return esc_html__( 'No media found! ', 'wpstream' );
			}
			$query_args['post_type'] = 'product';
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => 'live_channel' === $kind ? 'live_stream' : 'video_on_demand',
				),
			);
		}

		$active_raw   = isset( $options['active_only'] ) ? $options['active_only'] : ( $options['product_show_live'] ?? false );
		$active_value = is_scalar( $active_raw ) ? strtolower( trim( (string) $active_raw ) ) : '';
		$active_only  = in_array( $active_value, array( '1', 'true', 'yes' ), true );
		if ( 'live_channel' === $kind && $active_only ) {
			$active_ids = $this->active_catalog ? $this->active_catalog->active_channel_ids() : false;
			$active_ids = is_array( $active_ids ) ? array_filter( array_map( 'absint', $active_ids ) ) : array();
			$active_ids[]           = 0;
			$query_args['post__in'] = array_values( array_unique( $active_ids ) );
		}

		/**
		 * Filter the WP_Query arguments of a Media List before it runs.
		 *
		 * `post_status` and `post__in` carry the list's visibility restriction;
		 * widening them lists non-public posts.
		 *
		 * @since 4.14.0
		 *
		 * @param array  $query_args WP_Query arguments (post type, paging, order, access filter).
		 * @param string $kind       `live_channel` or `vod`.
		 * @param array  $options    Raw builder or shortcode options.
		 */
		$query_args = (array) apply_filters( 'wpstream_media_list_query_args', $query_args, $kind, $options );
		$query      = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			$html = esc_html__( 'No media found! ', 'wpstream' )
				. $this->render_pagination( intval( $query->max_num_pages ), max( 1, $current_page ), 2 );
			return $this->filter_list_html( $html, $kind, $options, $query );
		}

		$html = '<ul class="wpstream_media_list_wrapper products columns-' . esc_attr( $columns ) . '" >';
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();

			// Build one item: the WooCommerce product card for paid lists, the plugin card for free ones.
			if ( $is_paid ) {
				ob_start();
				wc_get_template_part( 'content', 'product' );
				$item = ob_get_clean();
			} else {
				$item = $this->render_free_item( $free_label );
			}

			/**
			 * Filter one Media List item.
			 *
			 * The markup is already escaped; callbacks own the escaping of anything
			 * they add.
			 *
			 * @since 4.14.0
			 *
			 * @param string $item    Item markup (the `<li>`).
			 * @param int    $post_id Listed post ID.
			 * @param string $kind    `live_channel` or `vod`.
			 */
			$item = (string) apply_filters( 'wpstream_media_list_item_html', $item, $post_id, $kind );

			// Template slots around the item; whatever they echo joins the list.
			ob_start();
			/**
			 * Fires before one Media List item, in the free and the paid branch.
			 *
			 * @since 4.14.0
			 *
			 * @param int    $post_id Listed post ID.
			 * @param string $kind    `live_channel` or `vod`.
			 * @param array  $options Raw builder or shortcode options.
			 */
			do_action( 'wpstream_before_media_list_item', $post_id, $kind, $options );
			echo $item;
			/**
			 * Fires after one Media List item, in the free and the paid branch.
			 *
			 * @since 4.14.0
			 *
			 * @param int    $post_id Listed post ID.
			 * @param string $kind    `live_channel` or `vod`.
			 * @param array  $options Raw builder or shortcode options.
			 */
			do_action( 'wpstream_after_media_list_item', $post_id, $kind, $options );
			$html .= ob_get_clean();
		}
		$html .= '</ul>';

		wp_reset_postdata();
		$html .= $this->render_pagination( intval( $query->max_num_pages ), max( 1, $current_page ), 2 );
		return $this->filter_list_html( $html, $kind, $options, $query );
	}

	/**
	 * Render the plugin's own card for the current post of a free list.
	 *
	 * @param string $free_label Sanitised call-to-action label ('' hides the link).
	 * @return string `<li>` markup.
	 */
	private function render_free_item( $free_label ) {
		$thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id(), 'medium' );
		$source    = isset( $thumbnail[0] ) ? $thumbnail[0] : '';
		if ( '' === $source ) {
			$source = plugin_dir_url( dirname( __FILE__ ) ) . 'img/default_300.png';
		}

		$permalink = get_permalink();
		$title     = wp_kses_post( get_the_title() );
		$html      = '<li class="wpstream_product_unit">'
			. '<a href="' . esc_url( $permalink ) . '" class="product_title" ><div class="product_image" style="background-image:url(' . esc_url( $source ) . ')"></div></a>'
			. '<a href="' . esc_url( $permalink ) . '" class="product_title" >' . $title . '</a>';
		if ( '' !== $free_label ) {
			$html .= '<a href="' . esc_url( $permalink ) . '"class="see_product">' . $free_label . '</a>';
		}
		return $html . '</li>';
	}

	/**
	 * Apply the whole-list filter to whatever render() returns after the query ran.
	 *
	 * @param string   $html    List markup (or the "no media" notice).
	 * @param string   $kind    `live_channel` or `vod`.
	 * @param array    $options Raw builder or shortcode options.
	 * @param WP_Query $query   The query that produced the list.
	 * @return string
	 */
	private function filter_list_html( $html, $kind, array $options, WP_Query $query ) {
		/**
		 * Filter the complete Media List markup.
		 *
		 * The markup is already escaped; callbacks own the escaping of anything
		 * they add.
		 *
		 * @since 4.14.0
		 *
		 * @param string   $html    List markup, or the "no media" notice with pagination.
		 * @param string   $kind    `live_channel` or `vod`.
		 * @param array    $options Raw builder or shortcode options.
		 * @param WP_Query $query   The query that produced the list.
		 */
		return (string) apply_filters( 'wpstream_media_list_html', $html, $kind, $options, $query );
	}

	/**
	 * Render the existing numbered pagination window with valid list markup.
	 *
	 * @param int $pages        Total pages.
	 * @param int $current_page Current shared WordPress page.
	 * @param int $range        Links shown on either side.
	 * @return string
	 */
	private function render_pagination( $pages, $current_page, $range ) {
		if ( $pages <= 1 ) {
			return '';
		}

		$show_items = ( $range * 2 ) + 1;
		$html       = '<ul class="wpstream_pagination ">';
		$html      .= '<li class="roundleft"><a href="' . esc_url( get_pagenum_link( $current_page - 1 ) ) . '"> < </a></li>';

		for ( $page = 1; $page <= $pages; $page++ ) {
			if ( $page >= $current_page + $range + 1 || $page <= $current_page - $range - 1 ) {
				if ( $pages > $show_items ) {
					continue;
				}
			}

			$class = $current_page === $page ? ' class="active"' : '';
			$html .= '<li' . $class . '><a href="' . esc_url( get_pagenum_link( $page ) ) . '" >' . intval( $page ) . '</a></li>';
		}

		$next_page = min( $pages, $current_page + 1 );
		$html     .= '<li class="roundright"><a href="' . esc_url( get_pagenum_link( $next_page ) ) . '"> > </a></li>';
		$html     .= '</ul>';
		return $html;
	}
}
