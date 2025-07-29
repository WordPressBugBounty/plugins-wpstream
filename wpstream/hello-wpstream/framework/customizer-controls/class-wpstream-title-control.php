<?php
/**
 * Title control
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
			unset( $args['settings'] );
			// dynamic id
			++self::$instance_title;
			$id = 'wpstream_title_' . self::$instance_title . '_control';

			$manager->add_setting( $id );

			parent::__construct( $manager, $id, $args );
		}

		/**
		 * Render content for the custom control.
		 */
		public function render_content() {
			if ( ! empty( $this->label ) ) {
				echo '<span class="customize-control-title">' . esc_html( $this->label ) . '</span>';
			}
		}
	}
}
