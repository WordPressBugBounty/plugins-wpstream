<?php
/**
 * Range control
 *
 * Registers a custom Customizer control that pairs a range slider with a
 * synced number input (and an optional unit label) for numeric theme options.
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guard against re-declaration if this file is included more than once.
if ( ! class_exists( 'Wpstream_Range_Control' ) ) {
	/**
	 * Custom control for range settings.
	 */
	class Wpstream_Range_Control extends WP_Customize_Control {
		/**
		 * Control type.
		 *
		 * @var string
		 */
		public $type = 'wpstream_range';

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
				'min'  => 8,
				'max'  => 30,
				'step' => 1,
				'unit' => '',
			);

			// Merge caller-supplied args over the defaults.
			$args = wp_parse_args( $args, $defaults );

			// Store the resolved slider bounds, step and unit on the instance.
			$this->min  = $args['min'];
			$this->max  = $args['max'];
			$this->step = $args['step'];
			$this->unit = $args['unit'];
		}

		/**
		 * Render content for the custom control.
		 *
		 * @return void
		 */
		public function render_content() {
			?>
			<!-- Label wraps both inputs so clicking the title focuses the control. -->
			<label>
				<!-- Show the control title only when a label was provided. -->
				<?php if ( ! empty( $this->label ) ) : ?>
					<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php endif; ?>
				<!-- Wrapper holding the slider, its number input and the unit label. -->
				<div class="wpstream_customizer_slider_wrapper" >
					<!-- Range slider bound to the setting via $this->link(); JS keeps it in sync with the number input. -->
					<input class="range-slider"
							min="<?php echo esc_attr( $this->min ); ?>"
							max="<?php echo esc_attr( $this->max ); ?>"
							step="<?php echo esc_attr( $this->step ); ?>"
							type="range"
							data-unit="<?php echo esc_attr( $this->unit ); ?>"
						    <?php $this->link(); ?>
							value="<?php echo esc_attr( $this->value() ); ?>"
                    >
                    <!-- Number input mirroring the slider so the exact value can be typed. -->
                    <input class="range-input"
                           type="number"
                           min="<?php echo esc_attr( $this->min ); ?>"
                           max="<?php echo esc_attr( $this->max ); ?>"
                           step="<?php echo esc_attr( $this->step ); ?>"
                           value="<?php echo esc_attr( $this->value() ); ?>"
	                        <?php $this->link(); ?>
                    >
                <!-- Append the unit (e.g. px, %) after the inputs when one is set. -->
                <?php if (!empty($this->unit)): ?>
					<span class="wpstream_customizer_slider_value" ><?php echo esc_html( $this->unit ); ?></span>
                <?php endif; ?>
				</div>
			</label>
			<?php
		}
	}
}
