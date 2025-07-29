<?php

/**
 * Elementor functions
 *
 * @package wpstream-theme
 */

if ( ! function_exists( 'wpstream_testimonial_slider' ) ) :
function wpstream_testimonial_slider($settings,$slider_id ){

    $return_string = '';

    if (isset($settings['list']) && is_array($settings['list'])) {
       

    	
		$is_auto        = false;
		$card_type      = 'template-parts/testimonial-templates/testimonial_type1.php';
        $return_string .= '<div class="wpstream_theme_testimonial_slider_wrapper_widget wpstream_testimonial_slider wpstream-item-list-slider row"  data-auto="' . esc_attr( $settings['autoscroll'] ) . '"  id="' . esc_attr( $slider_id ) . '">';

		ob_start();
		foreach ($settings['list'] as $key => $testimonial):
            include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $card_type;
        endforeach;
        $cards = ob_get_contents();
        ob_end_clean();
        $return_string.=$cards;
    	$return_string.='</div>';
	}

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
		
		
		?>
	<form method="get" class="search-form wpstream-theme-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	
		<label class="fildersec">
			<?php echo wpstream_plugin_dropdown_for_search_bootstrap(); ?>
		</label>
		
		<label class="search-field-label">
			<span class="screen-reader-text"><?php esc_html_e( 'Search for:', 'hello-wpstream' ); ?></span>
			<input type="search" class="search-field" placeholder="<?php esc_attr( 'Search', 'hello-wpstream' ); ?>" value="<?php echo get_search_query(); ?>" name="s"/>
		</label>
		
		<button type="submit" class="agent_submit_class_elementor wpstream_submit_button ">
			<?php 
			
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
		$values = array(
			'any'                  => esc_html__( 'All', 'hello-wpstream' ),
			'post'                 => esc_html__( 'Blog Post', 'hello-wpstream' ),
			'wpstream_product'     => esc_html__( 'Live Events', 'hello-wpstream' ),
			'wpstream_product_vod' => esc_html__( 'Video on Demand', 'hello-wpstream' ),
			'wpstream_bundles'     => esc_html__( 'Video Bundles', 'hello-wpstream' ),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$values = array_merge($values, [
				'products' => esc_html__( 'Products', 'hello-wpstream' ),
			]);
		}

		$return_string = wpstream_create_custom_dropdown( $values, $select_name, '', '', esc_html__( 'All', 'hello-wpstream' ) );

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
		$content_values = '';

		if ( is_array( $values ) ) {
			foreach ( $values as $key => $item_value ) {
				$content_values .= '<li><button class="dropdown-item wpstream-dropdown-item" type="button" data-value="' . esc_attr( $key ) . '" >' . esc_html( $item_value ) . '</button></li>';
			}
		}

		$return_string = '<div class="dropdown-wrapper wpstream_dropdown_select dropdown ' . esc_attr( $class ) . '">
		<button class="btn btn-secondary dropdown-toggle" type="button" id="' . esc_attr( $id ) . '_button" data-bs-toggle="dropdown" aria-expanded="false">
		' . esc_html( $label ) . '
		</button>

		<ul class="dropdown-menu" aria-labelledby="' . esc_attr( $id ) . '">
			' . trim( $content_values ) . '
		</ul>
		<input class="dropdown-value-holder" type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $selected_value ) . '">
	  	</div>';

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
		$return_string = '<select name="' . esc_attr( $select_name ) . '">
    <option value="any">' . esc_html__( 'All', 'hello-wpstream' ) . '</option>
    <option value="post">' . esc_html__( 'Blog Post', 'hello-wpstream' ) . '</option>
    <option value="wpstream_product">' . esc_html__( 'Live Events', 'hello-wpstream' ) . '</option>
    <option value="wpstream_product_vod">' . esc_html__( 'Video on Demand', 'hello-wpstream' ) . '</option>
    <option value="wpstream_bundles">' . esc_html__( 'Video Bundles', 'hello-wpstream' ) . '</option>
    <option value="products">' . esc_html__( 'Products', 'hello-wpstream' ) . '</option>
    
    </select>';

		return $return_string;
	}
endif;



/**
 * Featured category.
 *
 * @param array $attributes Attributes.
 */
