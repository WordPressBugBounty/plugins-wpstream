<?php
/**
 * Elementor video helper functions for the hello-wpstream theme.
 *
 * Shared building blocks used by the theme's Elementor video widgets and
 * shortcodes. Each function queries WpStream product / VOD / bundle posts,
 * loads the matching card template partial, and returns (or prints) the markup:
 *
 *  - wpstream_featured_video_slider()             Featured video carousel markup.
 *  - wpestream_theme_slider_items()               Generic video item slider by taxonomy.
 *  - wpstream_theme_list_items_by_id_function()   Grid of items chosen by post ID.
 *  - wpstream_item_list_shortcodes()              Paginated / AJAX item list + filter bar.
 *  - wpstream_theme_return_all_taxomy_array()     Cached list of all filter terms.
 *  - wpstream_theme_featured_video()              Single featured video card.
 *  - wpstream_featured_video_card_selector()      Picks a featured card template.
 *  - get_css_variable_from_url()                  Turns a global-color URL into a CSS var.
 *
 * @package Wpstream
 * @subpackage Wpstream/hello-wpstream/elementor
 */





/**
 * Build the markup for the Featured Video Items carousel.
 *
 * Emits an inline <style> block that fine-tunes the item border (see the
 * radius/box-shadow calculation below), queries the selected featured items and
 * concatenates each rendered card into a wrapper div returned to the caller.
 *
 * @param array  $attributes Widget settings (selected ids, border, effects, autoscroll).
 * @param string $slider_id  Unique DOM id for this slider instance.
 * @return string HTML for the slider wrapper and its items.
 */

if ( ! function_exists( 'wpstream_featured_video_slider' ) ) :
	function wpstream_featured_video_slider( $attributes, $slider_id ){

		// Determine the border color
		// Prefer the plain color; fall back to an Elementor global color URL if set.
		$item_border_color = $attributes['item_border_color'] ?? '';
		if ( empty( $item_border_color ) && !empty( $attributes['__globals__']['item_border_color'] ) ) {
			// Resolve the global-color URL into a CSS var( --e-global-color-* ) reference.
			$item_border_color = get_css_variable_from_url($attributes['__globals__']['item_border_color']);
		}

		// Check if border radius and width are set and not empty
		if ( !empty( $attributes['item_border_radius'] ) && !empty( $attributes['item_border_width'] ) ) {
			// Pull the per-side radius and width dimension arrays.
			$item_border_radius = $attributes['item_border_radius'];
			$item_border_width = $attributes['item_border_width'];

			// Ensure values are numeric
			// Inner radius per side = outer radius minus border width on that side.
			$calculated = [
				'top' => (float)$item_border_radius['top'] - (float)$item_border_width['top'],
				'right' => (float)$item_border_radius['right'] - (float)$item_border_width['right'],
				'bottom' => (float)$item_border_radius['bottom'] - (float)$item_border_width['bottom'],
				'left' => (float)$item_border_radius['left'] - (float)$item_border_width['left']
			];

			// Determine the maximum border width
			// Largest side width, used as the box-shadow spread fallback.
			$max_border_width = max( (float)$item_border_width['top'],
				(float)$item_border_width['right'],
				(float)$item_border_width['bottom'],
				(float)$item_border_width['left']
			);

			// Determine CSS based on calculated values
			// If any inner radius went negative, draw a box-shadow "border" instead of
			// a real rounded border; otherwise apply the adjusted inner border-radius.
			$style = array_filter($calculated, fn($value) => $value < 0)
				? "box-shadow: 0px 0px 0px {$max_border_width}px {$item_border_color};"
				: "border-radius: {$calculated['top']}{$item_border_width['unit']} {$calculated['right']}{$item_border_width['unit']} {$calculated['bottom']}{$item_border_width['unit']} {$calculated['left']}{$item_border_width['unit']} !important;";

			// Generate and print the CSS styles
			// Inject the computed rule scoped to this widget's cover element.
			echo '<style>
				.elementor-widget-WpStreamTheme_Featured_Video_Items_Slider .wpstream_featured_video > .wpstream_category_unit_item_cover {
					' . $style . '
				}
			</style>';
		}

		// Accumulator for the returned markup and default arrow class.
		$return_string='';
		$arrow_extra_class='';
		// Whether hover video preview is enabled (consumed by the card template).
	 	$preview_video = $attributes['show_video'];
		// Query only the explicitly-selected featured items, newest page first.
		$args = array(
			'post_type'		 => 	array('wpstream_product_vod','wpstream_product',	'wpstream_bundles',	'product' ),
			'post_status'    => 	'publish',
			'paged'          => 	-1,
			'posts_per_page' => 	'15',
			'post__in'	     => 	$attributes['video_id'],
		
			
		);

		// Add the Ken Burns effect CSS class only when that toggle is "yes".
	  	$ken_burns_class = is_array($attributes) &&
			array_key_exists('ken_burns_effect', $attributes) &&
			$attributes['ken_burns_effect'] === 'yes' ?
				'wpstream-ken-burns-effect' :
				'';


		// Transient caching is read then force-disabled here (always run a fresh query).
		$use_transient = wpstream_return_use_transient();
		$use_transient = false;
		$transient_key = 'wpstream_featured_video_item_slider_' . $slider_id;
		// Run the (cached-or-fresh) query for the selected items.
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );
		// Featured slider always uses the type-1 card template.
		$unit_card_type = 'template-parts/video-unit-templates/featured_video_item_type1.php';

		// Flag read by the included card template to adapt its markup for a slider.
		$is_video_items_slider =true;

		// Buffer each rendered card into $items_list.
		ob_start();
		if ( $query->have_posts() ) :
			while ( $query->have_posts() ) :
				$query->the_post();
				// Current post id is used by the card template.
				$postId=get_the_ID();
				include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;

			endwhile;
		endif;
		$items_list = ob_get_contents();
		ob_end_clean();



		// Wrap the buffered cards in the slider container (with arrow/ken-burns classes).
		$return_string .= '<div class="wpstream-shortcode-wrapper wpstream-featured-video-item-list-slider row  '.esc_attr($arrow_extra_class).' '.esc_attr($ken_burns_class).'"  data-auto="' . esc_attr( $attributes['autoscroll'] ) . '" id="' . esc_attr( $slider_id ) . '">';
		$return_string .= $items_list;
		$return_string .= '</div>';

		// Hand the assembled slider markup back to the caller.
		return 	$return_string ;
	}
