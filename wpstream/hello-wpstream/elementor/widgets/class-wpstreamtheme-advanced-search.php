<?php
/**
 * Advanced Search (Search Form) Elementor widget.
 *
 * Registers the "Search Form" widget for the hello-wpstream theme's Elementor
 * category. The widget is presentation-only from the editor's point of view:
 * its controls configure the submit button label plus extensive Style-tab
 * styling for the form container, the input fields, dropdowns and the submit
 * button (normal and hover states). The actual form markup is produced on
 * render by the `wpstreamtheme_advanced_search_function()` template helper.
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
		// Unique machine name Elementor uses to identify this widget type.
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
		// Human-readable label shown in the Elementor widget panel (translatable).
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
		// Elementor icon-font class used as the widget's panel icon.
		return 'eicon-site-search';
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
	 * Placeholder for the search form builder items list.
	 *
	 * Currently a no-op; retained as an extension point for building the
	 * dynamic list of search form fields.
	 *
	 * @return void
	 */
	public function wpstream_theme_elementor_search_form_builder_items_array() {
	}

	/**
	 * Register the widget's Elementor controls.
	 *
	 * Defines the Content-tab submit button text and the Style-tab sections for
	 * the form container, its fields/dropdowns, box shadow and the submit button
	 * (with separate normal and hover state tabs).
	 *
	 * @access protected
	 *
	 * @return void
	 */
	protected function register_controls() {

		/*
		 * -------------------------------------------------------------------------------------------------
		 * Button settings
		 */

		// ---- Content section: submit button text. ----
		$this->start_controls_section(
			'wpstream_theme_area_submit_button',
			array(
				'label' => esc_html__( 'Submit Button', 'hello-wpstream' ),
			)
		);

		// Text label rendered on the search form's submit button.
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

		// ---- Style section: the form container (gap, background, padding, radius). ----
		$this->start_controls_section(
			'wpstream_theme_area_form_style',
			array(
				'label' => esc_html__( 'Form', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Gap between the form's columns/fields (responsive slider).
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

	
		// Section heading separating the form styling controls in the panel.
		$this->add_control(
			'wpstream_theme_form_heading_label',
			array(
				'label'     => esc_html__( 'Form Label', 'hello-wpstream' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);



		// Background color of the form container (defaults to transparent).
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

		// Inner padding of the form container (responsive dimensions).
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

		// Corner radius of the form container (responsive dimensions).
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
		// ---- Style section: box shadow around the form container. ----
		$this->start_controls_section(
			'section_grid_box_shadow',
			array(
				'label' => esc_html__( 'Box Shadow', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);
		// Box-shadow group control targeting the search form wrapper.
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

		// ---- Style section: search input fields and dropdown styling. ----
		$this->start_controls_section(
			'wpstream_theme_field_style',
			array(
				'label' => esc_html__( 'Field Style', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Text color inside the search field and secondary button.
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

		// Typography for the search field and secondary button text.
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

		// Typography for the autocomplete/dropdown items.
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

		

		// Background color of the search field and secondary button.
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

		// Inner padding of the search field and secondary button.
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

	

	
		// Border color of the search field and secondary button.
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

		// Border width of the search field and secondary button.
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

		// Corner radius of the search field and secondary button.
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

		// ---- Style section: submit button (normal + hover state tabs). ----
		$this->start_controls_section(
			'wpstream_theme_area_button_style',
			array(
				'label' => esc_html__( 'Button', 'hello-wpstream' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// Begin the normal/hover tab group for the submit button.
		$this->start_controls_tabs( 'tabs_button_style' );

		// Normal (default) state tab.
		$this->start_controls_tab(
			'tab_button_normal',
			array(
				'label' => esc_html__( 'Normal State', 'hello-wpstream' ),
			)
		);

		// Normal-state submit button background color.
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

		// Normal-state submit button text color.
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


		// Typography for the submit button label.
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

		// Border group (type/width/color) for the submit button.
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'submit_button_border',
				'selector' => '{{WRAPPER}} .wpstream_submit_button ',
			)
		);

		// Corner radius of the submit button.
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

		// Inner padding of the submit button.
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

		// End the normal-state tab.
		$this->end_controls_tab();

		// Hover state tab.
		$this->start_controls_tab(
			'tab_button_hover',
			array(
				'label' => esc_html__( 'Hover State', 'hello-wpstream' ),
			)
		);

		// Hover-state submit button background color.
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

		// Hover-state submit button text color.
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

		// Hover-state submit button border color (only when a border is set).
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

		// End the hover-state tab.
		$this->end_controls_tab();

		// End the normal/hover tab group.
		$this->end_controls_tabs();

		/*
		-------------------------------------------------------------------------------------------------
		 *  End Button Style settings
		 */

		// ---- End Button style section. ----
		$this->end_controls_section();
	}

	/**
	 * Fetch the saved search-form configuration for the current post.
	 *
	 * Reads the `wpstream_elementor_search_form` post meta used to describe the
	 * dropdown/tab options for the current page's search form.
	 *
	 * @access protected
	 *
	 * @return mixed The stored search form meta value.
	 */
	protected function custom_serve() {

		// Current post whose meta holds the search form configuration.
		global $post;

		// Read the serialized search form settings saved against this post.
		$return = get_post_meta( $post->ID, 'wpstream_elementor_search_form', true );
		return $return;
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * Delegates markup generation to `wpstreamtheme_advanced_search_function()`,
	 * passing the resolved settings, the widget instance and the current post id.
	 * Output escaping is handled inside that helper (hence the phpcs ignore).
	 *
	 * @access protected
	 *
	 * @return void
	 */
	protected function render() {
		// Current post context passed through to the render helper.
		global $post;

		// Resolved control values ready for display/output.
		$settings = $this->get_settings_for_display();



		// Build and print the search form markup via the template helper.
		echo wpstreamtheme_advanced_search_function( $settings, $this, $post->ID ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}


}//end class
