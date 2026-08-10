<?php
/**
 * Template featured article type 1.
 *
 * @package wpstream-theme
 *
 * @var int $postId Post ID.
 */

// Gather the display fields for this featured article from its post ID.
$title      = get_the_title( $postId );          // Post title.
$excerpt    = get_the_excerpt( $postId );        // Raw post excerpt.
$excerpt 	= wp_trim_words($excerpt, 40, '...'); // Truncate the excerpt to 40 words.
$link       = get_permalink( $postId );          // Permalink to the full article.
$badge_text = esc_html__( 'FEATURED ARTICLE', 'hello-wpstream' ); // Translated badge label.
$author     = get_the_author();                  // Author display name.
$date       = get_the_date();                    // Formatted publish date.

// Resolve the full-size featured image, falling back to a bundled default.
$preview = wp_get_attachment_image_src( get_post_thumbnail_id( $postId ), 'full' );
if ( empty( $preview[0] ) ) {
	// No thumbnail set: use the theme's default video/image placeholder.
	$image_url = get_theme_file_uri( '/img/default-image-video.png' );
} else {
	// Use the resolved attachment URL.
	$image_url = $preview[0];
}

?>

<!-- Featured article (type 1): full-bleed hero with the featured image as background. -->
<div class="wpstream_featured_article type-1" style="background-image:url('<?php echo esc_url( $image_url ); ?>');">
	<!-- Dark overlay cover for contrast over the background image. -->
	<div class="wpstream_category_unit_item_cover"></div>
	<div class="container">
		<!-- "Featured article" badge. -->
		<span class="wpstream_featured_article__badge mb-2 d-inline-block"><?php echo esc_html( $badge_text ); ?></span>
		<!-- Article title linking to the full post. -->
		<h1><a href="<?php echo esc_url($link); ?>"><?php echo esc_html( $title ); ?></a></h1>
		<!-- Trimmed excerpt. -->
		<p class="mb-25 wpstream_featured_excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<!-- Author and publish date meta line. -->
		<p class="mb-25 wpstream_featured_meta "><?php echo esc_html( $author ) . '&nbsp;&nbsp;<span>&#183;</span>&nbsp;&nbsp;' . esc_html( $date ); ?></p>
		<!-- Footer row: "Read more" link on the left, share controls on the right. -->
		<div class="d-flex flex-wrap justify-content-between">
			<!-- "Read more" call to action linking to the article. -->
			<a href="<?php echo esc_url( get_permalink( $postId ) ); ?>" class="d-flex flex-nowrap align-items-center wpstream_featured_article__link align-self-center">
				<!-- Circular arrow icon. -->
				<span class="flex-shrink-0 d-flex align-items-center justify-content-center me-3 rounded-circle">
					<?php
					//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					// Inline the arrow-right SVG icon markup.
					echo wpstream_theme_get_svg_icon( 'arrow-right.svg' );
					?>
				</span>
				<?php echo esc_html__( 'Read More', 'hello-wpstream' ); ?>
			</a>
			<!-- Social share section. -->
			<div class="wp-stream-share-icon-section align-self-center">
				<!-- Share toggle button with icon and label. -->
				<div class="wp-stream-share-icon btn-hover-white">

					<?php
					// Inline the share SVG icon markup.
					echo wpstream_theme_get_svg_icon( 'share.svg' );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>

					<span><?php esc_html_e( 'Share', 'hello-wpstream' ); ?></span>

				</div>

				<!-- Expandable social share links for this post. -->
				<div class="wpstream-social-share-main">

					<?php echo wpstream_theme_show_social_share_page( $postId );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				</div>
			</div>
		</div>
	</div>
</div>
