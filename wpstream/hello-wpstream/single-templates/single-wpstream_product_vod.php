<?php
/**
 * Single template for a video-on-demand (VOD) product post.
 *
 * Template Post Type: post
 *
 * Renders the VOD player banner, then the post body (additional info, content,
 * categories, gallery and optional comments) with an optional sidebar, and
 * finally the "More from this broadcaster", "Latest Videos" and "Trending
 * Videos" sections, each gated behind its own Customizer toggle.
 *
 * @package wpstream-theme
 */

// Output the theme header.
get_header();
// Whether the free-to-view sidebar should be shown for this layout.
$wpstream_free_to_view_live_sidebar = wpstream_theme_is_show_sidebar();

// Customizer toggles controlling the optional sections on this template.
$show_comments_section       = get_theme_mod( 'wpstream_video_on_demand_show_comments_section', true );
$show_more_from_broadcaster  = get_theme_mod( 'wpstream_video_on_demand_show_more_from_broadcaster', true );
$show_latest_videos          = get_theme_mod( 'wpstream_video_on_demand_show_latest_videos', true );
$show_trending_posts_section = get_theme_mod( 'wpstream_video_on_demand_show_trending_posts_section', true );


?>
	<!-- Banner holding the VOD player for this post. -->
	<section class="wpstream_section wpstream_featured_banner_vod wpstream-featured-player-wrapper">
		<div class="<?php echo esc_attr( wpstream_theme_container_class() ); ?> ">
				<?php wpstream_theme_display_player_wrapper( $post->ID ); ?>
		</div>
	</section>

	<!-- Main content section for the VOD post body. -->
	<section class="wpstream_section wp-stream-vod-content">
		<div class="<?php echo esc_attr( wpstream_theme_container_class() ); ?>">
			<?php
			// Standard loop over the queried post.
			if ( have_posts() ) :
				while ( have_posts() ) :
					the_post();
					?>

			<div class="row">

					<?php
					// Content column width depends on whether the sidebar is shown.
					if ( $wpstream_free_to_view_live_sidebar ) {
						?>

				<div class="col-12 col-lg-9  wp-stream-blog-content-extra-padding">
					<?php } else { ?>
					<div class="col-12 col-sm-12 col-md-12 col-lg-12">
						<?php } ?>

						<!-- Post Additional Information Section -->
						<?php include get_template_directory() . '/template-parts/single/post-additional-content.php'; ?>

						<!-- content  -->
						<!-- The post's main body content. -->
						<div  id="content" class="wpstream-post-content ">
							<?php the_content(); ?>
						</div>

						<!-- Categories block, only when the post has taxonomy terms. -->
						<?php if ( get_object_taxonomies( $post ) ) : ?>

							<!-- Categories Section -->
							<div class="wpstream-vod-terms-main">
								<?php
								// Render the item's category/tag chips.
								echo wpstream_theme_show_item_categories( $post->ID );
								?>
							</div>

						<?php endif; ?>

						<!-- Gallery Section -->
							<?php include get_template_directory() . '/template-parts/single/gallery-content.php'; ?>


						<!-- Author Information Section -->
							<?php
							// Comments partial, gated by the Customizer toggle.
							if ( $show_comments_section ) {
								include get_template_directory() . '/template-parts/single/post-comments.php';
							}
							?>

						<!-- end container -->
					</div>

						<!-- Sidebar, only when the layout calls for it. -->
						<?php if ( $wpstream_free_to_view_live_sidebar ) { ?>
							<?php get_sidebar(); ?>
						<?php } ?>
				</div>

					<?php
				// End of the loop; restore the global post.
				endwhile;
				wp_reset_postdata();
				?>
				<!-- No posts matched. -->
				<?php else : ?>
					<p><?php esc_html_e( 'Sorry, no videos matched your criteria.', 'hello-wpstream' ); ?></p>
				<?php endif; ?>
			</div>
	</section>

<?php
// "More from this broadcaster" section, gated by its Customizer toggle.
if ( $show_more_from_broadcaster ) {
	$wpstream_section_title = esc_html__( 'More from this broadcaster', 'hello-wpstream' );
	require get_template_directory() . '/template-parts/single/section/video-broadcaster-content.php';
}

// "Latest Videos" section, gated by its Customizer toggle.
if ( $show_latest_videos ) {
	$wpstream_section_title = esc_html__( 'Latest Videos', 'hello-wpstream' );
	require get_template_directory() . '/template-parts/single/section/video-latest-content.php';
}

// "Trending Videos" section, gated by its Customizer toggle.
if ( $show_trending_posts_section ) {
	$wpstream_section_title = esc_html__( 'Trending Videos', 'hello-wpstream' );
	require get_template_directory() . '/template-parts/single/section/video-trending-content.php';
}

// Output the theme footer.
get_footer();
