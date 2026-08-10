<?php
/**
 * Customizer Button Settings
 *
 * Registers the WordPress Customizer settings and controls that let the site
 * owner style a family of themed buttons (text color, simple/gradient
 * background, hover background, border, and opacity). Every control here lives
 * under the shared `wpstream_buttons_colors` Customizer section and its setting
 * IDs are namespaced per button type (e.g. `wpstream_{button_type}_...`).
 *
 * The helper functions rely on custom control classes declared elsewhere
 * (Wpstream_Title_Control, Wpstream_Range_Control) plus core's
 * WP_Customize_Color_Control.
 *
 * @package wpstream-theme
 */

/**
 * Function to add button color settings and controls
 *
 * Builds the full set of styling controls for a single button type by
 * delegating to the smaller per-aspect helper functions below.
 *
 * @param WP_Customize_Manager $wp_customize The Customizer manager instance.
 * @param string               $button_type  Machine name of the button type (e.g. "primary_button").
 * @return void
 */
function wpstream_add_button_color_settings($wp_customize, $button_type) {
	// Build a human-readable label from the machine name (e.g. "primary_button" -> "Primary Button").
	$button_type_label = ucwords(str_replace('_', ' ', $button_type));

	// Add section for buttons colors
	// Render a non-setting title control that heads this button type's block of options.
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
	// Text/foreground color of the button label.
	wpstream_add_color_control($wp_customize, $button_type, 'button_text_color', 'Text color');

	// Add option to switch between simple and gradient for background color
	// Radio toggle: use a flat color or a gradient for the resting background.
	wpstream_add_background_option_control($wp_customize, $button_type, 'button_background', 'Background color');

	// Add simple background color option
	// The single flat color used when "simple" is selected above.
	wpstream_add_color_control($wp_customize, $button_type, 'button_background_color_option_simple', 'Background color');

	// Add gradient options for background color
	// Angle + two colors used when "gradient" is selected above.
	wpstream_add_gradient_controls($wp_customize, $button_type, 'button_background_color_gradient');

	// Add option to switch between simple and gradient for hover background color
	// Radio toggle mirroring the resting background, but for the hover state.
	wpstream_add_background_option_control($wp_customize, $button_type, 'button_hover_background', 'Hover Background color');

	// Add simple hover background color option
	// The single flat hover color used when "simple" is selected for hover.
	wpstream_add_color_control($wp_customize, $button_type, 'button_hover_background_color_option_simple', 'Hover Background color');

	// Add gradient options for hover background color
	// Angle + two colors used when "gradient" is selected for hover.
	wpstream_add_gradient_controls($wp_customize, $button_type, 'button_hover_background_color_gradient');

	// Add border options
	// Border width, color, hover color and radius controls.
	wpstream_add_border_control($wp_customize, $button_type, 'button_border');

	// Add opacity option
	// Overall button opacity (0-100%).
	wpstream_add_opacity_control($wp_customize, $button_type, 'button');
}

/**
 * Register a single color-picker setting + control for a button aspect.
 *
 * @param WP_Customize_Manager $wp_customize The Customizer manager instance.
 * @param string               $button_type  Machine name of the button type.
 * @param string               $setting_name Aspect suffix appended to the setting ID.
 * @param string               $label        Human-readable control label.
 * @return void
 */
function wpstream_add_color_control($wp_customize, $button_type, $setting_name, $label) {
	// Register the setting: empty default, sanitized as a hex color.
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}",
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_hex_color',
		)
	);
	// Attach a core color-picker control bound to the setting above.
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

/**
 * Register a "simple vs gradient" radio setting + control for a background aspect.
 *
 * @param WP_Customize_Manager $wp_customize The Customizer manager instance.
 * @param string               $button_type  Machine name of the button type.
 * @param string               $setting_name Aspect suffix; "_option" is appended to the setting ID.
 * @param string               $label        Human-readable control label.
 * @return void
 */
function wpstream_add_background_option_control($wp_customize, $button_type, $setting_name, $label) {
	// Register the setting: defaults to "simple", stored as plain text.
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_option",
		array(
			'default'           => 'simple',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	// Attach a radio control offering the two mutually exclusive choices.
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

/**
 * Register the controls that make up a gradient: an angle range plus two colors.
 *
 * @param WP_Customize_Manager $wp_customize The Customizer manager instance.
 * @param string               $button_type  Machine name of the button type.
 * @param string               $setting_name Gradient aspect suffix appended to the setting IDs.
 * @return void
 */
function wpstream_add_gradient_controls($wp_customize, $button_type, $setting_name) {
	// Register the gradient angle setting: 0deg default, numeric sanitizer.
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_angle",
		array(
			'default'           => 0,
			'sanitize_callback' => 'wpstream_sanitize_number_field',
		)
	);
	// Attach a slider (0-360 degrees) for the gradient direction.
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

	// The two color stops that define the gradient.
	wpstream_add_color_control($wp_customize, $button_type, "{$setting_name}_first_color", 'First color');
	wpstream_add_color_control($wp_customize, $button_type, "{$setting_name}_second_color", 'Second color');
}

/**
 * Register the full set of border controls: width, color, hover color and radius.
 *
 * @param WP_Customize_Manager $wp_customize The Customizer manager instance.
 * @param string               $button_type  Machine name of the button type.
 * @param string               $setting_name Border aspect suffix appended to the setting IDs.
 * @return void
 */
function wpstream_add_border_control($wp_customize, $button_type, $setting_name) {
	// Register the border width setting: 0 default, numeric sanitizer.
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_width",
		array(
			'default'           => 0,
			'sanitize_callback' => 'wpstream_sanitize_number_field',
		)
	);
	// Attach a slider (0-20px) for the border thickness.
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

	// Register the resting border color setting: empty default, hex sanitizer.
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_color",
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_hex_color',
		)
	);
	// Attach a color-picker for the resting border color.
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

	// Register the hover border color setting: empty default, hex sanitizer.
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_hover_color",
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_hex_color',
		)
	);
	// Attach a color-picker for the hover border color.
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

	// Register the border radius setting: 0 default, numeric sanitizer.
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_radius",
		array(
			'default'           => 0,
			'sanitize_callback' => 'wpstream_sanitize_number_field',
		)
	);
	// Attach a slider (0-50px) for the corner radius.
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

/**
 * Register an opacity slider (0-100%) for the button.
 *
 * @param WP_Customize_Manager $wp_customize The Customizer manager instance.
 * @param string               $button_type  Machine name of the button type.
 * @param string               $setting_name Aspect suffix; "_opacity" is appended to the setting ID.
 * @return void
 */
function wpstream_add_opacity_control($wp_customize, $button_type, $setting_name) {
	// Register the opacity setting: 100 (fully opaque) default, numeric sanitizer.
	$wp_customize->add_setting(
		"wpstream_{$button_type}_{$setting_name}_opacity",
		array(
			'default'           => 100,
			'sanitize_callback' => 'wpstream_sanitize_number_field',
		)
	);
	// Attach a percentage slider (0-100%) bound to the opacity setting.
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