<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/*
*  Video items functions
*
*/

if ( ! function_exists( 'wpstream_blog_list_shortcodes' ) ) {
	/**
	 * Process and display item list shortcodes.
	 *
	 * @param array $attributes Attributes for the item list shortcode.
	 */
	function wpstream_blog_list_shortcodes( $attributes ) {
		// populate from get if we have pagination.
		$attributes = wpstream_populate_from_get( $attributes );

		$item_post_type = 'post';
		$posts_per_page = intval( $attributes['number'] );

		$query_var_name = 'paged';
		if ( isset( $attributes['uid'] ) && $attributes['uid'] !== '' ) {
			$query_var_name .= '_' . $attributes['uid'];
		}

		$paged = 1;
		if ( isset( $_GET[ $query_var_name ] ) ) {
			$paged = intval( $_GET[ $query_var_name ] );
		}

		if ( isset( $attributes['is_ajax_request'] ) && 1 === intval( $attributes['is_ajax_request'] ) ) {
			$paged = intval( $attributes['paged'] );
		}

 		$card_grid_class_overwrite = wpstream_video_cards_column_class( intval( $attributes['rownumber'] ) );

		$tax_query_array = array(
			'relation' => 'AND',
		);

		$wpstream_item_list_shortcodes_input_to_tax = wpstream_item_list_shortcodes_input_to_tax();

		foreach ( $wpstream_item_list_shortcodes_input_to_tax as $input => $taxonomy ) :
			if ( isset( $attributes[ $input ] ) && ! empty( $attributes[ $input ] ) ) {
				$tax_array         = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array( $attributes[ $input ] ),
				);
				$tax_query_array[] = $tax_array;

			}
		endforeach;

		$args = array(
			'post_type'      => $item_post_type,
			'post_status'    => 'publish',
			'paged'          => $paged,
			'posts_per_page' => $posts_per_page,
			'tax_query'      => $tax_query_array, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);

//		$order_array = wpstream_query_arguments_add_order_by( $attributes['sort_by'] );
//		$args        = array_merge( $args, $order_array['order_array'] );

		$use_transient = wpstream_return_use_transient();
		$use_transient = false;
		$transient_key = 'wpstream_blog_post_' . $taxonomy . '_to_be_detterminded';
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );
			

		if ( isset( $attributes['is_elementor'] ) && $attributes['is_elementor'] ) {

			print '<div class="wpstream-shortcode-list-wrapper ">';
			print wpstream_filter_bar_blog_posts( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			print '<div class="wpstream-shortcode-wrapper wpstream-blog-list-wrapper row">';
			print wpstream_compose_ajax_holder_data( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $query->have_posts() ) :
				while ( $query->have_posts() ) :
					$query->the_post();

					include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/template-parts/single/cards/blog-card-v1.php';

				endwhile;
			endif;

			wp_reset_postdata();
			wp_reset_query();

			print '</div>';

			wpstream_return_item_list_pagination( $query, $attributes['pagination_type'], $attributes['uid'] );

			print '</div>';

		} elseif ( isset( $attributes['is_ajax_request'] ) && $attributes['is_ajax_request'] ) {
			$response_for_ajax                = wpstream_return_ajax_elements( $query, $card_grid_class_overwrite, 'blog' );
			$response_for_ajax['arg']         = $args;
			$response_for_ajax['found_posts'] = $query->found_posts;
			$response_for_ajax['card_grid_class_overwrite']= $card_grid_class_overwrite;
			$response_for_ajax['rownumber']=$attributes['rownumber'];
			if ( 2 === intval( $attributes['pagination_type'] ) ) {

				$response_for_ajax['html_pagination'] = wpstream_theme_pagination_ajax( $attributes, $query->max_num_pages, 2 );
			} else {
				$response_for_ajax['html_pagination'] = '<button type="button" class="btn btn-primary wpstream_load_more">' . esc_html__( 'Load More', 'hello-wpstream' ) . '</button>';
			}
			wp_send_json_success( $response_for_ajax );
			die();
		}
	}
}

/**
 * Return the transient option
 */
