<?php
/**
 * Class lust by id
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
 * List items by id
 */
class WpStreamTheme_Testimonial_Slider extends Widget_Base {
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
		return 'WpStreamTheme_Testimonial_Slider';
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
		return __( 'Testimonial slider', 'hello-wpstream' );
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

        protected function register_controls() {

        
        
        $this->start_controls_section(
            'content_section', [
            'label' => esc_html__('Content', 'hello-wpstream'),
                ]
        );
        
        $repeater = new Repeater();


        $repeater->add_control(
            'testimonial_title', [
            'label' => esc_html__('Title', 'hello-wpstream'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
                ]
        );
        $repeater->add_control(
                'testimonial_name', [
            'label' => esc_html__('Person Name', 'hello-wpstream'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
                ]
        );
        
        $repeater->add_control(
                'testimonial_job', [
            'label' => esc_html__('Person Position', 'hello-wpstream'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
                ]
        );
          
    
        
        $repeater->add_control(
            'testimonial_text', [
            'label' => esc_html__('Testimonial Text', 'hello-wpstream'),
            'type' => \Elementor\Controls_Manager::WYSIWYG,

            'default' => '',
                ]
        );

        $repeater->add_control(
                'testimonial_image',
                [
                        'label' => __( 'Choose Image', 'hello-wpstream' ),
                        'type' => \Elementor\Controls_Manager::MEDIA,
                        'default' => [
                                'url' => \Elementor\Utils::get_placeholder_image_src(),
                        ],
                ]
        );

        
        
        $this->add_control(
			'list',
			[
				'label' => __( 'Repeater List', 'hello-wpstream' ),
				'type' => \Elementor\Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'testimonial_title' => __( 'Testimonial #1', 'hello-wpstream' ),
						'testimonial_text' => __( 'Testimonial content. Click the edit button to change this text.', 'hello-wpstream' ),
					],
					[
						'testimonial_title' => __( 'Testimonial #2', 'hello-wpstream' ),
						'testimonial_text' => __( 'Testimonial content. Click the edit button to change this text.', 'hello-wpstream'),
					],
				],
				'title_field' => '{{{ testimonial_title }}}',
			]
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
        
        
        
        
        
        /*
        * -------------------------------------------------------------------------------------------------
        * Start typography section
       */
        $this->start_controls_section(
            'typography_section', [
            'label' => esc_html__('Style', 'hello-wpstream'),
            'tab' => Controls_Manager::TAB_STYLE,
                ]
        );
        $this->add_control(
                'hide_image',
                [
                    'label' => esc_html__('Hide image?', 'hello-wpstream'),
                    'type' => Controls_Manager::SWITCHER,
                    'label_on' => esc_html__('Yes', 'hello-wpstream'),
                    'label_off' => esc_html__('No', 'hello-wpstream'),
                    'return_value' => 'none',
                    'default' => '',
                    'selectors' => [
                        '{{WRAPPER}}  .testimonal_image' => 'display: {{VALUE}};',
                      
                    ],
                ]
        );
          $this->add_control(
                'hide_border',
                [
                    'label' => esc_html__('Hide Border?', 'hello-wpstream'),
                    'type' => Controls_Manager::SWITCHER,
                    'label_on' => esc_html__('Yes', 'hello-wpstream'),
                    'label_off' => esc_html__('No', 'hello-wpstream'),
                    'return_value' => 'none',
                    'default' => '',
                    'selectors' => [
                        '{{WRAPPER}}  .wpstream_testimonial_slider ' => 'border: {{VALUE}};',
                      
                    ],
                ]
        );

         $this->add_group_control(
                Group_Control_Typography::get_type(), [
            'name' => 'testimonial_content',
            'label' => esc_html__('Content Typography', 'hello-wpstream'),
'global' => [
            'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
        ],
            'selector' => '{{WRAPPER}} .item_testimonial_text ',
                ]
        );


        $this->add_group_control(
                Group_Control_Typography::get_type(), [
            'name' => 'testimonial_title',
            'label' => esc_html__('Person Name Typography', 'hello-wpstream'),
'global' => [
            'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
        ],
            'selector' => '{{WRAPPER}} .item_testimonial_name ',
                ]
        );
        
        
      
            
        $this->add_group_control(
            Group_Control_Typography::get_type(), [
            'name' => 'testimonial_postion',
            'label' => esc_html__('Person position', 'hello-wpstream'),
'global' => [
            'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
        ],
            'selector' => '{{WRAPPER}} .item_testimonial_job',
                ]
        );
              
              
           
                      
                      
	$this->add_responsive_control(
            'item_width',
            [
                        'label' => esc_html__('Item width', 'hello-wpstream'),
                        'type' => Controls_Manager::SLIDER,
                        'range' => [
                                        'px' => [
                                                        'min' => 300,
                                                        'max' => 2000,
                                        ],
                        ],
                        'devices' => [ 'desktop', 'tablet', 'mobile' ],
                        'desktop_default' => [
                                        'size' => '',
                                        'unit' => 'px',
                        ],
                        'tablet_default' => [
                                        'size' => '',
                                        'unit' => 'px',
                        ],
                        'mobile_default' => [
                                        'size' => '',
                                        'unit' => 'px',
                        ],
                        'selectors' => [
                                '{{WRAPPER}} .wpstream_theme_testimonial_slider_wrapper_widget' => 'width: {{SIZE}}{{UNIT}}',
                            
                                ],
                    ]
            );

        
        $this->end_controls_section();

       

        
         /*
         * -------------------------------------------------------------------------------------------------
         * Start color section
         */
        $this->start_controls_section(
                'section_grid_colors', [
            'label' => esc_html__('Colors', 'hello-wpstream'),
            'tab' => Controls_Manager::TAB_STYLE,
                ]
        );

        $this->add_control(
                'unit_backgorund', [
            'label' => esc_html__('Background', 'hello-wpstream'),
            'type' => Controls_Manager::COLOR,
            'default' => '',
            'selectors' => [
                '{{WRAPPER}} .wpstream_theme_testimonial_slider_wrapper_widget ' => 'background-color: {{VALUE}}',
            ],
                ]
        );

        $this->add_control(
                'content_color', [
            'label' => esc_html__('Content Color', 'hello-wpstream'),
            'type' => Controls_Manager::COLOR,
            'default' => '',
            'selectors' => [
                '{{WRAPPER}} .item_testimonial_text' => 'color: {{VALUE}}',
            ],
                ]
        );

     
   

        $this->add_control(
                'name_color', [
            'label' => esc_html__('Name Color', 'hello-wpstream'),
            'type' => Controls_Manager::COLOR,
            'default' => '',
            'selectors' => [
                '{{WRAPPER}} .item_testimonial_name' => 'color: {{VALUE}}',
            ],
                ]
        );

        $this->add_control(
                'item_testimonial_job', [
            'label' => esc_html__('Position Color', 'hello-wpstream'),
            'type' => Controls_Manager::COLOR,
            'default' => '',
            'selectors' => [
                '{{WRAPPER}} .item_testimonial_job' => 'color: {{VALUE}}',
            ],
                ]
        );


       

        $this->end_controls_section();
        /*
         * -------------------------------------------------------------------------------------------------
         * End color section
         */
        
       
         $this->start_controls_section(
                'arrow_section', [
            'label' => esc_html__('Arrows Styles & Colors', 'hello-wpstream'),
            'tab' => Controls_Manager::TAB_STYLE,
                ]
        );
        
           $this->add_responsive_control(
            'arrow_border_radius',
            [
                'label' => esc_html__( 'Border Radius', 'hello-wpstream' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%' ],
                'selectors' => [
                    '{{WRAPPER}} .wpstream_theme_testimonial_slider_wrapper_widget  .slick-prev.slick-arrow' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .wpstream_theme_testimonial_slider_wrapper_widget  .slick-next.slick-arrow' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                  ],
            ]
        );
        
            $this->add_control(
                   'arrow_color',
                   [
                       'label'     => esc_html__( 'Arrow Color', 'hello-wpstream' ),
                       'type'      => Controls_Manager::COLOR,
                       'default'   => '',
                       'selectors' => [
                           '{{WRAPPER}} .wpstream_theme_testimonial_slider_wrapper_widget .slick-prev.slick-arrow' => 'color: {{VALUE}}',
                           '{{WRAPPER}} .wpstream_theme_testimonial_slider_wrapper_widget .slick-next.slick-arrow' => 'color: {{VALUE}}',
                       ],
                   ]
            );
            
              $this->add_control(
                   'arrow_bck_color',
                   [
                       'label'     => esc_html__( 'Arrow Background Color', 'hello-wpstream' ),
                       'type'      => Controls_Manager::COLOR,
                       'default'   => '',
                       'selectors' => [
                           '{{WRAPPER}} .wpstream_theme_testimonial_slider_wrapper_widget .slick-prev.slick-arrow' => 'background-color: {{VALUE}}',
                           '{{WRAPPER}} .wpstream_theme_testimonial_slider_wrapper_widget .slick-next.slick-arrow' => 'background-color: {{VALUE}}',
                       ],
                   ]
            );
           
           
            
            $this->add_control(
                   'arrow_color_hover',
                   [
                       'label'     => esc_html__( 'Arrow Color Hover', 'hello-wpstream' ),
                       'type'      => Controls_Manager::COLOR,
                       'default'   => '',
                       'selectors' => [
                            '{{WRAPPER}} .wpstream_theme_testimonial_slider_wrapper_widget .slick-prev.slick-arrow:hover' => 'color: {{VALUE}}',
                            '{{WRAPPER}} .wpstream_theme_testimonial_slider_wrapper_widget .slick-next.slick-arrow:hover' => 'color: {{VALUE}}',
                       ],
                   ]
               );
            
             $this->add_control(
                   'arrow_bck_color_hover',
                   [
                       'label'     => esc_html__( 'Arrow Background Color Hover', 'hello-wpstream' ),
                       'type'      => Controls_Manager::COLOR,
                       'default'   => '',
                       'selectors' => [
                            '{{WRAPPER}} .wpstream_theme_testimonial_slider_wrapper_widget .slick-prev.slick-arrow:hover' => 'background-color: {{VALUE}}',
                            '{{WRAPPER}} .wpstream_theme_testimonial_slider_wrapper_widget .slick-next.slick-arrow:hover' => 'background-color: {{VALUE}}',
                       ],
                   ]
               );

             
       


           $this->end_controls_section();
        
        
        /*
         * -------------------------------------------------------------------------------------------------
         * Start shadow section
         */
        $this->start_controls_section(
                'section_grid_box_shadow', [
            'label' => esc_html__('Box Shadow', 'hello-wpstream'),
            'tab' => Controls_Manager::TAB_STYLE,
                ]
        );
        $this->add_group_control(
                Group_Control_Box_Shadow::get_type(), [
            'name' => 'box_shadow',
            'label' => esc_html__('Box Shadow', 'hello-wpstream'),
            'selector' => '{{WRAPPER}} .wpstream_testimonial_slider ',
                ]
        );

        $this->end_controls_section();
        /*
         * -------------------------------------------------------------------------------------------------
         * End shadow section
         */
     
    }

    protected function render() {
        global $post;
        $settings = $this->get_settings_for_display();

        $slider_id                        = 'categories_slider_carousel_elementor_v1_' . wp_rand( 1, 99999 );
        print   wpstream_testimonial_slider( $settings,	$slider_id );
        
        
        if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) :
			?>
			<script>

				jQuery('.wpstream_testimonial_slider').each(function () {
					var items = 1;
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
