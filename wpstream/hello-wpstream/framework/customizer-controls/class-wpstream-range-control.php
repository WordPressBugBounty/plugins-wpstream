<?php
/**
 * Range control
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

			parent::__construct( $manager, $id, $args );

			$defaults = array(
				'min'  => 8,
				'max'  => 30,
				'step' => 1,
				'unit' => '',
			);

			$args = wp_parse_args( $args, $defaults );

			$this->min  = $args['min'];
			$this->max  = $args['max'];
			$this->step = $args['step'];
			$this->unit = $args['unit'];
		}

		/**
		 * Render content for the custom control.
		 */
		public function render_content() {
			?>
			<label>
				<?php if ( ! empty( $this->label ) ) : ?>
					<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
				<?php endif; ?>
				<div class="wpstream_customizer_slider_wrapper" >
					<input class="range-slider"
							min="<?php echo esc_attr( $this->min ); ?>"
							max="<?php echo esc_attr( $this->max ); ?>"
							step="<?php echo esc_attr( $this->step ); ?>"
							type="range"
							data-unit="<?php echo esc_attr( $this->unit ); ?>"
						    <?php $this->link(); ?>
							value="<?php echo esc_attr( $this->value() ); ?>"
                    >
                    <input class="range-input"
                           type="number"
                           min="<?php echo esc_attr( $this->min ); ?>"
                           max="<?php echo esc_attr( $this->max ); ?>"
                           step="<?php echo esc_attr( $this->step ); ?>"
                           value="<?php echo esc_attr( $this->value() ); ?>"
	                        <?php $this->link(); ?>
                    >
                <?php if (!empty($this->unit)): ?>
					<span class="wpstream_customizer_slider_value" ><?php echo esc_html( $this->unit ); ?></span>
                <?php endif; ?>
				</div>
			</label>
			<?php
		}
	}
}
