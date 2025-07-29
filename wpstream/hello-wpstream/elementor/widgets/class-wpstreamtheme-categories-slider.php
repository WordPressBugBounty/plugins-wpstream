<?php
/**
 * Class categories slider
 *
 * @package wpstream-theme
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;

use Elementor\Group_Control_Typography;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Categories slider class
 */
class WpStreamTheme_Categories_Slider extends Widget_Base {
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
		return 'WpStreamTheme_Categories_Slider';
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
		return __( 'Categories Slider', 'hello-wpstream' );
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
		return 'eicon-slider-album';
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
	 * Transform input data for Elementor widget.
	 *
	 * This function transforms an input array into the format expected by Elementor widgets.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 *
	 * @param array $input The input data to transform.
	 *
	 * @return array The transformed data.
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
		$arrow_type         =   array('top'=>'top','sideways'=>'sideways');
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
			'arrows_position',
			[
				'label' => __('Slider Navigation Arrows Position', 'hello-wpstream'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'sideways',
				'options' => $arrow_type
			]
		);

	

		$this->add_control(
			'place_per_row',
			array(
				'label'       => __( 'Categories per row', 'hello-wpstream' ),
				'label_block' => true,
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

		$this->add_control(
				'autoscroll',
				[
					'label' => __('Auto scroll period in ms (1sec = 1000)', 'hello-wpstream'),
					'type' => Controls_Manager::TEXT,
					'Label Block',
					'default' => '0',

				]
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
					'design_type' => array('1','2',1,2),
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

		
		$this->start_controls_section(
			'arrow_colors_section',
			array(
				'label' => esc_html__( 'Arrows Colors', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)			
		);

		$this->add_control(
			'arrows_main_back_color',
			array(
				'label'     => esc_html__( 'Arrows background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .slick-arrow' => 'background-color: {{VALUE}}',

				),
			)
		);

		$this->add_control(
			'arrows_font_color',
			array(
				'label'     => esc_html__( 'Arrows Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .slick-arrow' => 'color: {{VALUE}}',

				),
			)
		);

		
	

		$this->add_control(
			'dropdown_menu_back_color',
			array(

				'label'     => esc_html__( 'Arrows hover background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .slick-arrow:hover' => 'background-color: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'dropdown_menu_font_color',
			array(
				'label'     => esc_html__( 'Arrows Hover Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .slick-arrow:hover' => 'color: {{VALUE}}',

				),
			)
		);
		

		$this->end_controls_section();

		$this->start_controls_section(
			'arrow_style_section',
			array(
				'label' => esc_html__( 'Arrows Styles', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)			
		);

		$this->add_responsive_control(
			'arrow_border_radius', [
			'label' => esc_html__('Border Radius', 'hello-wpstream'),
			'type' => Controls_Manager::DIMENSIONS,
			'size_units' => ['px', '%'],
			'selectors' => [
			'{{WRAPPER}} .slick-arrow' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'arrow_margin_top', [
				'label' => esc_html__('Arrows Top Margin', 'hello-wpstream'),
				'type' => Controls_Manager::SLIDER,

				'range' => [
					'px' => [
						'min' => -200,
						'max' => 200,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .slick-arrow' => 'top: {{SIZE}}{{UNIT}};',

				],
			]
		);


		$this->add_responsive_control(
			'arrow_size', [
		'label' => esc_html__('Arrow Circle Size', 'hello-wpstream'),
		'type' => Controls_Manager::SLIDER,
		
		'range' => [
			'px' => [
				'min' => 0,
				'max' => 200,
			],
		],
		'selectors' => [
			'{{WRAPPER}} .slick-arrow' => 'width: {{SIZE}}{{UNIT}};height: {{SIZE}}{{UNIT}};',
		
		],
			]
		);
		$this->add_responsive_control(
			'actual_arrow_size', [
		'label' => esc_html__('Arrow Size', 'hello-wpstream'),
		'type' => Controls_Manager::SLIDER,
		
		'range' => [
			'px' => [
				'min' => 0,
				'max' => 200,
			],
		],
		'selectors' => [
			'{{WRAPPER}} .slick-arrow svg' => 'height: {{SIZE}}{{UNIT}};',
		
		],
			]
		);

		$this->add_responsive_control(
			'arrow_margin_right', [
		'label' => esc_html__('Previous Button - Right Position ', 'hello-wpstream'),
		'type' => Controls_Manager::SLIDER,
		'condition' => [
			'arrows_position' => 'top'
		],
		
		'range' => [
			'px' => [
				'min' => 0,
				'max' => 200,
			],
		],
		'selectors' => [
			'{{WRAPPER}} .slick-prev' => 'right: {{SIZE}}{{UNIT}};',
		
		],
			]
		);

		$this->add_responsive_control(
			'arrows_border_width', [
                'label' => esc_html__('Border Width ', 'hello-wpstream'),
				'type' => Controls_Manager::SLIDER,
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 15,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .slick-arrow' => 'border-width: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'arrows_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .slick-arrow' => 'border-color: {{VALUE}}',
				],
			)
		);


		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(), [
		'name' => 'box_shadow_arrow',
		'label' => esc_html__(' Shadow', 'hello-wpstream'),
		'selector' => '{{WRAPPER}} .slick-arrow',
			]
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
				'selector' => '{{WRAPPER}} .wpstream_category_unit_wrapper_type1 ,{{WRAPPER}} .wpstream_category_unit_wrapper_type2 .wpstream_category_unit_item,{{WRAPPER}} .wpstream_category_unit_wrapper_type3 .wpstream_category_unit_item ',
			)
		);

		$this->end_controls_section();

		/*
		 * -------------------------------------------------------------------------------------------------
		 * End shadow section
		 */
	}

	/**
	 * Render the property category values.
	 *
	 * This function generates a comma-separated string of property category values.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 *
	 * @param array $input The array of property category values.
	 *
	 * @return string The comma-separated string of property category values.
	 */
	public function wpstream_property_category_values( $input ) {
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
	 * Render the widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$attributes['place_list']    = $settings['place_list'] ;
		$attributes['place_per_row'] = $settings['place_per_row'];
		$attributes['design_type']   = $settings['design_type'];
		$attributes['arrows_position']  = $settings['arrows_position'];
		$attributes['autoscroll']   	= $settings['autoscroll'];

		$slider_id                        = 'categories_slider_carousel_elementor_v1_' . wp_rand( 1, 99999 );
		echo wpstream_theme_categories_slider( $attributes ,$slider_id  ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) :
			?>
			<script>

				jQuery('.wpstream_category_slider').each(function () {
					var items = jQuery(this).attr('data-items-per-row');
					var auto = parseInt(jQuery(this).attr('data-auto'));
					var slick = jQuery(this).slick({
						infinite: true,
						slidesToShow: items,
						slidesToScroll: 1,
						dots: false,
						nextArrow:'<button class="slick-next slick-arrow 333 " aria-label="Next" type="button" style=""><svg width="12" height="20" viewBox="0 0 12 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.93934 0.93934C1.52513 0.353553 2.47487 0.353553 3.06066 0.93934L11.0607 8.93934C11.6464 9.52513 11.6464 10.4749 11.0607 11.0607L3.06066 19.0607C2.47487 19.6464 1.52513 19.6464 0.93934 19.0607C0.353553 18.4749 0.353553 17.5251 0.93934 16.9393L7.87868 10L0.93934 3.06066C0.353553 2.47487 0.353553 1.52513 0.93934 0.93934Z"/></svg></button>',
            			prevArrow:'<button class="slick-prev slick-arrow 222 " aria-label="Next" type="button" style=""><svg width="12" height="20" viewBox="0 0 12 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M11.0607 19.0607C10.4749 19.6464 9.52513 19.6464 8.93934 19.0607L0.93934 11.0607C0.353555 10.4749 0.353555 9.52513 0.939341 8.93934L8.93934 0.93934C9.52513 0.353554 10.4749 0.353554 11.0607 0.939341C11.6464 1.52513 11.6464 2.47487 11.0607 3.06066L4.12132 10L11.0607 16.9393C11.6464 17.5251 11.6464 18.4749 11.0607 19.0607Z"/></svg></button>',

						responsive: [
							{
								breakpoint: 1025,
								settings: {
									slidesToShow: 2,
									slidesToScroll: 1
								}
							},
							{
								breakpoint: 480,
								settings: {
									slidesToShow: 1,
									slidesToScroll: 1
								}
							}
						]
					});
					if (wpstream_theme.is_rtl === '1') {
						jQuery(this).slick('slickSetOption', 'rtl', true, true);
						jQuery(this).slick('slidesToScroll', '-1');
					}
				});
			</script>
			<?php

		endif;
	}
}
