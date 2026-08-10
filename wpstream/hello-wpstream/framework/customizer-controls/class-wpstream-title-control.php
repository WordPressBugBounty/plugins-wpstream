<?php
/**
 * Title control
 *
 * Registers a settingless Customizer control that outputs a section title,
 * used to visually label/group other controls within a Customizer section.
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guard against re-declaration if this file is included more than once.
if ( ! class_exists( 'Wpstream_Title_Control' ) ) {
	/**
	 * Use in places where you need to add a section title.
	 */
	class Wpstream_Title_Control extends WP_Customize_Control {
		/**
		 * Control type.
		 *
		 * @var string
		 */
		public $type = 'wpstream_title_control';

		/**
		 * Running counter used to generate a unique auto-id per title instance.
		 *
		 * @var int
		 */
		public static $instance_title = 0;

		/**
		 * Constructor method.
		 *
		 * @param WP_Customize_Manager $manager Customizer manager instance.
		 * @param array                $args    Additional arguments.
		 */
		public function __construct( $manager, $args = array() ) {
			// This control carries no real setting; drop any passed settings arg.
			unset( $args['settings'] );
			// dynamic id
			// Increment the shared counter so each title gets a distinct id.
			++self::$instance_title;
			// Build the auto-generated control id from the counter.
			$id = 'wpstream_title_' . self::$instance_title . '_control';

			// Register a placeholder setting for the generated id.
			$manager->add_setting( $id );

			// Hand off to the base control constructor with the generated id.
			parent::__construct( $manager, $id, $args );
		}

		/**
		 * Render content for the custom control.
		 *
		 * @return void
		 */
		public function render_content() {
			// Only output the title markup when a label was provided.
			if ( ! empty( $this->label ) ) {
				// Echo the label as a Customizer control title.
				echo '<span class="customize-control-title">' . esc_html( $this->label ) . '</span>';
			}
		}
	}
}
