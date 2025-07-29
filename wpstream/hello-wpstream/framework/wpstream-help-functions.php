<?php

if ( ! function_exists( 'wpstream_theme_featured_image' ) ) {
	/**
	 * Display the featured image for a post or page with additional functionality.
	 *
	 * @param int    $post_id The ID of the post or page.
	 * @param string $size    Optional. The image size to retrieve. Default is 'full'.
	 * @param string $context Optional. The context in which the function is being used. Default is 'unit_card'.
	 * @param bool   $link        Optional. Link to post.
	 * @param bool   $show_badge        Optional. Show badge.
	 * @return string         The HTML markup for the featured image.
	 */
	function wpstream_theme_featured_image( $post_id, $size = 'full', $context = 'unit_card', $link = false, $show_badge = false ) {
		$featured_image_src = wpstream_theme_default_card_no_image_url( $size );
		$featured_image_id  = get_post_thumbnail_id( $post_id );
		$featured_image_alt = get_post_meta( $featured_image_id, '_wp_attachment_image_alt', true );

		// Check if a featured image is set for the current post or page.

		if ( has_post_thumbnail( $post_id ) ) {
			$featured_image_src = get_the_post_thumbnail_url( $post_id, $size );
		}

		$video_attachment_id = get_post_meta( $post_id, 'video_preview', true );

		$return_string = '<div class="post-thumbnail wpstream_featured_image">';

		if ( $link ) {
			$return_string .= '<a class="d-block w-100 h-100" href="' . esc_url( get_permalink( $post_id ) ) . '">';
		}

		if ( ('unit_card' === $context || 'video_preview' === $context ) && '' !== $video_attachment_id ) {
			$video_id = 'wpstream_unit_video_preview_' . wp_rand( 0, 99999999 );

			$video_attachment_id = get_post_meta( $post_id, 'video_preview', true );

			$video_src = wp_get_attachment_url( $video_attachment_id );
			$play_button = $context === 'video_preview' ? '<div class="wpstream_video_unit_video_play">' . wpstream_theme_get_svg_icon('play_icon_white.svg') . '</div>' : "";

			if ($context === 'video_preview' && get_post_type($post_id) === 'post'){
				$play_button =  '<div class="wpstream_video_unit_video_play">' . wpstream_theme_get_svg_icon('bloghover.svg') . '</div>';
			}



			$return_string .= '<div class="wpstream_video_unit_video_wrapper wpstream_video_unit_video_wrapper_trigger" 
					data-video-id="'. esc_attr( $video_id ).'">'

				. $play_button
				.'<div class="wpstream_video_unit_overlay"></div>'
				.'<video id="' . esc_attr( $video_id ) . '" class="wpstream_video_unit_video" preload="none" poster="' . esc_url( $featured_image_src ) . '" muted>
					<source src="' . esc_url( $video_src ) . '" type="video/mp4">
					Your browser does not support the video tag.
				</video>

			</div>';
		} else {
			if( get_post_type($post_id) === 'post' || !wpstream_is_woo_video_product( $post_id ) ){
				$play_button =  '<div class="wpstream_video_unit_video_play">' . wpstream_theme_get_svg_icon('bloghover.svg') . '</div>';
			}else{
				$play_button =  '<div class="wpstream_video_unit_video_play">' . wpstream_theme_get_svg_icon('play_icon_white.svg') . '</div>';
			}


			$return_string .= '<div class="wpstream_video_unit_overlay"></div>'. $play_button .'<img src="' . esc_url( $featured_image_src ) . '" alt="' . esc_attr( ! empty( $featured_image_alt ) ? $featured_image_alt : get_the_title( $post_id ) ) . '" class="wpstream_featured_unit_cards" />';
		}

		if ( $link ) {
			$return_string .= '</a>';
		}

		if ( 'unit_card' === $context || $show_badge ) {
			$views       = intval( get_post_meta( $post_id, 'post_view_count', true ) );
			$views_label = __( 'reads', 'hello-wpstream' );
			if ( in_array(
				get_post_type( $post_id ),
				array(
					'wpstream_product_vod',
					'wpstream_product',
					'product',
					'wpstream_bundles'
				),
				true
			) ) {
				$views_label = __( 'views', 'hello-wpstream' );
			}
			$return_string .= '<div class="wpstream_featured_image_views">' . $views . ' ' . esc_html( $views_label ) . '</div>';
		}

		if ( ( get_post_type( $post_id ) === 'product' && has_term( 'live_stream', 'product_type', $post_id ) || get_post_type( $post_id ) === 'wpstream_product' ) ) {

			$live_events = wpestream_integrations_get_current_user_live_events('no');
			if ( is_array( $live_events) && array_key_exists( $post_id, $live_events ) ) {
				$return_string .= '<div class="wpstream_featured_image_live_tag">' . esc_html_x( 'LIVE', 'Card tag', 'hello-wpstream' ) . '</div>';
			}
		}

		$return_string .= '</div><!-- .post-thumbnail -->';

		return $return_string;
	}
}

