<?php

/**
 * Elementor functions
  *
  * Assorted Elementor widget helpers: the testimonial slider, the advanced search
  * form and its category dropdown, the top-bar item list renderer with taxonomy
  * filters, and the simple player (with WooCommerce add-to-cart / purchased states).
 *
 * @package wpstream-theme
 */

if ( ! function_exists( 'wpstream_testimonial_slider' ) ) :
function wpstream_testimonial_slider($settings,$slider_id ){

    // Output accumulator.
    $return_string = '';

    // Only render when a testimonial list array was supplied.
    if (isset($settings['list']) && is_array($settings['list'])) {
       

    	
		// Autoplay flag (declared here; the slider actually reads autoscroll from the data attribute).
		$is_auto        = false;
		// Testimonial card template.
		$card_type      = 'template-parts/testimonial-templates/testimonial_type1.php';
        // Open the slider wrapper, embedding autoscroll + slider id data attributes.
        $return_string .= '<div class="wpstream_theme_testimonial_slider_wrapper_widget wpstream_testimonial_slider wpstream-item-list-slider row"  data-auto="' . esc_attr( $settings['autoscroll'] ) . '"  id="' . esc_attr( $slider_id ) . '">';

		// Buffer one card per testimonial ($testimonial is in scope for the template).
		ob_start();
		// Render each testimonial card.
		foreach ($settings['list'] as $key => $testimonial):
            include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $card_type;
        endforeach;
        // Capture the buffered cards...
        $cards = ob_get_contents();
        // ...and discard the buffer.
        ob_end_clean();
        // Append the cards...
        $return_string.=$cards;
    	// ...and close the slider wrapper.
    	$return_string.='</div>';
	}

    // Return the slider HTML (empty string when no list was supplied).
    return $return_string;
}
endif;



/**
 * Function to handle advanced search functionality.
 *
 * @param array $attributes Attributes for the advanced search.
 */
if ( ! function_exists( 'wpstreamtheme_advanced_search_function' ) ) :
	function wpstreamtheme_advanced_search_function( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		
		
		// Everything below (until the closing PHP tag) is echoed HTML: the advanced search form.
		?>
	<!-- Advanced search form; submits a GET request to the site root. -->
	<form method="get" class="search-form wpstream-theme-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	
		<!-- Post-type filter: the custom category dropdown. -->
		<label class="fildersec">
			<?php echo wpstream_plugin_dropdown_for_search_bootstrap(); ?>
		</label>
		
		<!-- Free-text search field. -->
		<label class="search-field-label">
			<span class="screen-reader-text"><?php esc_html_e( 'Search for:', 'hello-wpstream' ); ?></span>
			<input type="search" class="search-field" placeholder="<?php esc_attr( 'Search', 'hello-wpstream' ); ?>" value="<?php echo get_search_query(); ?>" name="s"/>
		</label>
		
		<!-- Submit button; label comes from the widget setting or defaults to 'Search'. -->
		<button type="submit" class="agent_submit_class_elementor wpstream_submit_button ">
			<?php 
			
				// Use the custom button label when provided, otherwise the default 'Search'.
				if(!empty($attributes['submit_button_text'])){
			 		echo esc_html($attributes['submit_button_text']); 
				}else{
					esc_html_e( 'Search', 'hello-wpstream' ); 
				}
			?>
			</span>
		</button>

		
		<!-- <button class="closeBtn"></button> -->
	</form>


		<?php
	}
endif;


if ( ! function_exists( 'wpstream_plugin_dropdown_for_search_bootstrap' ) ) :
	function wpstream_plugin_dropdown_for_search_bootstrap( $select_name = 'search_filter' ) {
		// Base search-scope options (value => label) for the dropdown.
		$values = array(
			'any'                  => esc_html__( 'All', 'hello-wpstream' ),
			'post'                 => esc_html__( 'Blog Post', 'hello-wpstream' ),
			'wpstream_product'     => esc_html__( 'Live Events', 'hello-wpstream' ),
			'wpstream_product_vod' => esc_html__( 'Video on Demand', 'hello-wpstream' ),
			'wpstream_bundles'     => esc_html__( 'Video Bundles', 'hello-wpstream' ),
		);

		// Add a generic 'Products' option when WooCommerce is active.
		if ( class_exists( 'WooCommerce' ) ) {
			$values = array_merge($values, [
				'products' => esc_html__( 'Products', 'hello-wpstream' ),
			]);
		}

		// Build the custom Bootstrap dropdown markup from the options.
		$return_string = wpstream_create_custom_dropdown( $values, $select_name, '', '', esc_html__( 'All', 'hello-wpstream' ) );

		// Return the dropdown HTML.
		return $return_string;
	}
