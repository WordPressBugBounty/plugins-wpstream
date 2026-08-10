<?php
/**
 * Class categories list
 *
 * Defines the "Categories List" Elementor widget for the hello-wpstream theme.
 * The widget lets an editor pick taxonomy terms and render them as a responsive
 * list/grid of category items, with controls for categories-per-row, a design
 * style variant, item sizing, typography, colors and box-shadow. The frontend
 * markup is produced by the theme helper wpstreamtheme_categories_list_function().
 *
 * @package wpstream-theme
 */

// Pull in the Elementor base class and the control/group-control helpers this widget relies on.
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
		// Unique internal identifier Elementor uses to reference this widget.
		return 'WpStreamTheme_Categories_List';
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
		// Elementor icon-font class used for the widget's tile icon.
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
		// No extra registered scripts are required for this widget (empty handle).
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
	 * Builds every control the widget exposes: the Content tab (categories,
	 * per-row count, design style) and the Style tab sections (item sizing,
	 * typography/colors, and box shadow).
	 *
	 * @return void
	 */
	protected function register_controls() {
		// Fetch every available taxonomy term from the theme helper.
		$all_tax = wpstream_theme_return_all_taxomy_array();

		// Convert that list into the value => label map SELECT2 expects.
		$all_tax_elemetor = $this->elementor_transform( $all_tax );

		// --- Content tab: category selection and layout/design pickers ---
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

		// Multi-select of taxonomy terms to display as list items.
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

		// How many category items to place per row (2, 3 or 4; default 3).
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

		// Design style variant (Type 1-3) driving the item layout.
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

		// Border radius applied to the item across all wrapper types plus the cover overlay.
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

		// Typography group for the "listings" count text on each item.
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

		// Solid overlay color placed over the item image (default/non-hover state).
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

		// Overlay color used when the item image is hovered.
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

		/*
		-------------------------------------------------------------------------------------------------
		 * Start shadow section
		 */
		// --- Style tab: box-shadow group applied to the list items ---
		$this->start_controls_section(
			'section_grid_box_shadow',
			array(
				'label' => esc_html__( 'Box Shadow', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		// Box-shadow group targeting the item across all three wrapper types.
		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'label'    => esc_html__( 'Box Shadow', 'hello-wpstream' ),
				'selector' => '{{WRAPPER}} .wpstream_category_unit_wrapper_type1 .wpstream_category_unit_item ,{{WRAPPER}} .wpstream_category_unit_wrapper_type2 .wpstream_category_unit_item,{{WRAPPER}} .wpstream_category_unit_wrapper_type3 .wpstream_category_unit_item ',
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
		// Concatenate the selected values into a single string.
		$output = '';
		// Only build a string when there is at least one value.
		if ( !empty($input) ) {
			// $num_items/$i are initialised here but the loop below does not use them
			// to insert separators, so values are joined with no delimiter.
			$num_items = count( $input );
			$i         = 0;

			// Append each value directly to the output (no comma separator added).
			foreach ( $input as $key => $value ) {
				$output .= $value;
			
			}
		}
		// Return the concatenated string.
		return $output;
	}

	/**
	 * Render
	 *
	 * Collect the saved settings into an attributes array and echo the list markup
	 * produced by the theme helper wpstreamtheme_categories_list_function().
	 *
	 * @return void
	 */
	protected function render() {
		// Pull the current (editor/live) settings for this widget instance.
		$settings = $this->get_settings_for_display();

		// Selected taxonomy terms to render.
		$attributes['place_list']    = $settings['place_list'] ;
		// Number of categories per row.
		$attributes['place_per_row'] = $settings['place_per_row'];
		// Chosen design style variant (Type 1-3).
		$attributes['design_type']   = $settings['design_type'];
		// Output the list HTML built by the theme helper. Escaping is intentionally
		// skipped here because the helper returns already-built, trusted markup.
		echo wpstreamtheme_categories_list_function( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	
	}
}
