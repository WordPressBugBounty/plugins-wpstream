<?php
// Block direct access: this file must run inside WordPress.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/*
*  Video items functions
*
*/

/**
 * Elementor blog helpers.
 *
 * Rendering + data helpers behind the WpStream blog Elementor widgets:
 *   - list / by-id / slider renderers that query 'post' and buffer card templates,
 *   - label/value list builders for video and article pickers,
 *   - per-post metadata helpers (author id, read time, published-ago, avatar), and
 *   - the Watch Later control markup.
 *
 * @package wpstream-theme
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

		// This list always queries standard blog posts.
		$item_post_type = 'post';
		// Number of posts per page from the widget settings.
		$posts_per_page = intval( $attributes['number'] );

		// Base pagination query-var; suffixed with the widget uid so multiple lists don't clash.
		$query_var_name = 'paged';
		// Append the widget's unique id to the pagination var when present.
		if ( isset( $attributes['uid'] ) && $attributes['uid'] !== '' ) {
			$query_var_name .= '_' . $attributes['uid'];
		}

		// Default to the first page.
		$paged = 1;
		// Override the page number from the URL query string when present.
		if ( isset( $_GET[ $query_var_name ] ) ) {
			$paged = intval( $_GET[ $query_var_name ] );
		}

		// For an AJAX load-more request, take the page number from the request attributes.
		if ( isset( $attributes['is_ajax_request'] ) && 1 === intval( $attributes['is_ajax_request'] ) ) {
			$paged = intval( $attributes['paged'] );
		}

 		// Bootstrap column class controlling how many cards sit per row.
 		$card_grid_class_overwrite = wpstream_video_cards_column_class( intval( $attributes['rownumber'] ) );

		// Build a tax_query, AND-combining every taxonomy filter supplied to the widget.
		$tax_query_array = array(
			'relation' => 'AND',
		);

		// Map of widget input keys => taxonomy names.
		$wpstream_item_list_shortcodes_input_to_tax = wpstream_item_list_shortcodes_input_to_tax();

		// For each supplied filter input, add a taxonomy clause to the query.
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

		// Assemble the main WP_Query arguments.
		$args = array(
			'post_type'      => $item_post_type,
			'post_status'    => 'publish',
			'paged'          => $paged,
			'posts_per_page' => $posts_per_page,
			'tax_query'      => $tax_query_array, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);

//		$order_array = wpstream_query_arguments_add_order_by( $attributes['sort_by'] );
//		$args        = array_merge( $args, $order_array['order_array'] );

		// Transient caching flag (immediately forced off below).
		$use_transient = wpstream_return_use_transient();
		// Force caching off for this query.
		$use_transient = false;
		// Cache key (note: $taxonomy is the last foreach value; the key is a placeholder).
		$transient_key = 'wpstream_blog_post_' . $taxonomy . '_to_be_detterminded';
		// Run the query through the shared cached-query helper.
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );
			

		// Elementor render path: echo the full list markup directly.
		if ( isset( $attributes['is_elementor'] ) && $attributes['is_elementor'] ) {

			// Open the outer list wrapper.
			print '<div class="wpstream-shortcode-list-wrapper ">';
			// Print the filter bar above the list.
			print wpstream_filter_bar_blog_posts( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			// Open the inner row wrapper that holds the cards.
			print '<div class="wpstream-shortcode-wrapper wpstream-blog-list-wrapper row">';
			// Emit a hidden data holder carrying the attributes for AJAX pagination.
			print wpstream_compose_ajax_holder_data( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			// Loop the matched posts and render a card for each.
			if ( $query->have_posts() ) :
				while ( $query->have_posts() ) :
					// Set up the post globals for the current post.
					$query->the_post();

					// Render the blog card template for this post.
					include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/template-parts/single/cards/blog-card-v1.php';

				endwhile;
			endif;

			// Restore the main query's post globals.
			wp_reset_postdata();
			wp_reset_query();

			// Close the inner row wrapper.
			print '</div>';

			// Print pagination controls (numbered or load-more per pagination_type).
			wpstream_return_item_list_pagination( $query, $attributes['pagination_type'], $attributes['uid'] );

			// Close the outer list wrapper.
			print '</div>';

		// AJAX path: return the cards + pagination as a JSON payload.
		} elseif ( isset( $attributes['is_ajax_request'] ) && $attributes['is_ajax_request'] ) {
			// Render the cards into the AJAX response structure.
			$response_for_ajax                = wpstream_return_ajax_elements( $query, $card_grid_class_overwrite, 'blog' );
			// Include the query args (for inspection).
			$response_for_ajax['arg']         = $args;
			// Total number of matching posts.
			$response_for_ajax['found_posts'] = $query->found_posts;
			// Column class so the client can rebuild the grid.
			$response_for_ajax['card_grid_class_overwrite']= $card_grid_class_overwrite;
			// Requested per-row count.
			$response_for_ajax['rownumber']=$attributes['rownumber'];
			// Pagination type 2 uses numbered links; otherwise a Load More button.
			if ( 2 === intval( $attributes['pagination_type'] ) ) {

				$response_for_ajax['html_pagination'] = wpstream_theme_pagination_ajax( $attributes, $query->max_num_pages, 2 );
			} else {
				$response_for_ajax['html_pagination'] = '<button type="button" class="btn btn-primary wpstream_load_more">' . esc_html__( 'Load More', 'hello-wpstream' ) . '</button>';
			}
			// Send the JSON success response...
			wp_send_json_success( $response_for_ajax );
			// ...and halt.
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
		// Transient caching is currently disabled project-wide.
		$use_transient = false;

		// Always returns false for now.
		return $use_transient;
	}
}

/**
 * Video item unit selection.
 */