if ( ! function_exists( 'wpstream_return_use_transient' ) ) {
	/**
	 * Return use of transient
	 */
	function wpstream_return_use_transient() {
		$use_transient = false;

		return $use_transient;
	}
}

/**
 * Video item unit selection.
 */
if ( ! function_exists( 'wpstream_blog_post_card_selector' ) ) {
	function wpstream_blog_post_card_selector( $type = 0, $is_grid = 0 ) {
		if (intval($type) === 0) {
			$type = get_theme_mod('wpstream_theme_blog_post_card_type', 1);
		}

		$type == 1;
		if ($type == 1) {
			$template = 'cards/blog-card-v1.php';
		} elseif ($type == 2) {
			$template = 'cards/blog-card-v1.php';
		}

		return '/template-parts/single/' . $template;
	}
}


if ( ! function_exists( 'wpstream_get_author_id' ) ) {
	/**
	 * Retrieve the author ID of a post.
	 *
	 * @param int $post_id The ID of the post.
	 * @return int|null The author ID if found, otherwise null.
	 */
	function wpstream_get_author_id( $post_id ) {
		$author_id = get_post_field( 'post_author', $post_id );
		return is_numeric( $author_id ) ? intval( $author_id ) : null;
	}
}

if ( ! function_exists( 'wpstream_get_post_read_count_by_id' ) ) {
	/**
	 * Get the estimated read time of a post by its ID.
	 *
	 * Calculates and returns the estimated read time for a post based on its content length,
	 * assuming an average reading speed of 200 words per minute.
	 *
	 * @param int $post_id The ID of the post.
	 * @return string The estimated read time in a human-readable format.
	 */
	function wpstream_get_post_read_count_by_id( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return '';
		}

		$post_content = $post->post_content;
		$word_count   = str_word_count( wp_strip_all_tags( $post_content ) );

		// Calculate the estimated read time.
		$read_time = ceil( $word_count / 200 ); // Assuming an average reading speed of 200 words per minute.

		if ( $read_time <= 1 ) {
			return __( '1 min read', 'hello-wpstream' );
		} else {
			// translators: %d - time to read in minutes.
			return sprintf( _n( '%d min read', '%d mins read', $read_time, 'hello-wpstream' ), $read_time );
		}
	}
}

if ( ! function_exists( 'wpstream_get_post_published_duration_by_id' ) ) {
	/**
	 * Get the time elapsed since the post was published.
	 *
	 * This function calculates and returns the time elapsed since the post was published in a human-readable format.
	 *
	 * @param int $post_id The post ID.
	 * @return string The time elapsed since the post was published.
	 */
	function wpstream_get_post_published_duration_by_id( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return '';
		}

		$post_date    = strtotime( $post->post_date );
		$current_time = time();
		$time_diff    = $current_time - $post_date;

		if ( $time_diff < 60 ) {
			// translators: %s - time in seconds.
			$duration = sprintf( _n( '%s sec ago', '%s secs ago', $time_diff, 'hello-wpstream' ), $time_diff );
		} elseif ( $time_diff < 3600 ) {
			$minutes = floor( $time_diff / 60 );
			// translators: %s - time in minutes.
			$duration = sprintf( _n( '%s min ago', '%s mins ago', $minutes, 'hello-wpstream' ), $minutes );
		} elseif ( $time_diff < 86400 ) {
			$hours = floor( $time_diff / 3600 );
			// translators: %s - time in hours.
			$duration = sprintf( _n( '%s hour ago', '%s hours ago', $hours, 'hello-wpstream' ), $hours );
		} elseif ( $time_diff < 86400 * 365 ) {
			$days = floor( $time_diff / 86400 );
			// translators: %s - time in days.
			$duration = sprintf( _n( '%s day ago', '%s days ago', $days, 'hello-wpstream' ), $days );
		} else {
			$years = floor( $time_diff / ( 86400 * 365 ) );
			// translators: %s - time in years.
			$duration = sprintf( _n( '%s year ago', '%s years ago', $years, 'hello-wpstream' ), $years );
		}

		return $duration;
	}
}

