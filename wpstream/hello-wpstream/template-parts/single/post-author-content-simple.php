<?php
/**
 * Post author content (simple variant).
 *
 * Compact author/meta strip shown under a single post or video: an optional
 * media logo, the category/read-count/published-duration line, and — for
 * WooCommerce products the current user has not bought — an "Add to Cart"
 * button showing the price.
 *
 * @package wpstream-theme
 */

// Resolve the post ID: prefer the global $post, otherwise the $post_id passed
// in when this partial is rendered from wpstream_theme_display_player.
if(isset($post->ID)){
	$postID=$post->ID;
}else{
	$postID=$post_id; // called from wpstream_theme_display_player
}

// Determine the post type once for the branching output below.
$post_type   = wpstream_get_current_post_type($postID);//phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

?>

		<?php
		// Optional overlay logo stored as an attachment ID in the media_logo meta.
		$video_media_logo_id = intval( get_post_meta( $postID, 'media_logo', true) );
		// Only output the logo image when a real attachment ID is set.
		if ( $video_media_logo_id !=0 ) {
			// Resolve the full-size URL for the logo attachment.
			$video_media_logo = wp_get_attachment_url( $video_media_logo_id, 'full' );
			?>
			<!-- Media logo overlay image. -->
			<img class="wpstream_theme_media_logo" src="<?php echo esc_url($video_media_logo); ?>" alt="media logo">
			<?php
		}

		?>

		
	<div class="wpstream_author_wrapper  wpstream_author_wrapper_simple flex-wrap">


		


        <!-- Meta line renders only when the Wpstream_Player class is available. -->
        <?php if (class_exists('Wpstream_Player')) { ?>
		<!-- Author/meta name line: category, counts and published duration. -->
		<div class="wpstream_author_wrapper_name">
			<span>

                <?php
                // On single views, show taxonomy/read-count for posts, or a filtered message for other types.
                if ( is_single() ) {
					if ( 'post' === $post_type ) {
						// Standard blog post: list categories then the read count.
						the_category( ', ' );
						echo ' · ' . wpstream_get_post_read_count_by_id( $postID) . ' · ';//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						// Non-post types can inject their own meta message via this filter.
						echo apply_filters( 'wpstream_author_content_simple_post_type_message', '', $post_type );
					}
				}

				// Always append the human-readable "published X ago" duration.
				echo wpstream_get_post_published_duration_by_id( $postID );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
                
            </span>
		</div>
    <?php } ?>


	</div>

<?php

// For a WooCommerce product the visitor has NOT already purchased, show a buy button.
if ( is_singular( 'product' ) ) {
	global $product;
	// Guard on WooCommerce being active and the current user not having bought this product.
	if ( function_exists( 'wc_customer_bought_product' ) && ! wc_customer_bought_product( wp_get_current_user()->user_email, wp_get_current_user()->ID, $postID ) ) {
		?>

		<!-- Add-to-cart action button wrapper for an unpurchased product. -->
		<div class="wpstream-author-buttons">
			<?php
			// Formatted price HTML for the button label.
			$wp_stream_product_price   = $product->get_price_html();
			// Cart URL that adds one unit of this product when followed.
			$wp_stream_add_to_cart_url = wc_get_cart_url() . '?add-to-cart=' . $postID. '&quantity=1';
			?>

			<!-- Buy button linking to the add-to-cart URL. -->
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