endif;

if ( ! function_exists( 'wpstream_create_custom_dropdown' ) ) {
	/**
	 * Sanitize a number field value.
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return string Returns the sanitized number as a string, or an empty string if the input is not a number.
	 */
	function wpstream_create_custom_dropdown( $values, $name, $class, $id, $label, $selected_value = '' ) {
		// Accumulator for the <li> option items.
		$content_values = '';

		// Render one dropdown-item button per option.
		if ( is_array( $values ) ) {
			foreach ( $values as $key => $item_value ) {
				// Each item carries its value in data-value; the label is escaped for display.
				$content_values .= '<li><button class="dropdown-item wpstream-dropdown-item" type="button" data-value="' . esc_attr( $key ) . '" >' . esc_html( $item_value ) . '</button></li>';
			}
		}

		// Assemble the dropdown: toggle button, menu list, and a hidden input holding the selected value.
		$return_string = '<div class="dropdown-wrapper wpstream_dropdown_select dropdown ' . esc_attr( $class ) . '">
		<button class="btn btn-secondary dropdown-toggle" type="button" id="' . esc_attr( $id ) . '_button" data-bs-toggle="dropdown" aria-expanded="false">
		' . esc_html( $label ) . '
		</button>

		<ul class="dropdown-menu" aria-labelledby="' . esc_attr( $id ) . '">
			' . trim( $content_values ) . '
		</ul>
		<input class="dropdown-value-holder" type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $selected_value ) . '">
	  	</div>';

		// Return the dropdown HTML.
		return $return_string;
	}
}






if ( ! function_exists( 'wpestream_dropdown_for_search' ) ) :
	/**
	 * Function to return search dropdown
	 *
	 * @param array $attributes Attributes for the advanced search.
	 */
	function wpestream_dropdown_for_search( $select_name = 'search_filter' ) {
		// Build a plain <select> of the search-scope options.
		$return_string = '<select name="' . esc_attr( $select_name ) . '">
    <option value="any">' . esc_html__( 'All', 'hello-wpstream' ) . '</option>
    <option value="post">' . esc_html__( 'Blog Post', 'hello-wpstream' ) . '</option>
    <option value="wpstream_product">' . esc_html__( 'Live Events', 'hello-wpstream' ) . '</option>
    <option value="wpstream_product_vod">' . esc_html__( 'Video on Demand', 'hello-wpstream' ) . '</option>
    <option value="wpstream_bundles">' . esc_html__( 'Video Bundles', 'hello-wpstream' ) . '</option>
    <option value="products">' . esc_html__( 'Products', 'hello-wpstream' ) . '</option>
    
    </select>';

		// Return the select HTML.
		return $return_string;
	}
endif;



/**
 * Featured category.
 *
 * @param array $attributes Attributes.
 */
function wpstream_theme_featured_category( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	// Placeholder output; featured-category rendering is not implemented yet.
	print 'will do featured category';
}


