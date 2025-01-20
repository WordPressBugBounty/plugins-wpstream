<?php

if (!defined('ABSPATH')) exit;


    function ocdi_plugin_intro_text( $default_text ) {
        $default_text = '<div class="ocdi__intro-text intro-text_wpstream_theme notice notice-warning "> For speed purposes, demo images are not included in the import.</div>';

        return $default_text;
    }


    function ocdi_import_files() {

        if(!function_exists( 'wpstream_load_theme_files' )){
            return;
        }
        $demo_array= array(
                'main-demo' =>  array(
                    'import_file_name'              =>  'Main Demo',
                    'import_file_url'             =>  'https://wpstream.net/downloads/demos/main/demo-content.xml',
                    'import_widget_file_url'      =>  'https://wpstream.net/downloads/demos/main/widgets.wie',
                    'import_customizer_file_url'  =>  'https://wpstream.net/downloads/demos/main/customizer.dat',
                    'import_preview_image_url'      =>  'https://wpstream.net/downloads/demos/main/preview.png'  ,
                    'import_notice'                 =>  esc_html__( 'Clear theme cache after demo import is complete!', 'hello-wpstream' ),
                    'preview_url'                   =>  'https://theme.wpstream.net/',
                    
                ),
                'esports-demo' =>  array(
                                'import_file_name'              =>  'ESports Demo',
                                'import_file_url'             =>  'https://wpstream.net/downloads/demos/esports/esports-demo.xml',
                                'import_widget_file_url'      =>  'https://wpstream.net/downloads/demos/esports/widgets.wie',
                                'import_customizer_file_url'  =>  'https://wpstream.net/downloads/demos/esports/customizer.dat',
                                'import_preview_image_url'      =>  'https://wpstream.net/downloads/demos/esports/preview.png' ,
                                'import_notice'                 =>  esc_html__( 'Clear theme cache after demo import is complete!', 'hello-wpstream' ),
                                'preview_url'                   =>  'https://esports.wpstream.net/',
                                
                ),
                'church-demo' =>  array(
                                'import_file_name'              =>  'Church Demo',
                                'import_file_url'             =>  'https://wpstream.net/downloads/demos/church/church-demo.xml',
                                'import_widget_file_url'      =>  'https://wpstream.net/downloads/demos/church/widgets.wie',
                                'import_customizer_file_url'  =>  'https://wpstream.net/downloads/demos/church/customizer.dat',
                                'import_preview_image_url'      =>  'https://wpstream.net/downloads/demos/church/preview.png' ,
                                'import_notice'                 =>  esc_html__( 'Clear theme cache after demo import is complete!', 'hello-wpstream' ),
                                'preview_url'                   =>  'https://church.wpstream.net/',
                                
                )
            
                            
        );               


       
        return $demo_array;
    
    }





    function ocdi_after_import_setup() {
        // Assign menus to their locations.
        
        $main_menu = get_term_by( 'name', 'Main Menu', 'nav_menu' );
        $main_menu2 = get_term_by( 'name', 'First Menu', 'nav_menu' );
        $main_menu3 = get_term_by( 'name', 'Third menu', 'nav_menu' );
    
        set_theme_mod( 'nav_menu_locations', array(
            'main-menu'  => $main_menu->term_id,
            'main-menu2' => $main_menu2->term_id,
            'main-menu3' => $main_menu3->term_id
            )
        );

        
        // Assign front page and posts page (blog page).
        $front_page_id = get_page_by_title( 'Homepage' );
        $blog_page_id  = get_page_by_title( 'Blog' );

        update_option( 'show_on_front', 'page' );
        update_option( 'page_on_front', $front_page_id->ID );
        update_option( 'page_for_posts', $blog_page_id->ID );

    }
  



?>