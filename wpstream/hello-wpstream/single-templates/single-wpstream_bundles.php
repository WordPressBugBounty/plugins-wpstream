<?php
/**
 * Template Post Type: post
 *
 * @package wpstream-theme
 */

get_header();
$wpstream_free_to_view_live_sidebar = wpstream_theme_is_show_sidebar();
$show_similar_bundle_section       = get_theme_mod( 'wpstream_collection_free_to_view_show_similar_bundle_section', true );
$template_name = 'single-wpstream_bundles.php';
$template_path = locate_template($template_name);
?>
    <section class="wpstream_section wpstream_featured_banner_vod wpstream-featured-player-wrapper">
        <div class="<?php echo esc_attr(wpstream_theme_container_class()); ?>">
            <div class="row"> 
           
                <?php
                $post_id=$post->ID;
                $poster_id            =   get_post_thumbnail_id($post_id);
				$poster_data          =   wp_get_attachment_image_src($poster_id,'full');
				$poster_url           =   '';
				if(isset($poster_data[0])){
					$poster_url=$poster_data[0];
				} 
                ?>
				
                <div class="wpstream_player_wrapper wpstream_player_shortcode">
				<div class="wpstream_player_container">

				<div class="wpstream_video_poster_holder wpstream_hide_on_trailer" style="background-image:url('<?php echo esc_attr($poster_url);?>'"></div>
				<div class="wpstream_player_container_gradient wpstream_hide_on_play"></div>
                
                <div class="wpstream_title_wrapper_simple wpstream_hide_on_trailer">
				    <?php
					    include get_template_directory() . '/template-parts/single/post-author-content-simple.php';
					?>
					<h1 class="wpstream_title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
				
		

                <?php 
                $custom_field_values       = get_post_meta( $post->ID, 'wpstream_bundle_selection', true );
                $custom_field_values_array = explode( ',', $custom_field_values );
                $first_episode_link='';

                if(isset($custom_field_values_array[0])){
                    $first_episode_link=get_permalink($custom_field_values_array[0]);
                }
                ?>
                </div>
                <div class="wpstream_bundle_button_wrapper_simple">
                    <a href="<?php echo esc_html($first_episode_link); ?>" class="wpstream_collection_play_video_wrapper">
                        <div class="wpstream_collection_play_video">
                        <?php echo wpstream_theme_get_svg_icon('play2.svg');  ?>
                        </div>
                        <?php esc_html_e('Continue Watching','hello-wpstream'); ?>
                    </a>
                </div>

	 
                <?php
                
                
                $wpstream_plugin->main->wpstream_player->wpstream_video_on_demand_player_only_trailer( $post_id );
                ?>


				</div>
				</div>
                

            </div>
        </div>
    </section>

    <section class="wpstream_section wp-stream-vod-content">
        <div  id="content" class="<?php echo esc_attr(wpstream_theme_container_class()); ?>">
            <?php if (have_posts()) : ?>
                <?php while (have_posts()) : ?>

                <?php the_post(); ?>

                <?php if (!$template_path) : ?>
                    <?php include get_template_directory() . '/template-parts/single/post-author-content.php'; ?>
                <?php endif; ?>

            <div class="row">
                <?php if ( $wpstream_free_to_view_live_sidebar ) : ?>
                <div class="col-12 col-lg-9 wp-stream-blog-content-extra-padding">

                    <?php else : ?>

                    <div class="col-12">

                        <?php endif; ?>

                        <?php include get_template_directory() . '/template-parts/single/single-free-bundle-content-collection.php'; ?>

                        <!-- end container -->
                    </div>

                    <?php if ( $wpstream_free_to_view_live_sidebar ) : ?>
                        <?php get_sidebar(); ?>
                    <?php endif; ?>
                </div>

                <?php endwhile; ?>

                <?php wp_reset_postdata(); ?>

                <?php else : ?>
                    <p><?php esc_html_e('Sorry, no videos matched your criteria.', 'hello-wpstream'); ?></p>
                <?php endif; ?>
        </div>
    </section>

<?php
if ( $show_similar_bundle_section ){
	$wpstream_section_title = esc_html__('Similar Collections', 'hello-wpstream');
	require get_template_directory() . '/template-parts/single/section/video-related-content.php';
}

get_footer();
