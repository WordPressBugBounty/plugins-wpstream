<?php
/**
 * Elementor widget: WpStream Chat (Beta).
 *
 * Registers the "WpStream Chat" Elementor widget under the plugin's own
 * widget category. It exposes a single product/free-product id control and,
 * on render, delegates to the main plugin object to print the live chat box
 * bound to that channel.
 *
 * @package    Wpstream
 * @subpackage Wpstream/widgets
 */
namespace ElementorWpStream\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Elementor widget class for the WpStream live chat.
 *
 * Implements the standard Elementor widget contract: identity (name/title/icon),
 * editor controls, frontend render, and the editor preview template.
 */
class Wpstream_Chat_Base extends Widget_Base {

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
		return 'Wpstream_Chat';
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
            return '<div class="wpestate_elementor_widget_title">'.__( 'WpStream Chat -  Beta Version', 'wpstream' ).'</div>';
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
	 * Retrieve the list of scripts the widget depended on.
	 *
	 * Used to set scripts dependencies required to run the widget.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 *
	 * @return array Widget scripts dependencies.
	 */
	public function get_script_depends() {
	// No extra script handles are registered as dependencies.
	return [ '' ];
	}

	/**
	 * Register the widget controls.
	 *
	 * Adds different input fields to allow the user to change and customize the widget settings.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 */
     
    /**
     * Flatten a list of {value,label} pairs into a value => label map.
     *
     * @param array $input List of arrays each holding 'value' and 'label'.
     * @return array Map keyed by each item's 'value' with its 'label'.
     */
    public function elementor_transform($input){
            // Accumulator for the resulting value => label map.
            $output=array();
            // Only iterate when we were actually handed an array.
            if( is_array($input) ){
                // Re-key each entry so the option value becomes the map key.
                foreach ($input as $key=>$tax){
                    $output[$tax['value']]=$tax['label'];
                }
            }
            return $output;
        }

        /**
         * Register the widget's editor controls.
         *
         * @access protected
         */
        protected function _register_controls() {
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

		
          
                
                // Text field: the product / free-product id whose chat to display.
                $this->add_control(
			'item_id',
			[
                            'label' => __( 'Product/Free Product id', 'wpstream' ),
                            'label_block'=>true,
                            'type' => Controls_Manager::TEXT,
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
          * Join array values into a single comma-separated string.
          *
          * @param array|string $input Values to concatenate; '' short-circuits to empty.
          * @return string Comma-separated list, or empty string when nothing to join.
          */
         public function wpresidence_send_to_shortcode($input){
            // Start with an empty result buffer.
            $output='';
            // Nothing to concatenate for an empty input.
            if($input!==''){
                // Track the total so we can skip the trailing separator.
                $numItems = count($input);
                $i = 0;

                // Append each value, comma-separating all but the last.
                foreach ($input as $key=>$value){
                    $output.=$value;
                    if(++$i !== $numItems) {
                      $output.=', ';
                    }
                }
            }
            return $output;
        }
        
	/**
	 * Render the widget on the frontend.
	 *
	 * Reads the item id control and delegates to the plugin to echo the chat box.
	 *
	 * @access protected
	 */
	protected function render() {
            // Pull the saved control values for this widget instance.
            $settings = $this->get_settings_for_display();

            // The chosen product/free-product id whose chat to render.
            $attributes['id']                   =   $settings['item_id'] ;  
          
            // Main plugin instance that knows how to build the chat markup.
            global $wpstream_plugin;
            // Echo the assembled chat box for the chosen channel.
            echo  $wpstream_plugin->wpstream_insert_chat_elementor($attributes);
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
