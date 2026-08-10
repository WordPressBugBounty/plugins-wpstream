<?php
/**
 * Template featured article type 2.
 *
 * Compact card-style featured-article unit: a fixed-ratio image block carrying
 * the "FEATURED ARTICLE" badge, followed by author/date meta and the linked
 * title below it. Optionally accepts a column-class override to fit different
 * grid widths. Expects the post to render as $postId.
 *
 * @package wpstream-theme
 *
 * @var int $postId Post ID.
 */

// Collect the display fields for this featured post.
$title      = get_the_title( $postId );
$excerpt    = get_the_excerpt( $postId );
$link       = get_permalink( $postId );
$badge_text = esc_html__( 'FEATURED ARTICLE', 'hello-wpstream' );
$author     = get_the_author();
$date       = get_the_date();

// Resolve the card image (featured-shortcode size), falling back to a default.
$preview = wp_get_attachment_image_src( get_post_thumbnail_id( $postId ), 'wpstream_featured_shortcodes' );
if ( empty( $preview[0] ) ) {
	$image_url = get_theme_file_uri( '/img/default-image-video.png' );
} else {
	$image_url = $preview[0];
}
// Optional extra column class supplied by the calling context.
$class = '';

// Append the caller-provided column-class override when one is set.
if ( isset( $overwrite_wpstream_cols_name ) && '' !== $overwrite_wpstream_cols_name ) :
	$class .= $overwrite_wpstream_cols_name;
endif;

?>

<!-- Card wrapper; optional override class widens/narrows it in the grid. -->
<div class="wpstream_featured_article type-2 <?php echo esc_attr( $class ); ?>"> 
	<!-- Image block with the featured image as its background. -->
	<div class="wpstream_featured_article__image" style="background-image:url('<?php echo esc_url( $image_url ); ?>');">
		<!-- Overlay tint on the image. -->
		<div class="wpstream_category_unit_item_cover"></div>
		<!-- "FEATURED ARTICLE" badge over the image. -->
		<span class="wpstream_featured_article__badge mb-2 d-inline-block"><?php echo esc_html( $badge_text ); ?></span>
	</div>
	
	<!-- Author and date meta, separated by a middot. -->
	<p class=" wpstream_featured_meta">
		<?php echo esc_html( $author ) . '&nbsp;&nbsp;<span>&#183;</span>&nbsp;&nbsp;' . esc_html( $date ); ?>
	</p>
	
	<!-- Post title, linked to the article. -->
	<h2>
		<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
	</h2>
	
</div>
