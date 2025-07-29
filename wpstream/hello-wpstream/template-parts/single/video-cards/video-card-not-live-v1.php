<?php
/**
 * Video card not live v1
 *
 * @package wpstream-theme
 */
$postID= get_the_ID();
if ( isset( $overwrite_wpstream_cols_name ) && '' !== $overwrite_wpstream_cols_name ) {
	$wpstream_cols_name = $overwrite_wpstream_cols_name;
}
?>

<div class="<?php echo esc_attr( $wpstream_cols_name ?? '' ); ?> gridbox wpstream-video-card-unit">

	<div class="card wpstream-gridcard">
		<a class="wpstream_video_card_title" href="<?php echo esc_url( get_permalink() ); ?>">
			<?php
			$featured_image_size = 'wpstream_featured_unit_cards';
			print wpstream_theme_featured_image_simple( get_the_ID(), $featured_image_size );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</a>
		
		<div class="card-bodyx">

			<div class="wpstream_card_title_wrapper">
				<a class="wpstream_video_card_title" href="<?php echo esc_url( get_permalink() ); ?>">
					<?php echo esc_html( get_the_title() ); ?>
				</a>

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