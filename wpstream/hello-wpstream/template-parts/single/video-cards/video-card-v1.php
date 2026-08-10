<?php
/**
 * Video card v1
 *
 * Standard grid video card: featured image (optionally a video preview), an
 * optional author avatar, linked title, author name or WooCommerce price, and
 * an optional "remove from Watch Later" button on the dashboard. Visibility of
 * pieces is driven by Customizer theme mods.
 *
 * @package wpstream-theme
 */

// Accumulates the CSS classes applied to the card's outer grid cell.
$class = '';

// Merge in any class passed by the caller via $args.
if ( isset( $args['class'] ) ) {
	$class .= $args['class'];
}

// Allow an explicit column-class override to be appended.
if ( isset( $overwrite_wpstream_cols_name ) && '' !== $overwrite_wpstream_cols_name ) :
	$class .= $overwrite_wpstream_cols_name;
endif;

// Customizer toggles controlling which card elements are shown.
$wpstream_unit_card_show_user_thumb = get_theme_mod( 'wpstream_unit_card_show_hide_user_thumb', true );  // show author avatar?
$wpstream_unit_card_use_video       = get_theme_mod( 'wpstream_unit_card_use_video');                    // use hover video preview?
$wpstream_unit_card_show_hide_views = get_theme_mod( 'wpstream_unit_card_show_hide_views', true);        // show the view count?

// Current global post, used below for post-type / product checks.
global $post;
?>

<!-- Video card v1: outer grid cell (classes include any caller overrides). -->
<div class="<?php echo esc_attr( $class ); ?> gridbox wpstream-video-card-unit v1">
	<!-- Card shell. -->
	<div class="card wpstream-gridcard">
			<?php
			// Featured image / hover video preview for the card thumbnail.
			print wpstream_theme_featured_image( get_the_ID(), 'wpstream_featured_unit_cards', $wpstream_unit_card_use_video ? 'video_preview' : '', true,  $wpstream_unit_card_show_hide_views);//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		<!-- Card body: optional avatar, title, author/price, dashboard controls. -->
		<div class="card-bodyx">

			<!-- Author avatar block, shown only when the Customizer toggle is on. -->
			<?php if ( $wpstream_unit_card_show_user_thumb ) : ?>
				<!-- Resolve a 48px profile image URL for the post author. -->
				<?php $image_url = wpstream_get_author_profile_image_url_by_author_id( get_the_author_meta( 'ID' ), 48 ); ?>

				<!-- Author avatar linking to the author's archive. -->
				<a class="wpstream_author" href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( get_the_author() ); ?>">
				</a>
			<?php endif; ?>

			<!-- Title wrapper: title plus author name or product price. -->
			<div class="wpstream_card_title_wrapper">
				<!-- Linked card title. -->
				<a class="wpstream_video_card_title" href="<?php echo esc_url( get_permalink() ); ?>">
					<?php echo esc_html( get_the_title() ); ?>
				</a>

				<?php
				// For non-product posts, show the author name linking to their archive.
				if ( get_post_type( $post ) !== 'product' ) {
					?>
					<!-- Author name link (non-product posts only). -->
					<a class="wpstream_video_card_author" href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
						<?php echo esc_html( get_the_author() ); ?>
					</a>
				<?php } ?>

				<?php
				// For WooCommerce products, show the price instead of the author.
				if ( 'product' === get_post_type( $post ) && function_exists('wc_get_product') ) :
					// Load the product and read its display price.
					$product       = wc_get_product( $post );
					$product_price = $product->get_price();
					?>
					<!-- Product price badge. -->
					<div class="wpstream_video_card_price">
						<?php echo wc_price( $product_price ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>

				<!-- On the dashboard, offer a "remove from Watch Later" button. -->
				<?php if ( isset( $is_wpstream_theme_dashboard ) && $is_wpstream_theme_dashboard ) { ?>
					<!-- Watch Later remove button, carrying the post ID for the AJAX handler. -->
					<button class="wpstream_watch-later-remove-btn" data-toggle="tooltip" data-placement="top" title="<?php esc_attr_e( 'Remove Item from Watch Later list', 'hello-wpstream' ); ?>" data-post-id=<?php echo esc_attr( get_the_ID() ); ?>></button>
				<?php } ?>

			</div>

		</div>
	</div>
</div>
