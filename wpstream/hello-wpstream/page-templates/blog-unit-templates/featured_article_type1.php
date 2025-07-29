<?php
/**
 * Template featured article type 1.
 *
 * @package wpstream-theme
 *
 * @var int $postId Post ID.
 */

$title      = get_the_title( $postId );
$excerpt    = get_the_excerpt( $postId );
$excerpt 	= wp_trim_words($excerpt, 40, '...'); 
$link       = get_permalink( $postId );
$badge_text = esc_html__( 'FEATURED ARTICLE', 'hello-wpstream' );
$author     = get_the_author();
$date       = get_the_date();

$preview = wp_get_attachment_image_src( get_post_thumbnail_id( $postId ), 'full' );
if ( empty( $preview[0] ) ) {
	$image_url = get_theme_file_uri( '/img/default-image-video.png' );
} else {
	$image_url = $preview[0];
}

?>

<div class="wpstream_featured_article type-1" style="background-image:url('<?php echo esc_url( $image_url ); ?>');">
	<div class="wpstream_category_unit_item_cover"></div>
	<div class="container">
		<span class="wpstream_featured_article__badge mb-2 d-inline-block"><?php echo esc_html( $badge_text ); ?></span>
		<h1><a href="<?php echo esc_url($link); ?>"><?php echo esc_html( $title ); ?></a></h1>
		<p class="mb-25 wpstream_featured_excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<p class="mb-25 wpstream_featured_meta "><?php echo esc_html( $author ) . '&nbsp;&nbsp;<span>&#183;</span>&nbsp;&nbsp;' . esc_html( $date ); ?></p>
		<div class="d-flex flex-wrap justify-content-between">
			<a href="<?php echo esc_url( get_permalink( $postId ) ); ?>" class="d-flex flex-nowrap align-items-center wpstream_featured_article__link align-self-center">
				<span class="flex-shrink-0 d-flex align-items-center justify-content-center me-3 rounded-circle">
					<?php
					//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo wpstream_theme_get_svg_icon( 'arrow-right.svg' );
					?>
				</span>
				<?php echo esc_html__( 'Read More', 'hello-wpstream' ); ?>
			</a>
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
