<?php
/**
 * Bundled child card v1
 *
 * @package wpstream-theme
 *
 * @var string $post_type
 * @var string $excerpt
 */
$bundleID = get_the_ID();

?>
<div class="gridbox wpestream-bundle-item-card">
	<div class="card wpstream-gridcard">

		<?php
		if ( 'wpstream_bundles' === $post_type ) {
			$featured_image_size = 'wpstream_bundle_unit_cards_image'; // Set the field name for featured image.
		}
 
		$featured_image_size = 'wpstream_featured_unit_cards';
		print wpstream_theme_featured_image( $bundleID, $featured_image_size,'unit_card',true,false );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

		<div class="card-body wpestream-bundle-item-card__description">
			<a class="wpstream_video_card_title" href="<?php echo esc_url( get_permalink() ); ?>">
				<?php echo esc_html( get_the_title() ); ?>
			</a>

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

			<div class="wpstream_video_card_excerpt">
				<?php echo wp_kses_post(wp_trim_words ( $excerpt,20 )); ?>
			</div>
		</div>
	</div>
</div>