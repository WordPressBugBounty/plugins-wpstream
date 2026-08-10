<?php
use Elementor\Plugin;
/**
 * Elementor
  *
  * Bootstraps the WpStream theme's Elementor integration:
  *   - injects Elementor's active-kit container padding into <head> as CSS variables,
  *   - loads the shared helper libraries (functions/*.php) the widgets depend on,
  *   - registers every custom WpStream widget, and
  *   - adds the dedicated "Hello WpStream - Theme Widgets" panel category.
 *
 * @package wpstream-theme
 */

// Require file.
// Load the shared helper/function libraries used by the widgets registered below.
require_once plugin_dir_path( __FILE__ ) . 'wpstream-elementor-functions.php';
require_once plugin_dir_path( __FILE__ ) . 'functions/categories_functions.php';
require_once plugin_dir_path( __FILE__ ) . 'functions/blog_functions.php';
require_once plugin_dir_path( __FILE__ ) . 'functions/video_functions.php';
require_once plugin_dir_path( __FILE__ ) . 'functions/taxonomy-helpers.php';

// Mirror Elementor's active-kit container padding onto <html> as CSS custom properties,
// so the theme's own layout can align with Elementor's container gutters.
add_action( 'wp_head', function () {
    // Grab the running Elementor instance.
    $elementor = Plugin::instance();
    // Read the active kit's global settings array.
    $global_styles = $elementor->kits_manager->get_active_kit_for_frontend()->get_settings();
    // Extract the container padding config (left/right/unit); default to empty when unset.
    $container_padding = $global_styles['container_padding'] ?? [];
    
    if ( 
        !empty( $container_padding['left'] ) && 
        !empty( $container_padding['right'] ) && 
        !empty( $container_padding['unit'] ) &&
        is_numeric( $container_padding['left'] ) &&
        is_numeric( $container_padding['right'] ) &&
        in_array( $container_padding['unit'], ['px', '%', 'em', 'rem', 'vw', 'vh'] )
    ) {
        // Only when left+right+unit are all present, numeric, and a whitelisted CSS unit:
        // compose the left/right padding strings (numeric value concatenated with its unit).
        $left = floatval( $container_padding['left'] ) . esc_attr( $container_padding['unit'] );
        $right = floatval( $container_padding['right'] ) . esc_attr( $container_padding['unit'] );
        
        // Build a minimal <style> block defining the container-padding CSS variables.
        $style = sprintf(
            '<style>html{--container-default-padding-right:%s;--container-default-padding-left:%s;}</style>',
            esc_attr( $right ),
            esc_attr( $left )
        );
        
        // Emit the style, letting wp_kses allow only the <style> tag through.
        echo wp_kses( $style, [ 'style' => [] ] );
    }
} );



// Register all custom WpStream widgets when Elementor assembles its widget registry.
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
	// Each block below requires the widget's class file, then registers an instance.


	/**
	 * 
	 * Blog Post widgets
	 * 
	*/
	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-recent-blog-post.php';
	// Register the Recent Blog Post widget (grid of latest posts).
	$widgets_manager->register( new \WpStreamTheme_Recent_Blog_Post() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstream-theme-blog-post-by-id.php';
	// Register the Blog Post By Id widget (specific posts chosen by ID).
	$widgets_manager->register( new \WpStream_Theme_Blog_Post_By_Id() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-blog-post-slider.php';
	// Register the Blog Post Slider widget.
	$widgets_manager->register( new \WpStreamTheme_Blog_Post_Slider() );


	
	/**
	 * 
	 * Video Item widgets
	 * 
	*/

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-recent-items.php';
	// Register the Recent Items widget (grid of recent video items).
	$widgets_manager->register( new \WpStreamTheme_Recent_Items() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstream-theme-list-items-by-id.php';
	// Register the List Items By Id widget (specific video items by ID).
	$widgets_manager->register( new \WpStream_Theme_List_Items_By_Id() );

	require_once plugin_dir_path(__FILE__) .'widgets/class-wpstreamtheme-items-slider.php';
	// Register the Items Slider widget.
	$widgets_manager->register( new \WpStreamTheme_Items_Slider() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-items-top-bar.php';
	// Register the Items Top Bar widget (item list with a top filter bar).
	$widgets_manager->register( new \WpStreamTheme_Items_Top_Bar() );

	
	/**
	 * 
	 * Categories widgets
	 * 
	*/
	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-categories-list.php';
	// Register the Categories List widget.
	$widgets_manager->register( new \WpStreamTheme_Categories_List() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-categories-slider.php';
	// Register the Categories Slider widget.
	$widgets_manager->register( new \WpStreamTheme_Categories_Slider() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-display-categories-as-tabs.php';
	// Register the Display Categories As Tabs widget.
	$widgets_manager->register( new \WpStreamTheme_Display_Categories_As_Tabs() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-categories-grid.php';
	// Register the Categories Grid widget (mosaic layout).
	$widgets_manager->register( new \WpStreamTheme_Categories_Grid() );


	/**
	 * 
	 * Featured widgets
	 * 
	*/
	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-featured-article.php';
	// Register the Featured Article widget (single highlighted post).
	$widgets_manager->register( new \WpStreamTheme_Featured_Article() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-featured-video.php';
	// Register the Featured Video widget (single highlighted video).
	$widgets_manager->register( new \WpStreamTheme_Featured_Video() );


	/**
	 * 
	 * Other widgets
	 * 
	*/

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-simple-player.php';
	// Register the Simple Player widget (standalone video player).
	$widgets_manager->register( new \WpStreamTheme_Simple_Player() );


	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-advanced-search.php';
	// Register the Advanced Search widget (search form).
	$widgets_manager->register( new \WpStreamTheme_Advanced_Search() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstreamtheme-contact-form-builder.php';
	// Register the Contact Form Builder widget.
	$widgets_manager->register( new \WpStreamTheme_Contact_Form_Builder() );

	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstream-theme-testimonial-slider.php' ;
	// Register the Testimonial Slider widget.
	$widgets_manager->register( new \WpStreamTheme_Testimonial_Slider() );


	require_once plugin_dir_path(__FILE__) . 'widgets/class-wpstream-theme-featured--video-items-slider.php';
	// Register the Featured Video Items Slider widget.
	$widgets_manager->register( new \WpStreamTheme_Featured_Video_Items_Slider() );


}


// Add a dedicated Elementor panel category so all WpStream widgets group together.
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
	// Register the "Hello WpStream - Theme Widgets" category with its title and home icon.
	$elements_manager->add_category(
		'hello-wpstream',
		array(
			'title' => __( 'Hello WpStream - Theme Widgets', 'hello-wpstream' ),
			'icon'  => 'fa fa-home',
		)
	);
}
