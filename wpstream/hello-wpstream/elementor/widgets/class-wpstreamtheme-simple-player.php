<?php
/**
 * Simple Player Elementor widget.
 *
 * Registers the "Simple Player" widget for the hello-wpstream Elementor
 * category. It lets an editor pick a single video item (or a user whose first
 * channel is used) and exposes style controls for sizing, border radius, the
 * play-button position and colors. On render it delegates to the theme helper
 * wpstream_theme_simple_player() to output the actual player markup.
 *
 * @package wpstream-theme
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

/**
 * Simple player.
 *
 * Elementor widget wrapping the theme's single-item video player.
 */
class WpStreamTheme_Simple_Player extends \Elementor\Widget_Base {
	/**
	 * Retrieve the widget name.
	 *
	 * @return string Widget name.
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function get_name() {
		// Unique internal identifier Elementor uses for this widget.
		return 'WpStreamTheme_Simple_Player';
	}

	/**
	 * Get categories
	 *
	 * @return array The Elementor panel categories this widget belongs to.
	 */
	public function get_categories() {
		// Group this widget under the theme's custom Elementor category.
		return array( 'hello-wpstream' );
	}

	/**
	 * Retrieve the widget title.
	 *
	 * @return string Widget title.
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function get_title() {
		// Human-readable label shown in the Elementor widget panel.
		return __( 'Simple Player', 'hello-wpstream' );
	}

	/**
	 * Retrieve the widget icon.
	 *
	 * @return string Widget icon.
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function get_icon() {
		// Icon shown next to the widget name in the Elementor panel.
		return 'eicon-posts-masonry';
	}


	/**
	 * Retrieve the list of scripts the widget depended on.
	 *
	 * Used to set scripts dependencies required to run the widget.
	 *
	 * @return array Widget scripts dependencies.
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function get_script_depends() {
		// No extra script handles are required by this widget.
		return array( '' );
	}

	/**
	 * Register the widget controls.
	 *
	 * Adds different input fields to allow the user to change and customize the widget settings.
	 *
	 * @param array $input The input array to transform.
	 * @since 1.0.0
	 * @access protected
	 */
	public function elementor_transform( $input ) {
		// Build a value => label map Elementor SELECT/SELECT2 controls expect.
		$output = array();
		if ( is_array( $input ) ) {
			// Re-key each {value,label} entry into value => label.
			foreach ( $input as $key => $tax ) {
				$output[ $tax['value'] ] = $tax['label'];
			}
		}
		return $output;
	}


