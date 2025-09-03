<?php
/**
 * CHeck if we display chat
 *
 * @package wpstream-theme
 */

 if ( ! function_exists( 'wpstream_theme_check_if_show_chat' ) ) :
	function wpstream_theme_check_if_show_chat($post_id){
		$post_type = get_post_type($post_id);
		$show_chat = false;

		

		if($post_type==='product' && get_theme_mod( 'wpstream_product_how_chat' )  ){
			$show_chat = true;
		}else if($post_type==='wpstream_product' && get_theme_mod( 'wpstream_free_to_view_live_show_chat' )  ){
			$show_chat = true;
		}else if($post_type==='wpstream_product_vod' && get_theme_mod( 'wpstream_video_on_demand_show_chat' ) ){
			$show_chat = true;
		}

		return $show_chat;
	}

endif;



/**
 * Display video player wrapper .
 *
 * @package wpstream-theme
 */

if ( ! function_exists( 'wpstream_theme_display_player_wrapper' ) ) :
function wpstream_theme_display_player_wrapper($post_id){
	global $wpstream_plugin;
		$current_user           =       wp_get_current_user();
			
		$woo_product_bought_class='';
		if ( is_singular( 'product' ) && function_exists('wc_get_product') ) {
				$product    = wc_get_product( $post_id );
				$product_type 		  =   $product->get_type();
				if ( 'wpstream_bundle' !== $product_type ) {


					if ( function_exists( 'wc_customer_bought_product' ) &&  wc_customer_bought_product( $current_user->user_email, $current_user->ID, $post_id ) ) {
							$woo_product_bought_class='wpstream_woo_product_bought';
					}
				}
		}


	print '<div class="row '.esc_attr(	$woo_product_bought_class).'">';

		if(wpstream_theme_check_if_show_chat($post_id)){
			print '<div class="col-md-8 wpstream_theme_display_player_wrapper_with_chat">';
				wpstream_theme_display_player( $post_id );
			print '</div>';

			print '<div class="col-md-4 wpstream_theme_display_player_chat_wrapper">';
				wpstream_theme_display_better_messages_chat($post_id);
			print '</div>';
		}else{
			wpstream_theme_display_player( $post_id );
		}


	print '</div>';
}
endif;




/**
 * Display better messages chat
 *
 * @package wpstream-theme
 */



if ( ! function_exists( 'wpstream_theme_display_better_messages_chat' ) ) :
function wpstream_theme_display_better_messages_chat($post_id){

	
	if(function_exists('better_messages')){

		$chat_post_id=get_post_meta( $post_id, 'wpstream_chat_post_id',true );
		if(intval($chat_post_id)===0){
			$chat_post_id  = wpstream_create_better_messages_chat($post_id);
		}

		print do_shortcode('[bp_better_messages_chat_room id="'.intval($chat_post_id).'"]');
	}else{
		esc_html_e('For chat support you need to install Better Messages plugin','hello-wpstream');
	}
	

}
endif;



/**
 * Create Better messages chat room 
 *
 * @package wpstream-theme
 */



 if ( ! function_exists( 'wpstream_create_better_messages_chat' ) ) :
	function wpstream_create_better_messages_chat($post_id){
		
		if(function_exists('better_messages')){
			$current_user           =   wp_get_current_user();
			$userID                 =   $current_user->ID;

			$post = array(
				'post_title'	=> sanitize_text_field(get_the_title( $post_id )),
				'post_status'	=> 'publish',
				'post_type'   => 'bpbm-chat',
				'post_author' => $userID,
		);
			$chat_post_id =  wp_insert_post($post );
			update_post_meta( $post_id, 'wpstream_chat_post_id',$chat_post_id );
			return $chat_post_id;

		}
	}
endif;	



/**
 * Display video player.
 *
 * @package wpstream-theme
 */