if ( ! function_exists( 'wpstream_get_author_profile_image_url_by_author_id' ) ) {
	/**
	 * Retrieve the profile image URL of an author by their ID.
	 *
	 * This function retrieves the profile image URL of an author based on their ID and the specified image size.
	 *
	 * @param int    $author_id   The ID of the author.
	 * @param string $image_size  (optional) The size of the image. Default is '48'.
	 * @return string|null The profile image URL of the author, or null if the image is not found.
	 */
	function wpstream_get_author_profile_image_url_by_author_id( $author_id, $image_size = '48' ) {
		$profile_img_url = get_the_author_meta( 'custom_picture', $author_id );
		$author_gravatar_url = get_avatar_url( $author_id, array( 'size' => $image_size ) );

		return !empty($profile_img_url) ? $profile_img_url : $author_gravatar_url;
	}
}

/**
 * Article list
 *
 * @param array $attributes Attributes.
 */
if ( ! function_exists( 'wpstream_return_video_array' ) ) :
	function wpstream_return_video_array() {

		$article_array = false;
		if ( function_exists( 'wpstream_request_transient_cache' ) ) {
			$article_array = wpstream_request_transient_cache( 'wpstream_video_array' );
		}

		if ( boolval( $article_array ) === false ) {
			$args_inner           = array(
				'post_status'      => 'publish',
				'post_type'        => array( 'wpstream_product_vod', 'wpstream_product', 'product', 'wpstream_bundles' ),
				'showposts'        => -1,
				'suppress_filters' => false,
			);
			$article_array        = array();
			$all_article_packages = get_posts( $args_inner );
			if ( count( $all_article_packages ) > 0 ) {
				foreach ( $all_article_packages as $single_package ) {
						$temp_array          = array();
						$temp_array['label'] = $single_package->post_title;
						$temp_array['value'] = $single_package->ID;

						$article_array[] = $temp_array;
				}
			}
			wp_reset_query();
			wp_reset_postdata();
			if ( function_exists( 'wpstream_set_transient_cache' ) ) {
				wpstream_set_transient_cache( 'wpstream_video_array', $article_array, 60 * 60 * 4 );
			}
		}
		return $article_array;
	}
	endif;




/**
 * Article list
 *
 * @param array $attributes Attributes.
 */
if ( ! function_exists( 'wpstream_return_article_array' ) ) :
	function wpstream_return_article_array() {

		$article_array = false;
		if ( function_exists( 'wpstream_request_transient_cache' ) ) {
			$article_array = wpstream_request_transient_cache( 'wpstream_article_array' );
		}

		if ( boolval( $article_array ) === false ) {
			$args_inner           = array(
				'post_type'        => array( 'post' ),
				'showposts'        => -1,
				'suppress_filters' => false,
			);
			$article_array        = array();
			$all_article_packages = get_posts( $args_inner );
			if ( count( $all_article_packages ) > 0 ) {
				foreach ( $all_article_packages as $single_package ) {
						$temp_array          = array();
						$temp_array['label'] = $single_package->post_title;
						$temp_array['value'] = $single_package->ID;

						$article_array[] = $temp_array;
				}
			}
			wp_reset_query();
			wp_reset_postdata();
			if ( function_exists( 'wpstream_set_transient_cache' ) ) {
				wpstream_set_transient_cache( 'wpstream_article_array', $article_array, 60 * 60 * 4 );
			}
		}
		return $article_array;
	}
endif;


/**
 * Featured article.
 *
 * @param array $attributes Attributes.
 */
function wpstream_featured_article( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	$postId = intval( $attributes['id'] );
	$type   = sanitize_text_field( $attributes['type'] );

	$card_type = wpstream_featured_article_card_selector( $type, 0 );
	ob_start();

	include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' .  $card_type;
	$return_string = ob_get_contents();
	ob_end_clean();

	return $return_string;
}


/**
 * Featured article card selector.
 *
 * @param array $attributes Attributes.
 */

if ( ! function_exists( 'wpstream_featured_article_card_selector' ) ) :
	function wpstream_featured_article_card_selector( $type, $is_grid = 0 ) {

		if ( $type == 1 ) {
			$template = 'featured_article_type1.php';
		} elseif ( $type == 2 ) {
			$template = 'featured_article_type2.php';
		}

		return 'template-parts/blog-unit-templates/' . $template;
	}