endif;





/**
 * Build a generic video item slider filtered by type and taxonomy terms.
 *
 * @param array  $attributes Slider settings (type, number, taxonomy terms, sort, card...).
 * @param string $slider_id  Unique DOM id for this slider instance.
 * @return string HTML for the slider wrapper and its items.
 */
if ( ! function_exists( 'wpestream_theme_slider_items' ) ) :
	function wpestream_theme_slider_items( $attributes, $slider_id ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Ensure the player JS is loaded when a video item may be rendered.
		wpstream_load_player_js_on_demand();

		// Basic query inputs pulled from the widget attributes.
		$return_string                = '';
		$item_post_type               = ( $attributes['type'] );
		$posts_per_page               = intval( $attributes['number'] );
		$arrow_extra_class			  = '';
		// Optional arrow-position modifier class.
		if(isset($attributes['arrows_position'])){
			$arrow_extra_class="wpstream_arrows_position_".$attributes['arrows_position'];
		}


		// Column class derived from the requested items-per-row.
		$overwrite_wpstream_cols_name = wpstream_video_cards_column_class( intval( $attributes['rownumber'] ) );

		// Seed the tax_query; all supplied term filters are AND-combined.
		$tax_query_array = array(
			'relation' => 'AND',
		);

		// Map of attribute name => taxonomy for the supported filters.
		$wpstream_item_list_shortcodes_input_to_tax = wpstream_item_list_shortcodes_input_to_tax();

		// For every provided filter, add a term clause to the tax_query.
		foreach ( $wpstream_item_list_shortcodes_input_to_tax as $input => $taxonomy ) :
			if ( isset( $attributes[ $input ] ) && !empty($attributes[ $input ]) ) {
				$tax_array         = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array( $attributes[ $input ] ),
				);
				$tax_query_array[] = $tax_array;

			}
		endforeach;

		// Base query args (paged -1 disables paging for a slider).
		$args = array(
			'post_type'      => $item_post_type,
			'post_status'    => 'publish',
			'paged'          => -1,
			'posts_per_page' => $posts_per_page,
			'tax_query'      => $tax_query_array, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);

		// Merge in the order-by clause chosen by the sort_by setting.
		$order_array = wpstream_query_arguments_add_order_by( $attributes['sort_by'] );
		$args        = array_merge( $args, $order_array['order_array'] );

		// Transient caching is read then force-disabled (always a fresh query).
		$use_transient = wpstream_return_use_transient();
		$use_transient = false;
		$transient_key = 'wpstream_video_item_slider_' . $slider_id;
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );

		// Resolve which card template to render for each item.
		$unit_card_type = wpstream_video_item_card_selector( intval($attributes['video_card']) );

		// Buffer each rendered card into $items_list.
		ob_start();
		if ( $query->have_posts() ) :
			while ( $query->have_posts() ) :
				$query->the_post();
				include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;

			endwhile;
		endif;
		$items_list = ob_get_contents();
		ob_end_clean();

		// Wrap the buffered cards in the slider container.
		$return_string .= '<div class="wpstream-shortcode-wrapper wpstream-item-list-slider row  '.esc_attr($arrow_extra_class).'" data-items-per-row="' . intval( $attributes['rownumber'] ) . '" data-auto="' . esc_attr( $attributes['autoscroll'] ) . '" id="' . esc_attr( $slider_id ) . '">';
		$return_string .= $items_list;
		$return_string .= '</div>';

		// Restore global post/query state after the custom loop.
		wp_reset_postdata();
		wp_reset_query();

		return $return_string;
	}
	endif;





