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

<div class="wpstream_featured_video type-2">
	<div class="wpstream_featured_video__image" style="background-image:url('<?php echo esc_url( $image_url ); ?>');">
		
		<?php 
		if($preview_video=='yes'){
			$video_id = 'wpstream_featured_video_trailer_' . wp_rand( 0, 99999999 );
			$video_trailer_id = get_post_meta( $postId, 'video_trailer', true );
			$video_src = wp_get_attachment_url( $video_trailer_id );
			?>
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

		<div class="wpstream_category_unit_item_cover"></div>
		<span class="wpstream_featured_video__badge mb-2 d-inline-block"><?php echo esc_html( $badge_text ); ?></span>
		<a href="<?php echo esc_url( get_permalink( $postId ) ); ?>" class="d-flex flex-nowrap align-items-center wpstream_featured_video__link align-self-center">
				<span class="flex-shrink-0 d-flex align-items-center justify-content-center me-2 rounded-circle">
					<?php
					//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo wpstream_theme_get_svg_icon( 'play_icon_white.svg' );
					?>
				</span>
			<?php echo esc_html__( 'Play Video', 'hello-wpstream' ); ?>
		</a>
	</div>
	
	<p class="wpstream_featured_meta ">
		<?php echo esc_html( $author ) . '<span>&#183;</span>' . esc_html( $date ); ?>
	</p>
	
	<h2 class="mb-0">
		<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
	</h2>

	

</div>