endif;


/**
 * List items by id.
 *
 * @param array $attributes Attributes.
 */
if ( ! function_exists( 'wpstream_theme_list_blog_by_id_function' ) ) :
	function wpstream_theme_list_blog_by_id_function( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		$return_string   = '';
		$blog_ids_string = '';
		$blog_ids        = array();

		if (isset($attributes['blog_ids'])) {
			if (is_array($attributes['blog_ids'])) {
				$blog_ids = $attributes['blog_ids'];
				$blog_ids_string = implode('_', $blog_ids);
			} else {
				// If it's already a string, just use it directly
				$blog_ids = array($attributes['blog_ids']);
				$blog_ids_string = $attributes['blog_ids'];
			}
		}

		
		$items_per_row = 3;
		if ( isset( $attributes['items_per_row'] ) ) {
			$items_per_row = $attributes['items_per_row'];
		}

		$card_grid_class_overwrite = wpstream_video_cards_column_class( intval( $items_per_row ) );

		$post_number_total = count( $blog_ids );
		if ( $post_number_total === 0 ) {
			return;
		}

		$args = array(
			'post_status'    => 'publish',
			'post_type'      => array( 'post' ),
			'post__in'       => $blog_ids,
			'paged'          => 0,
			'posts_per_page' => $post_number_total,
			'orderby'        => 'post__in',
		);

		$use_transient = wpstream_return_use_transient();

		$transient_key = 'wpstream_blog_by_id_' . $blog_ids_string;
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );
		$blog_unit_card_type = wpstream_blog_post_card_selector( 0 );
		ob_start();

		if ( $query->have_posts() ) :
			while ( $query->have_posts() ) :
				$query->the_post();
				
				include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $blog_unit_card_type;

			endwhile;
		endif;

		wp_reset_postdata();
		wp_reset_query();

		$items_list = ob_get_contents();
		ob_end_clean();

		$return_string  = '<div class="wpstream-shortcode-wrapper wpstream-blog-by-id-wrapper row">';
		$return_string .= $items_list;
		$return_string .= '</div>';

		return $return_string;
	}
	endif;






	/**
 * Slider items.
 *
 * @param array  $attributes Attributes.
 * @param string $slider_id Slider id.
 * @return void
 */
if ( ! function_exists( 'wpestream_blog_post_slider_items' ) ) :
	function wpestream_blog_post_slider_items( $attributes, $slider_id ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		$return_string     = '';
		$arrow_extra_class = '';
		if ( isset( $attributes['arrows_position'] ) ) {
			$arrow_extra_class = 'wpstream_arrows_position_' . $attributes['arrows_position'];
		}

		$posts_per_page = 3;
		if ( isset( $attributes['number'] ) ) {
			$posts_per_page = intval( $attributes['number'] );
		}

		$card_grid_class_overwrite = wpstream_video_cards_column_class( intval( $attributes['rownumber'] ) );

		$tax_query_array = array(
			'relation' => 'AND',
		);

		$wpstream_item_list_shortcodes_input_to_tax = wpstream_item_list_shortcodes_input_to_tax();

		foreach ( $wpstream_item_list_shortcodes_input_to_tax as $input => $taxonomy ) :
			if ( isset( $attributes[ $input ] ) && ! empty( $attributes[ $input ] ) ) {
				$tax_array         = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array( $attributes[ $input ] ),
				);
				$tax_query_array[] = $tax_array;

			}
		endforeach;

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'paged'          => -1,
			'posts_per_page' => $posts_per_page,
			'tax_query'      => $tax_query_array, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);
	

		$order_array = wpstream_query_arguments_add_order_by( $attributes['sort_by'] );
		$args        = array_merge( $args, $order_array['order_array'] );

		$use_transient = wpstream_return_use_transient();
		$use_transient = false;
		$transient_key = 'wpstream_post_slider_' . $slider_id;
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );

		ob_start();
		if ( $query->have_posts() ) :
			while ( $query->have_posts() ) :
				$query->the_post();
				$blog_unit_card_type = wpstream_blog_post_card_selector( 0 );
				include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $blog_unit_card_type;

			endwhile;
		endif;

		$items_list = ob_get_contents();
		ob_end_clean();

		$return_string .= '<div class="wpstream-shortcode-wrapper wpstream-blog-post-slider wpstream-item-list-slider row ' . esc_attr( $arrow_extra_class ) . '" data-items-per-row="' . intval( $attributes['rownumber'] ) . '" data-auto="' . intval( $attributes['autoscroll'] ) . '" id="' . esc_attr( $slider_id ) . '">';
		$return_string .= $items_list;
		$return_string .= '</div>';

		wp_reset_postdata();
		wp_reset_query();

		return $return_string;
	}
	endif;