if ( ! function_exists( 'wpstream_blog_post_card_selector' ) ) {
	/**
	 * Resolve the blog card template path for a given card type.
	 *
	 * @param int $type    Card variant; 0 falls back to the theme customizer setting.
	 * @param int $is_grid Unused flag kept for signature compatibility.
	 * @return string Card template path relative to the theme.
	 */
	function wpstream_blog_post_card_selector( $type = 0, $is_grid = 0 ) {
		// With no explicit type, fall back to the theme customizer setting (default 1).
		if (intval($type) === 0) {
			$type = get_theme_mod('wpstream_theme_blog_post_card_type', 1);
		}

		// NOTE: this comparison result is discarded and has no effect (likely a stray statement).
		$type == 1;
		// Both type 1 and 2 currently resolve to the same v1 card template.
		if ($type == 1) {
			$template = 'cards/blog-card-v1.php';
		} elseif ($type == 2) {
			$template = 'cards/blog-card-v1.php';
		}

		// Return the card template path relative to the theme.
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
		// Read the post's author id field.
		$author_id = get_post_field( 'post_author', $post_id );
		// Return it as an int, or null when it isn't numeric.
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
		// Load the post.
		$post = get_post( $post_id );

		// Bail with an empty string when the post doesn't exist.
		if ( ! $post ) {
			return '';
		}

		// Grab the raw post content.
		$post_content = $post->post_content;
		// Count words after stripping all tags.
		$word_count   = str_word_count( wp_strip_all_tags( $post_content ) );

		// Calculate the estimated read time.
		$read_time = ceil( $word_count / 200 ); // Assuming an average reading speed of 200 words per minute.

		// Under a minute reads as '1 min read'; otherwise pluralise the minute count.
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
		// Load the post.
		$post = get_post( $post_id );

		// Bail with an empty string when the post doesn't exist.
		if ( ! $post ) {
			return '';
		}

		// Publish time as a Unix timestamp.
		$post_date    = strtotime( $post->post_date );
		// Current time.
		$current_time = time();
		// Seconds elapsed since publishing.
		$time_diff    = $current_time - $post_date;

		// Pick the largest fitting unit (seconds/minutes/hours/days/years) and pluralise it.
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

		// Return the human-readable 'ago' string.
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
		// Custom uploaded profile picture, if the author set one.
		$profile_img_url = get_the_author_meta( 'custom_picture', $author_id );
		// Gravatar URL used as the fallback.
		$author_gravatar_url = get_avatar_url( $author_id, array( 'size' => $image_size ) );

		// Prefer the custom picture, else the gravatar.
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

		// Start by assuming there is no cached list.
		$article_array = false;
		// Try to read the cached video list first.
		if ( function_exists( 'wpstream_request_transient_cache' ) ) {
			$article_array = wpstream_request_transient_cache( 'wpstream_video_array' );
		}

		// Cache miss: rebuild the list from a fresh query.
		if ( boolval( $article_array ) === false ) {
			// Fetch every published streamable item (live, VOD, product, bundle).
			$args_inner           = array(
				'post_status'      => 'publish',
				'post_type'        => array( 'wpstream_product_vod', 'wpstream_product', 'product', 'wpstream_bundles' ),
				'showposts'        => -1,
				'suppress_filters' => false,
			);
			// Reset the accumulator to an array.
			$article_array        = array();
			// Run the query.
			$all_article_packages = get_posts( $args_inner );
			// Reduce each post to a label(title)/value(ID) pair.
			if ( count( $all_article_packages ) > 0 ) {
				foreach ( $all_article_packages as $single_package ) {
						$temp_array          = array();
						$temp_array['label'] = $single_package->post_title;
						$temp_array['value'] = $single_package->ID;

						$article_array[] = $temp_array;
				}
			}
			// Restore global query state.
			wp_reset_query();
			wp_reset_postdata();
			// Cache the list for 4 hours.
			if ( function_exists( 'wpstream_set_transient_cache' ) ) {
				wpstream_set_transient_cache( 'wpstream_video_array', $article_array, 60 * 60 * 4 );
			}
		}
		// Return the label/value list.
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

		// Start by assuming there is no cached list.
		$article_array = false;
		// Try to read the cached article list first.
		if ( function_exists( 'wpstream_request_transient_cache' ) ) {
			$article_array = wpstream_request_transient_cache( 'wpstream_article_array' );
		}

		// Cache miss: rebuild the list from a fresh query.
		if ( boolval( $article_array ) === false ) {
			// Fetch every standard blog post.
			$args_inner           = array(
				'post_type'        => array( 'post' ),
				'showposts'        => -1,
				'suppress_filters' => false,
			);
			// Reset the accumulator to an array.
			$article_array        = array();
			// Run the query.
			$all_article_packages = get_posts( $args_inner );
			// Reduce each post to a label(title)/value(ID) pair.
			if ( count( $all_article_packages ) > 0 ) {
				foreach ( $all_article_packages as $single_package ) {
						$temp_array          = array();
						$temp_array['label'] = $single_package->post_title;
						$temp_array['value'] = $single_package->ID;

						$article_array[] = $temp_array;
				}
			}
			// Restore global query state.
			wp_reset_query();
			wp_reset_postdata();
			// Cache the list for 4 hours.
			if ( function_exists( 'wpstream_set_transient_cache' ) ) {
				wpstream_set_transient_cache( 'wpstream_article_array', $article_array, 60 * 60 * 4 );
			}
		}
		// Return the label/value list.
		return $article_array;
	}
endif;


/**
 * Featured article.
 *
 * @param array $attributes Attributes.
 */
function wpstream_featured_article( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	// Target post id.
	$postId = intval( $attributes['id'] );
	// Featured card design variant.
	$type   = sanitize_text_field( $attributes['type'] );

	// Resolve the featured-article template for this type.
	$card_type = wpstream_featured_article_card_selector( $type, 0 );
	// Buffer the rendered template.
	ob_start();

	// Render the featured-article template ($postId is in scope for it).
	include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' .  $card_type;
	// Capture the buffered HTML...
	$return_string = ob_get_contents();
	// ...and discard the buffer.
	ob_end_clean();

	// Return the featured-article HTML.
	return $return_string;
}


/**
 * Featured article card selector.
 *
 * @param array $attributes Attributes.
 */

if ( ! function_exists( 'wpstream_featured_article_card_selector' ) ) :
	function wpstream_featured_article_card_selector( $type, $is_grid = 0 ) {

		// Map the numeric type to its featured-article template file.
		if ( $type == 1 ) {
			$template = 'featured_article_type1.php';
		} elseif ( $type == 2 ) {
			$template = 'featured_article_type2.php';
		}

		// Return the template path (note: $template is undefined for types other than 1-2).
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

		// Output accumulator.
		$return_string   = '';
		// String form of the ids (used in the cache key).
		$blog_ids_string = '';
		// Normalised list of post ids to show.
		$blog_ids        = array();

		// Accept blog_ids as either an array or a single value.
		if (isset($attributes['blog_ids'])) {
			// Array form: use as-is and build an underscore-joined key.
			if (is_array($attributes['blog_ids'])) {
				$blog_ids = $attributes['blog_ids'];
				$blog_ids_string = implode('_', $blog_ids);
			// String form: wrap the single value into a one-element array.
			} else {
				// If it's already a string, just use it directly
				$blog_ids = array($attributes['blog_ids']);
				$blog_ids_string = $attributes['blog_ids'];
			}
		}

		
		// Default cards per row.
		$items_per_row = 3;
		// Override the per-row count when supplied.
		if ( isset( $attributes['items_per_row'] ) ) {
			$items_per_row = $attributes['items_per_row'];
		}

		// Bootstrap column class for the chosen per-row count.
		$card_grid_class_overwrite = wpstream_video_cards_column_class( intval( $items_per_row ) );

		// How many posts were requested.
		$post_number_total = count( $blog_ids );
		// Nothing to show: bail out.
		if ( $post_number_total === 0 ) {
			return;
		}

		// Query exactly the requested ids, preserving their given order (orderby post__in).
		$args = array(
			'post_status'    => 'publish',
			'post_type'      => array( 'post' ),
			'post__in'       => $blog_ids,
			'paged'          => 0,
			'posts_per_page' => $post_number_total,
			'orderby'        => 'post__in',
		);

		// Transient caching flag.
		$use_transient = wpstream_return_use_transient();

		// Cache key derived from the id list.
		$transient_key = 'wpstream_blog_by_id_' . $blog_ids_string;
		// Run the query.
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );
		// Resolve the blog card template.
		$blog_unit_card_type = wpstream_blog_post_card_selector( 0 );
		// Buffer the rendered cards.
		ob_start();

		// Render a card per matched post.
		if ( $query->have_posts() ) :
			while ( $query->have_posts() ) :
				// Set up the post globals.
				$query->the_post();
				
				// Render the blog card template.
				include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $blog_unit_card_type;

			endwhile;
		endif;

		// Restore post globals.
		wp_reset_postdata();
		wp_reset_query();

		// Capture the buffered cards...
		$items_list = ob_get_contents();
		// ...and discard the buffer.
		ob_end_clean();

		// Wrap the cards in the row container.
		$return_string  = '<div class="wpstream-shortcode-wrapper wpstream-blog-by-id-wrapper row">';
		$return_string .= $items_list;
		$return_string .= '</div>';

		// Return the assembled HTML.
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

		// Output accumulator.
		$return_string     = '';
		// Optional arrow-position modifier class.
		$arrow_extra_class = '';
		// Set the arrow-position class when configured.
		if ( isset( $attributes['arrows_position'] ) ) {
			$arrow_extra_class = 'wpstream_arrows_position_' . $attributes['arrows_position'];
		}

		// Default number of slides.
		$posts_per_page = 3;
		// Override the slide count when supplied.
		if ( isset( $attributes['number'] ) ) {
			$posts_per_page = intval( $attributes['number'] );
		}

		// Column class for the per-row count.
		$card_grid_class_overwrite = wpstream_video_cards_column_class( intval( $attributes['rownumber'] ) );

		// Build a tax_query AND-combining each supplied taxonomy filter.
		$tax_query_array = array(
			'relation' => 'AND',
		);

		// Map of widget input keys => taxonomy names.
		$wpstream_item_list_shortcodes_input_to_tax = wpstream_item_list_shortcodes_input_to_tax();

		// Add a taxonomy clause for each supplied filter input.
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

		// Assemble the WP_Query arguments for blog posts.
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'paged'          => -1,
			'posts_per_page' => $posts_per_page,
			'tax_query'      => $tax_query_array, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);
	

		// Merge in the ORDER BY clause derived from the sort_by setting.
		$order_array = wpstream_query_arguments_add_order_by( $attributes['sort_by'] );
		$args        = array_merge( $args, $order_array['order_array'] );

		// Transient caching flag (forced off below).
		$use_transient = wpstream_return_use_transient();
		// Force caching off.
		$use_transient = false;
		// Per-slider cache key.
		$transient_key = 'wpstream_post_slider_' . $slider_id;
		// Run the query.
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );

		// Buffer the rendered slides.
		ob_start();
		// Render a card per matched post.
		if ( $query->have_posts() ) :
			while ( $query->have_posts() ) :
				// Set up the post globals.
				$query->the_post();
				// Resolve the blog card template (per iteration).
				$blog_unit_card_type = wpstream_blog_post_card_selector( 0 );
				// Render the blog card template.
				include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $blog_unit_card_type;

			endwhile;
		endif;

		// Capture the buffered slides...
		$items_list = ob_get_contents();
		// ...and discard the buffer.
		ob_end_clean();

		// Open the slider wrapper with per-row/autoscroll data attributes.
		$return_string .= '<div class="wpstream-shortcode-wrapper wpstream-blog-post-slider wpstream-item-list-slider row ' . esc_attr( $arrow_extra_class ) . '" data-items-per-row="' . intval( $attributes['rownumber'] ) . '" data-auto="' . intval( $attributes['autoscroll'] ) . '" id="' . esc_attr( $slider_id ) . '">';
		// Append the rendered slides.
		$return_string .= $items_list;
		// Close the slider wrapper.
		$return_string .= '</div>';

		// Restore post globals.
		wp_reset_postdata();
		wp_reset_query();

		// Return the slider HTML.
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
		// Open the watch-later control wrapper.
		$return_string  = '<div class="wpstream-show-watch-later-wrapper">';
		// Insert the actual icon/button markup.
		$return_string .= wpstream_theme_show_watch_later_icons( $post_id );
		// Close the wrapper.
		$return_string .= '</div>';

		// Return the watch-later HTML.
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
		// Current visitor's user id (0 when logged out).
		$user_id           = get_current_user_id();
		// The user's saved watch-later post ids.
		$watch_later_items = get_user_meta( $user_id, 'wpstream_user_watch_later_items', true );
		// Extra CSS class toggled on when this post is already saved.
		$extra_class       = '';

		// Already saved: show the 'added' icon and label.
		if ( is_array( $watch_later_items ) && in_array( $post_id, $watch_later_items, true ) ) {
			$extra_class = 'wpstream_already_watched_later';

			// Set a different SVG icon path when post is in "Watch Later" list.
			$icon_name                   = 'tick-circle.svg';
			$wpstream_water_later_status = esc_html__( 'Added to Watch Later', 'hello-wpstream' );
		// Not saved: show the default 'add to watch later' icon and label.
		} else {
			// Set the default SVG icon path.
			$icon_name                   = 'folder-plus.svg';
			$wpstream_water_later_status = esc_html__( 'Watch Later', 'hello-wpstream' );
		}

		// Whether to render the control at all.
		$display_watch_later= true;
		// Post type drives whether watch-later applies.
		$post_type = get_post_type( $post_id );

		// For WooCommerce products, only certain product types support watch-later.
		if($post_type == 'product' && function_exists('wc_get_product') ){
			// Load the product.
			$product 		= wc_get_product( $post_id);

			// Determine the product type.
			$product_type 	= '';
			if($product){
				$product_type 	= $product->get_type();
			}

			// Product types that support watch-later.
			$permited_values= array('subscription','product', 'live_stream','video_on_demand','wpstream_bundle');



			// Hide the control for any other product type.
			if(  !in_array($product_type, $permited_values) ){
				$display_watch_later= false;
			}
		}



		// Only emit markup when watch-later is permitted for this post.
		if($display_watch_later){

			// Logged-in users get an active button that toggles watch-later.
			if ( is_user_logged_in() ) {
				$return_string = '<div class="wpstream-watch-later-action ' . esc_attr( $extra_class ) . '" '
					. 'data-postID="' . esc_attr( $post_id ) . '">'
					. wpstream_theme_get_svg_icon( $icon_name ) . '<span>' . $wpstream_water_later_status . '</span>'
					. '</div>';
			// Logged-out users get a disabled button prompting them to sign in.
			} else {
				$return_string = '  <div tabindex="0" class="wpstream-watch-later-action wpstream_no_action" data-toggle="tooltip" data-bs-placement="bottom" '
					. 'data-bs-original-title="' . esc_attr__( 'Sign in to watch later', 'hello-wpstream' ) . '"'
					. 'data-postID="' . esc_attr( $post_id ) . '">'
					. wpstream_theme_get_svg_icon( $icon_name ) . ' <span>' . $wpstream_water_later_status . '</span>'
					. '</div>';
			}

		}

		// Return the markup, or an empty string when nothing was built.
		return $return_string ?? '';
	}
}