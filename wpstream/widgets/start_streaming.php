<?php
/**
 * Elementor widget: WpStream - Start Streaming.
 *
 * Registers the "Start Streaming" Elementor widget under the plugin's own
 * widget category. It exposes a single product/free-product id control and,
 * on render, delegates to the plugin's live-stream unit wrapper to print the
 * broadcaster UI for that channel (or the user's first free/paid channel).
 *
 * @package    Wpstream
 * @subpackage Wpstream/widgets
 */
namespace ElementorWpStream\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Elementor widget class for the WpStream broadcaster (start streaming).
 *
 * Implements the standard Elementor widget contract: identity (name/title/icon),
 * editor controls, frontend render, and the editor preview template.
 */
class Wpstream_Start_Streaming_Base extends Widget_Base {

	/**
	 * Retrieve the widget name.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		// Internal identifier Elementor uses to reference this widget.
		return 'Wpstream_Start_Streaming';
	}

        /**
         * Retrieve the widget categories.
         *
         * @return array List of Elementor category slugs this widget belongs to.
         */
        public function get_categories() {
		// Group this widget under the plugin's own "wpstream" category.
		return [ 'wpstream' ];
	}
        
        
	/**
	 * Retrieve the widget title.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
            // Wrap the translated label in the theme's widget-title markup.
            return '<div class="wpstream_elementor_widget_title">'.__( 'WpStream - Start Streaming', 'wpstream' ).'</div>';
	}

	/**
	 * Retrieve the widget icon.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {
		// Elementor icon-font class shown for this widget in the panel.
		return 'eicon-play-o';
	}

	

        /**
         * Register the widget's editor controls.
         *
         * @access protected
         */
        protected function register_controls() {
                // Theme-wide taxonomy list kept in scope (unused by this widget).
                global $all_tax;
               
                // Local scratch array; declared but not used by this widget.
                $featured_places_array =array(1=>1,2=>2,3=>3);

		// Open the "Content" settings section.
		$this->start_controls_section(
			'section_content',
			[
				'label' => __( 'Content', 'wpstream' ),
			]
		);

		
          
                
                // Text field: the product / free-product id to stream on.
                // Left blank, we stream on the user's first free/paid channel.
                $this->add_control(
			'item_id',
			[
                            'label' => __( 'Product/Free Product id', 'wpstream' ),
                            'label_block'=>true,
                            'type' => Controls_Manager::TEXT,
                            'description'=>__('If you leave this option blank we will stream on the first free/paid channel for this user','wpstream')
			]
		);
                  
              
                
                
		// Close the "Content" settings section.
		$this->end_controls_section();

		
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 */
        
	/**
	 * Render the widget on the frontend.
	 *
	 * Reads the item id control and delegates to the plugin's live-stream unit
	 * wrapper, which echoes the broadcaster UI for that channel.
	 *
	 * @access protected
	 */
	protected function render() {
            // Pull the saved control values for this widget instance.
            $settings = $this->get_settings_for_display();

            // The chosen product/free-product id to broadcast on.
            $attributes['id']                   =   $settings['item_id'] ;  
          
            // Main plugin instance that renders the live-stream unit.
            global $wpstream_plugin;
           
          
            
           // echo  $wpstream_plugin->admin->wpstream_live_stream_unit(   $attributes['id'],'front' );
            // Echo the broadcaster UI wrapper for the chosen id, in front-end mode.
            echo  $wpstream_plugin->wpstream_live_stream_unit_wrapper(   $attributes['id'],'front' );
	}

	/**
	 * Render the widget output in the editor.
	 *
	 * Written as a Backbone JavaScript template and used to generate the live preview.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 */
	protected function content_template() {
		?>
		<div class="title">
			<!-- Backbone/Underscore template token echoed as the live editor preview. -->
			{{{ settings.title }}}
		</div>
		<?php
	}
}