if ( ! function_exists( 'wpstream_theme_show_watch_later' ) ) {
	/**
	 * Display the watch later icons for a specific post.
	 *
	 * This function generates HTML code to display watch later icons for a given post.
	 *
	 * @param int $post_id The ID of the post.
	 * @return string HTML code for watch later icons wrapped in a div container.
	 */
	function wpstream_theme_show_watch_later( $post_id ) {
		$return_string  = '<div class="wpstream-show-watch-later-wrapper">';
		$return_string .= wpstream_theme_show_watch_later_icons( $post_id );
		$return_string .= '</div>';

		return $return_string;
	}
}

if ( ! function_exists( 'wpstream_theme_show_watch_later_icons' ) ) {
	/**
	 * Display watch later icons for a specific post.
	 *
	 * This function generates HTML code to display watch later icons for a given post. It checks if the
	 * current user has added the post to their watch later list and displays appropriate icons and status.
	 *
	 * @param int $post_id The ID of the post.
	 * @return string HTML code for watch later icons.
	 */
	function wpstream_theme_show_watch_later_icons( $post_id ) {
		$user_id           = get_current_user_id();
		$watch_later_items = get_user_meta( $user_id, 'wpstream_user_watch_later_items', true );
		$extra_class       = '';

		if ( is_array( $watch_later_items ) && in_array( $post_id, $watch_later_items, true ) ) {
			$extra_class = 'wpstream_already_watched_later';

			// Set a different SVG icon path when post is in "Watch Later" list.
			$icon_name                   = 'tick-circle.svg';
			$wpstream_water_later_status = esc_html__( 'Added to Watch Later', 'hello-wpstream' );
		} else {
			// Set the default SVG icon path.
			$icon_name                   = 'folder-plus.svg';
			$wpstream_water_later_status = esc_html__( 'Watch Later', 'hello-wpstream' );
		}

		$display_watch_later= true;
		$post_type = get_post_type( $post_id );

		if($post_type == 'product' && function_exists('wc_get_product') ){
			$product 		= wc_get_product( $post_id);

			$product_type 	= '';
			if($product){
				$product_type 	= $product->get_type();
			}

			$permited_values= array('subscription','product', 'live_stream','video_on_demand','wpstream_bundle');



			if(  !in_array($product_type, $permited_values) ){
				$display_watch_later= false;
			}
		}



		if($display_watch_later){

			if ( is_user_logged_in() ) {
				$return_string = '<div class="wpstream-watch-later-action meta-style ' . esc_attr( $extra_class ) . '" '
					. 'data-postID="' . esc_attr( $post_id ) . '">'
					. wpstream_theme_get_svg_icon( $icon_name ) . '<span>' . $wpstream_water_later_status . '</span>'
					. '</div>';
			} else {
				$return_string = '  <div tabindex="0" class="wpstream-watch-later-action wpstream_no_action" data-toggle="tooltip" data-bs-placement="bottom" '
					. 'data-bs-original-title="' . esc_attr__( 'Sign in to watch later', 'hello-wpstream' ) . '"'
					. 'title="' . esc_attr__( 'Watch Later', 'hello-wpstream' ) . '"'
					. 'data-postID="' . esc_attr( $post_id ) . '">'
					. wpstream_theme_get_svg_icon( $icon_name ) . ' <span>' . $wpstream_water_later_status . '</span>'
					. '</div>';
			}

		}

		return $return_string ?? '';
	}
}