if ( ! function_exists( 'wpstream_theme_default_card_no_image_url' ) ) {
	/**
	 * Returns the default URL for card images if no image is available.
	 *
	 * @param string $size The size of the card image.
	 * @return string The URL of the default image.
	 */
	function wpstream_theme_default_card_no_image_url( $size ) {
		if ( 'wpstream_bundle_unit_cards_image' === $size ) {
			$image_path = '/img/default-cover-big.png';
		} else {
			$image_path = '/img/default-cover.png';
		}

		// Define the default image URL.
		$default_image_url = get_stylesheet_directory_uri() . $image_path;

		// Return the default image URL.
		return esc_url( $default_image_url );
	}
}

if ( ! function_exists( 'wpstream_theme_get_svg_icon' ) ) {
	/**
	 * Get the SVG content from a specified SVG file.
	 *
	 * @param string $icon_path The path to the SVG icon file.
	 * @return string|false The SVG content or false if the file doesn't exist.
	 */
	function wpstream_theme_get_svg_icon( $icon_path ) {
		$icon_path = get_template_directory() . '/img/icons/' . $icon_path;

		// Check if the file exists.
		if ( file_exists( $icon_path ) ) {
			ob_start();
			include $icon_path;

			return ob_get_clean();
		} else {
			return false; // Return false if the icon file doesn't exist.
		}
	}
}

/**
 * Get the count of likes for a specific post.
 *
 * @param int $post_id The ID of the post.
 * @return int The count of likes for the post.
 */
function wpstream_get_count_like_post( $post_id ) {
	return intval( get_post_meta( $post_id, 'wpstream_like_items', true ) );
}

