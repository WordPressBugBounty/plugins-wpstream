<?php
/**
 * Customizer Button Settings
 *
 * @package wpstream-theme
 */

/**
 * Function to add button color settings and controls
 *
 * @param WP_Customize_Manager $wp_customize
 * @param string $button_type
 */
function wpstream_add_button_color_settings($wp_customize, $button_type) {
	$button_type_label = ucwords(str_replace('_', ' ', $button_type));

	// Add section for buttons colors
	$wp_customize->add_control(
		new Wpstream_Title_Control(
			$wp_customize,
			array(
				'label'    => esc_html__( $button_type_label . ' button style', 'hello-wpstream' ),
				'section'  => 'wpstream_buttons_colors',
			)
		)
	);

	// Add text color option
	wpstream_add_color_control($wp_customize, $button_type, 'button_text_color', 'Text color');

	// Add option to switch between simple and gradient for background color
	wpstream_add_background_option_control($wp_customize, $button_type, 'button_background', 'Background color');

	// Add simple background color option
	wpstream_add_color_control($wp_customize, $button_type, 'button_background_color_option_simple', 'Background color');

	// Add gradient options for background color
	wpstream_add_gradient_controls($wp_customize, $button_type, 'button_background_color_gradient');

	// Add option to switch between simple and gradient for hover background color
	wpstream_add_background_option_control($wp_customize, $button_type, 'button_hover_background', 'Hover Background color');

	// Add simple hover background color option
	wpstream_add_color_control($wp_customize, $button_type, 'button_hover_background_color_option_simple', 'Hover Background color');

	// Add gradient options for hover background color
	wpstream_add_gradient_controls($wp_customize, $button_type, 'button_hover_background_color_gradient');

	// Add border options
	wpstream_add_border_control($wp_customize, $button_type, 'button_border');

	// Add opacity option
	wpstream_add_opacity_control($wp_customize, $button_type, 'button');
}

function wpstream_add_color_control($wp_customize, $button_type, $setting_name, $label) {
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}",
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_hex_color',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			"wpstream_{$button_type}_{$setting_name}",
			array(
				'label'    => esc_html__( $label, 'hello-wpstream' ),
				'section'  => 'wpstream_buttons_colors',
				'settings' => "wpstream_{$button_type}_{$setting_name}",
			)
		)
	);
}

function wpstream_add_background_option_control($wp_customize, $button_type, $setting_name, $label) {
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_option",
		array(
			'default'           => 'simple',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control(
		"wpstream_{$button_type}_{$setting_name}_option",
		array(
			'label'   => esc_html__( $label, 'hello-wpstream' ),
			'type'    => 'radio',
			'section' => 'wpstream_buttons_colors',
			'choices' => array(
				'simple'   => __( 'Simple color', 'hello-wpstream' ),
				'gradient' => __( 'Gradient color', 'hello-wpstream' ),
			),
		)
	);
}

function wpstream_add_gradient_controls($wp_customize, $button_type, $setting_name) {
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_angle",
		array(
			'default'           => 0,
			'sanitize_callback' => 'wpstream_sanitize_number_field',
		)
	);
	$wp_customize->add_control(
		new Wpstream_Range_Control(
			$wp_customize,
			"wpstream_{$button_type}_{$setting_name}_angle",
			array(
				'label'   => esc_html__( 'Gradient angle', 'hello-wpstream' ),
				'section' => 'wpstream_buttons_colors',
				'min'     => 0,
				'max'     => 360,
				'step'    => 1,
				'unit'    => 'deg',
			)
		)
	);

	wpstream_add_color_control($wp_customize, $button_type, "{$setting_name}_first_color", 'First color');
	wpstream_add_color_control($wp_customize, $button_type, "{$setting_name}_second_color", 'Second color');
}

function wpstream_add_border_control($wp_customize, $button_type, $setting_name) {
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_width",
		array(
			'default'           => 0,
			'sanitize_callback' => 'wpstream_sanitize_number_field',
		)
	);
	$wp_customize->add_control(
		new Wpstream_Range_Control(
			$wp_customize,
			"wpstream_{$button_type}_{$setting_name}_width",
			array(
				'label'   => esc_html__( 'Border width', 'hello-wpstream' ),
				'section' => 'wpstream_buttons_colors',
				'min'     => 0,
				'max'     => 20,
				'step'    => 1,
				'unit'    => 'px',
			)
		)
	);

	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_color",
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_hex_color',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			"wpstream_{$button_type}_{$setting_name}_color",
			array(
				'label'    => esc_html__( 'Border color', 'hello-wpstream' ),
				'section'  => 'wpstream_buttons_colors',
				'settings' => "wpstream_{$button_type}_{$setting_name}_color",
			)
		)
	);

	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_hover_color",
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_hex_color',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			"wpstream_{$button_type}_{$setting_name}_hover_color",
			array(
				'label'    => esc_html__( 'Hover border color', 'hello-wpstream' ),
				'section'  => 'wpstream_buttons_colors',
				'settings' => "wpstream_{$button_type}_{$setting_name}_hover_color",
			)
		)
	);

	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_radius",
		array(
			'default'           => 0,
			'sanitize_callback' => 'wpstream_sanitize_number_field',
		)
	);
	$wp_customize->add_control(
		new Wpstream_Range_Control(
			$wp_customize,
			"wpstream_{$button_type}_{$setting_name}_radius",
			array(
				'label'   => esc_html__( 'Border radius', 'hello-wpstream' ),
				'section' => 'wpstream_buttons_colors',
				'min'     => 0,
				'max'     => 50,
				'step'    => 1,
				'unit'    => 'px',
			)
		)
	);
}

function wpstream_add_opacity_control($wp_customize, $button_type, $setting_name) {
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_opacity",
		array(
			'default'           => 100,
			'sanitize_callback' => 'wpstream_sanitize_number_field',
		)
	);
	$wp_customize->add_control(
		new Wpstream_Range_Control(
			$wp_customize,
			"wpstream_{$button_type}_{$setting_name}_opacity",
			array(
				'label'   => esc_html__('Opacity', 'hello-wpstream'),
				'section' => 'wpstream_buttons_colors',
				'min'     => 0,
				'max'     => 100,
				'step'    => 1,
				'unit'    => '%',
			)
		)
	);
}