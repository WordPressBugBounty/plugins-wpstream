<?php
/**
 * Slides control
 *
 * Registers a custom Customizer control that exposes four text inputs
 * (top/right/bottom/left), used to capture spacing values such as padding or
 * margin as a grouped setting.
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guard against re-declaration if this file is included more than once.
if ( ! class_exists( 'Wpstream_Slides_Control' ) ) {
	/**
	 * Custom control for managing slide settings.
	 */
	class Wpstream_Slides_Control extends WP_Customize_Control {
		/**
		 * Control type.
		 *
		 * @var string
		 */
		public $type = 'wpstream_slides_control';

		/**
		 * Constructor method.
		 *
		 * @param WP_Customize_Manager $manager Customizer manager instance.
		 * @param string               $id Control ID.
		 * @param array                $args Additional arguments.
		 */
		public function __construct( $manager, $id, $args = array() ) {
			// Initialize the base control (label, description, settings, etc.).
			parent::__construct( $manager, $id, $args );

			// Fallback bounds/step/unit used when the caller omits them.
			$defaults = array(
				'min'  => 0,
				'max'  => 30,
				'step' => 1,
				'unit' => '',
			);

			// Merge caller-supplied args over the defaults.
			$args = wp_parse_args( $args, $defaults );

			// Store the resolved min/max bounds shared by all four inputs.
			// Note: 'step' and 'unit' are parsed above but not assigned here.
			$this->min  = $args['min'];
			$this->max  = $args['max'];
		}

		/**
		 * Render content for the custom control.
		 *
		 * @return void
		 */
		public function render_content() {
			?>

			<!-- Show the control title only when a label was provided. -->
			<?php if ( ! empty( $this->label ) ) : ?>
				<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
			<?php endif; ?>

			<!-- Show the helper description only when one was provided. -->
			<?php if ( ! empty( $this->description ) ) : ?>
				<span class="customize-control-description"><?php echo esc_html( $this->description ); ?></span>
			<?php endif; ?>


			<!-- Container for the four spacing inputs (top / right / bottom / left). -->
			<div class="padding-margin-control">
				<!--top-->
				<!-- Each input binds to a named sub-value of the setting via $this->link( 'side' ). -->
				<label for="wpstream-control-top">
					<input class="range-slider"
							id="wpstream-control-top"
							min="<?php echo esc_attr( $this->min ); ?>"
							max="<?php echo esc_attr( $this->max ); ?>"
							type="text"
						<?php $this->link( 'top' ); ?>
							value="<?php echo esc_attr( $this->value( 'top' ) ); ?>">
                    <span class="side-label"><?php esc_html_e( 'Top', 'hello-wpstream' ); ?></span>
				</label>

				<!-- Right-->
				<label for="wpstream-control-right">
					<input class="range-slider"
							id="wpstream-control-right"
							min="<?php echo esc_attr( $this->min ); ?>"
							max="<?php echo esc_attr( $this->max ); ?>"
							type="text"
						<?php $this->link( 'right' ); ?>
							value="<?php echo esc_attr( $this->value( 'right' ) ); ?>">
                    <span class="side-label"><?php esc_html_e( 'Right', 'hello-wpstream' ); ?></span>
				</label>

				<!-- Bottom-->
				<label for="wpstream-control-bottom">
					<input class="range-slider"
							id="wpstream-control-bottom"
							min="<?php echo esc_attr( $this->min ); ?>"
							max="<?php echo esc_attr( $this->max ); ?>"
							type="text"
						<?php $this->link( 'bottom' ); ?>
							value="<?php echo esc_attr( $this->value( 'bottom' ) ); ?>">
                    <span class="side-label"><?php esc_html_e( 'Bottom', 'hello-wpstream' ); ?></span>
				</label>

				<!-- Left-->
				<label for="wpstream-control-left">
					<input class="range-slider"
							id="wpstream-control-left"
							min="<?php echo esc_attr( $this->min ); ?>"
							max="<?php echo esc_attr( $this->max ); ?>"
							type="text"
							<?php $this->link( 'left' ); ?>
							value="<?php echo esc_attr( $this->value( 'left' ) ); ?>">
                    <span class="side-label"><?php esc_html_e( 'Left', 'hello-wpstream' ); ?></span>
				</label>

			</div>

			<?php
		}
	}
}