if ( ! function_exists( 'wpstream_query_arguments_add_order_by' ) ) {
	/**
	 * Generate query arguments for ordering posts.
	 *
	 * @param int $order The order value.
	 * @return array     An array containing the query arguments and transient appendix.
	 */
	function wpstream_query_arguments_add_order_by( $order ) {
		$meta_directions = 'DESC';
		$meta_order      = '';
		$order_by        = 'ID';

		switch ( $order ) {
			case 1:
				$meta_order      = '';
				$meta_directions = 'DESC';
				$order_by        = 'ID';
				break;
			case 2:
				$meta_order      = '';
				$meta_directions = 'ASC';
				$order_by        = 'ID';
				break;
			case 3:
				$meta_order      = '';
				$meta_directions = 'DESC';
				$order_by        = 'modified';
				break;
			case 4:
				$meta_order      = '';
				$meta_directions = 'ASC';
				$order_by        = 'modified';
				break;
			case 5:
				$meta_order      = 'post_view_count';
				$meta_directions = 'DESC';
				$order_by        = 'meta_value_num';
				break;
			case 6:
				$meta_order      = 'post_view_count';
				$meta_directions = 'ASC';
				$order_by        = 'meta_value_num';
				break;
			case 7:
				$meta_order      = '_price';
				$meta_directions = 'DESC';
				$order_by        = 'meta_value_num';
				break;
			case 8:
				$meta_order      = '_price';
				$meta_directions = 'ASC';
				$order_by        = 'meta_value_num';
				break;
			case 11:
				$meta_order      = 'wpstream_like_items';
				$meta_directions = 'DESC';
				$order_by        = 'meta_value_num';
				break;
			case 12:
				$meta_order      = 'wpstream_like_items';
				$meta_directions = 'ASC';
				$order_by        = 'meta_value_num';
				break;
			case 99:
				$meta_order      = '';
				$meta_directions = 'ASC';
				$order_by        = 'rand';
				break;
		}

		$transient_appendix = '_' . $meta_order . '_' . $meta_directions;

		if ( 0 === $order ) {
			$transient_appendix .= '_myorder';
		}

		$order_array = array(
			'orderby' => $order_by,
		);

		if ( '' !== $meta_order ) {
			$order_array['meta_key'] = $meta_order;//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}

		if ( '' !== $meta_directions ) {
			$order_array['order'] = $meta_directions;
		}

		return array(
			'order_array'        => $order_array,
			'transient_appendix' => $transient_appendix,
		);
	}
}

if ( ! function_exists( 'wpstream_post_type_options_select' ) ) {
	/**
	 * Generate a select dropdown for post type options.
	 *
	 * @param array  $attributes An array of attributes for the select dropdown.
	 * @param string $selected_option The selected option.
	 * @return string                 The HTML for the select dropdown.
	 */
	function wpstream_post_type_options_select( $attributes, $selected_option = '' ) {
		$options = wpstream_post_type_options();

		$name   = 'post_type';
		$class  = 'wpstream_dropdown_select   wpstream_dropdown_select_trigger_ajax wpstream_dropdown_select_post_type';
		$id     = 'post_type_dropdown';
		$label  = $attributes['label_post_types'];
		$output = wpstream_create_custom_dropdown( $options, $name, $class, $id, $label, $selected_option );


		return $output;
	}
}

if ( ! function_exists( 'wpstream_post_type_options' ) ) {
	/**
	 * Retrieve an array of available post type options.
	 *
	 * @return array An associative array of post type options.
	 */
	function wpstream_post_type_options() {
		$options = array(
			'product'              => esc_html__( 'Products', 'hello-wpstream' ),
			'wpstream_bundles'     => esc_html__( 'Bundles', 'hello-wpstream' ),
			'wpstream_product_vod' => esc_html__( 'Free Vod', 'hello-wpstream' ),
			'wpstream_product'     => esc_html__( 'Free Events', 'hello-wpstream' ),
		);

		return $options;
	}
}

if ( ! function_exists( 'wpstream_is_woo_video_product' ) ) {
	/**
	 * Check if the product is a WpStream video product.
	 *
	 * @param int $product_id The ID of the product.
	 * @return bool True if the product is a video product, false otherwise.
	 */
	function wpstream_is_woo_video_product( $product_id ) {
		$wpstream_woo_product_types = ['video_on_demand', 'live_stream', 'wpstream_bundle', 'subscription'];

		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return false;
		}

		return in_array( $product->get_type(), $wpstream_woo_product_types, true );
	}
}

/**
 * Get the full path for a given card type.
 *
 * @param string $card_type The card type template name.
 * @return string The full path to the card type template.
 */
if ( ! function_exists( 'wpstream_get_card_type_path' ) ) {
	function wpstream_get_card_type_path($card_type)
	{
		return WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $card_type;
	}
}

