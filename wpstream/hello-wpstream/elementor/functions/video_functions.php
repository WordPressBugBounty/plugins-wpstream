<?php





/**
 * Video Slider items.
 *
 * @param array  $attributes Attributes.
 * @param string $slider_id Slider id.
 * @return void
 */

if ( ! function_exists( 'wpstream_featured_video_slider' ) ) :
	function wpstream_featured_video_slider( $attributes, $slider_id ){

		// Determine the border color
		$item_border_color = $attributes['item_border_color'] ?? '';
		if ( empty( $item_border_color ) && !empty( $attributes['__globals__']['item_border_color'] ) ) {
			$item_border_color = get_css_variable_from_url($attributes['__globals__']['item_border_color']);
		}

		// Check if border radius and width are set and not empty
		if ( !empty( $attributes['item_border_radius'] ) && !empty( $attributes['item_border_width'] ) ) {
			$item_border_radius = $attributes['item_border_radius'];
			$item_border_width = $attributes['item_border_width'];

			// Ensure values are numeric
			$calculated = [
				'top' => (float)$item_border_radius['top'] - (float)$item_border_width['top'],
				'right' => (float)$item_border_radius['right'] - (float)$item_border_width['right'],
				'bottom' => (float)$item_border_radius['bottom'] - (float)$item_border_width['bottom'],
				'left' => (float)$item_border_radius['left'] - (float)$item_border_width['left']
			];

			// Determine the maximum border width
			$max_border_width = max( (float)$item_border_width['top'],
				(float)$item_border_width['right'],
				(float)$item_border_width['bottom'],
				(float)$item_border_width['left']
			);

			// Determine CSS based on calculated values
			$style = array_filter($calculated, fn($value) => $value < 0)
				? "box-shadow: 0px 0px 0px {$max_border_width}px {$item_border_color};"
				: "border-radius: {$calculated['top']}{$item_border_width['unit']} {$calculated['right']}{$item_border_width['unit']} {$calculated['bottom']}{$item_border_width['unit']} {$calculated['left']}{$item_border_width['unit']} !important;";

			// Generate and print the CSS styles
			echo '<style>
				.elementor-widget-WpStreamTheme_Featured_Video_Items_Slider .wpstream_featured_video > .wpstream_category_unit_item_cover {
					' . $style . '
				}
			</style>';
		}

		$return_string='';
		$arrow_extra_class='';
	 	$preview_video = $attributes['show_video'];
		$args = array(
			'post_type'		 => 	array('wpstream_product_vod','wpstream_product',	'wpstream_bundles',	'product' ),
			'post_status'    => 	'publish',
			'paged'          => 	-1,
			'posts_per_page' => 	'15',
			'post__in'	     => 	$attributes['video_id'],
		
			
		);

	  	$ken_burns_class = is_array($attributes) &&
			array_key_exists('ken_burns_effect', $attributes) &&
			$attributes['ken_burns_effect'] === 'yes' ?
				'wpstream-ken-burns-effect' :
				'';

		
		$use_transient = wpstream_return_use_transient();
		$use_transient = false;
		$transient_key = 'wpstream_featured_video_item_slider_' . $slider_id;
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );
		$unit_card_type = 'template-parts/video-unit-templates/featured_video_item_type1.php';

		$is_video_items_slider =true;
		
		ob_start();
		if ( $query->have_posts() ) :
			while ( $query->have_posts() ) :
				$query->the_post();
				$postId=get_the_ID();
				include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;

			endwhile;
		endif;
		$items_list = ob_get_contents();
		ob_end_clean();



		$return_string .= '<div class="wpstream-shortcode-wrapper wpstream-featured-video-item-list-slider row  '.esc_attr($arrow_extra_class).' '.esc_attr($ken_burns_class).'"  data-auto="' . esc_attr( $attributes['autoscroll'] ) . '" id="' . esc_attr( $slider_id ) . '">';
		$return_string .= $items_list;
		$return_string .= '</div>';

		return 	$return_string ;
	}
endif;





/**
 * Slider items.
 *
 * @param array  $attributes Attributes.
 * @param string $slider_id Slider id.
 * @return void
 */