/**
 * Build a grid of video items chosen explicitly by post ID.
 *
 * Preserves the order the ids were given (orderby post__in) and renders each
 * with the selected card template.
 *
 * @param array $attributes Settings (video_ids[], items_per_row, video_card).
 * @return string HTML for the grid wrapper and its items.
 */
if ( ! function_exists( 'wpstream_theme_list_items_by_id_function' ) ) :
	function wpstream_theme_list_items_by_id_function( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found



		// Defaults for the accumulator and the id list / cache key.
		$return_string    = '';
		$video_ids_string = '';
		$video_ids        = array();

		// Read the selected ids and derive a stable string for the transient key.
		if ( isset( $attributes['video_ids'] ) && is_array( $attributes['video_ids'] ) ) {
			$video_ids        = $attributes['video_ids'];
			$video_ids_string = implode( '_', $video_ids );
		}

		// Items per row, defaulting to 3 when not supplied.
		$items_per_row = 3;
		if ( isset( $attributes['items_per_row'] ) ) {
			$items_per_row = $attributes['items_per_row'];
		}

		// Column class derived from the items-per-row value.
		$overwrite_wpstream_cols_name = wpstream_video_cards_column_class( intval( $items_per_row ) );

		// Query exactly the selected posts, preserving the given order.
		$post_number_total = count( $video_ids );
		$args              = array(
			'post_status'    => 'publish',
			'post_type'      => array( 'wpstream_product_vod', 'wpstream_product', 'product', 'wpstream_bundles' ),
			'post__in'       => $video_ids,
			'paged'          => 0,
			'posts_per_page' => $post_number_total,
			'orderby'        => 'post__in',
		);

		// Here the transient flag is honoured as returned (not force-disabled).
		$use_transient = wpstream_return_use_transient();

		$transient_key = 'wpstream_item_by_id_' . $video_ids_string;
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );
		// Resolve the card template and buffer each rendered item.
		$unit_card_type = wpstream_video_item_card_selector( intval($attributes['video_card']) );
		ob_start();

		if ( $query->have_posts() ) :
			while ( $query->have_posts() ) :
				$query->the_post();

				include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;

			endwhile;
		endif;

		$items_list = ob_get_contents();
		ob_end_clean();

		// Wrap the buffered cards in the grid container.
		$return_string  = '<div class="wpstream-shortcode-wrapper wpstream-item-list-by-if-wrapper row">';
		$return_string .= $items_list;
		$return_string .= '</div>';

		// Restore global post/query state after the custom loop.
		wp_reset_query();
		wp_reset_postdata();

		return $return_string;
	}
endif;













/*
*  Video items functions
*
*/

