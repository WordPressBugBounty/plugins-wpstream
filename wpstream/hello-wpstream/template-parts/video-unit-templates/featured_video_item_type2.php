<?php
/**
 * Template featured video type 2.
 *
 * Featured-video card variant: a framed image block (optionally overlaying a
 * muted hover-preview <video>) with a badge and "Play Video" link, followed by
 * an author/date meta line and the linked title beneath the image.
 *
 * @package wpstream-theme
 *
 * @var int $postId Post ID.
 */

// Gather the post's display fields for the featured card.
$title      = get_the_title( $postId );
$excerpt    = get_the_excerpt( $postId );
$link       = get_permalink( $postId );
$badge_text = __( 'FEATURED VIDEO', 'hello-wpstream' );
$author     = get_the_author();
$date       = get_the_date();

// Resolve the card image, falling back to a bundled default.
$preview = wp_get_attachment_image_src( get_post_thumbnail_id( $postId ), 'full' );
if ( empty( $preview[0] ) ) {
	// No thumbnail set: use the theme's default video image.
	$image_url = get_theme_file_uri( '/img/default-image-video.png' );
} else {
	// Use the post's featured image URL.
	$image_url = $preview[0];
}

?>

<!-- Featured video card (type 2): framed image block above meta and title. -->
<div class="wpstream_featured_video type-2">
	<!-- Image block: background image, optional hover-preview video, badge, play link. -->
	<div class="wpstream_featured_video__image" style="background-image:url('<?php echo esc_url( $image_url ); ?>');">
		
		<?php 
		// Render the hover-preview trailer only when the caller opts in.
		if($preview_video=='yes'){
			// Unique DOM id so multiple cards on a page don't collide.
			$video_id = 'wpstream_featured_video_trailer_' . wp_rand( 0, 99999999 );
			// Trailer attachment id (post meta) resolved to a playable URL.
			$video_trailer_id = get_post_meta( $postId, 'video_trailer', true );
			$video_src = wp_get_attachment_url( $video_trailer_id );
			?>
			<!-- Hover-preview video wrapper (muted, trigger-activated, lazy-loaded). -->
			<div class="wpstream_video_unit_video_wrapper wpstream_video_unit_video_wrapper_trigger"  
					data-video-id="<?php echo esc_attr( $video_id ) ;?>"  >

                <video id="<?php echo esc_attr( $video_id );?>" class="wpstream_video_unit_video" preload="none" poster="<?php echo esc_url( $image_url );?>" muted>

                    <source src="<?php echo esc_url( $video_src );?>" type="video/mp4">

                    Your browser does not support the video tag.

                </video>

            </div>

		<?php		
		}
	
		?>

		<!-- Dark overlay/cover for text legibility. -->
		<div class="wpstream_category_unit_item_cover"></div>
		<!-- "FEATURED VIDEO" badge label. -->
		<span class="wpstream_featured_video__badge mb-2 d-inline-block"><?php echo esc_html( $badge_text ); ?></span>
		<!-- "Play Video" link (circular play icon + label) pointing at the post. -->
		<a href="<?php echo esc_url( get_permalink( $postId ) ); ?>" class="d-flex flex-nowrap align-items-center wpstream_featured_video__link align-self-center">
				<span class="flex-shrink-0 d-flex align-items-center justify-content-center me-2 rounded-circle">
					<?php
					// Inline play-icon SVG (helper returns pre-escaped markup).
					//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo wpstream_theme_get_svg_icon( 'play_icon_white.svg' );
					?>
				</span>
			<?php echo esc_html__( 'Play Video', 'hello-wpstream' ); ?>
		</a>
	</div>
	
	<!-- Meta line: author name, separator, and publish date. -->
	<p class="wpstream_featured_meta ">
		<?php echo esc_html( $author ) . '<span>&#183;</span>' . esc_html( $date ); ?>
	</p>
	
	<!-- Card title linking to the post. -->
	<h2 class="mb-0">
		<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
	</h2>

	

</div>