if ( ! function_exists( 'wpestream_theme_slider_items' ) ) :
	function wpestream_theme_slider_items( $attributes, $slider_id ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		wpstream_load_player_js_on_demand();

		$return_string                = '';
		$item_post_type               = ( $attributes['type'] );
		$posts_per_page               = intval( $attributes['number'] );
		$arrow_extra_class			  = '';
		if(isset($attributes['arrows_position'])){
			$arrow_extra_class="wpstream_arrows_position_".$attributes['arrows_position'];
		}


		$overwrite_wpstream_cols_name = wpstream_video_cards_column_class( intval( $attributes['rownumber'] ) );

		$tax_query_array = array(
			'relation' => 'AND',
		);

		$wpstream_item_list_shortcodes_input_to_tax = wpstream_item_list_shortcodes_input_to_tax();

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

		$args = array(
			'post_type'      => $item_post_type,
			'post_status'    => 'publish',
			'paged'          => -1,
			'posts_per_page' => $posts_per_page,
			'tax_query'      => $tax_query_array, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);

		$order_array = wpstream_query_arguments_add_order_by( $attributes['sort_by'] );
		$args        = array_merge( $args, $order_array['order_array'] );

		$use_transient = wpstream_return_use_transient();
		$use_transient = false;
		$transient_key = 'wpstream_video_item_slider_' . $slider_id;
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );
	
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

		$return_string .= '<div class="wpstream-shortcode-wrapper wpstream-item-list-slider row  '.esc_attr($arrow_extra_class).'" data-items-per-row="' . intval( $attributes['rownumber'] ) . '" data-auto="' . esc_attr( $attributes['autoscroll'] ) . '" id="' . esc_attr( $slider_id ) . '">';
		$return_string .= $items_list;
		$return_string .= '</div>';

		wp_reset_postdata();
		wp_reset_query();

		return $return_string;
	}
	endif;





/**
 * List items by id.
 *
 * @param array $attributes Attributes.
 */
if ( ! function_exists( 'wpstream_theme_list_items_by_id_function' ) ) :
	function wpstream_theme_list_items_by_id_function( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found



		$return_string    = '';
		$video_ids_string = '';
		$video_ids        = array();

		if ( isset( $attributes['video_ids'] ) && is_array( $attributes['video_ids'] ) ) {
			$video_ids        = $attributes['video_ids'];
			$video_ids_string = implode( '_', $video_ids );
		}

		$items_per_row = 3;
		if ( isset( $attributes['items_per_row'] ) ) {
			$items_per_row = $attributes['items_per_row'];
		}

		$overwrite_wpstream_cols_name = wpstream_video_cards_column_class( intval( $items_per_row ) );

		$post_number_total = count( $video_ids );
		$args              = array(
			'post_status'    => 'publish',
			'post_type'      => array( 'wpstream_product_vod', 'wpstream_product', 'product', 'wpstream_bundles' ),
			'post__in'       => $video_ids,
			'paged'          => 0,
			'posts_per_page' => $post_number_total,
			'orderby'        => 'post__in',
		);

		$use_transient = wpstream_return_use_transient();

		$transient_key = 'wpstream_item_by_id_' . $video_ids_string;
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );
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

		$return_string  = '<div class="wpstream-shortcode-wrapper wpstream-item-list-by-if-wrapper row">';
		$return_string .= $items_list;
		$return_string .= '</div>';

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
	 * Process and display item list shortcodes.
	 *
	 * @param array $attributes Attributes for the item list shortcode.
	 */
	function wpstream_item_list_shortcodes( $attributes ) {

		wpstream_load_player_js_on_demand();

		// populate from get if we have pagination.
		$attributes = wpstream_populate_from_get( $attributes );

		$item_post_type = sanitize_text_field( $attributes['type'] );
		$posts_per_page = intval( $attributes['number'] );
		$paged          = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		$query_var_name = 'paged';
		if ( isset( $attributes['uid'] ) && !empty($attributes['uid'] )) {
			$query_var_name .= '_' . $attributes['uid'];
		}

		$paged = 1;
		if ( isset( $_GET[ $query_var_name ] ) ) {
			$paged = intval( $_GET[ $query_var_name ] );
		}
		if ( isset( $attributes['is_ajax_request'] ) && 1 === intval($attributes['is_ajax_request']) ) {
			$paged = intval( $attributes['paged'] );
		}

		$overwrite_wpstream_cols_name = wpstream_video_cards_column_class( intval( $attributes['rownumber'] ) );

		$tax_query_array = array(
			'relation' => 'AND',
		);

		$wpstream_item_list_shortcodes_input_to_tax = wpstream_item_list_shortcodes_input_to_tax();

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

		$args = array(
			'post_type'      => $item_post_type,
			'post_status'    => 'publish',
			'paged'          => $paged,
			'posts_per_page' => $posts_per_page,
			'tax_query'      => $tax_query_array, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);

		$order_array = wpstream_query_arguments_add_order_by( $attributes['sort_by'] );
		$args        = array_merge( $args, $order_array['order_array'] );

		$use_transient = wpstream_return_use_transient();
		$use_transient = false;
		$transient_key = 'wpstream_archive_' . $taxonomy . '_to_be_detterminded';
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );

		if ( isset( $attributes['is_elementor'] ) && $attributes['is_elementor'] ) {

			print '<div class="wpstream-shortcode-list-wrapper ">';
			print wpstream_filter_bar( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			print '<div class="wpstream-shortcode-wrapper wpstream-item-list-wrapper row">';
			print wpstream_compose_ajax_holder_data( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			
			$unit_card_type = wpstream_video_item_card_selector( intval($attributes['video_card']) );

			
			if ( $query->have_posts() ) :
				while ( $query->have_posts() ) :
					$query->the_post();

					include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;

				endwhile;
			endif;

			wp_reset_postdata();
			wp_reset_query();

			print '</div>';

			wpstream_return_item_list_pagination( $query, $attributes['pagination_type'], $attributes['uid'] );

			print '</div>';

		} elseif ( isset( $attributes['is_ajax_request'] ) && $attributes['is_ajax_request'] ) {
			$response_for_ajax                = wpstream_return_ajax_elements( $query, $overwrite_wpstream_cols_name,'',$attributes['video_card'] );
			$response_for_ajax['arg']         = $args;
			$response_for_ajax['found_posts'] = $query->found_posts;
			if ( 2 === intval($attributes['pagination_type']) ) {
				$response_for_ajax['html_pagination'] = wpstream_theme_pagination_ajax( $attributes, $query->max_num_pages, 2 );
			} else {
				$response_for_ajax['html_pagination'] = '<button type="button" class="btn btn-primary wpstream_load_more">' . esc_html__( 'Load More', 'hello-wpstream' ) . '</button>';
			}
			wp_send_json_success( $response_for_ajax );
			die();
		}
	}
}





