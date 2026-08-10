<?php
/**
 * Bundled child card v1
 *
 * Renders one child item inside a bundle grid: featured image, linked title,
 * an (currently commented-out) taxonomy detail row, and a trimmed excerpt.
 * Included once per child post while looping over a bundle's contents.
 *
 * @package wpstream-theme
 *
 * @var string $post_type Post type of the item being rendered (e.g. wpstream_bundles).
 * @var string $excerpt   Excerpt text, trimmed to 20 words on output.
 */
// Current post ID for this card; used to resolve its featured image.
$bundleID = get_the_ID();

?>
<!-- Bundle child card: outer grid cell wrapping the card. -->
<div class="gridbox wpestream-bundle-item-card">
	<!-- Card shell. -->
	<div class="card wpstream-gridcard">

		<?php
		// Pick the featured-image size for this card's thumbnail.
		if ( 'wpstream_bundles' === $post_type ) {
			$featured_image_size = 'wpstream_bundle_unit_cards_image'; // Set the field name for featured image.
		}
 
		// Effective image size actually used for the card thumbnail.
		$featured_image_size = 'wpstream_featured_unit_cards';
		// Output the featured image (already escaped inside the helper).
		print wpstream_theme_featured_image( $bundleID, $featured_image_size,'unit_card',true,false );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

		<!-- Card body: title, details row, excerpt. -->
		<div class="card-body wpestream-bundle-item-card__description">
			<!-- Linked card title pointing at the item permalink. -->
			<a class="wpstream_video_card_title" href="<?php echo esc_url( get_permalink() ); ?>">
				<?php echo esc_html( get_the_title() ); ?>
			</a>

			<!-- Details row (taxonomy links) — currently disabled below. -->
			<div class="wpstream_video_card_details wpstream_video_card_details_bundle_child">
				<?php 	
				//echo wpstream_get_post_published_duration_by_id( $bundleID );  
				/*
				$terms = get_the_terms($bundleID, 'wpstream_category');

				if (!is_wp_error($terms) && !empty($terms)) {
					$count = count($terms);  // Get the number of terms
					$i = 0;  // Initialize a counter

					foreach ($terms as $term) {
						$url = get_term_link($term);
						if (!is_wp_error($url)) {
							print '<a href="' . esc_url($url) . '">' . esc_html($term->name) . '</a>';
							if (++$i !== $count) {  // Increment counter and check if it's not the last item
								print '<span>&#183;</span>';
							}
						}
					}
				}*/
				?>


			</div>

			<!-- Excerpt, trimmed to 20 words and sanitized for safe HTML. -->
			<div class="wpstream_video_card_excerpt">
				<?php echo wp_kses_post(wp_trim_words ( $excerpt,20 )); ?>
			</div>
		</div>
	</div>
</div>