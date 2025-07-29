<?php
/**
 * Class categories list
 *
 * @package wpstream-theme
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Core\Files\Assets\Svg\Svg_Handler;
use Elementor\Repeater;
use Elementor\Group_Control_Typography;
use Elementor\Core\Schemes\Typography;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Categories list class
 */
class WpStreamTheme_Categories_List extends Widget_Base {
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
		return 'WpStreamTheme_Categories_List';
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
		return __( 'Categories List', 'hello-wpstream' );
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
		return 'eicon-product-categories';
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
	 * Transform input data.
	 *
	 * This function transforms input data into a specific format.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 *
	 * @param array $input The input data to be transformed.
	 *
	 * @return array The transformed output data.
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
	 * Register controls
	 */
	protected function register_controls() {
		$all_tax = wpstream_theme_return_all_taxomy_array();

		$all_tax_elemetor = $this->elementor_transform( $all_tax );

		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

		$this->add_control(
			'place_list',
			array(
				'label'       => __( 'Type the category name you want to show', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $all_tax_elemetor,
			)
		);

		$this->add_control(
			'place_per_row',
			array(
				'label'       => __( 'Categories per row', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 3,
				'options'     => array(
					2 => 2,
					3 => 3,
					4 => 4,
				),
			)
		);

		$this->add_control(
			'design_type',
			array(
				'label'       => __( 'Design Style', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 1,
				'options'     => array(
					1 => __( 'Type 1', 'hello-wpstream' ),
					2 => __( 'Type 2', 'hello-wpstream' ),
					3 => __( 'Type 3', 'hello-wpstream' ),
				),
			)
		);

		$this->end_controls_section();



		$this->start_controls_section(
			'size_section',
			array(
				'label' => esc_html__( 'Item Settings', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'item_height',
			array(
				'label'           => esc_html__( 'Image Height', 'hello-wpstream' ),
				'type'            => Controls_Manager::SLIDER,
				'range'           => array(
					'px' => array(
						'min' => 50,
						'max' => 500,
					),
				),
				'condition'       => array(
					'design_type' => array('1','2'),
				),
				'devices'         => array( 'desktop', 'tablet', 'mobile' ),
				'desktop_default' => array(
					'size' => 350,
					'unit' => 'px',
				),
				'tablet_default'  => array(
					'size' => '',
					'unit' => 'px',
				),
				'mobile_default'  => array(
					'size' => '',
					'unit' => 'px',
				),
				'selectors'       => array(
					'{{WRAPPER}} .wpstream_category_unit_wrapper_type1' => 'height: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_category_unit_wrapper_type2 .wpstream_category_unit_item'  => 'height: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'item_height_square',
			array(
				'label'           => esc_html__( 'Image Size', 'hello-wpstream' ),
				'type'            => Controls_Manager::SLIDER,
				'range'           => array(
					'px' => array(
						'min' => 50,
						'max' => 500,
					),
				),
				'condition'       => array(
					'design_type' => '3',
				),
				'devices'         => array( 'desktop', 'tablet', 'mobile' ),
				'desktop_default' => array(
					'size' => 75,
					'unit' => 'px',
				),
				'tablet_default'  => array(
					'size' => '',
					'unit' => 'px',
				),
				'mobile_default'  => array(
					'size' => '',
					'unit' => 'px',
				),
				'selectors'       => array(
					'{{WRAPPER}} .wpstream_category_unit_wrapper_type3 .wpstream_category_unit_item' => 'height: {{SIZE}}{{UNIT}};width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'item_margin_bottom',
			array(
				'label'           => esc_html__( 'Item Margin Bottom', 'hello-wpstream' ),
				'type'            => Controls_Manager::SLIDER,
				'range'           => array(
					'px' => array(
						'min' => 5,
						'max' => 100,
					),
				),
				'condition'       => array(
					'design_type' => '2',
				),
				'devices'         => array( 'desktop', 'tablet', 'mobile' ),
				'desktop_default' => array(
					'size' => 15,
					'unit' => 'px',
				),
				'tablet_default'  => array(
					'size' => '',
					'unit' => 'px',
				),
				'mobile_default'  => array(
					'size' => '',
					'unit' => 'px',
				),
				'default'         => array(
					'size' => 15,
					'unit' => 'px',
				),
				'selectors'       => array(
					'{{WRAPPER}} .wpstream_category_unit_wrapper_type1' => 'margin-bottom: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_category_unit_wrapper_type2' => 'margin-bottom: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_category_unit_wrapper_type3' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'item_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wpstream_category_unit_wrapper_type1 .wpstream_category_unit_item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_category_unit_wrapper_type2 .wpstream_category_unit_item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_category_unit_wrapper_type3 .wpstream_category_unit_item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .	wpstream_category_unit_item_cover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			
		
				),
			)
		);

		$this->end_controls_section();

		/*
		 * -------------------------------------------------------------------------------------------------
		 * Start Typografy
		 */

		$this->start_controls_section(
			'typography_section',
			array(
				'label' => esc_html__( 'Style', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'tax_title',
				'label'          => esc_html__( 'Title Typography', 'hello-wpstream' ),
'global' => [
            'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
        ],
				'selector'       => '{{WRAPPER}} h4 a',
				'fields_options' => array(
					// Inner control name.
					'font_weight' => array(
						// Inner control settings.
						'default' => '500',
					),
					'font_family' => array(
						'default' => 'Roboto',
					),
					'font_size'   => array(
						'default' => array(
							'unit' => 'px',
							'size' => 24,
						),
					),
				),
			)
		);
		$this->add_responsive_control(
			'property_title_margin_bottom',
			array(
				'label'           => esc_html__( 'Title Margin Bottom(px)', 'hello-wpstream' ),
				'type'            => Controls_Manager::SLIDER,
				'range'           => array(
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'devices'         => array( 'desktop', 'tablet', 'mobile' ),
				'desktop_default' => array(
					'size' => '',
					'unit' => 'px',
				),
				'tablet_default'  => array(
					'size' => '',
					'unit' => 'px',
				),
				'mobile_default'  => array(
					'size' => '',
					'unit' => 'px',
				),
				'selectors'       => array(
					'{{WRAPPER}} h4' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'property_tagline_margin_bottom',
			array(
				'label'           => esc_html__( 'Tagline Margin Bottom(px)', 'hello-wpstream' ),
				'type'            => Controls_Manager::SLIDER,
				'range'           => array(
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'condition'       => array(
					'design_type' => array('1','2'),
				
				),
				'devices'         => array( 'desktop', 'tablet', 'mobile' ),
				'desktop_default' => array(
					'size' => '',
					'unit' => 'px',
				),
				'tablet_default'  => array(
					'size' => '',
					'unit' => 'px',
				),
				'mobile_default'  => array(
					'size' => '',
					'unit' => 'px',
				),
				'selectors'       => array(
					'{{WRAPPER}} .wpstream_category_unit_item_details_tagline' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'tax_listings',
				'label'          => esc_html__( 'Listings Text Typography', 'hello-wpstream' ),
'global' => [
            'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
        ],
				'selector'       => '{{WRAPPER}} .wpstream_category_unit_item_details_listings',
				'fields_options' => array(
					// Inner control name.
					'font_weight' => array(
						// Inner control settings.
						'default' => '300',
					),
					'font_family' => array(
						'default' => 'Roboto',
					),
					'font_size'   => array(
						'default' => array(
							'unit' => 'px',
							'size' => 14,
						),
					),
				),
			)
		);

		$this->add_control(
			'tax_title_color',
			array(
				'label'     => esc_html__( 'Title Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} h4 a' => 'color: {{VALUE}}!important',
				),
			)
		);

		$this->add_control(
			'tax_tagline_color',
			array(
				'label'     => esc_html__( 'Tagline Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'condition' => array(
					'design_type' => 'type1',
				),
				'selectors' => array(
					'{{WRAPPER}}  .wpstream_category_unit_item_details_tagline' => 'color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'tax_listings_color',
			array(
				'label'     => esc_html__( 'Listings Text Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}}  .wpstream_category_unit_item_details_listings' => 'color: {{VALUE}}',
				
				),
			)
		);

		$this->add_control(
			'tax_listings_color_back',
			array(
				'label'     => esc_html__( 'Listings Backgorund Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
			
				'selectors' => array(
					'{{WRAPPER}}  .wpstream_category_unit_item_details_listings' => 'background: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'ovarlay_color_back',
			array(
				'label'     => esc_html__( 'Image Overlay Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_category_unit_item_cover' => 'background: {{VALUE}};opacity: 1;',
				),
			)
		);

		$this->add_control(
			'ovarlay_color_back_hover',
			array(
				'label'     => esc_html__( 'Image Overlay Background Color Hover', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_category_unit_item_cover:hover' => 'background: {{VALUE}};opacity: 1;',
				),
			)
		);

		$this->end_controls_section();

		/*
		-------------------------------------------------------------------------------------------------
		 * Start shadow section
		 */
		$this->start_controls_section(
			'section_grid_box_shadow',
			array(
				'label' => esc_html__( 'Box Shadow', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'label'    => esc_html__( 'Box Shadow', 'hello-wpstream' ),
				'selector' => '{{WRAPPER}} .wpstream_category_unit_wrapper_type1 .wpstream_category_unit_item ,{{WRAPPER}} .wpstream_category_unit_wrapper_type2 .wpstream_category_unit_item,{{WRAPPER}} .wpstream_category_unit_wrapper_type3 .wpstream_category_unit_item ',
			)
		);

		$this->end_controls_section();

		/*
		 * -------------------------------------------------------------------------------------------------
		 * End shadow section
		 */
	}

	/**
	 * Send input data to shortcode.
	 *
	 * This function generates a string representation of the input data to be used in a shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 *
	 * @param array $input The input data to be sent to the shortcode.
	 *
	 * @return string The string representation of the input data.
	 */
	public function wpstream_theme_send_to_shortcode( $input ) {
		$output = '';
		if ( !empty($input) ) {
			$num_items = count( $input );
			$i         = 0;

			foreach ( $input as $key => $value ) {
				$output .= $value;
			
			}
		}
		return $output;
	}

	/**
	 * Render
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$attributes['place_list']    = $settings['place_list'] ;
		$attributes['place_per_row'] = $settings['place_per_row'];
		$attributes['design_type']   = $settings['design_type'];
		echo wpstreamtheme_categories_list_function( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	
	}
}
