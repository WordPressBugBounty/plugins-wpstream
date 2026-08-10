<?php
/**
 * Video card not live v1
 *
 * Card for a recorded (not live) broadcast: featured image, linked title, and an
 * author line showing who recorded it and how long ago. Column classes can be
 * overridden by the caller.
 *
 * @package wpstream-theme
 */
// Current post ID, reused for the "recorded ... ago" duration lookup.
$postID= get_the_ID();
// Allow the caller to override the grid column classes for this card.
if ( isset( $overwrite_wpstream_cols_name ) && '' !== $overwrite_wpstream_cols_name ) {
	$wpstream_cols_name = $overwrite_wpstream_cols_name;
}
?>

<!-- Not-live video card: outer grid cell (column classes may be overridden). -->
<div class="<?php echo esc_attr( $wpstream_cols_name ?? '' ); ?> gridbox wpstream-video-card-unit">

	<!-- Card shell. -->
	<div class="card wpstream-gridcard">
		<!-- Thumbnail link wrapping the featured image, pointing at the permalink. -->
		<a class="wpstream_video_card_title" href="<?php echo esc_url( get_permalink() ); ?>">
			<?php
			// Image size used for the card thumbnail.
			$featured_image_size = 'wpstream_featured_unit_cards';
			// Output the simple featured image (escaped inside the helper).
			print wpstream_theme_featured_image_simple( get_the_ID(), $featured_image_size );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</a>
		
		<!-- Card body: title and author/recorded metadata. -->
		<div class="card-bodyx">

			<!-- Wrapper for the title and author lines. -->
			<div class="wpstream_card_title_wrapper">
				<!-- Linked card title pointing at the permalink. -->
				<a class="wpstream_video_card_title" href="<?php echo esc_url( get_permalink() ); ?>">
					<?php echo esc_html( get_the_title() ); ?>
				</a>

				<!-- Author line: name, separator, then "Recorded <duration> ago". -->
				<a class="wpstream_video_card_author" href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
					<?php 
					echo esc_html( get_the_author() ); 
					echo ' <span>&#183;</span> ';
					esc_html_e('Recorded','hello-wpstream');
					echo ' '.wpstream_get_post_published_duration_by_id($postID );
					?>
				</a> 

			</div>

		</div>
	</div>
</div>