if ( ! function_exists( 'wpstream_theme_display_player' ) ) {
	/**
	 * Display the video player for the specified post.
	 *
	 * @param int $post_id The ID of the post.
	 * @return void
	 */
	function wpstream_theme_display_player( $post_id ) {
		global $wpstream_plugin;
		$current_user = wp_get_current_user();

		$poster_id            =   get_post_thumbnail_id($post_id);
		$poster_data          =   wp_get_attachment_image_src($poster_id,'full');
	
		$poster_url           =   '';
		if(isset($poster_data[0])){
			$poster_url=$poster_data[0];
		} 
		?>
		

			<div class="wpstream_video_poster_holder wpstream_hide_on_trailer" style="background-image:url('<?php echo esc_attr($poster_url);?>');"></div>


			<div class="wpstream_player_container_gradient wpstream_hide_on_play"></div>


			<div class="wpstream_title_wrapper_simple wpstream_hide_on_trailer">
				<?php
				if ( 'product' === get_post_type( $post_id )  ) {
					include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/single/product-add-to-cart-section.php';
				}else{
					include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/single/post-author-content-simple.php';
					?>
					<h1 class="wpstream_title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
				<?php
				}
				?>
			</div>

			<?php
			if ( $wpstream_plugin->main->wpstream_player->wpstream_check_if_player_can_dsplay_theme( $post_id ) ) {

				if ( 'subscription' === get_post_type( $post_id )  ) {
					$wpstream_plugin->main->wpstream_player->wpstream_video_on_demand_player_only_trailer( $post_id );
				}else	if ( 'product' === get_post_type( $post_id ) && function_exists('wc_get_product')  ) {
					$product      = wc_get_product( $post_id );
					$product_type 		  =   $product->get_type();
					if ( 'wpstream_bundle' === $product_type ) {
						$wpstream_plugin->main->wpstream_player->wpstream_video_on_demand_player_only_trailer( $post_id );
					}else{
					$wpstream_plugin->main->wpstream_player->wpstream_video_player_shortcode( $post_id );
					}
				}else{
					$wpstream_plugin->main->wpstream_player->wpstream_video_player_shortcode( $post_id );
					}
			//	
				
			} elseif ( get_post_type( $post_id ) === 'product'  && function_exists('wc_get_product') ) {

				$product      = wc_get_product( $post_id );
				$product_type 		  =   $product->get_type();

				if ( 'subscription' === $product_type ) {
					if ( ! wcs_user_has_subscription( $current_user->ID, $post_id, 'active' ) ) {
						$wpstream_plugin->main->wpstream_player->wpstream_display_no_buy_message( 'nobuy', $post_id );
					}
				} else {
					$wpstream_plugin->main->wpstream_player->wpstream_display_no_buy_message( 'nobuy', $post_id );
				}
			}

	}
}




if ( ! function_exists( 'wpstream_display_trailer' ) ) {
	/**
	 * Display the trailer for the specified post.
	 *
	 * @param int    $post_id The ID of the post.
	 * @param string $poster_thumb The URL of the poster thumbnail.
	 * @return string HTML content for displaying the trailer.
	 */
	function wpstream_display_trailer( $post_id, $poster_thumb = '' ) {

		$possible_bundle = get_post_meta( $post_id, 'wpstream_part_of_bundle', true );
		$current_user    = wp_get_current_user();

		if (
			get_post_type( $post_id ) === 'product' &&
			function_exists('wc_get_product') &&
			wc_get_product( $post_id )->get_type() === 'video_on_demand' &&
			function_exists( 'wc_customer_bought_product' ) &&
			wc_customer_bought_product( $current_user->user_email, $current_user->ID, $post_id )
		) {
			echo 'do not display trailer player - trailer in plugin';
			return; // We will display trailer in the plugin player.
		} elseif (
			get_post_type( $post_id ) === 'product' &&  
			function_exists('wc_get_product') &&
			wc_get_product( $post_id )->get_type() === 'subscription' &&
			function_exists( 'wcs_user_has_subscription' ) &&
			wcs_user_has_subscription( $current_user->ID, $post_id, 'active' )
		) {
			echo 'do not display trailer player - trailer in plugin';
			return; // We will display trailer in the plugin player.
		} elseif (
			get_post_type( $post_id ) === 'product' &&
			intval( $possible_bundle ) !== 0 &&
			function_exists( 'wc_customer_bought_product' ) &&
			wc_customer_bought_product( $current_user->user_email, $current_user->ID, $possible_bundle )
		) {
			echo 'do not display trailer player - trailer in plugin';
			return; // We will display trailer in the plugin player.
		} elseif ( get_post_type( $post_id ) === 'wpstream_product_vod' ) {
			echo 'do not display trailer player - trailer in plugin';
			return; // We will display trailer in the plugin player.
		}

		$return = '';
		ob_start();

		$video_attachment_id = get_post_meta( $post_id, 'video_trailer', true );
		$attachment_url      = wp_get_attachment_url( $video_attachment_id );
		$poster_thumb        = get_the_post_thumbnail_url( $post_id, 'full' );
		$attachment_metadata = wp_get_attachment_metadata( $video_attachment_id );
		$video_type          = '';
		if ( isset( $attachment_metadata['mime_type'] ) ) {
			$video_type = $attachment_metadata['mime_type'];
		}

		if ( $attachment_url ) {
			?>

			<video id="wpstream-video-trailer" class="video-js vjs-default-skin  vjs-16-9"
					data-product-id="<?php echo esc_attr( $post_id ); ?>" autoplay="true" muted="true" controls
					preload="auto" poster="<?php echo esc_url( $poster_thumb ); ?>">

				<source src="<?php echo esc_attr( trim( $attachment_url ) ); ?>"
						type="<?php echo esc_attr( $video_type ); ?>">
				<p class="vjs-no-js">
					<?php esc_html_e('To view this video please enable JavaScript, and consider upgrading to a web browser that supports HTML5 video','hello-wpstream')?>
				</p>
			</video>

			<?php
			print '<div class="wpstream_video_on_demand_play_trailer_sound">' . esc_html__( 'play trailer with sound','hello-wpstream' ) . '</div>';
		}

		$return = ob_get_contents();
		ob_end_clean();

		return $return;
	}
}


