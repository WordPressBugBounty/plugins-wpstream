<?php
/**
 * Template featured article type 2.
 *
 * @package wpstream-theme
 *
 * @var int $postId Post ID.
 */

// Gather the display fields for this featured article from its post ID.
$title      = get_the_title( $postId );
$excerpt    = get_the_excerpt( $postId );
$link       = get_permalink( $postId );
$badge_text = esc_html__( 'FEATURED ARTICLE', 'hello-wpstream' );
$author     = get_the_author();
$date       = get_the_date();

// Resolve the featured image at the shortcode-specific size, falling back to a default.
$preview = wp_get_attachment_image_src( get_post_thumbnail_id( $postId ), 'wpstream_featured_shortcodes' );
if ( empty( $preview[0] ) ) {
	// No thumbnail set: use the theme's default video/image placeholder.
	$image_url = get_theme_file_uri( '/img/default-image-video.png' );
} else {
	// Use the resolved attachment URL.
	$image_url = $preview[0];
}
// Extra wrapper class (e.g. column sizing) built up below.
$class = '';

// Allow the caller to inject a column class name onto the wrapper.
if ( isset( $overwrite_wpstream_cols_name ) && '' !== $overwrite_wpstream_cols_name ) :
	$class .= $overwrite_wpstream_cols_name;
endif;

?>

<!-- Featured article (type 2): stacked card with image on top, meta and title below. -->
<div class="wpstream_featured_article type-2 <?php echo esc_attr( $class ); ?>"> 
	<!-- Image block using the featured image as background. -->
	<div class="wpstream_featured_article__image" style="background-image:url('<?php echo esc_url( $image_url ); ?>');">
		<!-- Dark overlay cover for contrast. -->
		<div class="wpstream_category_unit_item_cover"></div>
		<!-- "Featured article" badge. -->
		<span class="wpstream_featured_article__badge mb-2 d-inline-block"><?php echo esc_html( $badge_text ); ?></span>
	</div>
	
	<!-- Author and publish date meta line. -->
	<p class=" wpstream_featured_meta">
		<?php echo esc_html( $author ) . '&nbsp;&nbsp;<span>&#183;</span>&nbsp;&nbsp;' . esc_html( $date ); ?>
	</p>
	
	<!-- Article title linking to the full post. -->
	<h2>
		<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
	</h2>
	
</div>
