<?php
/**
 * Class display categories as tabs
 *
 * Elementor widget for the hello-wpstream theme that renders one or more
 * taxonomies (categories, actors, media categories/ratings, product
 * categories) as a set of tabs. Each tab lists the terms of its taxonomy; the
 * editor controls how many items per row, how many items, whether empty terms
 * are hidden, and the full styling of the tab bar and tab content. The actual
 * markup is produced by the theme helper wpstream_theme_categories_list_functionas_tabs().
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
 * Categories as tabs
 */
class WpStreamTheme_Display_Categories_As_Tabs extends Widget_Base {
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
		return 'WpStreamTheme_Display_Categories_As_Tabs';
	}

	/**
	 * Retrieve the Elementor panel categories this widget belongs to.
	 *
	 * @return array List with the single 'hello-wpstream' panel category.
	 */
	public function get_categories() {
		// Place the widget under the theme's own "hello-wpstream" panel category.
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
		return __( 'Categories As Tabs', 'hello-wpstream' );
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
		return ' eicon-product-categories';
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
	 */
	protected function register_controls() {
		// Selectable taxonomies -> labels shown in the per-tab type dropdown.
		$all_tax_elemetor = array(
			'category'		 			=> esc_html__( 'Categories', 'hello-wpstream' ),
            'wpstream_actors'			=> esc_html__( 'Actors', 'hello-wpstream' ),
            'wpstream_category'			=> esc_html__( 'Media Categories', 'hello-wpstream' ),
            'wpstream_movie_rating'		=> esc_html__( 'Media Ratings', 'hello-wpstream' ),
            'product_cat'				=> esc_html__( 'Product Categories', 'hello-wpstream' ),

		);

		// -----------------------------------------------------------------
		// CONTENT tab: "Content" section (the tabs repeater + layout options).
		// -----------------------------------------------------------------
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

		// Repeater collecting the tabs (one taxonomy + label + icon per tab).
		$repeater = new Repeater();

		// Repeater control: which taxonomy this tab displays.
		$repeater->add_control(
			'field_type',
			array(
				'label'   => esc_html__( 'Form Fields', 'hello-wpstream' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $all_tax_elemetor,
				'default' => 'text',
			)
		);

		// Repeater control: the visible label printed on this tab.
		$repeater->add_control(
			'field_label',
			array(
				'label'   => esc_html__( 'Form Fields Label', 'hello-wpstream' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

		// Repeater control: icon shown alongside the tab label.
		$repeater->add_control(
			'icon',
			array(
				'label'   => __( 'Icon', 'hello-wpstream' ),
				'type'    => \Elementor\Controls_Manager::ICONS,
				'default' => array(
					'value'   => 'fas fa-star',
					'library' => 'solid',
				),
			)
		);

		// The repeater control itself, seeded with one default "Categories" tab.
		$this->add_control(
			'form_fields',
			array(
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array(
						'_id'         => 'name',
						'field_type'  => 'category',
						'field_label' => esc_html__( 'Categories', 'hello-wpstream' ),

					),

				),
				'title_field' => '{{{ field_label }}}',
			)
		);

		// How many term items are shown per row inside a tab's content.
		$this->add_control(
			'place_per_row',
			array(
				'label'   => __( 'Items per row (1, 2, 3, 4 or 6)', 'hello-wpstream' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 4,
			)
		);

		// Cap on the number of terms rendered per tab (blank = all terms).
		$this->add_control(
			'max_items',
			array(
				'label'   => __( 'How many Items(leave blank for all)', 'hello-wpstream' ),
				'type'    => Controls_Manager::TEXT,
				
			)
		);


		// Toggle hiding terms that have no associated listings.
		$this->add_control(
			'show_zero_terms',
			array(
				'label'        => __( 'Hide Terms with no listings', 'hello-wpstream' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'hello-wpstream' ),
				'label_off'    => __( 'no', 'hello-wpstream' ),
				'return_value' => true,
				'default'      => true,
			)
		);

		// Toggle hiding the tab bar entirely (show only the active tab content).
		$this->add_control(
			'hide_items_bar',
			array(
				'label'        => __( 'Hide tab Items bar', 'hello-wpstream' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'hello-wpstream' ),
				'label_off'    => __( 'no', 'hello-wpstream' ),
				'return_value' => true,
				'default'      => false,
			)
		);


		// End of the "Content" section.
		$this->end_controls_section();

		// -----------------------------------------------------------------
		// STYLE tab: "Tab Items Settings" section (styling of the tab buttons).
		// -----------------------------------------------------------------
		$this->start_controls_section(
			'tab_items_section',
			array(
				'label' => esc_html__( 'Tab Items Settings', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Horizontal alignment of the tab bar (justify-content on the list).
		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'hello-wpstream' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => __( 'Left', 'hello-wpstream' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Center', 'hello-wpstream' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'hello-wpstream' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_ul' => '    justify-content: {{VALUE}};',
				),
			)
		);

		// Inner padding of each tab button.
		$this->add_responsive_control(
			'form_wrapper-content_padding',
			array(
				'label'      => esc_html__( 'Tab item Content Padding ', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),

				'selectors'  => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

			// Outer margin around each tab button.
			$this->add_responsive_control(
				'tab_item_margin',
				array(
					'label'      => esc_html__( 'Tab item Margin ', 'hello-wpstream' ),
					'type'       => Controls_Manager::DIMENSIONS,
					'size_units' => array( 'px', 'em', '%' ),

					'selectors'  => array(
						'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					),
				)
			);

		// Corner radius of each tab button.
		$this->add_responsive_control(
			'tab_item_border_radius',
			array(
				'label'      => esc_html__( 'Tab Item Border Radius', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link ' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',

				),
			)
		);

		// Background color of an inactive tab button.
		$this->add_control(
			'tab_item_back_color',
			array(
				'label'     => esc_html__( 'Tab Item Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link' => 'background-color: {{VALUE}};',
				),

			)
		);

		// Text color of an inactive tab button.
		$this->add_control(
			'tab_item_font_color',
			array(
				'label'     => esc_html__( 'Tab Item Font Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link' => 'color: {{VALUE}};',
					
				),

			)
		);

		// Background color of the active/selected tab button.
		$this->add_control(
			'tab_item_back_selected_color',
			array(
				'label'     => esc_html__( 'Tab Item Active Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link.active' => 'background-color: {{VALUE}};',
				),

			)
		);

		// Text color of the active/selected tab button.
		$this->add_control(
			'tab_item_active_font_color',
			array(
				'label'     => esc_html__( 'Tab Item Active Font Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link.active' => 'color: {{VALUE}};',
					
				),

			)
		);

		// Typography group control for the tab button labels.
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'tab_item_typo',
				'label'          => esc_html__( 'Tab Item Typography', 'hello-wpstream' ),
'global' => [
            'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
        ],
				'selector'       => '{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link',
				'fields_options' => array(
					// Inner control name.
					'font_weight' => array(
						// Inner control settings.
						'default' => '400',
					),
					'font_family' => array(
						'default' => 'Roboto',
					),
					'font_size'   => array(
						'default' => array(
							'unit' => 'px',
							'size' => 16,
						),
					),
				),
			)
		);

		// Margin around the tab button icon (applies to both <i> and <svg>).
		$this->add_responsive_control(
			'tab_item_icon_margin',
			array(
				'label'      => esc_html__( 'Tab item Icon Margin ', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),

				'selectors'  => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link i' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link svg' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		// Icon color for an inactive tab (color for fonts, fill for SVGs).
		$this->add_control(
			'tab_item_icon_font_color',
			array(
				'label'     => esc_html__( 'Tab Item Icon Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
				'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link i' => 'color: {{VALUE}};',
				'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link svg' => 'fill: {{VALUE}};',
				),

			)
		);

		// Icon color for the active tab (color for fonts, fill for SVGs).
		$this->add_control(
			'tab_item_icon_active_font_color',
			array(
				'label'     => esc_html__( 'Tab Item Active Icon Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
				'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link.active i' => 'color: {{VALUE}};',
				'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link.active svg' => 'fill: {{VALUE}};',
			
				),

			)
		);

		// Tab icon size (font-size for <i>, width for <svg>).
		$this->add_responsive_control(
			'item_icon_size',
			array(
				'label'           => esc_html__( 'Icon Size', 'hello-wpstream' ),
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

					'{{WRAPPER}}  .wpstream_theme_categories_as_tabs_item i' => 'font-size: {{SIZE}}{{UNIT}};',
				'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link svg' => 'width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		// Toggle stacking the icon above the label (flex-direction: column).
		$this->add_control(
			'icon_position',
			array(
				'label'        => __( 'Put Icon above label', 'hello-wpstream' ),
				'label_block'  => false,
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'hello-wpstream' ),
				'label_off'    => __( 'no', 'hello-wpstream' ),
				'return_value' => 'none',
				'default'      => '',
				'selectors'    => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_wrapper .wpstream_categories_as_tabs_ul .nav-link  ' => 'flex-direction: column;',
				),
			)
		);

		// End of the "Tab Items Settings" section.
		$this->end_controls_section();

		// -----------------------------------------------------------------
		// STYLE tab: "Tab Content Settings" section (styling of the panel body).
		// -----------------------------------------------------------------
		$this->start_controls_section(
			'tab_content_items_section',
			array(
				'label' => esc_html__( 'Tab Content Settings', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Inner padding of the tab content panel.
		$this->add_responsive_control(
			'tab-content_padding',
			array(
				'label'      => esc_html__( 'Tab Content Padding ', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),

				'selectors'  => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_panel  ' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		// Outer margin of the tab content panel.
		$this->add_responsive_control(
			'wpersidence_tab_content_margin',
			array(
				'label'      => esc_html__( 'Tab Content Margin', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_panel ' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		// Margin around each term item inside the panel.
		$this->add_responsive_control(
			'wpersidence_tab_content_element_margin',
			array(
				'label'      => esc_html__( 'List Element Margin', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wpstream_category_unit_wrapper_type3  ' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		// Corner radius of the tab content panel.
		$this->add_responsive_control(
			'tab_content_border_radius',
			array(
				'label'      => esc_html__( 'Tab Content Border Radius', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_panel ' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',

				),
			)
		);

		// Background color of the tab content panel.
		$this->add_control(
			'tab_content_back_color',
			array(
				'label'     => esc_html__( 'Tab Item Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_panel ' => 'background-color: {{VALUE}};',
				),

			)
		);

		// Font color of the term title (first row) inside the panel.
		$this->add_control(
			'tab_content_font_color',
			array(
				'label'     => esc_html__( 'Term Font Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpstream_categories_as_tabs_panel  a' => 'color: {{VALUE}};',
					'{{WRAPPER}} .wpstream_categories_as_tabs_panel' => 'color: {{VALUE}};',
				),

			)
		);

		// Font color of the term's second row (listings count).
		$this->add_control(
			'tab_content_sec_row_font_color',
			array(
				'label'     => esc_html__( 'Term Second row Font Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpstream_category_unit_item_details_listings a' => 'color: {{VALUE}};',
					'{{WRAPPER}} .wpstream_category_unit_item_details_listings' => 'color: {{VALUE}};',
				),

			)
		);

		// Typography group control for the term title (h4).
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'tax_title',
				'label'          => esc_html__( 'Term Typography', 'hello-wpstream' ),
'global' => [
            'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
        ],
				'selector'       => '{{WRAPPER}} h4',
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

		// Typography group control for the term's second row (listings count).
		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'           => 'tax_title_Sec_row',
				'label'          => esc_html__( 'Second Row Typography', 'hello-wpstream' ),
'global' => [
            'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
        ],
				'selector'       => '{{WRAPPER}} .wpstream_category_unit_item_details_listings',
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
		// Square size (height = width) of each term's thumbnail image.
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
		// End of the "Tab Content Settings" section (and of register_controls()).
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
	 */
	protected function render() {
		// Pull the resolved control values for this widget instance.
		$settings = $this->get_settings_for_display();

		// Repackage the relevant settings into the args array the helper expects.
		$attributes['form_fields']      = $settings['form_fields'];   // the configured tabs
		$attributes['place_per_row']    = $settings['place_per_row']; // items per row in a panel
		$attributes['show_zero_terms']  = $settings['show_zero_terms']; // hide empty terms flag
		$attributes['hide_items_bar'] 	= $settings['hide_items_bar']; // hide the tab bar flag
		$attributes['max_items'] 		= $settings['max_items'];     // cap on terms per tab

		// Delegate the markup generation to the theme helper and print it.
		echo wpstream_theme_categories_list_functionas_tabs( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