if ( ! function_exists( 'wpstream_get_all_items_list' ) ) {
	/**
	 * Get all items list.
	 *
	 * @param int    $limit     The limit of posts to retrieve.
	 * @param string $post_type The post type to retrieve.
	 * @param array  $ids       The array of post IDs to retrieve.
	 * @return array            Array containing titles and types of retrieved posts.
	 */
	function wpstream_get_all_items_list( $limit = -1, $post_type = 'free', $ids = array() ) {

		$options = array();

		if ( 'free' === $post_type ) {
			$post_type      = array( 'wpstream_product_vod', 'wpstream_product' );
			$taxonomy_array = array();
		} else {
			$post_type = array( 'product' );

			$taxonomy_array = array(
				'relation' => 'OR',
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => array( 'live_stream', 'video_on_demand' ),
				),

				array(
					'taxonomy' => 'product_type',
					'operator' => 'NOT EXISTS',
				),

			);

		}

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $limit,
			'tax_query'      => $taxonomy_array, //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);

		if ( ! empty( $ids ) ) {
			$args['post__in'] = $ids;
			$args['orderby']  = 'post__in';
		}

		$custom_post_types = get_posts( $args );

		$options = array();

		foreach ( $custom_post_types as $custom_post ) {

			$options[ $custom_post->ID ] = array(
				'title' => $custom_post->post_title,
				'type'  => $custom_post->post_type,
			);
		}

		return $options;
	}
}


if ( ! function_exists( 'wstream_sort_options_array' ) ) {
	/**
	 * Sort options array
	 */
	function wstream_sort_options_array() {
		$listing_filter_array = array(
			'1' => esc_html__( 'Newest first', 'hello-wpstream' ),
			'2' => esc_html__( 'Oldest first', 'hello-wpstream' ),
			'5' => esc_html__( 'Views High to Low', 'hello-wpstream' ),
			'6' => esc_html__( 'Views Low to high', 'hello-wpstream' ),
			'11' => esc_html__( 'Liked High to Low', 'hello-wpstream' ),
			'12' => esc_html__( 'Liked Low to high', 'hello-wpstream' ),
			'7' => esc_html__( 'Price High to Low', 'hello-wpstream' ),
			'8' => esc_html__( 'Price Low to High', 'hello-wpstream' ),
			'3' => esc_html__( 'Newest Edited', 'hello-wpstream' ),
			'4' => esc_html__( 'Oldest Edited ', 'hello-wpstream' ),
			'0' => esc_html__( 'Default', 'hello-wpstream' ),
		);

		return $listing_filter_array;
	}
}

if ( ! function_exists( 'wpstream_theme_is_show_sidebar' ) ) {
	/**
	 * Returns true if the sidebar should be display for post.
	 *
	 * @param $post_id
	 * @return bool
	 */
	function wpstream_theme_is_show_sidebar( $post_id = null ) {
		$is_show_sidebar=null;
		if(function_exists('rwmb_meta')){
			$is_show_sidebar = rwmb_meta( 'wpstream_theme_show_sidebar_on_post', array(), $post_id );
		}
		if ( '' !== $is_show_sidebar ) {
			return '1' === $is_show_sidebar;
		} else {
			$post_type = get_post_type( $post_id );

			switch ( $post_type ) {
				case 'post':
					$wpstream_show_sidebar = get_theme_mod( 'wpstream_blog_post_sidebar', true );
					break;
				default:
					$wpstream_show_sidebar = apply_filters( 'wpstream_show_sidebar_for_post_type', false, $post_type );
					break;
			}

			return $wpstream_show_sidebar;
		}
	}
}