if ( ! function_exists( 'wpstream_item_list_shortcodes' ) ) {
	/**
	 * Render a paginated / filterable item list, or answer an AJAX page request.
	 *
	 * Two output paths: when called for Elementor it prints the filter bar, the
	 * item grid and the pagination; when called as an AJAX request it returns the
	 * next page of items (and pagination) as JSON.
	 *
	 * @param array $attributes Attributes for the item list shortcode.
	 */
	function wpstream_item_list_shortcodes( $attributes ) {

		// Ensure the player JS is available for any rendered video item.
		wpstream_load_player_js_on_demand();

		// populate from get if we have pagination.
		$attributes = wpstream_populate_from_get( $attributes );

		// Core query inputs from the attributes.
		$item_post_type = sanitize_text_field( $attributes['type'] );
		$posts_per_page = intval( $attributes['number'] );
		$paged          = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		// The page query-var is suffixed with the widget uid so multiple lists paginate independently.
		$query_var_name = 'paged';
		if ( isset( $attributes['uid'] ) && !empty($attributes['uid'] )) {
			$query_var_name .= '_' . $attributes['uid'];
		}

		// Resolve the requested page: default 1, overridden by $_GET, then by an AJAX-supplied page.
		$paged = 1;
		if ( isset( $_GET[ $query_var_name ] ) ) {
			$paged = intval( $_GET[ $query_var_name ] );
		}
		if ( isset( $attributes['is_ajax_request'] ) && 1 === intval($attributes['is_ajax_request']) ) {
			$paged = intval( $attributes['paged'] );
		}

		// Column class derived from the requested items-per-row.
		$overwrite_wpstream_cols_name = wpstream_video_cards_column_class( intval( $attributes['rownumber'] ) );

		// Seed the tax_query; all supplied term filters are AND-combined.
		$tax_query_array = array(
			'relation' => 'AND',
		);

		// Map of attribute name => taxonomy for the supported filters.
		$wpstream_item_list_shortcodes_input_to_tax = wpstream_item_list_shortcodes_input_to_tax();

		// For every provided filter, add a term clause to the tax_query.
		foreach ( $wpstream_item_list_shortcodes_input_to_tax as $input => $taxonomy ) :
			if ( isset( $attributes[ $input ] ) && !empty($attributes[ $input ]) ) {
				$tax_array         = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => array( $attributes[ $input ] ),
				);
				$tax_query_array[] = $tax_array;

			}
		endforeach;

		// Base query args using the resolved page number.
		$args = array(
			'post_type'      => $item_post_type,
			'post_status'    => 'publish',
			'paged'          => $paged,
			'posts_per_page' => $posts_per_page,
			'tax_query'      => $tax_query_array, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);

		// Merge in the order-by clause chosen by the sort_by setting.
		$order_array = wpstream_query_arguments_add_order_by( $attributes['sort_by'] );
		$args        = array_merge( $args, $order_array['order_array'] );

		// Transient caching is read then force-disabled (always a fresh query).
		$use_transient = wpstream_return_use_transient();
		$use_transient = false;
		$transient_key = 'wpstream_archive_' . $taxonomy . '_to_be_detterminded';
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );

		// Path A: full Elementor render — filter bar + grid + pagination.
		if ( isset( $attributes['is_elementor'] ) && $attributes['is_elementor'] ) {

			// Open the list wrapper and print the filter bar and AJAX holder data.
			print '<div class="wpstream-shortcode-list-wrapper ">';
			print wpstream_filter_bar( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			print '<div class="wpstream-shortcode-wrapper wpstream-item-list-wrapper row">';
			print wpstream_compose_ajax_holder_data( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped


			// Resolve the card template for each item.
			$unit_card_type = wpstream_video_item_card_selector( intval($attributes['video_card']) );


			// Render each item card directly (no output buffering on this path).
			if ( $query->have_posts() ) :
				while ( $query->have_posts() ) :
					$query->the_post();

					include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;

				endwhile;
			endif;

			// Restore global post/query state after the loop.
			wp_reset_postdata();
			wp_reset_query();

			// Close the item grid.
			print '</div>';

			// Print the pagination controls for this list.
			wpstream_return_item_list_pagination( $query, $attributes['pagination_type'], $attributes['uid'] );

			// Close the list wrapper.
			print '</div>';

		} elseif ( isset( $attributes['is_ajax_request'] ) && $attributes['is_ajax_request'] ) {
			// Path B: AJAX pagination — build the item HTML and metadata payload.
			$response_for_ajax                = wpstream_return_ajax_elements( $query, $overwrite_wpstream_cols_name,'',$attributes['video_card'] );
			$response_for_ajax['arg']         = $args;
			$response_for_ajax['found_posts'] = $query->found_posts;
			// Pagination type 2 => numbered pager markup; otherwise a "Load More" button.
			if ( 2 === intval($attributes['pagination_type']) ) {
				$response_for_ajax['html_pagination'] = wpstream_theme_pagination_ajax( $attributes, $query->max_num_pages, 2 );
			} else {
				$response_for_ajax['html_pagination'] = '<button type="button" class="btn btn-primary wpstream_load_more">' . esc_html__( 'Load More', 'hello-wpstream' ) . '</button>';
			}
			// Send the JSON response and stop.
			wp_send_json_success( $response_for_ajax );
			die();
		}
	}
}





