<?php
/**
 * Video card v2
 *
 * "Tower" variant of the grid video card: featured image (optionally a video
 * preview), linked title, a taxonomy details row (category + wpstream_category),
 * an optional WooCommerce price, and an optional dashboard "remove from Watch
 * Later" button. Visibility is driven by Customizer theme mods.
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
$wpstream_unit_card_show_user_thumb = get_theme_mod( 'wpstream_unit_card_show_hide_user_thumb', true );      // show author avatar?
$wpstream_unit_card_use_video       = intval( get_theme_mod( 'wpstream_unit_card_use_video', 0 ) );          // 1 = hover video preview
$wpstream_unit_card_show_hide_views = get_theme_mod( 'wpstream_unit_card_show_hide_views', true );           // show the view count?

// Current global post, used below for the product-type check.
global $post;
?>

<!-- Video card v2 (tower): outer grid cell (classes include any overrides). -->
<div class="<?php echo esc_attr( $class ); ?> gridbox wpstream-video-card-unit v2">
	<!-- Card shell. -->
	<div class="card wpstream-gridcard">
		<?php
		// Default to the tall "tower" thumbnail size.
		$featured_image_size = 'wpstream_featured_image_tower';

		// Bundles use their own thumbnail size instead.
		if ( 'wpstream_bundles' === get_post_type( get_the_ID() ) ) {
			$featured_image_size = 'wpstream_bundle_unit_cards_image'; // Set the field name for featured image.
		}

		// Featured image / hover video preview for the card thumbnail.
		print wpstream_theme_featured_image( get_the_ID(), $featured_image_size, 1 === $wpstream_unit_card_use_video ? 'video_preview' : '', true, $wpstream_unit_card_show_hide_views);//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<!-- Card body: title, taxonomy details, price, dashboard controls. -->
		<div class="card-bodyx">

			<!-- Title wrapper. -->
			<div class="wpstream_card_title_wrapper">
				<!-- Linked card title. -->
				<a class="wpstream_video_card_title" href="<?php echo esc_url( get_permalink() ); ?>">
					<?php echo esc_html( get_the_title() ); ?>
				</a>

				<!-- Details row: links for each attached category / wpstream_category term. -->
				<div class="wpstream_video_card_card_details">
					<?php
					// Collect terms from both the standard and wpstream taxonomies.
					$terms    = array();
					// Standard post categories.
					$category = get_the_terms( get_the_ID(), 'category' );
					if ( is_array( $category ) ) {
						$terms = array_merge( $terms, $category );
					}
					// WpStream-specific categories.
					$wpstream_category = get_the_terms( get_the_ID(), 'wpstream_category' );
					if ( is_array( $wpstream_category ) ) {
						$terms = array_merge( $terms, $wpstream_category );
					}

					// Print each term as a link, joined by a middot separator.
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
				// For WooCommerce products, show the price.
				if ( 'product' === get_post_type( $post ) && function_exists('wc_get_product') ) :
					// Load the product and read its display price.
					$product       = wc_get_product( $post );
					$product_price = $product->get_price();
					?>
					<!-- Product price badge. -->
					<span class="wpstream_video_card_price"><?php echo wc_price( $product_price ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
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
