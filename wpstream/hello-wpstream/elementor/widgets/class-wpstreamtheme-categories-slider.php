<?php
/**
 * Class categories slider
 *
 * Defines the "Categories Slider" Elementor widget for the hello-wpstream theme.
 * The widget lets an editor pick taxonomy terms and render them in a Slick
 * carousel, exposing controls for categories-per-row, a design style variant,
 * arrow position/auto-scroll, item sizing, typography/colors, arrow styling and
 * box-shadow. The frontend markup is produced by the theme helper
 * wpstream_theme_categories_slider(); an inline script re-initialises Slick in
 * the editor preview.
 *
 * @package wpstream-theme
 */

// Pull in the Elementor base class and the control/group-control helpers this widget relies on.
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
		// Unique internal identifier Elementor uses to reference this widget.
		return 'WpStreamTheme_Categories_Slider';
	}

	/**
	 * Get categories
	 *
	 * Elementor panel category (widget grouping) this widget is listed under.
	 *
	 * @return array Category slugs the widget belongs to.
	 */
	public function get_categories() {
		// Group this widget under the theme's own "hello-wpstream" panel section.
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
		// Human-readable, translatable label shown on the widget tile in the editor.
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
		// Elementor icon-font class used for the widget's tile icon.
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
		// No extra registered scripts are required for this widget (empty handle).
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
		// Reshape the taxonomy list into an Elementor-friendly value => label map.
		$output = array();
		// Guard against a non-array (e.g. empty/false) input before iterating.
		if ( is_array( $input ) ) {
			// Each entry carries a 'value' (term id/slug) and a human-readable 'label'.
			foreach ( $input as $key => $tax ) {
				// Key the output by the term value so it can be used as a SELECT2 option.
				$output[ $tax['value'] ] = $tax['label'];
			}
		}
		// Return the value => label map consumed by the place_list control.
		return $output;
	}

	/**
	 * Register controls
	 *
	 * Builds every control the widget exposes: the Content tab (categories, arrow
	 * position, per-row count, design style, auto-scroll) and the Style tab
	 * sections (item sizing, typography/colors, arrow colors, arrow styles and
	 * box shadow).
	 *
	 * @return void
	 */
	protected function register_controls() {
		// Fetch every available taxonomy term from the theme helper.
		$all_tax = wpstream_theme_return_all_taxomy_array();
		// Options for the arrow-position control: overlaid on top vs. on the sides.
		$arrow_type         =   array('top'=>'top','sideways'=>'sideways');
		// Convert the taxonomy list into the value => label map SELECT2 expects.
		$all_tax_elemetor = $this->elementor_transform( $all_tax );

		// --- Content tab: category selection, arrows, per-row, design, auto-scroll ---
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

		// Multi-select of taxonomy terms to display as slides.
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

		// Where the Slick navigation arrows sit: 'top' or 'sideways' (default sideways).
		$this->add_control(
			'arrows_position',
			[
				'label' => __('Slider Navigation Arrows Position', 'hello-wpstream'),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'sideways',
				'options' => $arrow_type
			]
		);

	

		// Number of slides visible per row (2-6; default 3), used as Slick's slidesToShow.
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

		

		// Design style variant (Type 1-3) driving the slide layout.
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

		// Auto-scroll interval in milliseconds; '0' disables auto-advance.
		$this->add_control(
				'autoscroll',
				[
					'label' => __('Auto scroll period in ms (1sec = 1000)', 'hello-wpstream'),
					'type' => Controls_Manager::TEXT,
					'Label Block',
					'default' => '0',

				]
		);

		// Close the Content tab section.
		$this->end_controls_section();

		
		// --- Style tab: per-item sizing (height, square size, spacing, radius) ---
		$this->start_controls_section(
			'size_section',
			array(
				'label' => esc_html__( 'Item Settings', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Responsive image height for design types 1 and 2 (type1/type2 wrappers).
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

		// Responsive square image size (height = width) used only for design type 3.
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

		// Responsive bottom spacing between items (applied for design type 2).
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

		// Border radius applied to the slide item across all wrapper types plus the cover overlay.
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

		// Close the Item Settings section.
		$this->end_controls_section();

		/*
		 * -------------------------------------------------------------------------------------------------
		 * Start Typografy
		 */

		// --- Style tab: title/tagline/listings typography and colors ---
		$this->start_controls_section(
			'typography_section',
			array(
				'label' => esc_html__( 'Style', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Typography group for the category title (h4 a), defaulting to the primary global font.
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
		// Responsive bottom margin below the title.
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

		// Responsive bottom margin below the tagline (only shown for design types 1 and 2).
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

		// Typography group for the "listings" count text on each slide.
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

		// Title text color (applied with !important to override theme styling).
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

		// Tagline text color (conditioned on design_type 'type1').
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

		// Listings count text color.
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

		// Background color behind the listings count text.
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

		// Solid overlay color placed over the slide image (default/non-hover state).
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

		// Overlay color used when the slide image is hovered.
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

		// Close the typography/colors Style section.
		$this->end_controls_section();

		
		// --- Style tab: Slick arrow colors (background/foreground, normal + hover) ---
		$this->start_controls_section(
			'arrow_colors_section',
			array(
				'label' => esc_html__( 'Arrows Colors', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)			
		);

		// Arrow button background color (normal state).
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

		// Arrow glyph (foreground) color (normal state).
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

		
	

		// Arrow button background color on hover. (Control key 'dropdown_menu_back_color'
		// is a carried-over name; it styles the slider arrows, not a dropdown menu.)
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

		// Arrow glyph (foreground) color on hover. (Control key 'dropdown_menu_font_color'
		// is a carried-over name; it styles the slider arrows, not a dropdown menu.)
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
		

		// Close the Arrows Colors section.
		$this->end_controls_section();

		// --- Style tab: Slick arrow geometry (radius, position, size, border, shadow) ---
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

		// Vertical (top) offset of the arrows, -200 to 200 px.
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


		// Outer size (width and height) of the round arrow button.
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
		// Size of the arrow SVG glyph itself (its height).
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

		// Right offset of the "previous" button; only applies when arrows_position is 'top'.
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

		// Border thickness of the arrow buttons, 0 to 15 px.
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

		// Border color of the arrow buttons.
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


		// Box-shadow group applied to the arrow buttons.
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(), [
		'name' => 'box_shadow_arrow',
		'label' => esc_html__(' Shadow', 'hello-wpstream'),
		'selector' => '{{WRAPPER}} .slick-arrow',
			]
		);




		// Close the Arrows Styles section.
		$this->end_controls_section();

		/*
		-------------------------------------------------------------------------------------------------
		 * Start shadow section
		 */
		// --- Style tab: box-shadow group applied to the slide wrappers/items ---
		$this->start_controls_section(
			'section_grid_box_shadow',
			array(
				'label' => esc_html__( 'Box Shadow', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		// Box-shadow group targeting the slide wrapper/item across all three wrapper types.
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'label'    => esc_html__( 'Box Shadow', 'hello-wpstream' ),
				'selector' => '{{WRAPPER}} .wpstream_category_unit_wrapper_type1 ,{{WRAPPER}} .wpstream_category_unit_wrapper_type2 .wpstream_category_unit_item,{{WRAPPER}} .wpstream_category_unit_wrapper_type3 .wpstream_category_unit_item ',
			)
		);

		// Close the Box Shadow section (end of control registration).
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
		// Accumulate the selected values into a comma-separated string.
		$output = '';
		// Only build a string when there is at least one value.
		if ( !empty($input) ) {
			// Track total count and current index to decide where separators go.
			$num_items = count( $input );
			$i         = 0;

			// Append each value, inserting ', ' between items but not after the last.
			foreach ( $input as $key => $value ) {
				$output .= $value;
				if ( ++$i !== $num_items ) {
					$output .= ', ';
				}
			}
		}
		// Return the comma-separated string.
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
		// Pull the current (editor/live) settings for this widget instance.
		$settings = $this->get_settings_for_display();

		// Selected taxonomy terms to render as slides.
		$attributes['place_list']    = $settings['place_list'] ;
		// Number of slides visible per row.
		$attributes['place_per_row'] = $settings['place_per_row'];
		// Chosen design style variant (Type 1-3).
		$attributes['design_type']   = $settings['design_type'];
		// Arrow placement ('top' or 'sideways').
		$attributes['arrows_position']  = $settings['arrows_position'];
		// Auto-scroll interval in ms ('0' disables it).
		$attributes['autoscroll']   	= $settings['autoscroll'];

		// Unique DOM id for this carousel so multiple instances don't clash.
		$slider_id                        = 'categories_slider_carousel_elementor_v1_' . wp_rand( 1, 99999 );
		// Output the slider HTML built by the theme helper. Escaping is intentionally
		// skipped here because the helper returns already-built, trusted markup.
		echo wpstream_theme_categories_slider( $attributes ,$slider_id  ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// In the Elementor editor the frontend init script may not have run, so
		// re-initialise the Slick carousel here for a live preview.
		if ( \Elementor\Plugin::instance()->editor->is_edit_mode() ) :
			?>
			<!-- Editor-only preview: initialise the Slick carousel for each slider on the canvas -->
			<script>

				// Iterate every slider container rendered on the canvas.
				jQuery('.wpstream_category_slider').each(function () {
					// Slides-per-row and auto-scroll values are read from data-attributes on the element.
					var items = jQuery(this).attr('data-items-per-row');
					var auto = parseInt(jQuery(this).attr('data-auto'));
					// Boot Slick with the base (desktop) configuration.
					var slick = jQuery(this).slick({
						infinite: true,
						slidesToShow: items,
						slidesToScroll: 1,
						dots: false,
						// Custom next/prev arrow markup (SVG chevrons) injected by Slick.
						nextArrow:'<button class="slick-next slick-arrow 333 " aria-label="Next" type="button" style=""><svg width="12" height="20" viewBox="0 0 12 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0.93934 0.93934C1.52513 0.353553 2.47487 0.353553 3.06066 0.93934L11.0607 8.93934C11.6464 9.52513 11.6464 10.4749 11.0607 11.0607L3.06066 19.0607C2.47487 19.6464 1.52513 19.6464 0.93934 19.0607C0.353553 18.4749 0.353553 17.5251 0.93934 16.9393L7.87868 10L0.93934 3.06066C0.353553 2.47487 0.353553 1.52513 0.93934 0.93934Z"/></svg></button>',
            			prevArrow:'<button class="slick-prev slick-arrow 222 " aria-label="Next" type="button" style=""><svg width="12" height="20" viewBox="0 0 12 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M11.0607 19.0607C10.4749 19.6464 9.52513 19.6464 8.93934 19.0607L0.93934 11.0607C0.353555 10.4749 0.353555 9.52513 0.939341 8.93934L8.93934 0.93934C9.52513 0.353554 10.4749 0.353554 11.0607 0.939341C11.6464 1.52513 11.6464 2.47487 11.0607 3.06066L4.12132 10L11.0607 16.9393C11.6464 17.5251 11.6464 18.4749 11.0607 19.0607Z"/></svg></button>',

						// Responsive overrides: fewer slides on tablet, single slide on phones.
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
					// For right-to-left sites, flip Slick into RTL mode after init.
					if (wpstream_theme.is_rtl === '1') {
						jQuery(this).slick('slickSetOption', 'rtl', true, true);
						jQuery(this).slick('slidesToScroll', '-1');
					}
				});
			</script>
			<?php

		// End of the editor-only preview initialisation.
		endif;
	}
}
