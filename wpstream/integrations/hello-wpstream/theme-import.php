<?php
/**
 * One Click Demo Import (OCDI) configuration.
 *
 * This file declares the demo packages the hello-wpstream theme can import via
 * OCDI (content/widgets/customizer URLs), tweaks the OCDI intro text, and runs
 * post-import setup (menu locations, front/blog pages, WooCommerce coming-soon
 * off).
 *
 * @package    Wpstream
 * @subpackage Wpstream/integrations
 */

// Block direct access outside of WordPress.
if (!defined('ABSPATH')) exit;


    /**
     * Provide the OCDI plugin intro text shown above the demo import screen.
     *
     * @param string $default_text Incoming default text (replaced).
     * @return string The WpStream intro notice HTML.
     */
    function wpstream_ocdi_plugin_intro_text( $default_text ) {
        // Note that demo images are intentionally excluded to speed up importing.
        $default_text = '<div class="ocdi__intro-text intro-text_wpstream_theme notice notice-warning "> For speed purposes, demo images are not included in the import.</div>';

        return $default_text;
    }


    /**
     * Define the demo packages OCDI offers for the hello-wpstream theme.
     *
     * @return array List of OCDI import descriptors (content/widgets/customizer/
     *               preview URLs, notice and preview link), or void when the
     *               theme helper is unavailable.
     */
    function wpstream_ocdi_import_files() {

        // Only expose demos when the theme's loader helper is present.
        if(!function_exists( 'wpstream_load_theme_files' )){
            return;
        }
        // Each entry describes one downloadable demo (content, widgets, customizer, preview).
        $demo_array= array(
			'main-demo' =>  array(
				'import_file_name'            =>  'Main Demo',
				'import_file_url'             =>  'https://wpstream.net/downloads/demos/main/demo-content.xml',
				'import_widget_file_url'      =>  'https://wpstream.net/downloads/demos/main/widgets.wie',
				'import_customizer_file_url'  =>  'https://wpstream.net/downloads/demos/main/customizer.dat',
				'import_preview_image_url'    =>  'https://wpstream.net/downloads/demos/main/preview.png'  ,
				'import_notice'               =>  esc_html__( 'Clear theme cache after demo import is complete!', 'wpstream' ),
				'preview_url'                 =>  'https://theme.wpstream.net/',

			),
			'esports-demo' =>  array(
				'import_file_name'            =>  'ESports Demo',
				'import_file_url'             =>  'https://wpstream.net/downloads/demos/esports/esports-demo.xml',
				'import_widget_file_url'      =>  'https://wpstream.net/downloads/demos/esports/widgets.wie',
				'import_customizer_file_url'  =>  'https://wpstream.net/downloads/demos/esports/customizer.dat',
				'import_preview_image_url'    =>  'https://wpstream.net/downloads/demos/esports/preview.png' ,
				'import_notice'               =>  esc_html__( 'Clear theme cache after demo import is complete!', 'wpstream' ),
				'preview_url'                 =>  'https://esports.wpstream.net/',
			),
			'church-demo' =>  array(
				'import_file_name'            =>  'Church Demo',
				'import_file_url'             =>  'https://wpstream.net/downloads/demos/church/church-demo.xml',
				'import_widget_file_url'      =>  'https://wpstream.net/downloads/demos/church/widgets.wie',
				'import_customizer_file_url'  =>  'https://wpstream.net/downloads/demos/church/customizer.dat',
				'import_preview_image_url'    =>  'https://wpstream.net/downloads/demos/church/preview.png' ,
				'import_notice'               =>  esc_html__( 'Clear theme cache after demo import is complete!', 'wpstream' ),
				'preview_url'                 =>  'https://church.wpstream.net/',

			),
			'live-shopping-demo' =>  array(
				'import_file_name'            =>  'Live Shopping Demo',
				'import_file_url'             =>  'https://wpstream.net/downloads/demos/live-shoping/live_shoping_content.xml',
				'import_widget_file_url'      =>  'https://wpstream.net/downloads/demos/live-shoping/live_shoping_widgets.wie',
				'import_customizer_file_url'  =>  'https://wpstream.net/downloads/demos/live-shoping/live_shoping_customizer.dat',
				'import_preview_image_url'    =>  'https://wpstream.net/downloads/demos/live-shoping/preview.png' ,
				'import_notice'               =>  esc_html__( 'Clear theme cache after demo import is complete!', 'wpstream' ),
				'preview_url'                 =>  'https://liveshopping.wpstream.net/',
			),
			'believe-demo' => array(
				'import_file_name'            =>  'Believe Demo',
				'import_file_url'             =>  'https://wpstream.net/downloads/demos/believe/believe-demo.xml',
				'import_widget_file_url'      =>  'https://wpstream.net/downloads/demos/believe/widgets.wie',
				'import_customizer_file_url'  =>  'https://wpstream.net/downloads/demos/believe/customizer.dat',
				'import_preview_image_url'    =>  'https://wpstream.net/downloads/demos/believe/preview.png' ,
				'import_notice'               =>  esc_html__( 'Clear theme cache after demo import is complete!', 'wpstream' ),
				'preview_url'                 =>  'https://believe.wpstream.net/',
			)
		);

	// Hand the demo catalog to OCDI.
	return $demo_array;

	}





    /**
     * Post-import setup run by OCDI after a demo finishes importing.
     *
     * Assigns the imported menus to theme menu locations, sets the static front
     * page and blog page, and disables WooCommerce coming-soon mode.
     */
    function wpstream_ocdi_after_import_setup() {
        // Assign menus to their locations.
        // Look up the imported menus by name.
        $main_menu = get_term_by( 'name', 'Main Menu', 'nav_menu' );
        $main_menu2 = get_term_by( 'name', 'First Menu', 'nav_menu' );
        $main_menu3 = get_term_by( 'name', 'Third menu', 'nav_menu' );

        // Map each imported menu to a theme menu location.
        set_theme_mod( 'nav_menu_locations', array(
            'main-menu'  => $main_menu->term_id,
            'main-menu2' => $main_menu2->term_id,
            'main-menu3' => $main_menu3->term_id
            )
        );


        // Assign front page and posts page (blog page).
        // Resolve the imported Homepage and Blog pages by title.
        $front_page_id = get_page_by_title( 'Homepage' );
        $blog_page_id  = get_page_by_title( 'Blog' );

        // Configure a static front page with a separate posts page.
        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $front_page_id->ID );
        update_option( 'page_for_posts', $blog_page_id->ID );

		// disable coming soon mode of WooCommerce
	    // so users can see the paid channels/VODs after demo import
	    update_option('woocommerce_coming_soon', 'no');
    }

?>