/*
*  Return array with all terms for $taxonomies
*
*/
/**
 * Return every term across the filterable taxonomies (cached when possible).
 *
 * @return array Terms for category, actors, wpstream_category, movie rating and product_cat.
 */
if ( ! function_exists( 'wpstream_theme_return_all_taxomy_array' ) ) {
	function wpstream_theme_return_all_taxomy_array() {
		// Start empty; try the transient cache first if the helper exists.
		$all_terms = [];

		if ( function_exists( 'wpstream_request_transient_cache' ) ) {
			$all_terms = wpstream_request_transient_cache( 'wpstream_all_taxonomies_array' );
		}

		// Taxonomies whose terms power the filter bar.
		$taxonomies = array(
			'category',
			'wpstream_actors',
			'wpstream_category',
			'wpstream_movie_rating',
			'product_cat',
		);

		// On a cache miss, generate (and let the generator cache) the term list.
		if ( ! $all_terms ) {
			$all_terms = wpstream_theme_generate_all_taxomy_array( $taxonomies );
		}

		return $all_terms;
	}
}



/**
 * Render a single featured video card.
 *
 * @param array $attributes Settings (id, type, show_video).
 * @return string Buffered HTML for the featured card.
 */
function wpstream_theme_featured_video( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	// Player scripts required to play the featured item.
	wp_enqueue_script('video.min');
	wp_enqueue_script('wpstream-player');

	// Card inputs; $preview_video is consumed by the included template.
	$postId = intval( $attributes['id'] );
	$type   = sanitize_text_field( $attributes['type'] );
   	$preview_video = $attributes['show_video'];

	// Pick the template for this featured type and buffer its output.
	$card_type = wpstream_featured_video_card_selector( $type, 0 );
	ob_start();

	include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $card_type;
	$return_string = ob_get_contents();
	ob_end_clean();

	return $return_string;
}

/**
 * Map a featured type to its card template path.
 *
 * @param int|string $type    Featured card type (1 or 2).
 * @param int        $is_grid Grid flag (accepted for signature compatibility; unused).
 * @return string Relative path to the featured card template.
 */

if ( ! function_exists( 'wpstream_featured_video_card_selector' ) ) :
	function wpstream_featured_video_card_selector( $type, $is_grid = 0 ) {

		// Choose the template file for the requested featured type.
		if ( $type == 1 ) {
			$template = 'featured_video_item_type1.php';
		} elseif ( $type == 2 ) {
			$template = 'featured_video_item_type2.php';
		}

		// Return the path under the featured video-unit templates directory.
		return 'template-parts/video-unit-templates/' . $template;
	}
endif;


/**
 * Extracts the color ID from a given URL and returns it in a CSS variable format.
 *
 * @param string $url The URL containing the color ID in the query string.
 * @return string The CSS variable string for the color ID.
 */
if ( ! function_exists( 'get_css_variable_from_url' ) ) :
	function get_css_variable_from_url( $url ) {
		// Parse the URL to get the query part
		// e.g. globals/colors?id=primary -> "id=primary".
		$query = parse_url( $url, PHP_URL_QUERY );

		// Parse the query string into an associative array
		parse_str( $query, $params );

		// Extract the color ID
		// The color id lives in the "id" query parameter (empty if absent).
		$color_id = $params['id'] ?? '';

		// Return the CSS variable format
		// Produce the Elementor global-color CSS custom property reference.
		return "var(--e-global-color-{$color_id})";
	}
endif;