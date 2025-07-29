<?php
/**
 * Template featured article type 2.
 *
 * @package wpstream-theme
 *
 * @var int $postId Post ID.
 */

$title      = get_the_title( $postId );
$excerpt    = get_the_excerpt( $postId );
$link       = get_permalink( $postId );
$badge_text = esc_html__( 'FEATURED ARTICLE', 'hello-wpstream' );
$author     = get_the_author();
$date       = get_the_date();

$preview = wp_get_attachment_image_src( get_post_thumbnail_id( $postId ), 'wpstream_featured_shortcodes' );
if ( empty( $preview[0] ) ) {
	$image_url = get_theme_file_uri( '/img/default-image-video.png' );
} else {
	$image_url = $preview[0];
}
$class = '';

if ( isset( $overwrite_wpstream_cols_name ) && '' !== $overwrite_wpstream_cols_name ) :
	$class .= $overwrite_wpstream_cols_name;
endif;

?>

<div class="wpstream_featured_article type-2 <?php echo esc_attr( $class ); ?>"> 
	<div class="wpstream_featured_article__image" style="background-image:url('<?php echo esc_url( $image_url ); ?>');">
		<div class="wpstream_category_unit_item_cover"></div>
		<span class="wpstream_featured_article__badge mb-2 d-inline-block"><?php echo esc_html( $badge_text ); ?></span>
	</div>
	
	<p class=" wpstream_featured_meta">
		<?php echo esc_html( $author ) . '&nbsp;&nbsp;<span>&#183;</span>&nbsp;&nbsp;' . esc_html( $date ); ?>
	</p>
	
	<h2>
		<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
	</h2>
	
</div>
