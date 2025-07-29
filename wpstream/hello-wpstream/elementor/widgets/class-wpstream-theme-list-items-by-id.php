<?php
/**
 * Class lust by id
 *
 * @package wpstream-theme
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List items by id
 */
class WpStream_Theme_List_Items_By_Id extends Widget_Base {
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
		return 'WpStream_Theme_List_Items_By_Id';
	}

	/**
	 * Get categories
	 */
	public function get_categories() {
		return array( 'hello-wpstream' );
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
		return __( ' Video Items by Id', 'hello-wpstream' );
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
		return 'eicon-post-list';
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
		return array( '' );
	}

	/**
	 * Register the widget controls.
	 *
	 * Adds different input fields to allow the user to change and customize the widget settings.
	 *
	 * @since 1.0.0
	 * @access protected
	 *
	 * @param array $input The input data containing the labels and values.
	 *
	 * @return array The transformed output array.
	 */
	public function elementor_transform( $input ) {
		$output = array();
		if ( is_array( $input ) ) {
			foreach ( $input as $key => $tax ) {
				$output[ $tax['value'] ] = $tax['label'];
			}
		}
		return $output;
	}

	/**
	 * Register control
	 */
	protected function register_controls() {
		$video_array              =   wpstream_return_video_array();
		$video_array_elemetor      = $this->elementor_transform( $video_array );
		

		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

		$this->add_control(
			'video_ids',
			[
				'label' => __( 'Select video item', 'hello-wpstream' ),
				'label_block'=>true,
				'type' => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => $video_array_elemetor,
			]
		);


		$this->add_control(
			'items_per_row',
			array(
				'label'   => __( 'No. of items per row', 'hello-wpstream' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'options'=>array(
					2=>2,
					3=>3,
					4=>4,
					5=>5,
					6=>6),
				'default' => 3,
			)
		);

		$this->add_control(
			'video_card',
			[
				'label' => __('Video Card Type', 'hello-wpstream'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 1,
				'options' =>  array(
					'1' => esc_html__( 'Video Card type 1', 'hello-wpstream' ),
					'2' => esc_html__( 'Video Card type 2', 'hello-wpstream' )
				)
			]
		);

		
		$this->end_controls_section();
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 * @access protected
	 *
	 * @param array $input The input data to be transformed into shortcode.
	 *
	 * @return string The generated shortcode.
	 */
	public function wpstream_send_to_shortcode( $input ) {
		$output = '';
		if ( !empty($input) ) {
			$num_items = count( $input );
			$i         = 0;

			foreach ( $input as $key => $value ) {
				$output .= $value;
				if ( ++$i !== $num_items ) {
					$output .= ', ';
				}
			}
		}
		return $output;
	}

	/**
	 * Render
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$attributes['video_ids']       = $settings['video_ids'];
		$attributes['items_per_row']   = $settings['items_per_row'];
		$attributes['video_card']      = $settings['video_card'];

		echo wpstream_theme_list_items_by_id_function( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
