<?php
/**
 * Toggle switch control
 *
 * Registers a custom Customizer control that renders a checkbox styled as a
 * yes/no toggle switch, used for boolean theme options in the Customizer.
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only declare the control when the base Customizer control class is available
// (it is not loaded on the front end / outside the Customizer context).
if ( class_exists( 'WP_Customize_Control' ) ) {
	/**
	 * Custom Customizer control rendering a boolean value as a yes/no toggle switch.
	 */
	class WP_Customize_Toggle_Control extends WP_Customize_Control {
		/**
		 * Control type identifier used by the Customizer.
		 *
		 * @var string
		 */
		public $type = 'toggle_switch';

		/**
		 * Render the toggle switch markup for the control.
		 *
		 * @return void
		 */
		public function render_content() {
			?>
			<!-- Wrapper for the control's label and the toggle switch. -->
			<div>
				<!-- Control label shown above the switch. -->
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<!-- The toggle switch itself: a checkbox plus its styled label. -->
				<div class="wpstream-theme-toggle-switch">
					<!-- Checkbox bound to the setting via $this->link(); reflects the stored value and checked state. -->
					<input id="<?php echo esc_attr($this->id); ?>" type="checkbox" <?php $this->link(); ?> value="<?php echo esc_attr( $this->value() ); ?>"  <?php checked( $this->value()); ?>>
					<!-- Styled label acting as the visual switch; the on/off spans are shown depending on checked state via CSS. -->
					<label for="<?php echo esc_attr($this->id); ?>" class="wpstream-theme-switch">
                        <!-- Visible when the switch is on. -->
                        <span class="wpstream-theme-switch-on"><?php echo esc_html__('yes', 'hello-wpstream') ?></span>
                        <!-- Visible when the switch is off. -->
                        <span class="wpstream-theme-switch-off"><?php echo esc_html__('no', 'hello-wpstream') ?></span>
                    </label>
				</div>
			</div>
			<?php
		}
	}
}

/**
 * Register the toggle switch control type with the Customizer manager.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager instance.
 * @return void
 */
function wpstream_theme_customize_register( $wp_customize ) {
	// Register our custom control with WP_Customize_Manager
	$wp_customize->register_control_type( 'WP_Customize_Toggle_Control' );
}

// Hook registration into the Customizer bootstrap so the control type is known.
add_action( 'customize_register', 'wpstream_theme_customize_register' );
