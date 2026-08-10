<?php
/**
 * Elementor widget: WpStream Channel List.
 *
 * Registers an Elementor widget that renders a grid of live-event channels.
 * The control panel lets the editor choose free/paid filtering, live-only
 * filtering, per-row/per-page counts, the "watch" label, and ordering; render()
 * maps those settings to attributes and delegates to the plugin's media-list
 * shortcode function.
 *
 * @package    Wpstream
 * @subpackage Wpstream/widgets
 */

namespace ElementorWpStream\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;


if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Channel-list Elementor widget (live events).
 */
class Wpstream_Media_List_Channel extends Widget_Base {

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
		// Unique identifier Elementor uses to reference this widget.
		return 'Wpstream_Media_List_Channel';
	}

        /**
         * Retrieve the Elementor panel categories this widget appears under.
         *
         * @return array Category slugs.
         */
        public function get_categories() {
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
            return '<div class="wpestate_elementor_widget_title">'.__( 'WpStream - Channel List', 'wpstream' ).'</div>';
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
         * Flatten a list of {value,label} rows into a value => label map.
         *
         * @param array $input Array of associative rows with 'value'/'label' keys.
         * @return array Map keyed by each row's value.
         */
        public function elementor_transform($input){
            $output=array();
            if( is_array($input) ){
                // Reindex each row so its 'value' becomes the key and 'label' the value.
                foreach ($input as $key=>$tax){
                    $output[$tax['value']]=$tax['label'];
                }
            }
            return $output;
        }

        protected function _register_controls() {
            global $all_tax;

           // Media type options (this widget ultimately forces Live Event; see render()).
           $product_type=array(
                    0 =>  __('Both','wpstream'),
                    1 =>  __('Live Event','wpstream'),
                    2 =>  __('Video on demand','wpstream')
            );

            // Free vs. paid filter options.
            $free_paid_type=array(
                0 =>  esc_html__('Free','wpstream'),
                1 =>  esc_html__('Paid','wpstream')
            );


            // Ordering options offered by the "Order by" select.
            $order_by_id=array(
                0=>esc_html('By date - ASC','wpstream'),
                1=>esc_html('By date - DESC','wpstream'),
                2=>esc_html('By title - ASC','wpstream'),
                3=>esc_html('By title - DESC','wpstream'),
            );


            // Yes/no option set (declared for reuse by live-only style controls).
            $live_settings=array(
                'no'=>esc_html__('no','wpstream'),
                'yes'=>esc_html__('yes','wpstream'),
            );

            // Open the single "Content" settings section for this widget.
            $this->start_controls_section(
                    'section_content',
                    [
                            'label' => __( 'Content', 'wpstream' ),
                    ]
            );

//            $this->add_control(
//                  'product_type',
//                  [
//                      'label' => __( 'What type of media', 'wpstream' ),
//                      'type' => \Elementor\Controls_Manager::SELECT,
//                      'default' => $product_type[0],
//                      'options' => $product_type
//                  ]
//            );
            
            // Free/paid filter dropdown.
            $this->add_control(
                  'product_type_free_paid',
                  [
                      'label' => __( 'Show Free or Paid Media ?', 'wpstream' ),
                      'type' => \Elementor\Controls_Manager::SELECT,
                      'default' => $free_paid_type[0],
                      'options' => $free_paid_type
                  ]
            );


            // Toggle to restrict the grid to channels that are live right now.
            $this->add_control(
                  'product_show_live',
                  [
                    'label' => __( 'Only show active channels', 'wpstream' ),
                    'type' => \Elementor\Controls_Manager::SWITCHER,
                    'label_on' => __( 'Yes', 'your-plugin' ),
                    'label_off' => __( 'No', 'your-plugin' ),
                    'default' => 'no',
                    'return_value' => 'yes',
                    'description'=>__('Only show channels that are live streaming right now.','wpstream'),

                  ]
            );

            // Items per row (grid columns); capped at 4 downstream.
            $this->add_control(
                'row_number',
                [
                    'label' => __( 'Number of Items per row', 'wpstream' ),
                    'label_block'=>true,
                    'type' => Controls_Manager::TEXT,
                    'description'=>__('How many items will be displayed per row. Maximum no is 4','wpstream'),
                    'default'=>3
                ]
            );


            // Items per page (pagination size).
            $this->add_control(
                    'media_number',
                    [
                        'label' => __( 'Number of Items per Page', 'wpstream' ),
                        'label_block'=>true,
                        'type' => Controls_Manager::TEXT,
                        'description'=>__('How many items will be displayed per page','wpstream'),
                        'default'=>3
                    ]
            );

            // Call-to-action label shown on free items.
            $this->add_control(
                    'free_label',
                    [
                        'label' => __( 'Link Label for free items', 'wpstream' ),
                        'label_block'=>true,
                        'type' => Controls_Manager::TEXT,
                        'default'=>esc_html__('Watch now!','wpstream'),
                        'description'=>__('Link Label for free items','wpstream')
                    ]
            );



            // Result ordering (date/title, asc/desc).
            $this->add_control(
                  'order_by',
                  [
                      'label' => __( 'Order by', 'wpstream' ),
                      'type' => \Elementor\Controls_Manager::SELECT,
                      'default' => 0,
                      'options' => $order_by_id
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
         * Join an array of values into a comma-separated string.
         *
         * @param array|string $input Values to join (empty string yields '').
         * @return string Comma-separated list.
         */
         public function wpresidence_send_to_shortcode($input){
            $output='';
            if($input!==''){
                // Track total count so the last element does not get a trailing comma.
                $numItems = count($input);
                $i = 0;

                foreach ($input as $key=>$value){
                    $output.=$value;
                    // Append a separator after every item except the last.
                    if(++$i !== $numItems) {
                      $output.=', ';
                    }
                }
            }
            return $output;
        }

	protected function render() {
            // Current editor settings for this widget instance.
            $settings = $this->get_settings_for_display();

            // Map panel settings onto the shortcode function's attribute array.
            $attributes['product_type']                 =   1;                                   // force Live Event type
            $attributes['product_type_free_paid']       =   $settings['product_type_free_paid'] ; // free/paid filter
            $attributes['media_number']                 =   $settings['media_number'] ;           // items per page
            $attributes['row_number']                   =   $settings['row_number'] ;             // items per row
            $attributes['free_label']                   =   $settings['free_label'] ;             // CTA label
            $attributes['order_by']                     =   $settings['order_by'] ;               // sort order
            $attributes['product_show_live']            =   $settings['product_show_live'];       // live-only toggle
            // Plugin instance exposing the media-list renderer.
            global $wpstream_plugin;



           // echo  $wpstream_plugin->admin->wpstream_live_stream_unit(   $attributes['id'],'front' );
            // Delegate to the shared media-list renderer and print its HTML.
            echo  $wpstream_plugin->wpstream_media_list_elementor_function(   $attributes );
	}


}
