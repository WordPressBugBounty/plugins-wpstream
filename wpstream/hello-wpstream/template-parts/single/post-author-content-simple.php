<?php
/**
 * Post author content simple
 *
 * @package wpstream-theme
 */

if(isset($post->ID)){
	$postID=$post->ID;
}else{
	$postID=$post_id; // called from wpstream_theme_display_player
}

$post_type   = wpstream_get_current_post_type($postID);//phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

?>

		<?php
		$video_media_logo_id = intval( get_post_meta( $postID, 'media_logo', true) );
		if ( $video_media_logo_id !=0 ) {
			$video_media_logo = wp_get_attachment_url( $video_media_logo_id, 'full' );
			?>
			<img class="wpstream_theme_media_logo" src="<?php echo esc_url($video_media_logo); ?>" alt="media logo">
			<?php
		}

		?>

		
	<div class="wpstream_author_wrapper  wpstream_author_wrapper_simple flex-wrap">


		


        <?php if (class_exists('Wpstream_Player')) { ?>
		<div class="wpstream_author_wrapper_name">
			<span>

                <?php
                if ( is_single() ) {
					if ( 'post' === $post_type ) {
						the_category( ', ' );
						echo ' · ' . wpstream_get_post_read_count_by_id( $postID) . ' · ';//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						echo apply_filters( 'wpstream_author_content_simple_post_type_message', '', $post_type );
					}
				}

				echo wpstream_get_post_published_duration_by_id( $postID );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
                
            </span>
		</div>
    <?php } ?>


	</div>

<?php

if ( is_singular( 'product' ) ) {
	global $product;
	if ( function_exists( 'wc_customer_bought_product' ) && ! wc_customer_bought_product( wp_get_current_user()->user_email, wp_get_current_user()->ID, $postID ) ) {
		?>

		<div class="wpstream-author-buttons">
			<?php
			$wp_stream_product_price   = $product->get_price_html();
			$wp_stream_add_to_cart_url = wc_get_cart_url() . '?add-to-cart=' . $postID. '&quantity=1';
			?>

			<a href="<?php echo esc_url( $wp_stream_add_to_cart_url ); ?>" class="wp-stream-playbtn">

				<?php
				// translators: %s product price.
				echo esc_html( sprintf( __( '%s&nbsp;- Add to Cart', 'hello-wpstream' ), $wp_stream_product_price ) );
				?>

			</a>
		</div>
		<?php
	} 
}
?>