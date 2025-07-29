<?php
/**
 * Template featured video type 1.
 *
 * @package wpstream-theme
 *
 * @var int $postId Post ID.
 */

$title      = get_the_title( $postId );
$excerpt    = get_the_excerpt( $postId );
$excerpt 	= wp_trim_words($excerpt, 40, '...'); 
$link       = get_permalink( $postId );
$badge_text = __( 'FEATURED VIDEO', 'hello-wpstream' );
$author     = get_the_author();
$date       = get_the_date();

$preview = wp_get_attachment_image_src( get_post_thumbnail_id( $postId ), 'full' );
if ( empty( $preview[0] ) ) {
	$image_url = get_theme_file_uri( '/img/default-image-video.png' );
} else {
	$image_url = $preview[0];
}

?>

<div class="wpstream_featured_video type-1" style="background-image:url('<?php echo esc_url( $image_url ); ?>');">
	<?php 
		if($preview_video=='yes'){
			$video_id = 'wpstream_featured_video_trailer_' . wp_rand( 0, 99999999 );
			$video_trailer_id = get_post_meta( $postId, 'video_trailer', true );
			$video_src = wp_get_attachment_url( $video_trailer_id );
			?>
			<div class="wpstream_video_unit_video_wrapper wpstream_video_unit_video_wrapper_trigger"  
				 data-video-id="<?php echo esc_attr( $video_id ) ;?>" >

                <video id="<?php echo esc_attr( $video_id );?>" class="wpstream_video_unit_video" preload="none" poster="<?php echo esc_url( $image_url );?>" muted>

                    <source src="<?php echo esc_url( $video_src );?>" type="video/mp4">

                    Your browser does not support the video tag.

                </video>

            </div>

		<?php		
		}
	
	?>

	<div class="wpstream_category_unit_item_cover"></div>
	<div class="container">
		<span class="wpstream_featured_video__badge mb-2 d-inline-block"><?php echo esc_html( $badge_text ); ?></span>
	
		<?php
			$video_media_logo_id = intval( get_post_meta( $postId, 'media_logo', true) );
			if ( $video_media_logo_id !=0 ) {
				$video_media_logo = wp_get_attachment_url( $video_media_logo_id, 'full' );
				?>
				<img class="wpstream_theme_media_logo" src="<?php echo esc_url($video_media_logo); ?>" alt="media logo">
				<?php
			}

		?>



		<h1>
			<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
		</h1>
		
		<?php 
		
		?>
			<p class=" wpstream_featured_excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<?php
		if( !isset($is_video_items_slider)){}
		?>


	
		<?php
		$terms    = array();
		$category = get_the_terms( $postId, 'category' );
		if ( is_array( $category ) ) {
			$terms = array_merge( $terms, $category );
		}
		$wpstream_category = get_the_terms( $postId, 'wpstream_category' );
		if ( is_array( $wpstream_category ) ) {
			$terms = array_merge( $terms, $wpstream_category );
		}

		if ( ! empty( $terms ) &&  !isset($is_video_items_slider)  ) {
			echo '<p class="mb-25 wpstream_featured_meta">';
			echo implode(
				'<span>&#183;</span>', //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array_map(
					function ( $term ) {
						return '<a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
					},
					$terms
				)
			);
			echo '</p>';
		}

		?>
		<div class="d-flex flex-wrap wpstream_featured_action gap-2">

			<a href="<?php echo esc_url( get_permalink( $postId ) ); ?>" class="d-flex flex-nowrap align-items-center  wpstream_video_on_demand_play_video_container align-self-center">
				<span class="flex-shrink-0 d-flex align-items-center justify-content-center me-3 wpstream_video_on_demand_play_video rounded-circle">
					<?php
					//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo wpstream_theme_get_svg_icon( 'play_icon_white.svg' );
					?>
				</span>
				<?php echo esc_html__( 'Play Video', 'hello-wpstream' ); ?>
			</a>

			<div class="d-flex align-items-center gap-2">
				<div class="wpstream-watch-later-btn align-self-center">

					<?php echo wpstream_theme_show_watch_later( $postId );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				</div>
				<div class="wp-stream-share-icon-section align-self-center">
					<div class="wp-stream-share-icon btn-hover-white">

						<?php
						echo wpstream_theme_get_svg_icon( 'share.svg' );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>

						<span><?php esc_html_e( 'Share', 'hello-wpstream' ); ?></span>

					</div>

					<div class="wpstream-social-share-main">

						<?php echo wpstream_theme_show_social_share_page( $postId );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

					</div>
				</div>
			</div>

		</div>
	</div>
</div>
