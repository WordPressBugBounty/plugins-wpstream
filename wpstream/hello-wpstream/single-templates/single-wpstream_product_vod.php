<?php
/**
 * Template Post Type: post
 *
 * @package wpstream-theme
 */

get_header();
$wpstream_free_to_view_live_sidebar = wpstream_theme_is_show_sidebar();

$show_comments_section       = get_theme_mod( 'wpstream_video_on_demand_show_comments_section', true );
$show_more_from_broadcaster  = get_theme_mod( 'wpstream_video_on_demand_show_more_from_broadcaster', true );
$show_latest_videos          = get_theme_mod( 'wpstream_video_on_demand_show_latest_videos', true );
$show_trending_posts_section = get_theme_mod( 'wpstream_video_on_demand_show_trending_posts_section', true );


?>
	<section class="wpstream_section wpstream_featured_banner_vod wpstream-featured-player-wrapper">
		<div class="<?php echo esc_attr( wpstream_theme_container_class() ); ?> ">
				<?php wpstream_theme_display_player_wrapper( $post->ID ); ?>
		</div>
	</section>

	<section class="wpstream_section wp-stream-vod-content">
		<div class="<?php echo esc_attr( wpstream_theme_container_class() ); ?>">
			<?php
			if ( have_posts() ) :
				while ( have_posts() ) :
					the_post();
					?>

			<div class="row">

					<?php
					if ( $wpstream_free_to_view_live_sidebar ) {
						?>

				<div class="col-12 col-lg-9  wp-stream-blog-content-extra-padding">
					<?php } else { ?>
					<div class="col-12 col-sm-12 col-md-12 col-lg-12">
						<?php } ?>

						<!-- Post Additional Information Section -->
						<?php include get_template_directory() . '/template-parts/single/post-additional-content.php'; ?>

						<!-- content  -->
						<div  id="content" class="wpstream-post-content ">
							<?php the_content(); ?>
						</div>

						<?php if ( get_object_taxonomies( $post ) ) : ?>

							<!-- Categories Section -->
							<div class="wpstream-vod-terms-main">
								<?php
								echo wpstream_theme_show_item_categories( $post->ID );
								?>
							</div>

						<?php endif; ?>

						<!-- Gallery Section -->
							<?php include get_template_directory() . '/template-parts/single/gallery-content.php'; ?>


						<!-- Author Information Section -->
							<?php
							if ( $show_comments_section ) {
								include get_template_directory() . '/template-parts/single/post-comments.php';
							}
							?>

						<!-- end container -->
					</div>

						<?php if ( $wpstream_free_to_view_live_sidebar ) { ?>
							<?php get_sidebar(); ?>
						<?php } ?>
				</div>

					<?php
				endwhile;
				wp_reset_postdata();
				?>
				<?php else : ?>
					<p><?php esc_html_e( 'Sorry, no videos matched your criteria.', 'hello-wpstream' ); ?></p>
				<?php endif; ?>
			</div>
	</section>

<?php
if ( $show_more_from_broadcaster ) {
	$wpstream_section_title = esc_html__( 'More from this broadcaster', 'hello-wpstream' );
	require get_template_directory() . '/template-parts/single/section/video-broadcaster-content.php';
}

if ( $show_latest_videos ) {
	$wpstream_section_title = esc_html__( 'Latest Videos', 'hello-wpstream' );
	require get_template_directory() . '/template-parts/single/section/video-latest-content.php';
}

if ( $show_trending_posts_section ) {
	$wpstream_section_title = esc_html__( 'Trending Videos', 'hello-wpstream' );
	require get_template_directory() . '/template-parts/single/section/video-trending-content.php';
}

get_footer();
