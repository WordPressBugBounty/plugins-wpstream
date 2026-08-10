<?php
/**
 * Separator control
 *
 * Registers a settingless Customizer control that outputs a horizontal
 * separator, used to visually divide groups of controls within a section.
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guard against re-declaration if this file is included more than once.
if ( ! class_exists( 'Wpstream_Separator_Control' ) ) {
	/**
	 * Use this control without setting, it will be added automatically.
	 * Use in places where you need to separate controls.
	 */
	class Wpstream_Separator_Control extends WP_Customize_Control {
		/**
		 * Control type.
		 *
		 * @var string
		 */
		public $type = 'wpstream_separator_control';

		/**
		 * Running counter used to generate a unique auto-id per separator instance.
		 *
		 * @var int
		 */
		public static $instance_separator = 0;

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
			// Increment the shared counter so each separator gets a distinct id.
			++self::$instance_separator;
			// Build the auto-generated control id from the counter.
			$id = 'wpstream_separator_' . self::$instance_separator . '_control';

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
			?>
			<!-- Empty div styled by CSS as the visual separator line. -->
			<div class="wpstream-separator-control"></div>
			<?php
		}
	}
}
