<?php
/**
 * Video card v1
 *
 * @package wpstream-theme
 */

$class = '';

if ( isset( $args['class'] ) ) {
	$class .= $args['class'];
}

if ( isset( $overwrite_wpstream_cols_name ) && '' !== $overwrite_wpstream_cols_name ) :
	$class .= $overwrite_wpstream_cols_name;
endif;

$wpstream_unit_card_show_user_thumb = get_theme_mod( 'wpstream_unit_card_show_hide_user_thumb', true );
$wpstream_unit_card_use_video       = get_theme_mod( 'wpstream_unit_card_use_video');
$wpstream_unit_card_show_hide_views = get_theme_mod( 'wpstream_unit_card_show_hide_views', true);

global $post;
?>

<div class="<?php echo esc_attr( $class ); ?> gridbox wpstream-video-card-unit v1">
	<div class="card wpstream-gridcard">
			<?php
			print wpstream_theme_featured_image( get_the_ID(), 'wpstream_featured_unit_cards', $wpstream_unit_card_use_video ? 'video_preview' : '', true,  $wpstream_unit_card_show_hide_views);//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		<div class="card-bodyx">

			<?php if ( $wpstream_unit_card_show_user_thumb ) : ?>
				<?php $image_url = wpstream_get_author_profile_image_url_by_author_id( get_the_author_meta( 'ID' ), 48 ); ?>

				<a class="wpstream_author" href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( get_the_author() ); ?>">
				</a>
			<?php endif; ?>

			<div class="wpstream_card_title_wrapper">
				<a class="wpstream_video_card_title" href="<?php echo esc_url( get_permalink() ); ?>">
					<?php echo esc_html( get_the_title() ); ?>
				</a>

				<?php
				if ( get_post_type( $post ) !== 'product' ) {
					?>
					<a class="wpstream_video_card_author" href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
						<?php echo esc_html( get_the_author() ); ?>
					</a>
				<?php } ?>

				<?php
				if ( 'product' === get_post_type( $post ) && function_exists('wc_get_product') ) :
					$product       = wc_get_product( $post );
					$product_price = $product->get_price();
					?>
					<div class="wpstream_video_card_price">
						<?php echo wc_price( $product_price ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>

				<?php if ( isset( $is_wpstream_theme_dashboard ) && $is_wpstream_theme_dashboard ) { ?>
					<button class="wpstream_watch-later-remove-btn" data-toggle="tooltip" data-placement="top" title="<?php esc_attr_e( 'Remove Item from Watch Later list', 'hello-wpstream' ); ?>" data-post-id=<?php echo esc_attr( get_the_ID() ); ?>></button>
				<?php } ?>

			</div>

		</div>
	</div>
</div>
