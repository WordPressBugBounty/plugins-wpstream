<?php
/**
 * Shortcodes functions.
 *
 * AJAX "load more" / filtering endpoints and the HTML-building helpers behind
 * the theme's item-list and blog-list shortcodes: reading paging/filter params
 * from the request, rendering item cards into buffered HTML, composing the
 * hidden AJAX data holder, and generating taxonomy filter dropdowns.
 *
 * @package wpstream-theme
 */

 // Register the "with top bar" load-more handler for logged-out and logged-in users.
 add_action( 'wp_ajax_nopriv_wpstream_shortcode_with_top_bar_load_more_function', 'wpstream_shortcode_with_top_bar_load_more_function' );
 add_action( 'wp_ajax_wpstream_shortcode_with_top_bar_load_more_function', 'wpstream_shortcode_with_top_bar_load_more_function' );
 // Guard against redeclaration.
 if ( ! function_exists( 'wpstream_shortcode_with_top_bar_load_more_function' ) ) {
	 /**
	  * Shortcode load more (top-bar variant).
	  *
	  * Reads paging/filter parameters from $_POST, then delegates rendering to
	  * wpstream_theme_recent_items_top_bar().
	  *
	  * @return void
	  */
	 function wpstream_shortcode_with_top_bar_load_more_function() {
		// Verify nonce.
		// if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'shortcode_nonce_ajax_action' ) ) {
		//	 wp_send_json_error( 'Nonce verification failed.', 403 );
		// }
 
		$attributes                          = array();
		$attributes['paged']                 = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 0;
		$attributes['type']                  = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$attributes['category_ids']          = isset( $_POST['category_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['category_ids'] ) ) : '';
		$attributes['wpstream_category_ids'] = isset( $_POST['wpstream_category_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['wpstream_category_ids'] ) ) : '';
		$attributes['movie_ratings_ids']     = isset( $_POST['movie_ratings_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['movie_ratings_ids'] ) ) : '';
		$attributes['actors_ids']            = isset( $_POST['actors_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['actors_ids'] ) ) : '';
		$attributes['number']                = isset( $_POST['number'] ) ? intval( $_POST['number'] ) : 0;
		$attributes['rownumber']             = isset( $_POST['rownumber'] ) ? intval( $_POST['rownumber'] ) : 0;
		$attributes['sort_by']               = isset( $_POST['sort_by'] ) ? intval( $_POST['sort_by'] ) : 0;
		$attributes['pagination_type']       = isset( $_POST['pagination_type'] ) ? intval( $_POST['pagination_type'] ) : 0;
		$attributes['is_ajax_request']       = isset( $_POST['is_ajax'] ) ? intval( $_POST['is_ajax'] ) : 0;
		$attributes['current_page']          = isset( $_POST['current_page'] ) ? intval( $_POST['current_page'] ) : 0;
		$attributes['video_card']            = isset( $_POST['video_card'] ) ? intval( $_POST['video_card'] ) : 1;
		// Render the next page of items (top-bar layout) and echo the HTML.
		wpstream_theme_recent_items_top_bar( $attributes );
	 }
 }



/**
 * Shortcodes functions
 *
 * @package wpstream-theme
 */

// Register the standard item-list load-more handler for logged-out and logged-in users.
add_action( 'wp_ajax_nopriv_wpstream_shortcode_load_more_function', 'wpstream_shortcode_load_more_function' );
add_action( 'wp_ajax_wpstream_shortcode_load_more_function', 'wpstream_shortcode_load_more_function' );
// Guard against redeclaration.
if ( ! function_exists( 'wpstream_shortcode_load_more_function' ) ) {
	/**
	 * Shortcode load more (item list).
	 *
	 * Verifies the AJAX nonce, reads paging/filter parameters from $_POST, then
	 * delegates rendering to wpstream_item_list_shortcodes().
	 *
	 * @return void
	 */
	function wpstream_shortcode_load_more_function() {
		// Verify nonce.
		// Reject the request when the shortcode AJAX nonce is missing or invalid.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'shortcode_nonce_ajax_action' ) ) {
			wp_send_json_error( 'Nonce verification failed.', 403 );
		}

		$attributes                          = array();
		$attributes['paged']                 = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 0;
		$attributes['type']                  = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$attributes['category_ids']          = isset( $_POST['category_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['category_ids'] ) ) : '';
		$attributes['wpstream_category_ids'] = isset( $_POST['wpstream_category_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['wpstream_category_ids'] ) ) : '';
		$attributes['movie_ratings_ids']     = isset( $_POST['movie_ratings_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['movie_ratings_ids'] ) ) : '';
		$attributes['actors_ids']            = isset( $_POST['actors_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['actors_ids'] ) ) : '';
		$attributes['number']                = isset( $_POST['number'] ) ? intval( $_POST['number'] ) : 0;
		$attributes['rownumber']             = isset( $_POST['rownumber'] ) ? intval( $_POST['rownumber'] ) : 0;
		$attributes['sort_by']               = isset( $_POST['sort_by'] ) ? intval( $_POST['sort_by'] ) : 0;
		$attributes['pagination_type']       = isset( $_POST['pagination_type'] ) ? intval( $_POST['pagination_type'] ) : 0;
		$attributes['is_ajax_request']       = isset( $_POST['is_ajax'] ) ? intval( $_POST['is_ajax'] ) : 0;
		$attributes['current_page']          = isset( $_POST['current_page'] ) ? intval( $_POST['current_page'] ) : 0;
		$attributes['video_uid']          = isset( $_POST['video_uid'] ) ? sanitize_text_field( $_POST['video_uid'] ) : 0;
		$attributes['video_card']          = isset( $_POST['video_card'] ) ? sanitize_text_field( $_POST['video_card'] ) : 1;
		// Render the next page of item cards and echo the HTML.
		wpstream_item_list_shortcodes( $attributes );
	}
}


/**
 * Shortcodes functions
 *
 * @package wpstream-theme
 */

 // Register the blog-list load-more handler for logged-out and logged-in users.
 add_action( 'wp_ajax_nopriv_wpstream_shortcode_load_more_blog_list_function', 'wpstream_shortcode_load_more_blog_list_function' );
 add_action( 'wp_ajax_wpstream_shortcode_load_more_blog_list_function', 'wpstream_shortcode_load_more_blog_list_function' );
 // Guard against redeclaration.
 if ( ! function_exists( 'wpstream_shortcode_load_more_blog_list_function' ) ) {
	 /**
	  * Shortcode load more (blog list).
	  *
	  * Verifies the AJAX nonce, reads paging/filter parameters from $_POST, then
	  * delegates rendering to wpstream_blog_list_shortcodes().
	  *
	  * @return void
	  */
	 function wpstream_shortcode_load_more_blog_list_function() {
		 // Verify nonce.
		 // Reject the request when the shortcode AJAX nonce is missing or invalid.
		 if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'shortcode_nonce_ajax_action' ) ) {
			 wp_send_json_error( 'Nonce verification failed.', 403 );
		 }
 
		 $attributes                          = array();
		 $attributes['paged']                 = isset( $_POST['paged'] ) ? intval( $_POST['paged'] ) : 0;
		 $attributes['type']                  = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		 $attributes['category_ids']          = isset( $_POST['category_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['category_ids'] ) ) : '';
		 $attributes['number']                = isset( $_POST['number'] ) ? intval( $_POST['number'] ) : 0;
		 $attributes['rownumber']             = isset( $_POST['rownumber'] ) ? intval( $_POST['rownumber'] ) : 0;
		 $attributes['sort_by']               = isset( $_POST['sort_by'] ) ? intval( $_POST['sort_by'] ) : 0;
		 $attributes['pagination_type']       = isset( $_POST['pagination_type'] ) ? intval( $_POST['pagination_type'] ) : 0;
		 $attributes['is_ajax_request']       = isset( $_POST['is_ajax'] ) ? intval( $_POST['is_ajax'] ) : 0;
		 $attributes['current_page']          = isset( $_POST['current_page'] ) ? intval( $_POST['current_page'] ) : 0;
		 $attributes['blog_uid']              = isset( $_POST['blog_uid'] ) ? sanitize_text_field( $_POST['blog_uid'] ) : '';
 
		 // Render the next page of blog posts and echo the HTML.
		 wpstream_blog_list_shortcodes( $attributes );
	 }
 }

/**
 * Populates attributes from $_GET array if 'from_ajax' is set to 'yes'.
 *
 * @param array $attributes Array of attributes.
 * @return array Populated attributes.
 */
function wpstream_populate_from_get( $attributes ) {
	// Only overlay $_GET values when this is an AJAX-driven filter request.
	if ( isset( $_GET['from_ajax'] ) && 'yes' === $_GET['from_ajax'] ) {
		// Bail (leaving attributes untouched) if the nonce is missing or invalid.
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'shortcode_nonce_ajax_action' ) ) {
			// Nonce verification failed.
			return $attributes;
		}

		// Copy each supported filter parameter from the query string into the attributes.
		$attributes['type']                  = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
		$attributes['category_ids']          = isset( $_GET['category_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['category_ids'] ) ) : '';
		$attributes['wpstream_category_ids'] = isset( $_GET['wpstream_category_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['wpstream_category_ids'] ) ) : '';
		$attributes['movie_ratings_ids']     = isset( $_GET['movie_ratings_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['movie_ratings_ids'] ) ) : '';
		$attributes['actors_ids']            = isset( $_GET['actors_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['actors_ids'] ) ) : '';
		$attributes['sort_by']               = isset( $_GET['sort_by'] ) ? sanitize_text_field( wp_unslash( $_GET['sort_by'] ) ) : '';
	}

	return $attributes;
}


if ( ! function_exists( 'wpstream_return_item_list_elementor' ) ) {
	/**
	 * Returns a list of items for use in Elementor.
	 *
	 * @param WP_Query $query              The WordPress query to retrieve items.
	 * @param string   $items_column_class CSS class for the columns where the items will be placed.
	 */
	function wpstream_return_item_list_elementor( $query, $items_column_class ) {
		// Exposed to the included template so it can apply the column CSS class.
		$overwrite_wpstream_cols_name = $items_column_class;

		// Render the loop template only when the query returned results.
		if ( $query->found_posts > 0 ) {
			include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/single/video-loop-content.php';
		}
	}
}


if ( ! function_exists( 'wpstream_return_item_list_pagination' ) ) {
	/**
	 * Returns the pagination for the item list.
	 *
	 * @param WP_Query $query      The WordPress query used to retrieve items.
	 * @param int      $pagination Type of pagination (1 for "Load More" button, 2 for standard pagination).
	 */
	function wpstream_return_item_list_pagination( $query, $pagination,$uid='' ) {

		// With results, print the pagination controls; otherwise print an empty-state message.
		if ( $query->found_posts > 0 ) {
			print wpstream_add_pagination( $query, $pagination ,$uid); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			print '<div class="wpstream-pagination">' . esc_html__( 'There are no items', 'hello-wpstream' ) . '</div>';
		}
	}
}

if ( ! function_exists( 'wpstream_return_ajax_elements' ) ) {
	/**
	 * Returns the HTML elements for AJAX response.
	 *
	 * @param WP_Query $query              The WordPress query used to retrieve items.
	 * @param string   $items_column_class The CSS class for the items column.
	 * @return array                       An array containing the post count and HTML content.
	 */
	function wpstream_return_ajax_elements( $query, $items_column_class,$unit='',$card_type=1 ) {
		// Exposed to the included card templates for column sizing.
		$overwrite_wpstream_cols_name = $items_column_class;

		// Buffer the card markup so it can be returned as a string.
		ob_start();
		// Alternate variable name some card templates read for the grid class.
		$card_grid_class_overwrite =$items_column_class;
		if ( $query->found_posts > 0 ) {
			// Render one card per post in the loop.
			while ( $query->have_posts() ) {
				$query->the_post();
				// Blog items use the blog card template; everything else uses the video card template.
				if ( 'blog' === $unit ) {
					$blog_unit_card_type = wpstream_blog_post_card_selector(0);
					include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $blog_unit_card_type;
				} else {
					$unit_card_type = wpstream_video_item_card_selector(intval($card_type));
					include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;
				}
			}
		}
		// Capture and clear the buffered markup.
		$html = ob_get_contents();
		ob_end_clean();

		// Return the number of posts on this page plus the rendered HTML.
		return array(
			'post_count' => $query->post_count,
			'html'       => $html,
		);
	}
}

if ( ! function_exists( 'wpstream_add_pagination' ) ) {
	/**
	 * Generates pagination HTML based on the pagination type.
	 *
	 * @param WP_Query $query      The WordPress query object.
	 * @param int      $pagination The pagination type.
	 * @return string              The generated pagination HTML.
	 */
	function wpstream_add_pagination( $query, $pagination,$uid='' ) {
		// Accumulator for the pagination markup.
		$return_string = '';

		// Type 1: a single "Load More" button.
		if ( 1 === intval($pagination) ) {
			$return_string = '<div class="wpstream_load_more_wrapper wpstream-pagination"><button type="button" class="btn btn-primary wpstream_load_more">' . esc_html__( 'Load More', 'hello-wpstream' ) . '</button></div>';
		// Type 2: numbered pagination (the generating call is currently commented out, so output is empty).
		} elseif ( 2 === intval($pagination) ) {
			ob_start();
//			wpstream_shortcode_pagination( $query->max_num_pages ,2,'',$uid);
			$return_string = ob_get_contents();
			ob_end_clean();
		}

		return $return_string;
	}
}

if ( ! function_exists( 'wpstream_compose_ajax_holder_data' ) ) {
	/**
	 * Composes the HTML data attributes for the AJAX holder shortcode.
	 *
	 * @param array $attributes The attributes for the AJAX holder shortcode.
	 * @return string           The composed HTML data attributes.
	 */
	function wpstream_compose_ajax_holder_data( $attributes ) {

		// Default card design; overridden by the attribute when present.
		$video_card=1;
		if(isset($attributes['video_card'])){
			$video_card = intval($attributes['video_card']);
		}

		// Hidden holder element that the front-end JS reads to drive AJAX paging.
		$return_string = '<div class="wpstream_ajax_holder_shortcode" style="display:none" data-video-card="'.esc_attr($video_card).'" data-current-page="' . get_the_ID() . '" data-paged="1" ';
		// Serialize every attribute into a data-* attribute (underscores become dashes).
		foreach ( $attributes as $key => $value ) {

			$data_key_name  = str_replace( '_', '-', $key );
			$return_string .= ' data-' . esc_attr( $data_key_name ) . '="';
			// Arrays are JSON-encoded; scalars are sanitized as text.
			if ( is_array( $value ) ) {
				$return_string .= wp_json_encode( $value );
			} else {
				$return_string .= sanitize_text_field( $value );
			}
			$return_string .= '"';
		}

		// Close the holder element and return the markup.
		$return_string .= '></div>';
		return $return_string;
	}
}


/*
* Wpstream shortcode input to tax
* 
*
*/

if ( ! function_exists( 'wpstream_item_list_shortcodes_input_to_tax' ) ) {
	/**
	 * Item list shortcodes
	 */
	function wpstream_item_list_shortcodes_input_to_tax() {
		// Maps each shortcode/request input key to its underlying taxonomy slug.
		return array(
			'actors_ids'            => 'wpstream_actors',
			'movie_ratings_ids'     => 'wpstream_movie_rating',
			'wpstream_category_ids' => 'wpstream_category',
			'category_ids'          => 'category',
			'post_tag_ids'          => 'post_tag',
		);
	}
}
/*
* Wpstream filter bar - shortcode 
* 
*
*/

if ( ! function_exists( 'wpstream_filter_bar_blog_posts' ) ) {
	/**
	 * Generates the filter bar HTML content based on the provided attributes.
	 *
	 * @param array $attributes_data The attributes data for generating the filter bar.
	 * @return string                The HTML content of the filter bar.
	 */
	function wpstream_filter_bar_blog_posts( $attributes_data ) {
		$return_string = '';
		// Exposed to the included template under the expected variable name.
		$attributes    = $attributes_data;
		// Buffer the blog-list filter template into a string.
		ob_start();
		include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/template-parts/shortcodes/blog-list-filters.php';
		$filters = ob_get_contents();
		ob_end_clean();

		$return_string .= $filters;
		return $return_string;
	}
}




/*
* Wpstream filter bar - shortcode 
* 
*
*/

if ( ! function_exists( 'wpstream_filter_bar' ) ) {
	/**
	 * Generates the filter bar HTML content based on the provided attributes.
	 *
	 * @param array $attributes_data The attributes data for generating the filter bar.
	 * @return string                The HTML content of the filter bar.
	 */
	function wpstream_filter_bar( $attributes_data ) {
		$return_string = '';
		// Exposed to the included template under the expected variable name.
		$attributes    = $attributes_data;
		// Buffer the item/property-list filter template into a string.
		ob_start();
		include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/template-parts/shortcodes/property-list-filters.php';
		$filters = ob_get_contents();
		ob_end_clean();

		$return_string .= $filters;
		return $return_string;
	}
}

if ( ! function_exists( 'wpstream_taxonomy_terms_dropdown' ) ) {
	/**
	 * Generates a dropdown select element for a taxonomy's terms.
	 *
	 * @param string $taxonomy         The taxonomy to retrieve terms from.
	 * @param string $selected_term_slug Optional. The slug of the selected term. Default is ''.
	 * @param array  $attributes       Optional. Additional attributes for the select element. Default is an empty array.
	 * @return string                  The HTML markup for the dropdown select element.
	 */
	function wpstream_taxonomy_terms_dropdown( $taxonomy, $selected_term_slug = '', $attributes = '' ) {
		// Nothing to build for an unregistered taxonomy.
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		// Initialize $selected_term_slug based on $_GET if it's not provided.
		// Map the taxonomy to its matching request parameter to pre-select a term.
		switch ( $taxonomy ) {
			case 'category':
				// Blog-list dropdowns only filter by category; pre-select it from the request.
		$selected_term_slug = isset( $_GET['category_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['category_ids'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			case 'wpstream_actors':
				$selected_term_slug = isset( $_GET['actors_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['actors_ids'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			case 'wpstream_category':
				$selected_term_slug = isset( $_GET['wpstream_category_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['wpstream_category_ids'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			case 'wpstream_movie_rating':
				$selected_term_slug = isset( $_GET['movie_ratings_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['movie_ratings_ids'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
		}

		// Fetch every term (including empty ones) for the taxonomy.
		$args  = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false, // This will include terms with 0 posts.
		);
		$terms = get_terms( $args );

		// Start the markup for our select element.
		$output = '<select name="' . esc_attr( $taxonomy ) . '" class="wpstream_dropdown_select  wpstream_dropdown_select_trigger_ajax" id="wpstream_dropdown_' . esc_attr( $taxonomy ) . '">';

		// Add a default option (optional).
		$output .= '<option value="">';
		// Use a custom label when provided, otherwise a translated default.
		if ( isset( $attributes[ 'label_' . $taxonomy ] ) ) {
			$output .= $attributes[ 'label_' . $taxonomy ];
		} else {
			$output .= esc_html_e( 'Select term', 'hello-wpstream' );
		}

		$output .= '</option>';

		// Check if we have any terms.
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			// One <option> per term.
			foreach ( $terms as $term ) {
				// Check if this term is the selected term.
				$selected = selected( $selected_term_slug, $term->term_id, false );

				// Add an option for the term.
				$output .= sprintf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $term->term_id ),
					$selected,
					esc_html( $term->name )
				);
			}
		}

		// Close the select element.
		$output .= '</select>';

		// Return the generated dropdown.
		return $output;
	}
}


if ( ! function_exists( 'wpstream_taxonomy_terms_dropdown_bootstrap' ) ) {
	/**
	 * Generates a dropdown select element for a taxonomy's terms.
	 *
	 * @param string $taxonomy         The taxonomy to retrieve terms from.
	 * @param string $selected_term_slug Optional. The slug of the selected term. Default is ''.
	 * @param array  $attributes       Optional. Additional attributes for the select element. Default is an empty array.
	 * @return string                  The HTML markup for the dropdown select element.
	 */
	function wpstream_taxonomy_terms_dropdown_bootstrap( $taxonomy, $selected_term_slug = '', $attributes = '' ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		// Initialize $selected_term_slug based on $_GET if it's not provided.
		switch ( $taxonomy ) {
			case 'category':
				// Blog-list dropdowns only filter by category; pre-select it from the request.
		$selected_term_slug = isset( $_GET['category_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['category_ids'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			case 'wpstream_actors':
				$selected_term_slug = isset( $_GET['actors_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['actors_ids'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			case 'wpstream_category':
				$selected_term_slug = isset( $_GET['wpstream_category_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['wpstream_category_ids'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			case 'wpstream_movie_rating':
				$selected_term_slug = isset( $_GET['movie_ratings_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['movie_ratings_ids'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
		}

		$args  = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false, // This will include terms with 0 posts.
		);
		$terms = get_terms( $args );

		// Build the option data + select attributes for the custom Bootstrap dropdown renderer.
		$values		=	array();
		$label 		=	'';
		$name 		= 	esc_attr( $taxonomy );
		$class 		=	'wpstream_dropdown_select  wpstream_dropdown_select_trigger_ajax wpstream_dropdown_select_'.$name;
		$id			=	'wpstream_dropdown_' . esc_attr( $taxonomy );


		// Placeholder label: custom when supplied, otherwise a translated default.
		if ( isset( $attributes[ 'label_' . $taxonomy ] ) ) {
			$label .= $attributes[ 'label_' . $taxonomy ];
		} else {
			$label .= esc_html_e( 'Select term', 'hello-wpstream' );
		}

		


		// Check if we have any terms.
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			// Collect term id => name pairs for the dropdown options.
			foreach ( $terms as $term ) {
				$values[$term->term_id]=$term->name;
			}
		}

		// Delegate the actual markup to the shared custom-dropdown builder.
		$output = wpstream_create_custom_dropdown( $values,$name, $class, $id, $label,$selected_term_slug);
		// Return the generated dropdown.
		return $output;
	}
}



/*
* Filter BLog Post list
* 
*/

if ( ! function_exists( 'wpstream_taxonomy_terms_dropdown_blog_post_list' ) ) {
	/**
	 * Generates a dropdown select element for a taxonomy's terms.
	 *
	 * @param string $taxonomy         The taxonomy to retrieve terms from.
	 * @param string $selected_term_slug Optional. The slug of the selected term. Default is ''.
	 * @param array  $attributes       Optional. Additional attributes for the select element. Default is an empty array.
	 * @return string                  The HTML markup for the dropdown select element.
	 */
	function wpstream_taxonomy_terms_dropdown_blog_post_list( $taxonomy, $selected_term_slug = '', $attributes = '' ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		// Blog-list dropdowns only filter by category; pre-select it from the request.
		$selected_term_slug = isset( $_GET['category_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['category_ids'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			
		$args  = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false, // This will include terms with 0 posts.
		);
		$terms = get_terms( $args );

		// Start the markup for our select element.
		// Blog-list variant: uses the blog-specific AJAX trigger class.
		$output = '<select name="' . esc_attr( $taxonomy ) . '" class="wpstream_dropdown_select  wpstream_dropdown_select_trigger_ajax_blog_list" id="wpstream_dropdown_' . esc_attr( $taxonomy ) . '">';

		// Add a default option (optional).
		$output .= '<option value="">';
		// Custom label when supplied, otherwise a translated default.
		if ( isset( $attributes[ 'label_' . $taxonomy ] ) ) {
			$output .= $attributes[ 'label_' . $taxonomy ];
		} else {
			$output .= esc_html_e( 'Select term', 'hello-wpstream' );
		}

		$output .= '</option>';

		// Check if we have any terms.
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			// One <option> per term.
			foreach ( $terms as $term ) {
				// Check if this term is the selected term.
				$selected = selected( $selected_term_slug, $term->term_id, false );

				// Add an option for the term.
				$output .= sprintf(
					'<option value="%s"%s>%s</option>',
					esc_attr( $term->term_id ),
					$selected,
					esc_html( $term->name )
				);
			}
		}

		// Close the select element.
		$output .= '</select>';

		// Return the generated dropdown.
		return $output;
	}
}
/*
* Filter BLog Post list with Bootstrea
* 
*/

if ( ! function_exists( 'wpstream_taxonomy_terms_dropdown_blog_post_list_with_bootstrap' ) ) {
	/**
	 * Generates a dropdown select element for a taxonomy's terms.
	 *
	 * @param string $taxonomy         The taxonomy to retrieve terms from.
	 * @param string $selected_term_slug Optional. The slug of the selected term. Default is ''.
	 * @param array  $attributes       Optional. Additional attributes for the select element. Default is an empty array.
	 * @return string                  The HTML markup for the dropdown select element.
	 */
	function wpstream_taxonomy_terms_dropdown_blog_post_list_with_bootstrap( $taxonomy, $selected_term_slug = '', $attributes = '' ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		// Blog-list dropdowns only filter by category; pre-select it from the request.
		$selected_term_slug = isset( $_GET['category_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['category_ids'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			
		$args  = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false, // This will include terms with 0 posts.
		);
		$terms 		= 	get_terms( $args );
		// Build the option data + select attributes for the custom Bootstrap blog-list dropdown.
		$values		=	array();
		$label 		=	'';
		$name 		= 	esc_attr( $taxonomy );
		$class 		=	'wpstream_dropdown_select  wpstream_dropdown_select_trigger_ajax_blog_list wpstream_blog_dropdown_' . esc_attr( $taxonomy );
		$id			=	'wpstream_dropdown_' . esc_attr( $taxonomy );

		// Start the markup for our select element.
		//$output = '<select name="' . esc_attr( $taxonomy ) . '" class="wpstream_dropdown_select  wpstream_dropdown_select_trigger_ajax_blog_list" id="wpstream_dropdown_' . esc_attr( $taxonomy ) . '">';

		// Add a default option (optional).

		if ( isset( $attributes[ 'label_' . $taxonomy ] ) ) {
			$label .= $attributes[ 'label_' . $taxonomy ];
		} else {
			$label .= esc_html_e( 'Select term', 'hello-wpstream' );
		}

		// Check if we have any terms.
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			// Collect term id => name pairs for the dropdown options.
			foreach ( $terms as $term ) {
				$values[$term->term_id]=$term->name;
			}
		}
		// Delegate the actual markup to the shared custom-dropdown builder.
		$output = wpstream_create_custom_dropdown( $values,$name, $class, $id, $label,$selected_term_slug);
		// Return the generated dropdown.
		return $output;
	}
}
