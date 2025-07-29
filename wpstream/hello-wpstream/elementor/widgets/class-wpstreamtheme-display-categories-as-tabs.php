<?php
/**
 * Class display categories as tabs
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
		$all_tax_elemetor = array(
			'category'		 			=> esc_html__( 'Categories', 'hello-wpstream' ),
            'wpstream_actors'			=> esc_html__( 'Actors', 'hello-wpstream' ),
            'wpstream_category'			=> esc_html__( 'Media Categories', 'hello-wpstream' ),
            'wpstream_movie_rating'		=> esc_html__( 'Media Ratings', 'hello-wpstream' ),
            'product_cat'				=> esc_html__( 'Product Categories', 'hello-wpstream' ),

		);

		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'hello-wpstream' ),
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'field_type',
			array(
				'label'   => esc_html__( 'Form Fields', 'hello-wpstream' ),
				'type'    => Controls_Manager::SELECT,
				'options' => $all_tax_elemetor,
				'default' => 'text',
			)
		);

		$repeater->add_control(
			'field_label',
			array(
				'label'   => esc_html__( 'Form Fields Label', 'hello-wpstream' ),
				'type'    => Controls_Manager::TEXT,
				'default' => '',
			)
		);

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

		$this->add_control(
			'place_per_row',
			array(
				'label'   => __( 'Items per row (1, 2, 3, 4 or 6)', 'hello-wpstream' ),
				'type'    => Controls_Manager::TEXT,
				'default' => 4,
			)
		);

		$this->add_control(
			'max_items',
			array(
				'label'   => __( 'How many Items(leave blank for all)', 'hello-wpstream' ),
				'type'    => Controls_Manager::TEXT,
				
			)
		);


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


		$this->end_controls_section();

		$this->start_controls_section(
			'tab_items_section',
			array(
				'label' => esc_html__( 'Tab Items Settings', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

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

		$this->end_controls_section();

		$this->start_controls_section(
			'tab_content_items_section',
			array(
				'label' => esc_html__( 'Tab Content Settings', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

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
		$settings = $this->get_settings_for_display();

		$attributes['form_fields']      = $settings['form_fields'];
		$attributes['place_per_row']    = $settings['place_per_row'];
		$attributes['show_zero_terms']  = $settings['show_zero_terms'];
		$attributes['hide_items_bar'] 	= $settings['hide_items_bar'];
		$attributes['max_items'] 		= $settings['max_items'];

		echo wpstream_theme_categories_list_functionas_tabs( $attributes ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
