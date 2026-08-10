<?php
/**
 * Testimonial Slider Elementor widget.
 *
 * Registers the "Testimonial slider" widget for the hello-wpstream theme's
 * Elementor category. The widget lets an editor build a repeatable list of
 * testimonials (title, person name, position, WYSIWYG text and an image) and
 * exposes Style-tab controls for typography, colors, slider arrows and box
 * shadow. On the frontend it hands the collected settings to the
 * `wpstream_testimonial_slider()` template helper, which emits the slick-based
 * carousel markup; in the Elementor editor it additionally prints an inline
 * script that initialises the slick slider so the preview animates.
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
 * Testimonial slider widget class.
 *
 * Extends Elementor's base widget to provide the testimonial carousel.
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
		// Unique machine name Elementor uses to identify this widget type.
		return 'WpStreamTheme_Testimonial_Slider';
	}

	/**
	 * Retrieve the widget categories.
	 *
	 * @return array Elementor panel category slugs this widget belongs to.
	 */
	public function get_categories() {
		// Group the widget under the theme's own "hello-wpstream" panel category.
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
		// Human-readable label shown in the Elementor widget panel (translatable).
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
		// Elementor icon-font class used as the widget's panel icon.
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
		// No enqueued script handles are required for this widget.
		return array( '' );
	}

	/**
	 * Flatten an Elementor label/value list into an associative map.
	 *
	 * Converts a numerically-indexed array of `array( 'value' => ..., 'label' => ... )`
	 * entries into a `value => label` map suitable for a SELECT control's options.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param array $input The input data containing the labels and values.
	 *
	 * @return array The transformed output array keyed by value.
	 */
	public function elementor_transform( $input ) {
		// Accumulator for the value => label pairs.
		$output = array();
		// Only iterate when we were handed an array to transform.
		if ( is_array( $input ) ) {
			// Re-key each entry so its 'value' becomes the key and 'label' the value.
			foreach ( $input as $key => $tax ) {
				$output[ $tax['value'] ] = $tax['label'];
			}
		}
		return $output;
	}


	/**
	 * Join a list of values into a comma-separated string.
	 *
	 * Used to serialise multi-select control values into the comma list the
	 * downstream shortcode/template helper expects.
	 *
	 * @param array $input List of values to concatenate.
	 *
	 * @return string Comma-separated string of the input values.
	 */
	public function wpstream_send_to_shortcode( $input ) {
		// Start with an empty result string.
		$output = '';
		// Nothing to do when the input is empty.
		if ( !empty($input) ) {
			// Total number of values, used to decide where separators go.
			$num_items = count( $input );
			// Running counter of processed items.
			$i         = 0;

			// Append each value, inserting ", " between items but not after the last.
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
         * Register the widget's Elementor controls.
         *
         * Builds the Content section (a repeater of testimonial entries plus an
         * autoscroll period) and several Style-tab sections: typography,
         * colors, slider arrow styling and box shadow.
         *
         * @since 1.0.0
         * @access protected
         *
         * @return void
         */
        protected function register_controls() {



        // ---- Content section: the list of testimonials and slider timing. ----
        $this->start_controls_section(
            'content_section', [
            'label' => esc_html__('Content', 'hello-wpstream'),
                ]
        );

        // Repeater instance whose fields describe a single testimonial entry.
        $repeater = new Repeater();


        // Repeater field: the testimonial's title/heading.
        $repeater->add_control(
            'testimonial_title', [
            'label' => esc_html__('Title', 'hello-wpstream'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
                ]
        );
        // Repeater field: the name of the person giving the testimonial.
        $repeater->add_control(
                'testimonial_name', [
            'label' => esc_html__('Person Name', 'hello-wpstream'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
                ]
        );

        // Repeater field: the person's job title / position.
        $repeater->add_control(
                'testimonial_job', [
            'label' => esc_html__('Person Position', 'hello-wpstream'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
                ]
        );



        // Repeater field: the testimonial body copy (rich text editor).
        $repeater->add_control(
            'testimonial_text', [
            'label' => esc_html__('Testimonial Text', 'hello-wpstream'),
            'type' => \Elementor\Controls_Manager::WYSIWYG,

            'default' => '',
                ]
        );

        // Repeater field: the person's photo, defaulting to Elementor's placeholder.
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

        
        
        // Main repeater control that holds the collection of testimonial entries,
        // seeded with two example testimonials by default.
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
        
        
        
        
        
        // Autoscroll interval in milliseconds (0 disables automatic sliding).
        $this->add_control(
				'autoscroll',
				[
					'label' => __('Auto scroll period in ms (1sec = 1000)', 'hello-wpstream'),
					'type' => Controls_Manager::TEXT,
					'Label Block',
					'default' => '0',

				]
		);




        // ---- End Content section. ----
        $this->end_controls_section();
        
        
        
        
        
        /*
        * -------------------------------------------------------------------------------------------------
        * Start typography section
       */
        // ---- Style section: typography, image/border visibility, item width. ----
        $this->start_controls_section(
            'typography_section', [
            'label' => esc_html__('Style', 'hello-wpstream'),
            'tab' => Controls_Manager::TAB_STYLE,
                ]
        );
        // Switcher: when on, hides the testimonial image via display:none.
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
          // Switcher: when on, removes the slider's border via border:none.
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

         // Typography group for the testimonial body text.
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


        // Typography group for the person's name.
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
        
        
      
            
        // Typography group for the person's position/job line.
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
              
              
           
                      
                      
	// Responsive slider item width, tunable per device (desktop/tablet/mobile).
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

        
        // ---- End Style/typography section. ----
        $this->end_controls_section();




         /*
         * -------------------------------------------------------------------------------------------------
         * Start color section
         */
        // ---- Colors section: background and per-text-element color pickers. ----
        $this->start_controls_section(
                'section_grid_colors', [
            'label' => esc_html__('Colors', 'hello-wpstream'),
            'tab' => Controls_Manager::TAB_STYLE,
                ]
        );

        // Color: the slider wrapper's background.
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

        // Color: the testimonial body text.
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

     
   

        // Color: the person's name text.
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

        // Color: the person's position/job text.
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
        
       
         // ---- Arrows section: slick prev/next arrow radius and colors. ----
         $this->start_controls_section(
                'arrow_section', [
            'label' => esc_html__('Arrows Styles & Colors', 'hello-wpstream'),
            'tab' => Controls_Manager::TAB_STYLE,
                ]
        );

           // Responsive border radius for both prev and next slick arrows.
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
        
            // Arrow glyph color in the normal state.
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
            
              // Arrow background color in the normal state.
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
           
           
            
            // Arrow glyph color on hover.
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
            
             // Arrow background color on hover.
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

             
       


           // ---- End Arrows section. ----
           $this->end_controls_section();


        /*
         * -------------------------------------------------------------------------------------------------
         * Start shadow section
         */
        // ---- Box Shadow section: drop shadow applied to the slider box. ----
        $this->start_controls_section(
                'section_grid_box_shadow', [
            'label' => esc_html__('Box Shadow', 'hello-wpstream'),
            'tab' => Controls_Manager::TAB_STYLE,
                ]
        );
        // Box-shadow group control targeting the slider wrapper.
        $this->add_group_control(
                Group_Control_Box_Shadow::get_type(), [
            'name' => 'box_shadow',
            'label' => esc_html__('Box Shadow', 'hello-wpstream'),
            'selector' => '{{WRAPPER}} .wpstream_testimonial_slider ',
                ]
        );

        // ---- End Box Shadow section. ----
        $this->end_controls_section();
        /*
         * -------------------------------------------------------------------------------------------------
         * End shadow section
         */

    }

    /**
     * Render the widget on the frontend (and in the editor preview).
     *
     * Pulls the saved control values, generates a unique slider DOM id, and
     * prints the carousel markup produced by the `wpstream_testimonial_slider()`
     * template helper. When rendering inside the Elementor editor it also emits
     * an inline script that boots the slick slider so the preview animates.
     *
     * @access protected
     *
     * @return void
     */
    protected function render() {
        // Current post context (available to the template helper if needed).
        global $post;
        // Resolved control values ready for display/output.
        $settings = $this->get_settings_for_display();

        // Unique DOM id so multiple sliders on one page don't collide.
        $slider_id                        = 'categories_slider_carousel_elementor_v1_' . wp_rand( 1, 99999 );
        // Emit the testimonial carousel markup built by the template helper.
        print   wpstream_testimonial_slider( $settings,	$slider_id );


        // Only inside the Elementor editor: initialise slick so the preview slides.
        if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) :
			?>
			<!-- Editor-only slick initialiser so the testimonial slider animates in the preview. -->
			<script>

				// Boot slick on each testimonial slider instance in the preview.
				jQuery('.wpstream_testimonial_slider').each(function () {
					// This slider shows a single testimonial per view.
					var items = 1;
					// Autoplay period read from the element's data-auto attribute.
					var auto = parseInt(jQuery(this).attr('data-auto'));
					// Initialise the slick carousel with the desired options.
					var slick = jQuery(this).slick({
						infinite: true,
						slidesToShow: items,
						slidesToScroll: 1,
						dots: false,
						nextArrow:'<button class="slick-next slick-arrow 333 " aria-label="Next" type="button" style=""><svg width="12" height="20" viewBox="0 0 12 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.93934 0.93934C1.52513 0.353553 2.47487 0.353553 3.06066 0.93934L11.0607 8.93934C11.6464 9.52513 11.6464 10.4749 11.0607 11.0607L3.06066 19.0607C2.47487 19.6464 1.52513 19.6464 0.93934 19.0607C0.353553 18.4749 0.353553 17.5251 0.93934 16.9393L7.87868 10L0.93934 3.06066C0.353553 2.47487 0.353553 1.52513 0.93934 0.93934Z"/></svg></button>',
            			prevArrow:'<button class="slick-prev slick-arrow 222 " aria-label="Next" type="button" style=""><svg width="12" height="20" viewBox="0 0 12 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M11.0607 19.0607C10.4749 19.6464 9.52513 19.6464 8.93934 19.0607L0.93934 11.0607C0.353555 10.4749 0.353555 9.52513 0.939341 8.93934L8.93934 0.93934C9.52513 0.353554 10.4749 0.353554 11.0607 0.939341C11.6464 1.52513 11.6464 2.47487 11.0607 3.06066L4.12132 10L11.0607 16.9393C11.6464 17.5251 11.6464 18.4749 11.0607 19.0607Z"/></svg></button>',

						// Responsive overrides: fewer slides on tablet and mobile widths.
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
					// For right-to-left sites, flip the slider direction after init.
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
