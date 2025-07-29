<?php
/**
 * Item slider
 *
 * @package wpstream-theme
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Scheme_Color;
use Elementor\Group_Control_Typography;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Item slider class
 */
class WpStreamTheme_Items_Slider extends Widget_Base {
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
		return 'WpStreamTheme_Items_Slider';
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
		return __( 'Items Slider', 'hello-wpstream' );
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
		return array( 'owl_carousel' );
	}

	/**
	 * Transform input data into a specific format.
	 *
	 * This method converts input data into an associative array where the keys are values from the input array and the values are corresponding labels.
	 *
	 * @param array $input An array containing input data to transform.
	 *
	 * @return array The transformed data as an associative array.
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

		$taxonomy_data = array();
		$available_tax = wpstream_return_taxonomy_array();
		unset( $available_tax['post_tag'] );

		foreach ( $available_tax as $taxonoy_name => $post_types ) :
			$temp_taxonomy_values           = wpstream_theme_generate_category_values( $taxonoy_name );
			$temp_taxonomy_values           = $this->elementor_transform( $temp_taxonomy_values );
			$available_tax[ $taxonoy_name ] = $temp_taxonomy_values;

		endforeach;


		$arrow_type         =   array('top'=>'top','sideways'=>'sideways');
		$wpstream_items_array= array( 
			'wpstream_product_vod'	=>	esc_html__('Free VOD','hello-wpstream'),
			'wpstream_product'		=>	esc_html__('Free Live Channels','hello-wpstream'),		
			'wpstream_bundles'		=>	esc_html__('Free Video Bundles','hello-wpstream'),
			'product'				=>	esc_html__('WooCommerce Products','hello-wpstream'),
	
		);

		
		$sort_options = array();
		if ( function_exists( 'wstream_sort_options_array' ) ) {
			$sort_options = wstream_sort_options_array();
		}
		
		$this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'hello-wpstream'),
            ]
        );

		$this->add_control(
			'type',
			[
				'label' => __('What type of items', 'hello-wpstream'),
				'label_block'=>true,
				'type' => \Elementor\Controls_Manager::SELECT2,
				'default' => 'posts',
				'multiple'=> true,
				'options' => $wpstream_items_array
			]
			
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
            'number',
            [
				'label' => __('No. of items', 'hello-wpstream'),
				'type' => Controls_Manager::TEXT,
				'default' => 5,
            ]
        );
			
		$this->add_control(
			'rownumber',
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
				'autoscroll',
				[
					'label' => __('Auto scroll period in ms (1sec = 1000)', 'hello-wpstream'),
					'type' => Controls_Manager::TEXT,
					'Label Block',
					'default' => '0',

				]
		);
		$this->add_control(
			'sort_by',
			[
				'label' => __('Sort By ?', 'hello-wpstream'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 0,
				'options' => $sort_options
			]
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





		/*
		 * Start filters
		 */
		/*
		* Start filters
		*/
		$this->start_controls_section(
			'filters_section',
			array(
				'label' => esc_html__( 'Filters', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'category_ids',
			array(
				'label'       => __( 'List of categories', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => '',
				'options'     => $available_tax['category'],
			)
		);

		$this->add_control(
			'wpstream_category_ids',
			array(
				'label'       => __( 'List of Media Category', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => '',
				'options'     => $available_tax['wpstream_category'],
			)
		);

		$this->add_control(
			'movie_ratings_ids',
			array(
				'label'       => __( 'List of Movie Ratings', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => '',
				'options'     => $available_tax['wpstream_movie_rating'],
			)
		);

		$this->add_control(
			'actors_ids',
			array(
				'label'       => __( 'List of Actors', 'hello-wpstream' ),
				'label_block' => true,
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'default'     => '',
				'options'     => $available_tax['wpstream_actors'],
			)
		);

		$this->end_controls_section();


	

		$this->start_controls_section(
			'size_section',
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
		'default' => [
			'size' =>-20,
		],
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
		'default' => [
			'size' => 55,
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
		'name' => 'box_shadow',
		'label' => esc_html__(' Shadow', 'hello-wpstream'),
		'selector' => '{{WRAPPER}} .slick-arrow',
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
	 * @param array $input The input data to be rendered.
	 *
	 * @return string The HTML output generated from the input data.
	 */
	public function wpstream_send_to_shortcode( $input ) {
		$output = '';
		if ( !empty($input) && is_array($input)) {
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

		$attributes['type']                  = $settings['type'];
		$attributes['arrows_position']       = $settings['arrows_position'];
		$attributes['number']                = $settings['number'];
		$attributes['rownumber']             = $settings['rownumber'];
		$attributes['autoscroll']            = $settings['autoscroll'];
		$attributes['sort_by']               = $settings['sort_by'];
		$attributes['video_card'] 			 = $settings['video_card'];

		$attributes['category_ids']          = $this->wpstream_send_to_shortcode( $settings['category_ids'] );
		$attributes['wpstream_category_ids'] = $this->wpstream_send_to_shortcode( $settings['wpstream_category_ids'] );
		$attributes['movie_ratings_ids']     = $this->wpstream_send_to_shortcode( $settings['movie_ratings_ids'] );
		$attributes['actors_ids']            = $this->wpstream_send_to_shortcode( $settings['actors_ids'] );


		$attributes['is_elementor']          = true;

		$slider_id                        = 'video_slider_carousel_elementor_v1_' . wp_rand( 1, 99999 );
		$slider_data                      = wpestream_theme_slider_items( $attributes, $slider_id );

		print trim( $slider_data); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) :
			?>
			<script>

				jQuery('.wpstream-item-list-slider').each(function () {
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
