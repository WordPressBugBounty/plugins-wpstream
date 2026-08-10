<?php
/**
 * Single template for a free-to-view live-stream product post.
 *
 * Template Post Type: post
 *
 * Renders the player banner for the stream, then the post body (additional info,
 * content, categories, gallery and optional comments) with an optional sidebar,
 * and finally the Past Broadcasts / Similar Streams / Trending Streams sections,
 * each gated behind its own Customizer toggle.
 *
 * @package wpstream-theme
 */

// Output the theme header.
get_header();
// Current post being displayed.
global $post;
// Whether the free-to-view sidebar should be shown for this layout.
$wpstream_free_to_view_live_sidebar = wpstream_theme_is_show_sidebar();

// Customizer toggles controlling the optional sections on this template.
$show_comments_section = get_theme_mod( 'wpstream_free_to_view_live_show_comments_section', true );
$show_similar_streams  = get_theme_mod( 'wpstream_free_to_view_live_show_similar_streams', true );
$show_past_broadcast   = get_theme_mod( 'wpstream_free_to_view_live_show_past_broadcast', true );
$show_trending_streams = get_theme_mod( 'wpstream_free_to_view_live_show_trending_streams', true );
?>

	<!-- Player section -->
	<!-- Banner holding the live-stream player for this post. -->
	<section class="wpstream_section wpstream_featured_banner_vod wpstream-featured-player-wrapper">
		<div class="<?php echo esc_attr( wpstream_theme_container_class() ); ?>">
			<?php wpstream_theme_display_player_wrapper( $post->ID ); ?>
		</div>
	</section>

	<!-- Main content section for the stream post body. -->
	<section class="wpstream_section wp-stream-vod-content">
		<div class="<?php echo esc_attr( wpstream_theme_container_class() ); ?>">
			<?php
			// Standard loop over the queried post.
			if ( have_posts() ) :
				while ( have_posts() ) :
					the_post();
					?>

		
			<div class="row">

					<!-- Content column width depends on whether the sidebar is shown. -->
					<?php if ( $wpstream_free_to_view_live_sidebar ) : ?>

				<div class="col-12 col-lg-9 wp-stream-blog-content-extra-padding">

					<?php else : ?>

					<div class="col-12">

						<?php endif; ?>

						<!-- Post Additional Information Section -->
						<?php include get_template_directory() . '/template-parts/single/post-additional-content.php'; ?>

						<!-- content  -->
						<!-- The post's main body content. -->
						<div id="content" class="wpstream-post-content ">
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

						<?php
						// Sidebar, only when the layout calls for it.
						if ( $wpstream_free_to_view_live_sidebar ) {
							get_sidebar();
						}
						?>
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

// Past broadcasts related section, gated by its Customizer toggle.
if ( $show_past_broadcast ) {
	$wpstream_section_title = esc_html__( 'Past Broadcasts', 'hello-wpstream' );
	require get_template_directory() . '/template-parts/single/section/video-past-broadcast.php';
}


// Similar streams related section, gated by its Customizer toggle.
if ( $show_similar_streams ) {
	$wpstream_section_title = esc_html__( 'Similar Streams', 'hello-wpstream' );
	require get_template_directory() . '/template-parts/single/section/video-related-content.php';
}

// Trending streams related section, gated by its Customizer toggle.
if ( $show_trending_streams ) {
	$wpstream_section_title = esc_html__( 'Trending Streams', 'hello-wpstream' );
	require get_template_directory() . '/template-parts/single/section/video-trending-content.php';
}


// Output the theme footer.
get_footer();
