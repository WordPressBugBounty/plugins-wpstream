<?php
/**
 * Separator control
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
			unset( $args['settings'] );
			// dynamic id
			++self::$instance_separator;
			$id = 'wpstream_separator_' . self::$instance_separator . '_control';

			$manager->add_setting( $id );

			parent::__construct( $manager, $id, $args );
		}

		/**
		 * Render content for the custom control.
		 */
		public function render_content() {
			?>
			<div class="wpstream-separator-control"></div>
			<?php
		}
	}
}
