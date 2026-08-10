<?php
/**
 * Elementor widget: WpStream Low-Latency Player (private beta).
 *
 * Registers an Elementor widget that embeds the low-latency player for a given
 * product/free-product id, or (when a user id is supplied) that user's first
 * channel. render() forwards the ids to the plugin's low-latency player builder;
 * content_template() supplies a minimal editor-time preview.
 *
 * @package    Wpstream
 * @subpackage Wpstream/widgets
 */

namespace ElementorWpStream\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Low-latency player Elementor widget (beta / approval-gated).
 */
class Wpstream_Player_LowLatecy_Base extends Widget_Base {

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
		return 'Wpstream_Player_LowLatecy';
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
            return '<div class="wpestate_elementor_widget_title">'.__( 'WpStream Player - Private Beta / Requires Approval', 'wpstream' ).'</div>';
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



		// Open the single "Content" settings section for this widget.
		$this->start_controls_section(
			'section_content',
			[
				'label' => __( 'Content', 'wpstream' ),
			]
		);

                // Product / free-product id whose channel should be played.
                $this->add_control(
			'item_id',
			[
                            'label' => __( 'Product/Free Product id', 'wpstream' ),
                            'label_block'=>true,
                            'type' => Controls_Manager::TEXT,
			]
		);

                // Optional user id; when set, the user's first channel is used and item_id is ignored.
                $this->add_control(
			'user_id',
			[
                            'label' => __( 'User Id', 'wpstream' ),
                            'label_block'=>true,
                            'type' => Controls_Manager::TEXT,
                            'description' => esc_html__( "We will use the first channel of this user id(product id will be ignored.).","wpestate")
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

            // Forward the product id and optional user id to the player builder.
            $attributes['id']                   =   $settings['item_id'] ;   // product/free-product id
            $attributes['user_id']              =   $settings['user_id'] ;   // optional user id (overrides id)
            // Plugin instance exposing the low-latency player renderer.
            global $wpstream_plugin;
            // Build and print the low-latency player markup.
            echo  $wpstream_plugin->wpstream_insert_player__low_latency_elementor($attributes);
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
		<!-- Editor-time preview: echoes the Backbone `title` setting as a placeholder. -->
		<div class="title">
			{{{ settings.title }}}
		</div>
		<?php
	}
}
