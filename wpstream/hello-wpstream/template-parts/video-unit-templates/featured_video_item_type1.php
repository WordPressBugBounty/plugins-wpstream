<?php
/**
 * Template featured video type 1.
 *
 * Full-bleed featured-video hero: sets a background image, optionally overlays a
 * muted hover-preview <video>, then shows the badge, optional media logo, title,
 * excerpt, taxonomy links, a "Watch Now" button, and Watch Later / share actions.
 *
 * @package wpstream-theme
 *
 * @var int $postId Post ID.
 */

// Gather the post's display fields for the hero.
$title      = get_the_title( $postId );
$excerpt    = get_the_excerpt( $postId );
$excerpt 	= wp_trim_words($excerpt, 40, '...'); 
$link       = get_permalink( $postId );
$badge_text = __( 'FEATURED VIDEO', 'hello-wpstream' );
$author     = get_the_author();
$date       = get_the_date();

// Resolve the hero background image, falling back to a bundled default.
$preview = wp_get_attachment_image_src( get_post_thumbnail_id( $postId ), 'full' );
if ( empty( $preview[0] ) ) {
	// No thumbnail set: use the theme's default video image.
	$image_url = get_theme_file_uri( '/img/default-image-video.png' );
} else {
	// Use the post's featured image URL.
	$image_url = $preview[0];
}

?>

<!-- Featured video hero (type 1): full-bleed background image with overlaid content. -->
<div class="wpstream_featured_video type-1" style="background-image:url('<?php echo esc_url( $image_url ); ?>');">
	<?php 
		// Render the hover-preview trailer only when the caller opts in.
		if($preview_video=='yes'){
			// Unique DOM id so multiple heroes on a page don't collide.
			$video_id = 'wpstream_featured_video_trailer_' . wp_rand( 0, 99999999 );
			// Trailer attachment id (post meta) resolved to a playable URL.
			$video_trailer_id = get_post_meta( $postId, 'video_trailer', true );
			$video_src = wp_get_attachment_url( $video_trailer_id );
			?>
			<!-- Hover-preview video wrapper (muted, trigger-activated). -->
			<!-- Below <video> lazy-loads (preload="none") with the hero image as its poster. -->
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

	<!-- Dark overlay/cover behind the hero content for text legibility. -->
	<div class="wpstream_category_unit_item_cover"></div>
    <!-- Centered content container. -->
    <div class="container">
        <div class="container-wrapper">
            <!-- "FEATURED VIDEO" badge label. -->
            <span class="wpstream_featured_video__badge mb-2 d-inline-block"><?php echo esc_html( $badge_text ); ?></span>

            <?php
                // Optional per-post media/brand logo overlaid on the hero.
                $video_media_logo_id = intval( get_post_meta( $postId, 'media_logo', true) );
                if ( $video_media_logo_id !=0 ) {
                    // Resolve the logo attachment to a URL and render it.
                    $video_media_logo = wp_get_attachment_url( $video_media_logo_id, 'full' );
                    ?>
                    <img class="wpstream_theme_media_logo" src="<?php echo esc_url($video_media_logo); ?>" alt="media logo">
                    <?php
                }

            ?>



            <!-- Hero title linking to the post. -->
            <h1>
                <a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
            </h1>

            <?php

            ?>
                <!-- Trimmed excerpt (40 words, set at the top of the file). -->
                <p class=" wpstream_featured_excerpt"><?php echo esc_html( $excerpt ); ?></p>
            <?php
            // No-op guard: the excerpt is shown regardless of the slider-context flag.
            if( !isset($is_video_items_slider)){}
            ?>



            <?php
            // Collect category + wpstream_category terms for the meta line.
            $terms    = array();
            // Standard post categories.
            $category = get_the_terms( $postId, 'category' );
            if ( is_array( $category ) ) {
                $terms = array_merge( $terms, $category );
            }
            // WpStream-specific categories.
            $wpstream_category = get_the_terms( $postId, 'wpstream_category' );
            if ( is_array( $wpstream_category ) ) {
                $terms = array_merge( $terms, $wpstream_category );
            }

            // Print term links (middot-separated) unless rendering inside a slider.
            if ( ! empty( $terms ) &&  !isset($is_video_items_slider)  ) {
                echo '<p class="mb-25 wpstream_featured_meta">';
                echo implode(
                    '<span>&#183;</span>', //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    array_map(
                        // Map each term to an anchor linking to its term archive.
                        function ( $term ) {
                            return '<a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
                        },
                        $terms
                    )
                );
                echo '</p>';
            }

            ?>
            <!-- Action bar: Watch Now button plus Watch Later / share controls. -->
            <div class="d-flex flex-wrap wpstream_featured_action gap-2">

                <!-- Primary "Watch Now" button: circular play icon + label, links to the post. -->
                <a href="<?php echo esc_url( get_permalink( $postId ) ); ?>" class="d-flex flex-nowrap align-items-center  wpstream_video_on_demand_play_video_container align-self-center">
                    <span class="flex-shrink-0 d-flex align-items-center justify-content-center me-3 wpstream_video_on_demand_play_video rounded-circle">
                        <?php
                        // Inline play-icon SVG (helper returns pre-escaped markup).
                        //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo wpstream_theme_get_svg_icon( 'play_icon_white.svg' );
                        ?>
                    </span>
                    <?php echo esc_html__( 'Watch Now', 'hello-wpstream' ); ?>
                </a>

                <!-- Secondary actions group: watch-later + share. -->
                <div class="d-flex align-items-center gap-2">
                    <!-- Watch Later toggle button (rendered by helper). -->
                    <div class="wpstream-watch-later-btn align-self-center">

                        <?php echo wpstream_theme_show_watch_later( $postId );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                    </div>
                    <!-- Share button that reveals the social-share popover. -->
                    <div class="wp-stream-share-icon-section align-self-center">
                        <div class="wp-stream-share-icon btn-hover-white">

                            <?php
                            // Inline share-icon SVG (helper returns pre-escaped markup).
                            echo wpstream_theme_get_svg_icon( 'share.svg' );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>

                            <span><?php esc_html_e( 'Share', 'hello-wpstream' ); ?></span>

                        </div>

                        <!-- Social-share links popover (rendered by helper). -->
                        <div class="wpstream-social-share-main">

                            <?php echo wpstream_theme_show_social_share_page( $postId );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
