<?php
/**
 * Blog Posts Slider Elementor widget.
 *
 * Registers the "Blog Posts Slider" widget for the hello-wpstream theme's
 * Elementor category. Its controls choose how many posts to show, per-row
 * count, autoscroll timing, sort order, arrow position and category filtering,
 * plus Style-tab controls for the slider navigation arrows. On render the
 * collected settings are marshalled into a shortcode-style attributes array and
 * handed to the `wpestream_blog_post_slider_items()` template helper; in the
 * Elementor editor an inline script additionally boots the slick carousel.
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
 * Blog posts slider widget class.
 *
 * Extends Elementor's base widget to provide the blog post carousel.
 */
class WpStreamTheme_Blog_Post_Slider extends Widget_Base {
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
		return 'WpStreamTheme_Blog_Post_Slider';
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
		return __( 'Blog Posts Slider', 'hello-wpstream' );
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
		// Enqueue the owl_carousel script handle whenever this widget is used.
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
	 * Register the widget's Elementor controls.
	 *
	 * Builds the Content section (count, per-row, autoscroll, sort, arrow
	 * position), a Filters section (category multi-select) and Style-tab
	 * sections for arrow colors and arrow geometry/shadow. Category options are
	 * gathered up-front from the site's taxonomies.
	 *
	 * @access protected
	 *
	 * @return void
	 */
	protected function register_controls() {

		// Holds resolved taxonomy option lists (populated in the loop below).
		$taxonomy_data = array();
		// Taxonomies to expose as filters, mapped to the post types they apply to.
		$available_tax = array(
            'category'              => array('post')
        );



		// For each taxonomy, build its value/label options and store them back.
		foreach ( $available_tax as $taxonoy_name => $post_types ) :
			// Raw term list for this taxonomy as label/value pairs.
			$temp_taxonomy_values           = wpstream_theme_generate_category_values( $taxonoy_name );
			// Flatten into a value => label map for the SELECT2 control.
			$temp_taxonomy_values           = $this->elementor_transform( $temp_taxonomy_values );
			// Replace the post-type list with the ready-to-use options map.
			$available_tax[ $taxonoy_name ] = $temp_taxonomy_values;

		endforeach;


		// Allowed slider arrow placements: above the slider or to the sides.
		$arrow_type         =   array('top'=>'top','sideways'=>'sideways');



		// Sort options for the query, if the helper providing them exists.
		$sort_options = array();
		if ( function_exists( 'wstream_sort_options_array' ) ) {
			$sort_options = wstream_sort_options_array();
		}

		// ---- Content section: post count, layout, timing, sort, arrows. ----
		$this->start_controls_section(
            'section_content',
            [
                'label' => __('Content', 'hello-wpstream'),
            ]
        );



		// Where the navigation arrows sit relative to the slider.
		$this->add_control(
			'arrows_position',
			[
				'label' => __('Slider Navigation Arrows Position', 'hello-wpstream'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'top',
				'options' => $arrow_type
			]
		);

		// Total number of posts to pull into the slider.
		$this->add_control(
            'number',
            [
				'label' => __('No. of items', 'hello-wpstream'),
				'type' => Controls_Manager::TEXT,
				'default' => 5,
            ]
        );

		// How many posts are shown per row/slide (2-6).
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
					6=>6
				),
				'default' => 3,
			)
		);


		// Autoscroll period; empty disables automatic sliding.
		$this->add_control(
				'autoscroll',
				[
					'label' => __('Auto scroll period', 'hello-wpstream'),
					'type' => Controls_Manager::TEXT,
					'Label Block',
					'default' => '',

				]
		);
		// Ordering applied to the queried posts.
		$this->add_control(
			'sort_by',
			[
				'label' => __('Sort By ?', 'hello-wpstream'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 0,
				'options' => $sort_options
			]
		);


		// ---- End Content section. ----
		$this->end_controls_section();





		/*
		 * Start filters
		 */
		/*
		* Start filters
		*/
		// ---- Filters section: restrict the slider to chosen categories. ----
		$this->start_controls_section(
			'filters_section',
			array(
				'label' => esc_html__( 'Filters', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		// Multi-select of category ids used to filter the queried posts.
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



		// ---- End Filters section. ----
		$this->end_controls_section();

		// ---- Style section: navigation arrow colors (normal + hover). ----
		$this->start_controls_section(
			'size_section',
			array(
				'label' => esc_html__( 'Arrows Colors', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Arrow background color in the normal state.
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

		// Arrow glyph color in the normal state.
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




		// Arrow background color on hover.
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

		// Arrow glyph color on hover.
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


		// ---- End Arrow colors section. ----
		$this->end_controls_section();

		// ---- Style section: arrow geometry (radius, position, size, shadow). ----
		$this->start_controls_section(
			'arrow_style_section',
			array(
				'label' => esc_html__( 'Arrows Styles', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Corner radius of the arrow buttons.
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

		// Vertical offset (top) of the arrows relative to the slider.
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

		// Diameter of the circular arrow button (width and height).
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
		// Size of the arrow SVG glyph itself inside the button.
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
			'{{WRAPPER}} .slick-arrow svg' => 'width: {{SIZE}}{{UNIT}};height: {{SIZE}}{{UNIT}};',

		],
			]
		);

		// Horizontal (right) offset of the prev button, only for top-positioned arrows.
		$this->add_responsive_control(
			'arrow_margin_right', [
		'label' => esc_html__('Previous Button- Right Position ', 'hello-wpstream'),
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

		// Box-shadow group applied to the arrow buttons.
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(), [
		'name' => 'box_shadow',
		'label' => esc_html__(' Shadow', 'hello-wpstream'),
		'selector' => '{{WRAPPER}} .slick-arrow',
			]
		);



		// ---- End Arrow styles section. ----
		$this->end_controls_section();






	}

	/**
	 * Join a list of values into a comma-separated string.
	 *
	 * Used to serialise the multi-select category ids into the comma list the
	 * downstream slider template helper expects.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param array $input List of values to concatenate.
	 *
	 * @return string Comma-separated string of the input values.
	 */
	public function wpstream_send_to_shortcode( $input ) {
		// Start with an empty result string.
		$output = '';
		// Only proceed when we have a non-empty array to join.
		if ( !empty($input) && is_array($input)) {
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
	 * Render the widget on the frontend (and in the editor preview).
	 *
	 * Copies the resolved control values into a shortcode-style attributes
	 * array, generates a unique slider DOM id, and prints the carousel markup
	 * from `wpestream_blog_post_slider_items()`. Inside the Elementor editor it
	 * additionally emits an inline script that boots the slick slider so the
	 * preview animates.
	 *
	 * @access protected
	 *
	 * @return void
	 */
	protected function render() {
		// Resolved control values ready for display/output.
		$settings = $this->get_settings_for_display();

		//$attributes['arrows']                = $settings['arrows'];
		// Arrow placement (top/sideways).
		$attributes['arrows_position']       = $settings['arrows_position'];

		// Total number of posts requested.
		$attributes['number']       = $settings['number'];

		// Posts per row/slide.
		$attributes['rownumber']             = $settings['rownumber'];
		// Autoscroll period.
		$attributes['autoscroll']             = $settings['autoscroll'];
		// Sort order.
		$attributes['sort_by']               = $settings['sort_by'];

		// Category filter, flattened from the multi-select into a comma list.
		$attributes['category_ids']          = $this->wpstream_send_to_shortcode( $settings['category_ids'] );



		// Flag so the template helper knows it is running inside Elementor.
		$attributes['is_elementor']          = true;

		// Unique DOM id so multiple sliders on one page don't collide.
		$slider_id                        = 'post_slider_carousel_elementor_v1_' . wp_rand( 1, 99999 );
		// Build the slider markup from the collected attributes.
		$slider_data                      = wpestream_blog_post_slider_items( $attributes, $slider_id );

		// Output the (trimmed) slider markup.
		print trim( $slider_data); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Only inside the Elementor editor: initialise slick so the preview slides.
		if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) :
			?>
			<!-- Editor-only slick initialiser so the blog post slider animates in the preview. -->
			<script>

				// Boot slick on each blog post slider instance in the preview.
				jQuery('.wpstream-blog-post-slider').each(function () {
					// Slides per view, read from the element's data-items-per-row attribute.
					var items = jQuery(this).attr('data-items-per-row');
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
