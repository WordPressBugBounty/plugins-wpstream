<?php
/**
 * Class advanced search
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
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor Properties Widget.
 *
 * @since 2.0
 */
class WpStreamTheme_Advanced_Search extends Widget_Base {
	/**
	 * Get widget name.
	 *
	 * Retrieve widget name.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'Wpstream_Search_Form';
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
		return esc_html__( 'Search Form', 'hello-wpstream' );
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
		return 'eicon-site-search';
	}

	/**
	 * Get categories
	 */
	public function get_categories() {
		return array( 'hello-wpstream' );
	}

	/**
	 * Search form builder items
	 */
	public function wpstream_theme_elementor_search_form_builder_items_array() {
	}

	/**
	 * Register controls
	 */
	protected function register_controls() {

		/*
		 * -------------------------------------------------------------------------------------------------
		 * Button settings
		 */

		$this->start_controls_section(
			'wpstream_theme_area_submit_button',
			array(
				'label' => esc_html__( 'Submit Button', 'hello-wpstream' ),
			)
		);

		$this->add_control(
			'submit_button_text',
			array(
				'label'       => esc_html__( 'Text', 'hello-wpstream' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Search', 'hello-wpstream' ),
				'placeholder' => esc_html__( 'Search', 'hello-wpstream' ),
			)
		);

	

	
		$this->end_controls_section();

		/*
		 * -------------------------------------------------------------------------------------------------
		 * END Button settings
		 */

		$this->start_controls_section(
			'wpstream_theme_area_form_style',
			array(
				'label' => esc_html__( 'Form', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'wpersidence_form_column_gap',
			array(
				'label'     => esc_html__( 'Form Columns Gap', 'hello-wpstream' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array(
					'size' => 10,
				),
				'range'     => array(
					'px' => array(
						'min' => 0,
						'max' => 100,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .wpstream-theme-search-form' => 'gap: {{SIZE}}{{UNIT}}',
				),
			)
		);

	
		$this->add_control(
			'wpstream_theme_form_heading_label',
			array(
				'label'     => esc_html__( 'Form Label', 'hello-wpstream' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);



		$this->add_control(
			'wpstream_theme_form_back_color',
			array(
				'label'     => esc_html__( 'Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'=>'transparent',
				'selectors' => array(
					'{{WRAPPER}} .wpstream-theme-search-form' => 'background-color: {{VALUE}};',
				),
				'global' => [
    				'default' => Global_Colors::COLOR_TEXT,
				],
			)
		);

		$this->add_responsive_control(
			'form_wrapper-content_padding',
			array(
				'label'      => esc_html__( 'Form Padding ', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),

				'selectors'  => array(
					'{{WRAPPER}} .wpstream-theme-search-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'form_border_radius',
			array(
				'label'      => esc_html__( 'Form Border Radius', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wpstream-theme-search-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',

				),
			)
		);



		$this->end_controls_section();

		/*
		-------------------------------------------------------------------------------------------------
		 * End Form  settings
		 */

		/*
		 * -------------------------------------------------------------------------------------------------
		 * Start shadow section
		 * {{WRAPPER}} .adv_search_tab_item
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
				'name'     => 'box_shadow_form',
				'label'    => esc_html__( 'Box Shadow Form', 'hello-wpstream' ),
				'selector' => '{{WRAPPER}} .wpstream-theme-search-form ',
			)
		);

		$this->end_controls_section();

		/*
		-------------------------------------------------------------------------------------------------
		 *  Form Fields settings
		 */

		$this->start_controls_section(
			'wpstream_theme_field_style',
			array(
				'label' => esc_html__( 'Field Style', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'wpstream_theme_field_text_color1',
			array(
				'label'     => esc_html__( 'Field Text Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .search-field'        => 'color:{{VALUE}}!important;',
					'{{WRAPPER}} .btn-secondary' => 'color: {{VALUE}};',
	

				),
				'global' => [
    				'default' => Global_Colors::COLOR_TEXT,
				],
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'wpstream_theme_field_typography',
				'selector' => '{{WRAPPER}} .search-field, {{WRAPPER}} .btn-secondary',
	'global' => [
            'default' => Global_Typography::TYPOGRAPHY_TEXT,
        ],
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'wpstream_theme_field_typography_dropdown',
				'label'    => esc_html__( 'Dropdown Typography', 'hello-wpstream' ),
				'selector' => '{{WRAPPER}} .dropdown-item',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			)
		);

		

		$this->add_control(
			'wpstream_theme_field_background_color',
			array(
				'label'     => esc_html__( 'Field Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
	
				'selectors' => array(
					'{{WRAPPER}}  .search-field'    => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .btn-secondary' => 'background-color: {{VALUE}};',
				),
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'tab-wpstream_theme_field_padding-color',
			array(
				'label'      => esc_html__( 'Field Padding', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .search-field'    => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}!important;',
					'{{WRAPPER}} .btn-secondary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',

				),
			)
		);

	

	
		$this->add_control(
			'wpstream_theme_field_border_color',
			array(
				'label'     => esc_html__( 'Border Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,

				'selectors' => array(
					'{{WRAPPER}} .btn-secondary' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .search-field' => 'border-color: {{VALUE}};',
				),
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'field_border_width',
			array(
				'label'       => esc_html__( 'Border Width', 'hello-wpstream' ),
				'type'        => Controls_Manager::DIMENSIONS,
				'placeholder' => '1',
				'size_units'  => array( 'px' ),
				'selectors'   => array(
					'{{WRAPPER}} .btn-secondary' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .search-field' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'field_border_radius',
			array(
				'label'      => esc_html__( 'Border Radius', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}}  .btn-secondary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}}  .search-field' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/*
		-------------------------------------------------------------------------------------------------
		 *  END Form Fields settings
		 */

		$this->start_controls_section(
			'wpstream_theme_area_button_style',
			array(
				'label' => esc_html__( 'Button', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->start_controls_tabs( 'tabs_button_style' );

		$this->start_controls_tab(
			'tab_button_normal',
			array(
				'label' => esc_html__( 'Normal State', 'hello-wpstream' ),
			)
		);

		$this->add_control(
			'submit_button_background_color',
			array(
				'label'     => esc_html__( 'Submit Button Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'global' => [
    				'default' => Global_Colors::COLOR_ACCENT,
				],
				'selectors' => array(
					'{{WRAPPER}} .wpstream_submit_button ' => 'background-color:  {{VALUE}}!important;',
				),
			)
		);

		$this->add_control(
			'submit_button_text_color',
			array(
				'label'     => esc_html__( 'Submit Button Text Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpstream_submit_button ' => 'color: {{VALUE}}!important;',
				),
			)
		);
		

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'submit_button_typography',
				'global' => [
                    'default' => Global_Typography::TYPOGRAPHY_ACCENT,
                ],
				'selector' => '{{WRAPPER}} .wpstream_submit_button ',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'submit_button_border',
				'selector' => '{{WRAPPER}} .wpstream_submit_button ',
			)
		);

		$this->add_responsive_control(
			'submit_ button_border_radius',
			array(
				'label'      => esc_html__( 'Submit Button Border Radius', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wpstream_submit_button ' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'submit_button_text_padding',
			array(
				'label'      => esc_html__( 'Submit Button Text Padding', 'hello-wpstream' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .wpstream_submit_button ' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			array(
				'label' => esc_html__( 'Hover State', 'hello-wpstream' ),
			)
		);

		$this->add_control(
			'submit_button_background_hover_color',
			array(
				'label'     => esc_html__( 'Submit Button Background Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_submit_button:hover' => 'background-color:  {{VALUE}}!important;',
				),
			)
		);

		$this->add_control(
			'submit_button_hover_color',
			array(
				'label'     => esc_html__( 'Submit Button Text Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .wpstream_submit_button:hover' => 'color: {{VALUE}}!important;',
				),
			)
		);

		$this->add_control(
			'submit_button_hover_border_color',
			array(
				'label'     => esc_html__( 'Submit Button Border Color', 'hello-wpstream' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .wpstream_submit_button:hover' => 'border-color: {{VALUE}};',
				),
				'condition' => array(
					'button_border_border!' => '',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		/*
		-------------------------------------------------------------------------------------------------
		 *  End Button Style settings
		 */

		$this->end_controls_section();
	}

	/**
	 * Return option for tabs dropdown
	 */
	protected function custom_serve() {

		global $post;

		$return = get_post_meta( $post->ID, 'wpstream_elementor_search_form', true );
		return $return;
	}

	/**
	 * Render
	 */
	protected function render() {
		global $post;

		$settings = $this->get_settings_for_display();

		

		echo wpstreamtheme_advanced_search_function( $settings, $this, $post->ID ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}


}//end class