/**
 * Determine the column classes for video cards based on the number of columns per row.
 *
 * @param int $per_row The number of columns per row.
 * @return string The string of column classes.
 */
function wpstream_video_cards_column_class( $per_row ) {
	$per_row = intval( $per_row );
	$return = 'col-12 col-sm-6 col-md-4 col-lg-4';
	
	if ( 4 === $per_row ) {
		$return = 'col-12 col-sm-6 col-md-4 col-lg-3';
	}else if ( 2 === $per_row ) {
		$return = 'col-12 col-sm-6 col-md-6 col-lg-6';
	}else if ( 6 === $per_row ) {
		$return = 'col-12 col-sm-6 col-md-4 col-lg-3 col-xl-2';
	}else if ( 5 === $per_row ) {
		$return = 'col-12 col-sm-6 col-md-4 col-2-4';
	} 
	return $return;
}

/**
 * Generate the HTML markup for the non-live section of the WPStream theme.
 *
 * @param int $channel_id The ID of the channel.
 * @return string The HTML markup for the non-live section.
 */
function wpstream_theme_not_live_section( $channel_id ) {
    $transient_name = 'event_data_to_return_'.   $channel_id;
    $event_data = get_transient( $transient_name );

	$return_string  = '<div class="wpstream_not_live_mess wpstream_theme_not_live_section " style="display: none"><div class="wpstream_not_live_mess_back"></div>';
	$return_string .= '<div class="wpstream_not_live_mess_mess">';

	if ( ( isset( $event_data['status']) &&
		   in_array( $event_data['status'], array( 'stopped', 'stopping', 'starting' ) ) ) ||
		 ( isset($event_data['error']) && $event_data['error'] === 'NO_SUCH_CHANNEL' ) ){
		$return_string  .= esc_html( get_option( 'wpstream_you_are_not_live', 'We are not live at this moment' ) );;
	} else {
        $return_string .= '<div classs="wpstream_loading_spinner vjs-loading-spinner" style="display: block;"></div>';
    }

	$return_string .= '</div>';
	$return_string .= '</div>';
	return $return_string;
}

/**
 * Generate the HTML markup for past broadcasts in the non-live section of the WPStream theme.
 *
 * @param int $channel_id The ID of the channel.
 * @return string The HTML markup for past broadcasts.
 */
function wpstream_past_broascast_for_non_live_section( $channel_id ) {
	$wpstream_get_post_type = get_post_type( $channel_id );
	$author_id              = wpstream_get_author_id( $channel_id );
	$use_transient          = wpstream_return_use_transient();
	$transient_key          = 'wpstream_product_broadcaster_vod_query_' . $author_id;

	$broadcaster_video_query_args = array(
		'post_status'    => 'publish',
		'post_type'      => 'wpstream_product_vod',
		'posts_per_page' => 3,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => array(//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => 'wpstream_theme_attach_to_channel',
				'value'   => $channel_id,
				'compare' => '=',
				'type'    => 'numeric',
			),

		),
	);

	$query = wpstream_custom_query( $broadcaster_video_query_args, $transient_key, $use_transient );

	$return = '';
	if ( $query->found_posts > 0 ) {
		$return .= '<section class="wpstream_section wpstream_broadcaster_section">';
		$return .= '<div class="row wpstream_past_broascast_for_non_live_section_wrapper">';
		ob_start();
		include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/single/not-live-past-broadcast.php';
		$return .= ob_get_contents();
		ob_end_clean();
		$return .= '</div></section>';
	}

	return $return;
}
