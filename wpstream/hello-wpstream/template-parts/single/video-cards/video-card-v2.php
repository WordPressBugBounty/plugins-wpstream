<?php
/**
 * Video card v2
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
$wpstream_unit_card_use_video       = intval( get_theme_mod( 'wpstream_unit_card_use_video', 0 ) );
$wpstream_unit_card_show_hide_views = get_theme_mod( 'wpstream_unit_card_show_hide_views', true );

global $post;
?>

<div class="<?php echo esc_attr( $class ); ?> gridbox wpstream-video-card-unit v2">
	<div class="card wpstream-gridcard">
		<?php
		$featured_image_size = 'wpstream_featured_image_tower';

		if ( 'wpstream_bundles' === get_post_type( get_the_ID() ) ) {
			$featured_image_size = 'wpstream_bundle_unit_cards_image'; // Set the field name for featured image.
		}

		print wpstream_theme_featured_image( get_the_ID(), $featured_image_size, 1 === $wpstream_unit_card_use_video ? 'video_preview' : '', true, $wpstream_unit_card_show_hide_views);//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="card-bodyx">

			<div class="wpstream_card_title_wrapper">
				<a class="wpstream_video_card_title" href="<?php echo esc_url( get_permalink() ); ?>">
					<?php echo esc_html( get_the_title() ); ?>
				</a>

				<div class="wpstream_video_card_card_details">
					<?php
					$terms    = array();
					$category = get_the_terms( get_the_ID(), 'category' );
					if ( is_array( $category ) ) {
						$terms = array_merge( $terms, $category );
					}
					$wpstream_category = get_the_terms( get_the_ID(), 'wpstream_category' );
					if ( is_array( $wpstream_category ) ) {
						$terms = array_merge( $terms, $wpstream_category );
					}

					echo implode(
						'<span>&#183;</span>',
                        //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array_map(
							function ( $term ) {
								return '<a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
							},
							$terms
						)
					);
					?>
				</div>

				<?php
				if ( 'product' === get_post_type( $post ) && function_exists('wc_get_product') ) :
					$product       = wc_get_product( $post );
					$product_price = $product->get_price();
					?>
					<span class="wpstream_video_card_price"><?php echo wc_price( $product_price ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<?php endif; ?>

				<?php if ( isset( $is_wpstream_theme_dashboard ) && $is_wpstream_theme_dashboard ) { ?>
					<button class="wpstream_watch-later-remove-btn" data-toggle="tooltip" data-placement="top" title="<?php esc_attr_e( 'Remove Item from Watch Later list', 'hello-wpstream' ); ?>" data-post-id=<?php echo esc_attr( get_the_ID() ); ?>></button>
				<?php } ?>

			</div>

		</div>
	</div>
</div>
