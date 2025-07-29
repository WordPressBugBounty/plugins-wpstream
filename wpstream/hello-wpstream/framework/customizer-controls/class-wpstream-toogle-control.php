<?php
/**
 * Slides control
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WP_Customize_Control' ) ) {
	class WP_Customize_Toggle_Control extends WP_Customize_Control {
		public $type = 'toggle_switch';

		public function render_content() {
			?>
			<div>
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<div class="wpstream-theme-toggle-switch">
					<input id="<?php echo esc_attr($this->id); ?>" type="checkbox" <?php $this->link(); ?> value="<?php echo esc_attr( $this->value() ); ?>"  <?php checked( $this->value()); ?>>
					<label for="<?php echo esc_attr($this->id); ?>" class="wpstream-theme-switch">
                        <span class="wpstream-theme-switch-on"><?php echo esc_html__('yes', 'hello-wpstream') ?></span>
                        <span class="wpstream-theme-switch-off"><?php echo esc_html__('no', 'hello-wpstream') ?></span>
                    </label>
				</div>
			</div>
			<?php
		}
	}
}

function wpstream_theme_customize_register( $wp_customize ) {
	// Register our custom control with WP_Customize_Manager
	$wp_customize->register_control_type( 'WP_Customize_Toggle_Control' );
}

add_action( 'customize_register', 'wpstream_theme_customize_register' );