if ( ! function_exists( 'wpstream_theme_featured_image_simple' ) ) {
	/**
	 * Display the featured image for a post or page in a simple format.
	 *
	 * @param int    $post_id The ID of the post or page.
	 * @param string $size    Optional. The image size to retrieve. Default is 'full'.
	 * @param string $context Optional. The context in which the function is being used. Default is 'unit_card'.
	 * @return string         The HTML markup for the featured image.
	 */
	function wpstream_theme_featured_image_simple( $post_id, $size = 'full', $context = 'unit_card' ) {

		$featured_image_src = wpstream_theme_default_card_no_image_url( $size );

		// Check if a featured image is set for the current post or page.
		if ( has_post_thumbnail( $post_id ) ) {
			$featured_image_src = get_the_post_thumbnail_url( $post_id, $size );
		}

		$return_string = '<div class="post-thumbnail wpstream_featured_image">';

		$return_string .= '<img src="' . esc_url( $featured_image_src ) . '" class="wpstream_featured_unit_cards rounded mb-3" />';

		if ( 'unit_card' === $context ) {
			$views          = intval( get_post_meta( $post_id, 'post_view_count', true ) );
			$return_string .= '<div class="wpstream_featured_image_views">' . $views . ' ' . esc_html__( 'views', 'hello-wpstream' ) . '</div>';
		}

		$return_string .= '</div><!-- .post-thumbnail -->';

		return $return_string;
	}
}

if ( ! function_exists( 'wpstream_generate_user_menu' ) ) {
	/**
	 * Generate user menu
	 */
	function wpstream_generate_user_menu() {
		$return_string = '';

		$svg_icons = [
			'dashboard' => wpstream_theme_get_svg_icon('dashboard.svg'),
			'orders' => wpstream_theme_get_svg_icon('orders-icon.svg'),
			'subscriptions' => wpstream_theme_get_svg_icon('orders-subscription.svg'),
			'edit-address' => wpstream_theme_get_svg_icon('location-icon.svg'),
			'edit-account' => wpstream_theme_get_svg_icon('edit-account-icon.svg'),
			'event-list' => wpstream_theme_get_svg_icon('purchased-events-icon.svg'),
			'video-list' => wpstream_theme_get_svg_icon('purchased-video-icon.svg'),
			'start-streaming' => wpstream_theme_get_svg_icon('go-to-live-icon.svg'),
			'watch-later' => wpstream_theme_get_svg_icon('watch-later-icon.svg'),
			'customer-logout' => wpstream_theme_get_svg_icon('logout_icon.svg'),
			'logout' => wpstream_theme_get_svg_icon('logout_icon.svg'),
		];

		ob_start();

		if ( function_exists( 'wc_get_account_menu_items' ) ) {
			$menu_items = wc_get_account_menu_items();

			if ( ! wpstream_check_if_user_can_stream()) {
				unset($menu_items['start-streaming']);
			}

			foreach ( $menu_items as $endpoint => $label ) :
				$menu_link= wc_get_account_endpoint_url( $endpoint ) ;
				if($endpoint === 'subscriptions') {
					$menu_link = home_url('/dashboard/subscriptions/');
				}
				?>
				<a href="<?php echo esc_url($menu_link); ?>" class="list-group-item list-group-item-action wpstream-account-item-<?php echo esc_attr( $endpoint ); ?>">
					<?php echo isset($svg_icons[$endpoint]) ? trim($svg_icons[$endpoint]) : ''; ?>
					<?php echo trim( esc_html( $label ));?>
				</a>
			<?php
			endforeach;

		} else {
			$menu_items = array(
				'dashboard'       => esc_html__( 'Dashboard', 'hello-wpstream' ),
				'start-streaming' => esc_html__( 'Go Live', 'hello-wpstream' ),
				'edit-account'    => esc_html__( 'Edit Account', 'hello-wpstream' ),
				'watch-later'     => esc_html__( 'Watch Later', 'hello-wpstream' ),
				'logout'          => esc_html__( 'Logout', 'hello-wpstream' ),
			);

			if ( ! wpstream_check_if_user_can_stream()) {
				unset($menu_items['start-streaming']);
			}

			foreach ( $menu_items as $endpoint => $label ) :
				$item_link = wpstream_non_woo_get_account_endpoint_url( $endpoint );
				if ( 'logout' === $endpoint ) {
					$item_link = wp_logout_url( home_url() );
				}
				?>

				<a href="<?php echo esc_url( $item_link ); ?>"
				   class="list-group-item list-group-item-action wpstream-account-item-<?php echo esc_attr( $endpoint ); ?>">
					<?php echo trim($svg_icons[ $endpoint ]) ?? ''; ?>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php

			endforeach;

		}

		$return_string = ob_get_contents();

		ob_end_clean();

		return $return_string;
	}
}

