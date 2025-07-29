<?php
use Elementor\Plugin;
/**
 * Elementor
 *
 * @package wpstream-theme
 */

// Require file.
require_once plugin_dir_path( __FILE__ ) . 'wpstream-elementor-functions.php';
require_once plugin_dir_path( __FILE__ ) . 'functions/categories_functions.php';
require_once plugin_dir_path( __FILE__ ) . 'functions/blog_functions.php';
require_once plugin_dir_path( __FILE__ ) . 'functions/video_functions.php';
require_once plugin_dir_path( __FILE__ ) . 'functions/taxonomy-helpers.php';

add_action( 'wp_head', function () {
    $elementor = Plugin::instance();
    $global_styles = $elementor->kits_manager->get_active_kit_for_frontend()->get_settings();
    $container_padding = $global_styles['container_padding'] ?? [];
    
    if ( 
        !empty( $container_padding['left'] ) && 
        !empty( $container_padding['right'] ) && 
        !empty( $container_padding['unit'] ) &&
        is_numeric( $container_padding['left'] ) &&
        is_numeric( $container_padding['right'] ) &&
        in_array( $container_padding['unit'], ['px', '%', 'em', 'rem', 'vw', 'vh'] )
    ) {
        $left = floatval( $container_padding['left'] ) . esc_attr( $container_padding['unit'] );
        $right = floatval( $container_padding['right'] ) . esc_attr( $container_padding['unit'] );
        
        $style = sprintf(
            '<style>html{--container-default-padding-right:%s;--container-default-padding-left:%s;}</style>',
            esc_attr( $right ),
            esc_attr( $left )
        );
        
        echo wp_kses( $style, [ 'style' => [] ] );
    }
} );



add_action( 'elementor/widgets/register', 'wpstream_theme_register_new_widgets' );

/**
 * Register custom Elementor widgets.
 *
 * This function registers various custom Elementor widgets by including their respective PHP files
 * and then registering them with the given Elementor widgets manager.
 *
 * @param \Elementor\Widget_Manager $widgets_manager The Elementor widgets manager instance.
 */
function wpstream_theme_register_new_widgets( $widgets_manager ) {


	/**
	 * 
	 * Blog Post widgets
	 * 
	*/
	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-recent-blog-post.php';
	$widgets_manager->register( new \WpStreamTheme_Recent_Blog_Post() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstream-theme-blog-post-by-id.php';
	$widgets_manager->register( new \WpStream_Theme_Blog_Post_By_Id() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-blog-post-slider.php';
	$widgets_manager->register( new \WpStreamTheme_Blog_Post_Slider() );


	
	/**
	 * 
	 * Video Item widgets
	 * 
	*/

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-recent-items.php';
	$widgets_manager->register( new \WpStreamTheme_Recent_Items() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstream-theme-list-items-by-id.php';
	$widgets_manager->register( new \WpStream_Theme_List_Items_By_Id() );

	require_once plugin_dir_path(__FILE__) .'widgets/class-wpstreamtheme-items-slider.php';
	$widgets_manager->register( new \WpStreamTheme_Items_Slider() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-items-top-bar.php';
	$widgets_manager->register( new \WpStreamTheme_Items_Top_Bar() );

	
	/**
	 * 
	 * Categories widgets
	 * 
	*/
	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-categories-list.php';
	$widgets_manager->register( new \WpStreamTheme_Categories_List() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-categories-slider.php';
	$widgets_manager->register( new \WpStreamTheme_Categories_Slider() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-display-categories-as-tabs.php';
	$widgets_manager->register( new \WpStreamTheme_Display_Categories_As_Tabs() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-categories-grid.php';
	$widgets_manager->register( new \WpStreamTheme_Categories_Grid() );


	/**
	 * 
	 * Featured widgets
	 * 
	*/
	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-featured-article.php';
	$widgets_manager->register( new \WpStreamTheme_Featured_Article() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-featured-video.php';
	$widgets_manager->register( new \WpStreamTheme_Featured_Video() );


	/**
	 * 
	 * Other widgets
	 * 
	*/

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-simple-player.php';
	$widgets_manager->register( new \WpStreamTheme_Simple_Player() );


	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-advanced-search.php';
	$widgets_manager->register( new \WpStreamTheme_Advanced_Search() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-contact-form-builder.php';
	$widgets_manager->register( new \WpStreamTheme_Contact_Form_Builder() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstream-theme-testimonial-slider.php' ;
	$widgets_manager->register( new \WpStreamTheme_Testimonial_Slider() );


	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstream-theme-featured--video-items-slider.php';
	$widgets_manager->register( new \WpStreamTheme_Featured_Video_Items_Slider() );


}


add_action( 'elementor/elements/categories_registered', 'wpstream_theme_add_elementor_widget_categories' );

/**
 * Add a custom category for WpStream Theme widgets to the Elementor widget manager.
 *
 * This function adds a custom category titled "WpStream Theme Widgets" with the specified icon
 * to the Elementor widget manager for organizing widgets related to the WpStream theme.
 *
 * @param \Elementor\Elements_Manager $elements_manager The Elementor elements manager instance.
 */
function wpstream_theme_add_elementor_widget_categories( $elements_manager ) {
	$elements_manager->add_category(
		'hello-wpstream',
		array(
			'title' => __( 'Hello WpStream - Theme Widgets', 'hello-wpstream' ),
			'icon'  => 'fa fa-home',
		)
	);
}
