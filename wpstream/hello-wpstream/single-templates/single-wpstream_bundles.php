<?php
/**
 * Single template for a "bundle" (collection) post.
 *
 * Template Post Type: post
 *
 * Renders a bundle/collection: a featured banner with the poster, a trailer-only
 * player and a "Continue Watching" link to the first episode, then the list of
 * bundled items (with optional sidebar), and finally a "Similar Collections"
 * section when enabled in the Customizer.
 *
 * @package wpstream-theme
 */

// Output the theme header.
get_header();
// Whether the free-to-view sidebar should be shown for this layout.
$wpstream_free_to_view_live_sidebar = wpstream_theme_is_show_sidebar();
// Customizer toggle: show the "Similar Collections" section at the bottom.
$show_similar_bundle_section       = get_theme_mod( 'wpstream_collection_free_to_view_show_similar_bundle_section', true );
// Locate this template so the loop below can detect its presence.
$template_name = 'single-wpstream_bundles.php';
$template_path = locate_template($template_name);
$template_path = plugin_dir_path( __FILE__ ) . $template_name;
// Fall back to false when the resolved template file does not exist.
if ( ! file_exists( $template_path ) ) {
	$template_path = false;
}
?>
    <!-- Featured banner: poster, trailer-only player and continue-watching CTA. -->
    <section class="wpstream_section wpstream_featured_banner_vod wpstream-featured-player-wrapper">
        <div class="<?php echo esc_attr(wpstream_theme_container_class()); ?>">
            <div class="row"> 
           
                <?php
                // Resolve the poster image for the current bundle post.
                $post_id=$post->ID;
                $poster_id            =   get_post_thumbnail_id($post_id);
				$poster_data          =   wp_get_attachment_image_src($poster_id,'full');
				$poster_url           =   '';
				// Use the resolved poster URL when one exists.
				if( isset( $poster_data[0] ) ){
					$poster_url = $poster_data[0];
				} 
                ?>
				
                <!-- Player wrapper for the bundle's trailer. -->
                <div class="wpstream_player_wrapper wpstream_player_shortcode">
                    <div class="wpstream_player_container">
                        <!-- Poster still, shown until the trailer plays. -->
                        <div class="wpstream_video_poster_holder wpstream_hide_on_trailer" style="background-image:url('<?php echo esc_attr($poster_url);?>'"></div>
                        <!-- Gradient overlay, hidden once playback starts. -->
                        <div class="wpstream_player_container_gradient wpstream_hide_on_play"></div>

                        <!-- Title block, hidden while the trailer is playing. -->
                        <div class="wpstream_title_wrapper_simple wpstream_hide_on_trailer">
                            <?php
                                // Author byline partial.
                                include get_template_directory() . '/template-parts/single/post-author-content-simple.php';
                            ?>
                            <h1 class="wpstream_title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>



                        <?php
                        // Read the bundle's selected item IDs (comma-separated) and
                        // work out the permalink of the first episode for the CTA.
                        $custom_field_values       = get_post_meta( $post->ID, 'wpstream_bundle_selection', true );
                        $custom_field_values_array = explode( ',', $custom_field_values );
                        $first_episode_link='';

                        // Link to the first item in the bundle, when present.
                        if(isset($custom_field_values_array[0])){
                            $first_episode_link=get_permalink($custom_field_values_array[0]);
                        }
                        ?>
                        </div>
                        <!-- "Continue Watching" button linking to the first episode. -->
                        <div class="wpstream_bundle_button_wrapper_simple">
                            <a href="<?php echo esc_html($first_episode_link); ?>" class="wpstream_collection_play_video_wrapper">
                                <div class="wpstream_collection_play_video">
                                <?php echo wpstream_theme_get_svg_icon('play2.svg');  ?>
                                </div>
                                <?php esc_html_e('Continue Watching','hello-wpstream'); ?>
                            </a>
                        </div>

                        <!-- Render the trailer-only VOD player for this bundle. -->
                        <?php $wpstream_plugin->main->wpstream_player->wpstream_video_on_demand_player_only_trailer( $post_id ); ?>

                    </div>
				</div>
            </div>
        </div>
    </section>

    <!-- Main bundle content: the list of bundled items, with optional sidebar. -->
    <section class="wpstream_section wp-stream-vod-content">
        <div  id="content" class="<?php echo esc_attr(wpstream_theme_container_class()); ?>">
            <!-- Standard WordPress loop over the queried bundle post. -->
            <?php if (have_posts()) : ?>
                <?php while (have_posts()) : ?>

                <?php the_post(); ?>

                <!-- When this template file is not resolvable, fall back to the author partial. -->
                <?php if (!$template_path) : ?>
                    <?php include get_template_directory() . '/template-parts/single/post-author-content.php'; ?>
                <?php endif; ?>

            <div class="row">
                <!-- Content column width depends on whether the sidebar is shown. -->
                <?php if ( $wpstream_free_to_view_live_sidebar ) : ?>
                <div class="col-12 col-lg-9 wp-stream-blog-content-extra-padding">

                    <?php else : ?>

                    <div class="col-12">

                        <?php endif; ?>

                        <!-- The bundle's collection of items. -->
                        <?php include get_template_directory() . '/template-parts/single/single-free-bundle-content-collection.php'; ?>

                        <!-- end container -->
                    </div>

                    <!-- Sidebar, only when the layout calls for it. -->
                    <?php if ( $wpstream_free_to_view_live_sidebar ) : ?>
                        <?php get_sidebar(); ?>
                    <?php endif; ?>
                </div>

                <?php endwhile; ?>

                <!-- Restore the global post after the custom loop. -->
                <?php wp_reset_postdata(); ?>

                <!-- No posts matched. -->
                <?php else : ?>
                    <p><?php esc_html_e('Sorry, no videos matched your criteria.', 'hello-wpstream'); ?></p>
                <?php endif; ?>
        </div>
    </section>

<?php
// Optionally render the "Similar Collections" related section below the content.
if ( $show_similar_bundle_section ){
	$wpstream_section_title = esc_html__('Similar Collections', 'hello-wpstream');
	require get_template_directory() . '/template-parts/single/section/video-related-content.php';
}

// Output the theme footer.
get_footer();