/*
*  Return array with all terms for $taxonomies
*
*/
if ( ! function_exists( 'wpstream_theme_return_all_taxomy_array' ) ) {
	function wpstream_theme_return_all_taxomy_array() {
		$all_terms = [];

		if ( function_exists( 'wpstream_request_transient_cache' ) ) {
			$all_terms = wpstream_request_transient_cache( 'wpstream_all_taxonomies_array' );
		}

		$taxonomies = array(
			'category',
			'wpstream_actors',
			'wpstream_category',
			'wpstream_movie_rating',
			'product_cat',
		);

		if ( ! $all_terms ) {
			$all_terms = wpstream_theme_generate_all_taxomy_array( $taxonomies );
		}

		return $all_terms;
	}
}



/**
 * Featured video.
 *
 * @param array $attributes Attributes.
 */
function wpstream_theme_featured_video( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	wp_enqueue_script('video.min');
	wp_enqueue_script('wpstream-player');

	$postId = intval( $attributes['id'] );
	$type   = sanitize_text_field( $attributes['type'] );
   	$preview_video = $attributes['show_video'];

	$card_type = wpstream_featured_video_card_selector( $type, 0 );
	ob_start();

	include locate_template( $card_type );
	$return_string = ob_get_contents();
	ob_end_clean();

	return $return_string;
}

/**
 * Featured article card selector.
 *
 * @param array $attributes Attributes.
 */

if ( ! function_exists( 'wpstream_featured_video_card_selector' ) ) :
	function wpstream_featured_video_card_selector( $type, $is_grid = 0 ) {

		if ( $type == 1 ) {
			$template = 'featured_video_item_type1.php';
		} elseif ( $type == 2 ) {
			$template = 'featured_video_item_type2.php';
		}

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
		$query = parse_url( $url, PHP_URL_QUERY );

		// Parse the query string into an associative array
		parse_str( $query, $params );

		// Extract the color ID
		$color_id = $params['id'] ?? '';

		// Return the CSS variable format
		return "var(--e-global-color-{$color_id})";
	}
endif;