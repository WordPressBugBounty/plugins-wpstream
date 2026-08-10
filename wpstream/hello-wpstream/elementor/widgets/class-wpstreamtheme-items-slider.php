<?php
/**
 * Item slider
 * Elementor widget rendering a horizontal carousel ("slider") of WpStream
 * items - free VOD, free live channels, free bundles, or WooCommerce products.
 * It registers the editor controls (content, filters, arrow colours/geometry),
 * maps chosen settings to shortcode-style attributes, and delegates the markup
 * to `wpestream_theme_slider_items()`. In edit mode it also boots the slick carousel.
 *
 * @package wpstream-theme
 */

// Elementor base class plus the control/typography/border/shadow helpers used below.
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
		// Unique machine id Elementor stores this widget under.
		return 'WpStreamTheme_Items_Slider';
	}

	/**
	 * Get categories
	 *
	 * @return array Panel category slug(s) this widget is grouped under.
	 */
	public function get_categories() {
		// Show this widget under the custom hello-wpstream panel category.
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
		// Label shown on the widget tile in the editor.
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
		// Elementor icon-font class for the widget tile.
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
		// Front-end carousel script this widget needs enqueued.
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
		// Collects the reshaped value => label pairs.
		$output = array();
		if ( is_array( $input ) ) {
			// Re-key each entry: term value becomes the key, label becomes the value.
			foreach ( $input as $key => $tax ) {
				$output[ $tax['value'] ] = $tax['label'];
			}
		}
		// Return the value => label map (empty when input is not an array).
		return $output;
	}


	/**
	 * Register control
	 * Builds the Content, Filters and Style (arrow colour + geometry) sections.
	 * Runs when Elementor renders this widget's settings panel.
	 */
	protected function register_controls() {

		// Scratch array for taxonomy data.
		$taxonomy_data = array();
		// Fetch the registered WpStream taxonomies keyed by taxonomy name.
		$available_tax = wpstream_return_taxonomy_array();
		// Drop post tags - not offered as a slider filter.
		unset( $available_tax['post_tag'] );

		// Convert each taxonomy's terms into SELECT2 value => label options.
		foreach ( $available_tax as $taxonoy_name => $post_types ) :
			// Raw term choices for this taxonomy.
			$temp_taxonomy_values           = wpstream_theme_generate_category_values( $taxonoy_name );
			// Normalise to the value => label shape.
			$temp_taxonomy_values           = $this->elementor_transform( $temp_taxonomy_values );
			// Store the normalised options back under the taxonomy name.
			$available_tax[ $taxonoy_name ] = $temp_taxonomy_values;

		endforeach;


		// Arrow placement choices: overlaid on top or on the sides.
		$arrow_type         =   array('top'=>'top','sideways'=>'sideways');
		// Content types the slider can display.
		$wpstream_items_array= array( 
			'wpstream_product_vod'	=>	esc_html__('Free VOD','hello-wpstream'),
			'wpstream_product'		=>	esc_html__('Free Live Channels','hello-wpstream'),		
			'wpstream_bundles'		=>	esc_html__('Free Video Bundles','hello-wpstream'),
			'product'				=>	esc_html__('WooCommerce Products','hello-wpstream'),
	
		);

		
		// Sort-order options from the theme helper (when available).
		$sort_options = array();
		if ( function_exists( 'wstream_sort_options_array' ) ) {
			$sort_options = wstream_sort_options_array();
		}
		
		// === Content section: item type, counts, sort, card style ===
		$this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'hello-wpstream'),
            ]
        );

		// Control: which content type(s) feed the slider.
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

		// Control: where the navigation arrows sit (top vs sideways).
		$this->add_control(
			'arrows_position',
			[
				'label' => __('Slider Navigation Arrows Position', 'hello-wpstream'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'sideways',
				'options' => $arrow_type
			]
		);

		// Control: total number of items to load.
		$this->add_control(
            'number',
            [
				'label' => __('No. of items', 'hello-wpstream'),
				'type' => Controls_Manager::TEXT,
				'default' => 5,
            ]
        );
			
		// Control: how many items are visible per row.
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


		// Control: auto-scroll interval in ms (0 disables autoplay).
		$this->add_control(
				'autoscroll',
				[
					'label' => __('Auto scroll period in ms (1sec = 1000)', 'hello-wpstream'),
					'type' => Controls_Manager::TEXT,
					'Label Block',
					'default' => '0',

				]
		);
		// Control: sort order for the queried items.
		$this->add_control(
			'sort_by',
			[
				'label' => __('Sort By ?', 'hello-wpstream'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 0,
				'options' => $sort_options
			]
		);

		// Control: which video-card template to render.
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
		// End Content section.
		$this->end_controls_section();





		/*
		 * Start filters
		 */
		/*
		* Start filters
		*/
		// === Filters section: restrict items by taxonomy term ===
		$this->start_controls_section(
			'filters_section',
			array(
				'label' => esc_html__( 'Filters', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		// Filter: standard WP categories.
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

		// Filter: WpStream media categories.
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

		// Filter: movie-rating terms.
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

		// Filter: actor terms.
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

		// End Filters section.
		$this->end_controls_section();


	

		// === Style: arrow colours (normal + hover) ===
		$this->start_controls_section(
			'size_section',
			array(
				'label' => esc_html__( 'Arrows Colors', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)			
		);

		// Style: arrow background colour.
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

		// Style: arrow icon colour.
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

		
	

		// Style: arrow hover background colour.
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

		// Style: arrow hover icon colour.
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
		

		// End arrow-colour section.
		$this->end_controls_section();

		// === Style: arrow geometry (radius, size, position, border, shadow) ===
		$this->start_controls_section(
			'arrow_style_section',
			array(
				'label' => esc_html__( 'Arrows Styles', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)			
		);

		// Style: arrow corner radius.
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

		// Style: vertical offset of the arrows.
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


		// Style: arrow circle diameter.
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
		// Style: arrow glyph (svg) size.
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

		// Style: previous-arrow right offset (top layout only).
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

		// Style: arrow border thickness.
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

		// Style: arrow border colour.
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

		// Style: arrow drop-shadow group control.
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(), [
		'name' => 'box_shadow',
		'label' => esc_html__(' Shadow', 'hello-wpstream'),
		'selector' => '{{WRAPPER}} .slick-arrow',
			]
		);




		// End arrow-geometry section.
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
		// Build a comma-separated string from the selected term values.
		$output = '';
		// Only process a non-empty array of values.
		if ( !empty($input) && is_array($input)) {
			// Track the total count so we can avoid a trailing comma.
			$num_items = count( $input );
			$i         = 0;

			// Append each value, comma-separating all but the last.
			foreach ( $input as $key => $value ) {
				$output .= $value;
				if ( ++$i !== $num_items ) {
					$output .= ', ';
				}
			}
		}
		// Return the joined "a, b, c" string.
		return $output;
	}


	/**
	 * Render
	 * Maps editor settings to attributes, prints the slider markup via
	 * `wpestream_theme_slider_items()`, and (in edit mode) boots slick.
	 */
	protected function render() {
		// Pull the resolved control values for this widget instance.
		$settings = $this->get_settings_for_display();

		// Copy the simple scalar settings into the attributes array.
		$attributes['type']                  = $settings['type'];
		$attributes['arrows_position']       = $settings['arrows_position'];
		$attributes['number']                = $settings['number'];
		$attributes['rownumber']             = $settings['rownumber'];
		$attributes['autoscroll']            = $settings['autoscroll'];
		$attributes['sort_by']               = $settings['sort_by'];
		$attributes['video_card'] 			 = $settings['video_card'];

		// Flatten the multi-select taxonomy filters into comma-separated lists.
		$attributes['category_ids']          = $this->wpstream_send_to_shortcode( $settings['category_ids'] );
		$attributes['wpstream_category_ids'] = $this->wpstream_send_to_shortcode( $settings['wpstream_category_ids'] );
		$attributes['movie_ratings_ids']     = $this->wpstream_send_to_shortcode( $settings['movie_ratings_ids'] );
		$attributes['actors_ids']            = $this->wpstream_send_to_shortcode( $settings['actors_ids'] );


		// Flag so the helper knows it is rendering inside Elementor.
		$attributes['is_elementor']          = true;

		// Unique DOM id for this carousel instance.
		$slider_id                        = 'video_slider_carousel_elementor_v1_' . wp_rand( 1, 99999 );
		// Build the slider markup from the assembled attributes.
		$slider_data                      = wpestream_theme_slider_items( $attributes, $slider_id );

		// Output the markup (helper escapes internally; phpcs ignore below).
		print trim( $slider_data); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// In the editor preview, initialise slick so the carousel is live.
		if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) :
			?>
			<script>

				// For each slider on the page, initialise a slick carousel.
				jQuery('.wpstream-item-list-slider').each(function () {
					// Read items-per-row and autoplay period from data attributes.
					var items = jQuery(this).attr('data-items-per-row');
					var auto = parseInt(jQuery(this).attr('data-auto'));
					// Build the carousel: custom arrows and responsive breakpoints.
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
					// Flip the carousel direction for RTL locales.
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
