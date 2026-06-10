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
		

            <?php if( $poster_url!='' ) {
			    echo '<div class="wpstream_video_poster_holder wpstream_hide_on_trailer" style="background-image:url(' . esc_attr($poster_url) . ');"></div>';
            } ?>


			<div class="wpstream_player_container_gradient wpstream_hide_on_play"></div>


			<div class="wpstream_title_wrapper_simple wpstream_hide_on_trailer">
				<?php
				if ( 'product' === get_post_type( $post_id )  ) {
					include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/single/product-add-to-cart-section.php';
				} else {
					include WPSTREAM_PLUGIN_PATH . '/hello-wpstream/template-parts/single/post-author-content-simple.php'; ?>
					<h1 class="wpstream_title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
				<?php
				}
				?>
			</div>

        <?php
		$now                              = time().rand(0,1000000);
		$mute_trailer_button_element_id   = 'wpstream_live_video_mute_trailer_btn_' . $now;
		$unmute_trailer_button_element_id = 'wpstream_live_video_unmute_trailer_btn_' . $now;
		$trailer_attachment_id = intval (get_post_meta( $post_id, 'video_trailer', true ));;

        if( function_exists('wpstream_theme_not_live_section' ) && 'wpstream_product' === get_post_type( $post_id ) ) {
            echo '<div class="wpstream_live_channel_actions_wrapper wpstream_video_on_demand_actions_wrapper">';
                print wpstream_theme_not_live_section( $post_id );
                if ( $trailer_attachment_id !== 0 ) {
                    echo '<button type="button" class="wpstream_player_controls wpstream_video_on_demand_play_trailer" aria-label="' . esc_attr__( 'Play Trailer', 'wpstream' ) . '">
                        <svg width="30" height="24" viewBox="0 0 30 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M26.6667 1.5H3.33337C2.50495 1.5 1.83337 2.17157 1.83337 3V21C1.83337 21.8284 2.50495 22.5 3.33338 22.5H26.6667C27.4951 22.5 28.1667 21.8284 28.1667 21V3C28.1667 2.17157 27.4951 1.5 26.6667 1.5ZM3.33337 0C1.67652 0 0.333374 1.34315 0.333374 3V21C0.333374 22.6569 1.67652 24 3.33338 24H26.6667C28.3236 24 29.6667 22.6569 29.6667 21V3C29.6667 1.34315 28.3236 0 26.6667 0H3.33337ZM4.83337 4C4.55723 4 4.33337 4.22386 4.33337 4.5V6.16667C4.33337 6.44281 4.55723 6.66667 4.83337 6.66667H6.50004C6.77618 6.66667 7.00004 6.44281 7.00004 6.16667V4.5C7.00004 4.22386 6.77618 4 6.50004 4H4.83337ZM23.5 4C23.2239 4 23 4.22386 23 4.5V6.16667C23 6.44281 23.2239 6.66667 23.5 6.66667H25.1667C25.4428 6.66667 25.6667 6.44281 25.6667 6.16667V4.5C25.6667 4.22386 25.4428 4 25.1667 4H23.5ZM4.33337 11.167C4.33337 10.8909 4.55723 10.667 4.83337 10.667H6.50004C6.77618 10.667 7.00004 10.8909 7.00004 11.167V12.8337C7.00004 13.1098 6.77618 13.3337 6.50004 13.3337H4.83337C4.55723 13.3337 4.33337 13.1098 4.33337 12.8337V11.167ZM23.5001 10.667C23.224 10.667 23.0001 10.8909 23.0001 11.167V12.8337C23.0001 13.1098 23.224 13.3337 23.5001 13.3337H25.1668C25.4429 13.3337 25.6668 13.1098 25.6668 12.8337V11.167C25.6668 10.8909 25.4429 10.667 25.1668 10.667H23.5001ZM4.33337 17.833C4.33337 17.5569 4.55723 17.333 4.83337 17.333H6.50004C6.77618 17.333 7.00004 17.5569 7.00004 17.833V19.4997C7.00004 19.7758 6.77618 19.9997 6.50004 19.9997H4.83337C4.55723 19.9997 4.33337 19.7758 4.33337 19.4997V17.833ZM23.5001 17.333C23.224 17.333 23.0001 17.5569 23.0001 17.833V19.4997C23.0001 19.7758 23.224 19.9997 23.5001 19.9997H25.1668C25.4429 19.9997 25.6668 19.7758 25.6668 19.4997V17.833C25.6668 17.5569 25.4429 17.333 25.1668 17.333H23.5001ZM19.0677 13.0997L13.4077 16.5087C13.0434 16.7281 12.6092 16.7094 12.2661 16.5091C11.9218 16.3081 11.6666 15.9224 11.6666 15.4086V8.59072C11.6666 8.07698 11.9218 7.69125 12.2661 7.49026C12.6092 7.28999 13.0434 7.27126 13.4077 7.49064L19.0677 10.8996C19.8663 11.3805 19.8663 12.6188 19.0677 13.0997Z"/>
                        </svg>
                        ' . esc_html__( 'Play Trailer', 'wpstream' ) . '
                    </button>';
                    echo '<div id="' . esc_attr( $mute_trailer_button_element_id ) . '" style="display: none;" class="wpstream_video_on_demand_mute_trailer">
                        <svg width="37" height="36" viewBox="0 0 37 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M1.32143 10.0789H8.69499L18.8964 0L21.1428 0.921053V35.1316L18.8964 36L8.69499 25.8684H1.32143L0 24.5526V11.3947L1.32143 10.0789ZM10.175 23.6842L18.5 31.9474V4.10526L10.175 12.3158L9.24999 12.7105H2.64286V23.2368H9.24999L10.175 23.6842ZM37 17.9737C37.0069 22.2216 35.5329 26.3401 32.8295 29.6263L30.9478 27.7579C33.1613 24.9734 34.3629 21.5249 34.3571 17.9737C34.3571 14.2895 33.0885 10.8974 30.9637 8.21053L32.8454 6.34211C35.5382 9.62494 37.0062 13.735 37 17.9737ZM31.7143 17.9737C31.7193 20.8255 30.7895 23.6011 29.0661 25.8789L27.1738 23.9947C28.4127 22.2295 29.0752 20.1272 29.0714 17.9737C29.0751 15.8287 28.4174 13.7344 27.1871 11.9737L29.0793 10.0895C30.7338 12.2868 31.7143 15.0158 31.7143 17.9737ZM26.4286 17.9737C26.4286 19.4842 26.0057 20.8947 25.2657 22.0947L23.3126 20.1526C23.6249 19.4729 23.7876 18.7345 23.7899 17.9869C23.7922 17.2394 23.634 16.5001 23.3258 15.8184L25.2789 13.8737C26.0083 15.0684 26.4286 16.4737 26.4286 17.9737Z" fill="white"/>
                        </svg>
                    </div>';
                    echo '<div id="' . esc_attr( $unmute_trailer_button_element_id ) . '" style="display: none;" class="wpstream_video_on_demand_unmute_trailer">
                        <svg width="33" height="32" viewBox="0 0 33 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M1.15625 8.85688H7.60813L16.5344 0L18.5 0.809375V30.8719L16.5344 31.635L7.60813 22.7319H1.15625L0 21.5756V10.0131L1.15625 8.85688ZM8.90313 20.8125L16.1875 28.0738V3.6075L8.90313 10.8225L8.09375 11.1694H2.3125V20.4194H8.09375L8.90313 20.8125ZM30.5967 11.3127L32.2316 12.9477L28.2287 16.9506L32.2316 20.9559L30.5967 22.5908L26.5938 18.5856L22.5885 22.5908L20.9536 20.9559L24.9588 16.9506L20.9513 12.95L22.5862 11.3151L26.5938 15.3157L30.5967 11.3127Z" fill="white"/>
                        </svg>
                    </div>';
                }
                echo '</div>';
        }
        ?>

		<?php
		if ( 'wpstream_product_vod' === get_post_type( $post_id ) ) {
			$muted = false;
			$wpstream_vod_start_muted   =   intval ( get_option('wpstream_vod_start_muted','') );
			if($wpstream_vod_start_muted===1){
				$muted=true;
			}

			$autoplay = false;
			$wpstream_vod_autoplay      =   intval  ( get_option('wpstream_vod_autoplay','') );
			if($wpstream_vod_autoplay===1){
				$autoplay=true;
			}

			echo '<div class="wpstream_video_on_demand_actions_wrapper" data-trailer-muted-default="' . ( $muted ? '1' : '0' ) . '"' . ( $autoplay && $trailer_attachment_id !== 0 ? ' data-autoplay-trailer="1"' : '' ) . '>';
			echo '<button type="button" class="wpstream_player_controls wpstream_video_on_demand_play_video_wrapper" aria-label="' . esc_attr__( 'Play Video', 'wpstream' ) . '">
						<span class="wpstream_video_on_demand_play_video">
							<svg width="29" height="30" viewBox="0 0 29 30" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
								<path fill-rule="evenodd" clip-rule="evenodd" d="M6.1808 28.9035L26.274 18.1652C29.1087 16.6503 29.1087 12.7497 26.274 11.2348L6.1808 0.496557C4.88769 -0.194506 3.34623 -0.1355 2.1283 0.495357C0.906043 1.12846 1.0095e-06 2.34351 9.38766e-07 3.96179L0 25.4382C-7.07369e-08 27.0565 0.906042 28.2715 2.1283 28.9046C3.34622 29.5355 4.88769 29.5945 6.1808 28.9035ZM24.8221 13.8026C25.5742 14.2045 25.5742 15.1955 24.8221 15.5974L4.72891 26.3356C3.94628 26.7539 3.01386 26.2165 3.01386 25.4382L3.01386 3.96179C3.01386 3.18347 3.94628 2.6461 4.72891 3.06436L24.8221 13.8026Z" fill="#F1F1F1"></path>
							</svg>
						</span>
						' . esc_html__( 'Play Video', 'wpstream' ) . '
					</button>';
			if ( $trailer_attachment_id !== 0 ) {
				echo '<button type="button" class="wpstream_player_controls wpstream_video_on_demand_play_trailer" aria-label="' . esc_attr__( 'Play Trailer', 'wpstream' ) . '">
							<svg width="30" height="24" viewBox="0 0 30 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
								<path fill-rule="evenodd" clip-rule="evenodd" d="M26.6667 1.5H3.33337C2.50495 1.5 1.83337 2.17157 1.83337 3V21C1.83337 21.8284 2.50495 22.5 3.33338 22.5H26.6667C27.4951 22.5 28.1667 21.8284 28.1667 21V3C28.1667 2.17157 27.4951 1.5 26.6667 1.5ZM3.33337 0C1.67652 0 0.333374 1.34315 0.333374 3V21C0.333374 22.6569 1.67652 24 3.33338 24H26.6667C28.3236 24 29.6667 22.6569 29.6667 21V3C29.6667 1.34315 28.3236 0 26.6667 0H3.33337ZM4.83337 4C4.55723 4 4.33337 4.22386 4.33337 4.5V6.16667C4.33337 6.44281 4.55723 6.66667 4.83337 6.66667H6.50004C6.77618 6.66667 7.00004 6.44281 7.00004 6.16667V4.5C7.00004 4.22386 6.77618 4 6.50004 4H4.83337ZM23.5 4C23.2239 4 23 4.22386 23 4.5V6.16667C23 6.44281 23.2239 6.66667 23.5 6.66667H25.1667C25.4428 6.66667 25.6667 6.44281 25.6667 6.16667V4.5C25.6667 4.22386 25.4428 4 25.1667 4H23.5ZM4.33337 11.167C4.33337 10.8909 4.55723 10.667 4.83337 10.667H6.50004C6.77618 10.667 7.00004 10.8909 7.00004 11.167V12.8337C7.00004 13.1098 6.77618 13.3337 6.50004 13.3337H4.83337C4.55723 13.3337 4.33337 13.1098 4.33337 12.8337V11.167ZM23.5001 10.667C23.224 10.667 23.0001 10.8909 23.0001 11.167V12.8337C23.0001 13.1098 23.224 13.3337 23.5001 13.3337H25.1668C25.4429 13.3337 25.6668 13.1098 25.6668 12.8337V11.167C25.6668 10.8909 25.4429 10.667 25.1668 10.667H23.5001ZM4.33337 17.833C4.33337 17.5569 4.55723 17.333 4.83337 17.333H6.50004C6.77618 17.333 7.00004 17.5569 7.00004 17.833V19.4997C7.00004 19.7758 6.77618 19.9997 6.50004 19.9997H4.83337C4.55723 19.9997 4.33337 19.7758 4.33337 19.4997V17.833ZM23.5001 17.333C23.224 17.333 23.0001 17.5569 23.0001 17.833V19.4997C23.0001 19.7758 23.224 19.9997 23.5001 19.9997H25.1668C25.4429 19.9997 25.6668 19.7758 25.6668 19.4997V17.833C25.6668 17.5569 25.4429 17.333 25.1668 17.333H23.5001ZM19.0677 13.0997L13.4077 16.5087C13.0434 16.7281 12.6092 16.7094 12.2661 16.5091C11.9218 16.3081 11.6666 15.9224 11.6666 15.4086V8.59072C11.6666 8.07698 11.9218 7.69125 12.2661 7.49026C12.6092 7.28999 13.0434 7.27126 13.4077 7.49064L19.0677 10.8996C19.8663 11.3805 19.8663 12.6188 19.0677 13.0997Z"/>
							</svg>
							' . esc_html__( 'Play Trailer', 'wpstream' ) . '
						</button>';
				echo '<div id="' . esc_attr( $mute_trailer_button_element_id ) . '" style="display: none;" class="wpstream_video_on_demand_mute_trailer">
							<svg width="37" height="36" viewBox="0 0 37 36" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path fill-rule="evenodd" clip-rule="evenodd" d="M1.32143 10.0789H8.69499L18.8964 0L21.1428 0.921053V35.1316L18.8964 36L8.69499 25.8684H1.32143L0 24.5526V11.3947L1.32143 10.0789ZM10.175 23.6842L18.5 31.9474V4.10526L10.175 12.3158L9.24999 12.7105H2.64286V23.2368H9.24999L10.175 23.6842ZM37 17.9737C37.0069 22.2216 35.5329 26.3401 32.8295 29.6263L30.9478 27.7579C33.1613 24.9734 34.3629 21.5249 34.3571 17.9737C34.3571 14.2895 33.0885 10.8974 30.9637 8.21053L32.8454 6.34211C35.5382 9.62494 37.0062 13.735 37 17.9737ZM31.7143 17.9737C31.7193 20.8255 30.7895 23.6011 29.0661 25.8789L27.1738 23.9947C28.4127 22.2295 29.0752 20.1272 29.0714 17.9737C29.0751 15.8287 28.4174 13.7344 27.1871 11.9737L29.0793 10.0895C30.7338 12.2868 31.7143 15.0158 31.7143 17.9737ZM26.4286 17.9737C26.4286 19.4842 26.0057 20.8947 25.2657 22.0947L23.3126 20.1526C23.6249 19.4729 23.7876 18.7345 23.7899 17.9869C23.7922 17.2394 23.634 16.5001 23.3258 15.8184L25.2789 13.8737C26.0083 15.0684 26.4286 16.4737 26.4286 17.9737Z" fill="white"/>
							</svg>
						</div>';
				echo '<div id="' . esc_attr( $unmute_trailer_button_element_id ) . '" style="display: none;" class="wpstream_video_on_demand_unmute_trailer">
							<svg width="33" height="32" viewBox="0 0 33 32" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path fill-rule="evenodd" clip-rule="evenodd" d="M1.15625 8.85688H7.60813L16.5344 0L18.5 0.809375V30.8719L16.5344 31.635L7.60813 22.7319H1.15625L0 21.5756V10.0131L1.15625 8.85688ZM8.90313 20.8125L16.1875 28.0738V3.6075L8.90313 10.8225L8.09375 11.1694H2.3125V20.4194H8.09375L8.90313 20.8125ZM30.5967 11.3127L32.2316 12.9477L28.2287 16.9506L32.2316 20.9559L30.5967 22.5908L26.5938 18.5856L22.5885 22.5908L20.9536 20.9559L24.9588 16.9506L20.9513 12.95L22.5862 11.3151L26.5938 15.3157L30.5967 11.3127Z" fill="white"/>
							</svg>
						</div>';
			}
			echo '</div>';
		}
		?>

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
	$status = isset( $event_data['status'] ) ? $event_data['status'] : '';
    $embedUrl = get_post_meta( $channel_id, 'embedUrl', true );

    if ( $embedUrl ) {
	    $return_string   = '<div class="wpstream_not_live_mess wpstream_live_channel_status"><div class="wpstream_not_live_mess_back"></div>';
	    $message_classes = array( 'wpstream_live_channel_status_message' );
	    if ( in_array( $status, array( 'stopped', 'stopping', 'starting' ) ) || $status === '' ) {
		    $message_classes[] = 'wpstream_not_live_mess_mess';
	    }
	    if ( in_array( $status, array( 'active' ) ) ) {
		    $message_classes[] = 'wpstream_player_state_init_class wpstream_not_live_mess_mess';
	    }

	    $return_string .= '<div class="' . esc_attr( implode( ' ', $message_classes ) ) . '">';
	    if ( ( in_array( $status, array( 'stopped', 'stopping', 'starting' ) ) || $status === '' ) ||
	         ( isset( $event_data['error'] ) && $event_data['error'] === 'NO_SUCH_CHANNEL' ) ) {
		    $return_string .= esc_html( get_option( 'wpstream_you_are_not_live', 'We are not live at this moment' ) );;
	    }
	    if ( in_array( $status, array( 'active' ) ) ) {
		    $return_string .= esc_html__( 'The live stream has not yet started', 'hello-wpstream' );
	    }
    } else {
	    $return_string  = '<div class="wpstream_not_live_mess wpstream_theme_not_live_section " style="display: none"><div class="wpstream_not_live_mess_back"></div>';
	    $return_string .= '<div class="wpstream_not_live_mess_mess">';

	    if ( ( isset( $event_data['status']) &&
	           in_array( $event_data['status'], array( 'stopped', 'stopping', 'starting' ) ) ) ||
	         ( isset($event_data['error']) && $event_data['error'] === 'NO_SUCH_CHANNEL' ) ){
		    $return_string  .= esc_html( get_option( 'wpstream_you_are_not_live', 'We are not live at this moment' ) );
	    } else {
		    $return_string .= '<div class="wpstream_loading_spinner vjs-loading-spinner" style="display: block;"></div>';
	    }
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
