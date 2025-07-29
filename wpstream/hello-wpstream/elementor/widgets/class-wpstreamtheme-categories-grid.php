<?php
/**
 * Class categories grid
 *
 * @package wpstream-theme
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Core\Schemes\Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Categories grid class
 */
class WpStreamTheme_Categories_Grid extends Widget_Base {
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
		return 'WpStreamTheme_Categories_Grid';
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
		return __( 'Categories Grids', 'hello-wpstream' );
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
		return 'eicon-posts-masonry';
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
	 *
	 * @access protected
	 *
	 * @param array $input An array of input data.
	 * @return array The transformed array.
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
		$this->start_controls_section(
			'content_section',
			array(
				'label' => esc_html__( 'Content', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$all_tax = wpstream_theme_return_all_taxomy_array();

		$all_tax_elemetor = $this->elementor_transform( $all_tax );

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
			'wpstream_grid_type',
			array(
				'label'       => esc_html__( 'Select Grid Type', 'hello-wpstream' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array(
					1 => esc_html__( 'Type 1', 'hello-wpstream' ),
					2 => esc_html__( 'Type 2', 'hello-wpstream' ),
					3 => esc_html__( 'Type 3', 'hello-wpstream' ),
					4 => esc_html__( 'Type 4', 'hello-wpstream' ),
					5 => esc_html__( 'Type 5', 'hello-wpstream' ),
					6 => esc_html__( 'Type 6', 'hello-wpstream' ),
				),
				'description' => '',
				'default'     => 1,
			)
		);

		$this->add_control(
			'wpstream_design_type',
			array(
				'label'       => esc_html__( 'Select Design Type', 'hello-wpstream' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array(
					1 => esc_html__( 'Design Type 1', 'hello-wpstream' ),
					2 => esc_html__( 'Design Type 2', 'hello-wpstream' ),
	
				),
				'description' => '',
				'default'     => 1,
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
						'max' => 700,
					),
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
	 * Render the widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 *
	 * @param string $post_type The post type to query for.
	 * @return array An array of posts.
	 */
	public function wpstream_theme_drop_posts( $post_type ) {
		$args = array(
			'numberposts' => -1,
			'post_type'   => $post_type,
		);

		$posts = get_posts( $args );
		$list  = array();
		foreach ( $posts as $cpost ) {
			$list[ $cpost->ID ] = $cpost->post_title;
		}
		return $list;
	}

	/**
	 * Transform an array of input values into a string for use in a shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input An array of input values.
	 * @return string The transformed string.
	 */
	public function wpstream_theme_send_to_shortcode( $input ) {
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
		$settings                           = $this->get_settings_for_display();
		$attributes['place_list']   		= $settings['place_list'] ;
		$attributes['grid_type'] 	 		= $settings['wpstream_grid_type'];
		$attributes['design_type']  		= $settings['wpstream_design_type'];

		

		echo wpstream_theme_display_grids( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