if ( ! function_exists( 'wpstream_dashboard_get_products_by_user' ) ) {
	/**
	 * Retrieve products purchased by a specific user.
	 *
	 * Retrieves all products of a specific type (default: 'video_on_demand') purchased by a user.
	 *
	 * @param int    $user_id      The ID of the user.
	 * @param string $product_type The type of products to retrieve. Default is 'video_on_demand'.
	 * @param int    $page         Optional. The page number of the results. Default is 1.
	 * @param int    $per_page     Optional. The number of products to retrieve per page. Default is 10.
	 * @return array An array containing paginated products, total number of products, and maximum number of pages.
	 */
	function wpstream_dashboard_get_products_by_user( $user_id, $product_type = 'video_on_demand', $page = 1, $per_page = 10 ) {
		$all_products = array();

		// Get all customer orders.
		$customer_orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'status'      => 'completed',
				'limit' 	=> -1
			)
		);

		// Loop through each customer order.
		foreach ( $customer_orders as $customer_order ) {
			$order_items    = $customer_order->get_items();
			$order          = wc_get_order( $customer_order );
			$order_id       = $order->get_id();
			$order_date     = $order->get_date_created();
			$formatted_date = wc_format_datetime( $order_date );

			foreach ( $order_items as $item ) {
				$product = $item->get_product();

				if ( $product && $product_type === $product->get_type() ) {
					$total_paid = $item->get_total();
					$temp_array = array(
						'price'      => $total_paid,
						'order_id'   => $order_id,
						'order_date' => $formatted_date,
						'product'    => $product,
					);

					$all_products[$product->get_id()] = $temp_array;
				}
			}
		}

		if(intval($per_page) === -1){
			return $all_products;
		}

		// Calculate offset for pagination.
		$offset             = ( $page - 1 ) * $per_page;
		$paginated_products = array_slice( $all_products, $offset, $per_page );

		return array(
			'products'      => $paginated_products,
			'total'         => count( $all_products ),
			'max_num_pages' => ceil( count( $all_products ) / $per_page ),
		);
	}
}

if ( ! function_exists( 'wpstream_non_woo_get_account_endpoint_url' ) ) {
	/**
	 * Retrieves the URL for a given account endpoint.
	 *
	 * @param string $endpoint Optional. Endpoint to append to the URL.
	 * @return string URL for the account endpoint.
	 */
	function wpstream_non_woo_get_account_endpoint_url( $endpoint = '' ) {
		$url = wpstream_return_dashboard_page();

		if ( $endpoint ) {
			$url = add_query_arg( 'endpoint', $endpoint, $url );
		}

		return esc_url_raw( $url );
	}
}

if ( ! function_exists( 'wpstream_theme_show_social_share_page' ) ) {
	/**
	 * Display social share buttons for a given post.
	 *
	 * @param int    $post_id   The ID of the post.
	 * @param string $size      Optional. The size of the post image. Default is 'full'.
	 * @param int    $is_single Optional. Whether the post is single or not. Default is 1 (true).
	 * @return string           The HTML markup for the social share buttons.
	 */
	function wpstream_theme_show_social_share_page( $post_id, $size = 'full', $is_single = 1 ) {
		$return_string  = '<div class="wpstream-social-share-wrapper">';
		$return_string .= wpstream_share_unit_desing( $post_id, $size, $is_single );
		$return_string .= '</div>';

		return $return_string;
	}
}