	/**
	 * Register controls
	 *
	 * Defines the Content and Style controls (item picker, user id, sizing,
	 * border radius, play-button position and colors) for this widget.
	 */
	protected function register_controls() {
		// Fetch the list of available video items and convert it for Elementor.
		$video_array              =   wpstream_return_video_array();
		$video_array_elemetor      = $this->elementor_transform( $video_array );

		// --- Content tab: choose what the player displays. ---
		$this->start_controls_section(
			'section_content',
			[
				'label' => __( 'Content', 'hello-wpstream' ),
			]
		);
 
         
                  
		// Control: pick which single video item the player shows.
		$this->add_control(
			'item_id',
			[
				'label' => __( 'Select video item', 'hello-wpstream' ),
				'label_block'=>true,
				'type' => \Elementor\Controls_Manager::SELECT2,
				'multiple' => false,
				'options' => $video_array_elemetor,
			]
		);
              
		// Control: alternatively target a user; their first channel is used.
		$this->add_control(
			'user_id',
			[
                            'label' => __( 'User Id', 'hello-wpstream' ),
                            'label_block'=>true,
                            'type' => Controls_Manager::TEXT,
                            'description' => esc_html__( 'We will use the first channel of this user id(product id will be ignored.).','hello-wpstream')
			]
		);		
		$this->end_controls_section();

		// --- Style tab: item sizing controls. ---
		$this->start_controls_section(
			'size_section',
			array(
				'label' => esc_html__( 'Item Settings', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Control: player/poster height (responsive), applied via selectors.
		$this->add_responsive_control(
			'item_height',
			array(
				'label'           => esc_html__( 'Image Height', 'hello-wpstream' ),
				'type'            => Controls_Manager::SLIDER,
				'default' => array(
					'size' => 500,
					'unit' => 'px',
				),
				'range'           => array(
					'px' => array(
						'min' => 100,
						'max' => 900,
					),
				),
				
				'devices'         => array( 'desktop', 'tablet', 'mobile' ),
				
				'selectors'       => array(
					'{{WRAPPER}} .wpstream_simple_player_shortcode_wrapper'  => 'height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_player_container'  => 'height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .vjs-poster img '=> 'height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}}  .vjs-fluid '	=> 'height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_player_shortcode'=> 'height: {{SIZE}}{{UNIT}};',
					
					),
			)
		);
 
		// Control: rounded corners on the player wrapper (responsive).
		$this->add_responsive_control(
			'item_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wpstream_simple_player_shortcode_wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				
		
				),
			)
		);

		
		// Control: absolute position of the play button over the player.
		$this->add_responsive_control(
			'play_button_controls_position', [
			'label' => esc_html__('Player button position', 'hello-wpstream'),
			'type' => Controls_Manager::DIMENSIONS,
			'size_units' => ['px', 'em', '%'],
			'default' => [
            'top' => '430',
            'right' => 'auto',
            'bottom' => '0',
            'left' => '30',
            'unit' => 'px',
            'isLinked' => false,
        ],
			'selectors' => [
				'{{WRAPPER}} .wpstream_simple_player_shortcode_wrapper .wpstream_player_container .wpstream_theme_trailer_wrapper .wpstream_video_on_demand_play_video_wrapper ' => 'position: absolute; top: {{TOP}}{{UNIT}}; right: {{RIGHT}}{{UNIT}}; bottom: {{BOTTOM}}{{UNIT}}; left: {{LEFT}}{{UNIT}};',
			],
				]
		);

		$this->end_controls_section();

		// --- Style tab: play-button color controls. ---
		$this->start_controls_section(
			'style_section',
			array(
				'label' => esc_html__( 'Style', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Control: play-button text color.
		$this->add_control(
			'play_video_button_text_color',
			array(
				'label'     => esc_html__( 'Play Video Button Text Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_video_on_demand_play_video_wrapper' => 'color: {{VALUE}} !important',
					'{{WRAPPER}} .vjs-control-text' => 'color: {{VALUE}} !important',
				),
			)
		);

		// Control: play-button text color on hover.
		$this->add_control(
			'play_video_button_hover_text_color',
			array(
				'label'     => esc_html__( 'Play Video Button Hover Text Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_video_on_demand_play_video_wrapper:hover' => 'color: {{VALUE}} !important',
					'{{WRAPPER}} .vjs-control-text:hover' => 'color: {{VALUE}} !important',
				),
			)
		);

		$this->end_controls_section();

	}


	/**
	 * Render the widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @param array $input The input array containing shortcode attributes.
	 * @since 1.0.0
	 * @access protected
	 */
	public function wpstream_send_to_shortcode( $input ) {
		// Flatten an array of values into a comma-separated string.
		$output = '';
		if ( !empty($input) ) {
			// Track position so separators go between items, not after the last.
			$num_items = count( $input );
			$i         = 0;

			// Append each value, adding ", " until the final element.
			foreach ( $input as $key => $value ) {
				$output .= $value;
				if ( ++$i !== $num_items ) {
					$output .= ', ';
				}
			}
		}
		// Return the joined string.
		return $output;
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
	protected function render() {
		// Pull the saved control values for this widget instance.
		$settings     = $this->get_settings_for_display();
		// Map the selected item id and user id into the helper's args.
		$args['item_id'] = $settings['item_id'];
		$args['user_id'] = $settings['user_id'];

		// Delegate to the theme helper that outputs the player markup.
		echo wpstream_theme_simple_player( $args ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