if ( ! function_exists( 'wpstream_theme_recent_items_top_bar' ) ) :
	/**
	 * Recent items tob bar.
	 *
	 * @param array  $attributes Attributes.
	 * @param string $slider_id Slider id.
	 * @return void
	 */
	function wpstream_theme_recent_items_top_bar( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		
		// Ensure the on-demand player JS is enqueued.
		wpstream_load_player_js_on_demand();
		// Print the top filter bar above the list.
		print wpstream_theme_recent_items_top_bar_with_filters( $attributes );

		// Post type to query.
		$item_post_type = sanitize_text_field( $attributes['type'] );
		// Items per page.
		$posts_per_page = intval( $attributes['number'] );
		// Current page from the main query, defaulting to 1.
		$paged          = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		// AJAX pagination: take the page number from the request attributes instead.
		if ( isset( $attributes['is_ajax_request'] ) && 1 === intval( $attributes['is_ajax_request'] ) ) {
			$paged = intval( $attributes['paged'] );
		}

		// Bootstrap column class for the per-row count.
		$overwrite_wpstream_cols_name = wpstream_video_cards_column_class( intval( $attributes['rownumber'] ) );

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

		// Assemble the main WP_Query arguments.
		$args = array(
			'post_type'      => $item_post_type,
			'post_status'    => 'publish',
			'paged'          => $paged,
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
		// Cache key placeholder ($taxonomy is the last foreach value).
		$transient_key = 'wpstream_item_list_filters_' . $taxonomy . '_to_be_detterminded';
		// Run the query through the shared cached-query helper.
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );

		// Elementor render path: echo the list markup directly.
		if ( isset( $attributes['is_elementor'] ) && $attributes['is_elementor'] ) {
			// Open the outer list wrapper.
			print '<div class="wpstream-shortcode-list-wrapper ">';
			// Open the inner row wrapper.
			print '<div class="wpstream-shortcode-wrapper wpstream-item-list-with-top-filters-wrapper row">'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			// Emit the hidden AJAX data holder.
			print wpstream_compose_ajax_holder_data( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	  		// Resolve the video card template variant.
	  		$unit_card_type = wpstream_video_item_card_selector( intval($attributes['video_card']) );
			// Prefer a theme override of the card template if one exists.
			$theme_template_path = locate_template( $unit_card_type );
			// Otherwise fall back to the plugin's bundled card template.
			$plugin_template_path = WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;

			// Render a card per matched post.
			if ( $query->have_posts() ) :
				while ( $query->have_posts() ) :
					// Set up the post globals.
					$query->the_post();
					// Include the theme override when present, else the plugin template.
					if ( ! empty( $theme_template_path ) && file_exists( $theme_template_path ) ) {
						include $theme_template_path;
					} elseif ( file_exists( $plugin_template_path ) ) {
						include $plugin_template_path;
					}
				endwhile;
			endif;

			// Close the inner row wrapper.
			print '</div>';
			// Print pagination controls.
			wpstream_return_item_list_pagination( $query, 1 );
			// Close the outer list wrapper.
			print '</div>';

			// Restore post globals.
			wp_reset_postdata();
			// Restore global query state.
			wp_reset_query();

		// AJAX path: return the cards + a Load More button as JSON.
		} elseif ( isset( $attributes['is_ajax_request'] ) && $attributes['is_ajax_request'] ) {
			// Render the cards into the AJAX response structure.
			$response_for_ajax                = wpstream_return_ajax_elements( $query, $overwrite_wpstream_cols_name,'',$attributes['video_card'] );
			// Include the query args (for inspection).
			$response_for_ajax['arg']         = $args;
			// Total number of matching posts.
			$response_for_ajax['found_posts'] = $query->found_posts;

			// Load More button markup for the client.
			$response_for_ajax['html_pagination'] = '<button type="button" class="btn btn-primary wpstream_load_more">' . esc_html__( 'Load More', 'hello-wpstream' ) . '</button>';

			// Send the JSON success response...
			wp_send_json_success( $response_for_ajax );
			// ...and halt.
			die();
		}
	}
endif;



/**
 * Recent items tob bar.
 *
 * @param array  $attributes Attributes.
 * @param string $slider_id Slider id.
 * @return void
 */
if ( ! function_exists( 'wpstream_theme_recent_items_top_bar_with_filters' ) ) :
	function wpstream_theme_recent_items_top_bar_with_filters( $attributes ) {
		// Output accumulator.
		$return_string = '';
		// Only build the bar when filter field definitions were supplied.
		if ( isset( $attributes['form_fields'] ) and is_array( $attributes['form_fields'] ) ) {
			// Open the filter bar wrapper.
			$return_string .= '<div class="control_tax_wrapper">';
			// Render one clickable filter chip per configured field.
			foreach ( $attributes['form_fields'] as $key => $field ) {
				// Look up the term this field targets.
				$term_data = get_term( $field['field_type'] );

				// Only render the chip for a valid term.
				if ( isset( $term_data->term_id ) ) {

					// Mark the chip active if this term is among the selected ids.
					$is_item_active_class = wpstream_theme_topbar_sh_is_item_active( $attributes, $term_data->term_id );

					// Open the chip, tagging it with the term id + taxonomy for the JS filter.
					$return_string .= '<div  class="control_tax_sh ' . esc_attr( $is_item_active_class ) . '" data-taxid="' . $term_data->term_id . '" data-taxonomy="' . $term_data->taxonomy . '">';

					// Render the optional Elementor icon into the chip.
					if ( isset( $field['icon'] ) && ! empty( $field['icon'] ) ) {
						ob_start();
						\Elementor\Icons_Manager::render_icon( $field['icon'], array( 'aria-hidden' => 'true' ) );
						$item_icon = ob_get_contents();
						ob_end_clean();
						// Append the rendered icon.
						$return_string .= $item_icon;
					}

					// Append the label and close the chip.
					$return_string .= $field['field_label'] . '</div>';
				}
			}
			// Close the filter bar wrapper.
			$return_string .= '</div>';

			// Return the filter bar HTML (only set when fields were supplied).
			return $return_string;
		}
	}
endif;



/**
 *
 *
 * @param array  $attributes Attributes.
 * @param string $term_id Slider id.
 * @return void
 */
if ( ! function_exists( 'wpstream_theme_topbar_sh_is_item_active' ) ) :
	function wpstream_theme_topbar_sh_is_item_active( $attributes, $term_id ) {
		// Empty unless the term id matches one of the selected filter groups.
		$return_class   = '';
		// Compared as a substring within each id list below.
		$term_id_string = strval( $term_id );

		// Active when the term id appears in any of the selected id lists:
		// category / action / city / area / state / status / features.
		if ( ( isset( $attributes['category_ids'] ) && strpos( strval( $attributes['category_ids'] ), $term_id_string ) !== false ) ||
		( isset( $attributes['action_ids'] ) && strpos( strval( $attributes['action_ids'] ), $term_id_string ) !== false ) ||
		( isset( $attributes['city_ids'] ) && strpos( strval( $attributes['city_ids'] ), $term_id_string ) !== false ) ||
		( isset( $attributes['area_ids'] ) && strpos( strval( $attributes['area_ids'] ), $term_id_string ) !== false ) ||
		( isset( $attributes['state_ids'] ) && strpos( strval( $attributes['state_ids'] ), $term_id_string ) !== false ) ||
		( isset( $attributes['status_ids'] ) && strpos( strval( $attributes['status_ids'] ), $term_id_string ) !== false ) ||
		( isset( $attributes['features_ids'] ) && strpos( strval( $attributes['features_ids'] ), $term_id_string ) !== false ) ) {

			// Matched: mark the chip active.
			$return_class = 'tax_active';

		}

		// Return the active class (or empty string).
		return $return_class;
	}
endif;








/**
 * Simple player.
 *
 * @param array $attributes Attributes.
 */
if ( ! function_exists( 'wpstream_theme_simple_player' ) ) :
	function wpstream_theme_simple_player( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Access the main plugin instance (used to render the player).
		global $wpstream_plugin;
		// Post/product id whose media to play.
		$product_id     =   '';
		// Buffered output accumulator.
		$return_string  =   '';


		// Apply defaults for the accepted attributes (item_id, user_id).
		$attributes =   shortcode_atts( 
			array(
				'item_id'                       => 0,
				'user_id'                  => 0,
			), $attributes) ;


		// Use the explicit item id when provided.
		if ( isset($attributes['item_id']) ){
			$product_id=$attributes['item_id'];
		}
		// Capture the user id when provided.
		if ( isset($attributes['user_id']) ){
			$user_id = intval( $attributes['user_id'] );
		}
		
		// No item id but a user id: fall back to that user's first playable item.
		if(intval($product_id)==0 && $user_id!=0 ){
			$product_id= $wpstream_plugin->wpstream_player_retrive_first_id($user_id);
		}
		

		// Featured image id used as the video poster.
		$poster_id            =   get_post_thumbnail_id($product_id);
		// Full-size poster image data.
		$poster_data          =   wp_get_attachment_image_src($poster_id,'full');
		// Poster URL (empty when the item has no featured image).
		$poster_url           =   '';
		// Use the resolved poster URL when available.
		if(isset($poster_data[0])){
			$poster_url=$poster_data[0];
		} 
		
		
		// Buffer the player markup below.
		ob_start();?>
		<!-- Simple player wrapper. -->
		<div class="wpstream_simple_player_shortcode_wrapper">
				<!-- Poster image shown before playback. -->
				<div class="wpstream_video_poster_holder wpstream_hide_on_trailer" style="background-image:url('<?php echo esc_attr($poster_url);?>'"></div>
			
		<!-- Gradient overlay, hidden once playback starts. -->
		<div class="wpstream_player_container_gradient wpstream_hide_on_play"></div>
		
			<?php 
			// Render the actual video player for this item.
			$wpstream_plugin->main->wpstream_player->wpstream_video_player_shortcode($product_id);
			?>

			<?php
			
				// For a WooCommerce product, show either an add-to-cart button or a purchased note.
				if( get_post_type($product_id) == 'product' && function_exists('wc_get_product') ){
					// Load the product.
					$product    = wc_get_product( $product_id );
					// Current user (used for the purchase check).
					$current_user   =   wp_get_current_user();

					// NOTE: leftover debug output ('xxx1 product') printed into the page.
					print 'xxx1 product';

					// Not purchased yet: render the add-to-cart call to action.
					if ( function_exists( 'wc_customer_bought_product' ) && ! wc_customer_bought_product( $current_user->user_email, $current_user->ID,$product_id) ) {
						?>
						<!-- Add-to-cart call to action for an unpurchased product. -->
						<div class="wpstream-author-buttons wpstream-author-buttons-simple-player-block">

							<?php
							// Product price HTML.
							$wp_stream_product_price   = $product->get_price_html();
							// Build the add-to-cart URL (product id + quantity).
							$wp_stream_add_to_cart_url = add_query_arg(
								array(
									'add-to-cart' => $product_id,
									'quantity'    => 1,
								),
								wc_get_cart_url()
							);
							?>

							<!-- Add-to-cart link showing a cart icon and the price. -->
							<a href="<?php echo esc_url( $wp_stream_add_to_cart_url ); ?>" class="wp-stream-playbtn">
								<span class="wp-stream-playbtn__cart-wrap">
									<svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path fill-rule="evenodd" clip-rule="evenodd" d="M19.5031 27.1253C19.7482 26.6779 20.2164 26.4 20.7249 26.4H30.2888C31.3346 26.4 32.255 25.826 32.7291 24.958L37.4433 16.3626C37.5965 16.0834 37.6441 15.7744 37.5997 15.4818C37.8472 15.2291 38 14.8825 38 14.5C38 13.7268 37.3757 13.1 36.6056 13.1H15.6892C15.6432 13.1 15.5978 13.1022 15.5529 13.1066L15.3151 12.6021C14.8539 11.6238 13.8724 11 12.7943 11H11.3944C10.6243 11 10 11.6268 10 12.4C10 13.1732 10.6243 13.8 11.3944 13.8H11.7084C12.3682 13.8 12.9691 14.1816 13.2519 14.7802L17.1955 23.1278C17.5805 23.9427 17.5509 24.894 17.1159 25.6832L15.9263 27.842C14.9084 29.718 16.247 32 18.3666 32H33.7052C34.4753 32 35.0996 31.3732 35.0996 30.6C35.0996 29.8268 34.4753 29.2 33.7052 29.2H20.7249C19.6643 29.2 18.992 28.0583 19.5031 27.1253ZM31.0848 22.1548L34.529 15.9H16.8699L19.7447 21.998C20.2059 22.9762 21.1874 23.6 22.2654 23.6H28.6441C29.659 23.6 30.5937 23.0464 31.0848 22.1548Z" fill="#F1F1F1"/>
										<path d="M17.9925 33C16.3383 33 15 34.35 15 36C15 37.65 16.3383 39 17.9925 39C19.6466 39 21 37.65 21 36C21 34.35 19.6466 33 17.9925 33Z" fill="#F1F1F1"/>
										<path d="M31.9925 33C30.3383 33 29 34.35 29 36C29 37.65 30.3383 39 31.9925 39C33.6466 39 35 37.65 35 36C35 34.35 33.6466 33 31.9925 33Z" fill="#F1F1F1"/>
									</svg>
								</span>
								<?php
								// translators: %s product price.
								printf( esc_html__( '%s&nbsp;- Add to Cart', 'hello-wpstream' ), $wp_stream_product_price ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
								?>
							</a>

						</div>
						<?php
					// Already purchased: show a confirmation with the purchase date.
					}else{
						?>
						<!-- Purchased confirmation block. -->
						<div class="wpstream-product-purchased-section">
							<?php
							// Current user.
							$current_user           =       wp_get_current_user();
							// Product type.
							$product_type 			= 		$product->get_type();
							// Show a check icon.
							print  wpstream_theme_get_svg_icon( 'check.svg' );
							// 'You have purchased this video on' label.
							esc_html_e('You have purchased this video on','hello-wpstream');
							// Look up this user's purchases of this product type...
							$list  = wpstream_dashboard_get_products_by_user( $current_user->ID, $product_type, 1,-1);
							// ...and print the order date for this product when available.
							if( isset($list[$product_id]['order_date']) ){
								print esc_html(' '.$list[$product_id]['order_date']);
							}
							?>
						</div>
						<?php
					}
				}
				?>
			
		


		</div>
		<?php
		// Capture the buffered player markup...
		$return_string= ob_get_contents();
			
		// ...and discard the buffer.
		ob_end_clean(); 

		// Return the player HTML.
		return $return_string;
	}
endif;