function wpstream_theme_featured_category( $attributes ) { //phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
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
		
		wpstream_load_player_js_on_demand();
		print wpstream_theme_recent_items_top_bar_with_filters( $attributes );

		$item_post_type = sanitize_text_field( $attributes['type'] );
		$posts_per_page = intval( $attributes['number'] );
		$paged          = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		if ( isset( $attributes['is_ajax_request'] ) && 1 === intval( $attributes['is_ajax_request'] ) ) {
			$paged = intval( $attributes['paged'] );
		}

		$overwrite_wpstream_cols_name = wpstream_video_cards_column_class( intval( $attributes['rownumber'] ) );

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

		$order_array = wpstream_query_arguments_add_order_by( $attributes['sort_by'] );
		$args        = array_merge( $args, $order_array['order_array'] );

		$use_transient = wpstream_return_use_transient();
		$use_transient = false;
		$transient_key = 'wpstream_item_list_filters_' . $taxonomy . '_to_be_detterminded';
		$query         = wpstream_custom_query( $args, $transient_key, $use_transient );

		if ( isset( $attributes['is_elementor'] ) && $attributes['is_elementor'] ) {
			print '<div class="wpstream-shortcode-list-wrapper ">';
			print '<div class="wpstream-shortcode-wrapper wpstream-item-list-with-top-filters-wrapper row">'; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			print wpstream_compose_ajax_holder_data( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	  		$unit_card_type = wpstream_video_item_card_selector( intval($attributes['video_card']) );
			
			if ( $query->have_posts() ) :
				while ( $query->have_posts() ) :
					$query->the_post();
					include locate_template( $unit_card_type );
					endwhile;
				endif;

			print '</div>';
			wpstream_return_item_list_pagination( $query, 1 );
			print '</div>';

			wp_reset_postdata();
			wp_reset_query();

		} elseif ( isset( $attributes['is_ajax_request'] ) && $attributes['is_ajax_request'] ) {
			$response_for_ajax                = wpstream_return_ajax_elements( $query, $overwrite_wpstream_cols_name,'',$attributes['video_card'] );
			$response_for_ajax['arg']         = $args;
			$response_for_ajax['found_posts'] = $query->found_posts;

			$response_for_ajax['html_pagination'] = '<button type="button" class="btn btn-primary wpstream_load_more">' . esc_html__( 'Load More', 'hello-wpstream' ) . '</button>';

			wp_send_json_success( $response_for_ajax );
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
		$return_string = '';
		if ( isset( $attributes['form_fields'] ) and is_array( $attributes['form_fields'] ) ) {
			$return_string .= '<div class="control_tax_wrapper">';
			foreach ( $attributes['form_fields'] as $key => $field ) {
				$term_data = get_term( $field['field_type'] );

				if ( isset( $term_data->term_id ) ) {

					$is_item_active_class = wpstream_theme_topbar_sh_is_item_active( $attributes, $term_data->term_id );

					$return_string .= '<div  class="control_tax_sh ' . esc_attr( $is_item_active_class ) . '" data-taxid="' . $term_data->term_id . '" data-taxonomy="' . $term_data->taxonomy . '">';

					if ( isset( $field['icon'] ) && ! empty( $field['icon'] ) ) {
						ob_start();
						\Elementor\Icons_Manager::render_icon( $field['icon'], array( 'aria-hidden' => 'true' ) );
						$item_icon = ob_get_contents();
						ob_end_clean();
						$return_string .= $item_icon;
					}

					$return_string .= $field['field_label'] . '</div>';
				}
			}
			$return_string .= '</div>';

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
		$return_class   = '';
		$term_id_string = strval( $term_id );

		if ( ( isset( $attributes['category_ids'] ) && strpos( strval( $attributes['category_ids'] ), $term_id_string ) !== false ) ||
		( isset( $attributes['action_ids'] ) && strpos( strval( $attributes['action_ids'] ), $term_id_string ) !== false ) ||
		( isset( $attributes['city_ids'] ) && strpos( strval( $attributes['city_ids'] ), $term_id_string ) !== false ) ||
		( isset( $attributes['area_ids'] ) && strpos( strval( $attributes['area_ids'] ), $term_id_string ) !== false ) ||
		( isset( $attributes['state_ids'] ) && strpos( strval( $attributes['state_ids'] ), $term_id_string ) !== false ) ||
		( isset( $attributes['status_ids'] ) && strpos( strval( $attributes['status_ids'] ), $term_id_string ) !== false ) ||
		( isset( $attributes['features_ids'] ) && strpos( strval( $attributes['features_ids'] ), $term_id_string ) !== false ) ) {

			$return_class = 'tax_active';

		}

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
		global $wpstream_plugin;
		$product_id     =   '';
		$return_string  =   '';


		$attributes =   shortcode_atts( 
			array(
				'item_id'                       => 0,
				'user_id'                  => 0,
			), $attributes) ;


		if ( isset($attributes['item_id']) ){
			$product_id=$attributes['item_id'];
		}
		if ( isset($attributes['user_id']) ){
			$user_id = intval( $attributes['user_id'] );
		}
		
		if(intval($product_id)==0 && $user_id!=0 ){
			$product_id= $wpstream_plugin->wpstream_player_retrive_first_id($user_id);
		}
		

		$poster_id            =   get_post_thumbnail_id($product_id);
		$poster_data          =   wp_get_attachment_image_src($poster_id,'full');
		$poster_url           =   '';
		if(isset($poster_data[0])){
			$poster_url=$poster_data[0];
		} 
		
		
		ob_start();?>
		<div class="wpstream_simple_player_shortcode_wrapper">
				<div class="wpstream_video_poster_holder wpstream_hide_on_trailer" style="background-image:url('<?php echo esc_attr($poster_url);?>'"></div>
			
		<div class="wpstream_player_container_gradient wpstream_hide_on_play"></div>
		
			<?php 
			$wpstream_plugin->main->wpstream_player->wpstream_video_player_shortcode($product_id);
			?>

			<?php
			
				if( get_post_type($product_id) == 'product' && function_exists('wc_get_product') ){
					$product    = wc_get_product( $product_id );
					$current_user   =   wp_get_current_user();

					print 'xxx1 product';

					if ( function_exists( 'wc_customer_bought_product' ) && ! wc_customer_bought_product( $current_user->user_email, $current_user->ID,$product_id) ) {
						?>
						<div class="wpstream-author-buttons wpstream-author-buttons-simple-player-block">

							<?php
							$wp_stream_product_price   = $product->get_price_html();
							$wp_stream_add_to_cart_url = add_query_arg(
								array(
									'add-to-cart' => $product_id,
									'quantity'    => 1,
								),
								wc_get_cart_url()
							);
							?>

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
					}else{
						?>
						<div class="wpstream-product-purchased-section">
							<?php
							$current_user           =       wp_get_current_user();
							$product_type 			= 		$product->get_type();
							print  wpstream_theme_get_svg_icon( 'check.svg' );
							esc_html_e('You have purchased this video on','hello-wpstream');
							$list  = wpstream_dashboard_get_products_by_user( $current_user->ID, $product_type, 1,-1);
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
		$return_string= ob_get_contents();
			
		ob_end_clean(); 

		return $return_string;
	}
endif;