if ( ! function_exists( 'wpstream_share_unit_desing' ) ) {
	/**
	 * Generate social share buttons HTML for a post.
	 *
	 * @param int    $post_id   The ID of the post.
	 * @param string $size      Optional. The size of the post image. Default is 'full'.
	 * @param int    $is_single Optional. Whether the post is single or not. Default is 0 (false).
	 * @return string           The HTML markup for the social share buttons.
	 */
	function wpstream_share_unit_desing( $post_id, $size = 'full', $is_single = 0 ) {
		$protocol       = is_ssl() ? 'https' : 'http';
		$pinterest      = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ), $size );
		$link           = esc_url( get_permalink( $post_id ) );
		$title          = get_the_title( $post_id );
		$twitter_status = rawurlencode( $title . ' ' . $link );
		$email_link     = 'subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( esc_url( $link ) );

		ob_start();

		$facebook_label  = '';
		$twitter_label   = '';
		$pinterest_label = '';
		$whatsup_label   = '';
		$email_label     = '';

		if ( intval( $is_single ) === 1 ) {
			$facebook_label  = esc_html__( 'Facebook', 'hello-wpstream' );
			$twitter_label   = esc_html__( 'Twitter', 'hello-wpstream' );
			$pinterest_label = esc_html__( 'Pinterest', 'hello-wpstream' );
			$whatsup_label   = esc_html__( 'WhatsApp', 'hello-wpstream' );
			$email_label     = esc_html__( 'Email', 'hello-wpstream' );
		}

		$text_whats = get_the_title( $post_id ) . ' ' . esc_url( get_permalink( $post_id ) );

		$whatsup_link = 'https://wa.me/?text=' . ( $text_whats );

		?>

        <div class="share_unit">
            <a href="<?php echo esc_url( $protocol . '://www.facebook.com/sharer.php?u=' . $link . '&amp;t=' . rawurlencode( get_the_title() ) ); ?>"
               target="_blank" rel="noreferrer noopener nofollow" class="social_facebook d-flex align-items-center">

				<?php echo wpstream_theme_get_svg_icon( 'facebook.svg' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php echo esc_html( $facebook_label ); ?>

            </a>
            <a href="<?php echo esc_url( $protocol . '://twitter.com/intent/tweet?text=' . $twitter_status ); ?>"
               class="social_twitter d-flex align-items-center" rel="noreferrer noopener nofollow" target="_blank">

				<?php echo wpstream_theme_get_svg_icon( 'x_twitter.svg' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php echo esc_html( $twitter_label ); ?>

            </a>
            <a href="<?php echo esc_url( $protocol . '://pinterest.com/pin/create/button/?url=' . $link . '&amp;media=' .
				( isset( $pinterest[0] ) ? esc_url( $pinterest[0] ) : '' ) .
				'&amp;description=' . rawurlencode( get_the_title() ) ); ?>" target="_blank"
               rel="noreferrer noopener nofollow" class="social_pinterest d-flex align-items-center">

				<?php echo wpstream_theme_get_svg_icon( 'pinterest.svg' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php echo esc_html( $pinterest_label ); ?>

            </a>
            <a href="<?php echo esc_url( $whatsup_link ); ?>" class="social_whatsapp d-flex align-items-center"
               rel="noreferrer noopener nofollow" target="_blank">

				<?php echo wpstream_theme_get_svg_icon( 'whatsapp.svg' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php echo esc_html( $whatsup_label ); ?>

            </a>
            <a href="mailto:email@email.com?<?php echo esc_attr( trim( $email_link ) ); ?>" data-action="share email"
               class="social_email d-flex align-items-center" rel="noreferrer noopener nofollow">

				<?php echo wpstream_theme_get_svg_icon( 'email.svg' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php echo esc_html( $email_label ); ?>

            </a>
        </div>
		<?php

		return ob_get_clean();
	}
}