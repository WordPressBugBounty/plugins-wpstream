<?php
/**
 * Widget base
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wpstream_Widget_Base' ) ) {
	/**
	 * Base class for custom widgets.
	 */
	class Wpstream_Widget_Base extends WP_Widget {
		/**
		 * Settings.
		 *
		 * @var array
		 */
		public $settings;

		/**
		 * The method return an array of class names to be deleted.
		 *
		 * @return array
		 */
		public static function get_widgets_for_unregister(): array {
			return array();
		}

		/**
		 * Updates a particular instance of a widget.
		 *
		 * @param array $new_instance New instance.
		 * @param array $old_instance Old instance.
		 *
		 * @return array
		 * @see    WP_Widget->update
		 */
		public function update( $new_instance, $old_instance ) {
			$instance = $old_instance;

			if ( empty( $this->settings ) ) {
				return $instance;
			}

			// Loop settings and get values to save.
			foreach ( $this->settings as $key => $setting ) {
				if ( ! isset( $setting['type'] ) ) {
					continue;
				}

				// Format the value based on settings type.
				switch ( $setting['type'] ) {
					case 'number':
						$instance[ $key ] = absint( $new_instance[ $key ] );

						if ( isset( $setting['min'] ) && '' !== $setting['min'] ) {
							$instance[ $key ] = max( $instance[ $key ], $setting['min'] );
						}

						if ( isset( $setting['max'] ) && '' !== $setting['max'] ) {
							$instance[ $key ] = min( $instance[ $key ], $setting['max'] );
						}
						break;
					case 'textarea':
						$instance[ $key ] = wp_kses( trim( wp_unslash( $new_instance[ $key ] ) ), wp_kses_allowed_html( 'post' ) );
						break;
					case 'checkbox':
						$instance[ $key ] = empty( $new_instance[ $key ] ) ? 0 : 1;
						break;
					default:
						$instance[ $key ] = isset( $new_instance[ $key ] ) ? sanitize_text_field( $new_instance[ $key ] ) : $setting['std'];
						break;
				}
			}

			return $instance;
		}

		/**
		 * Outputs the settings update form.
		 *
		 * @param array $instance Instance.
		 *
		 * @see   WP_Widget->form
		 */
		public function form( $instance ) {
			if ( empty( $this->settings ) ) {
				return;
			}

			foreach ( $this->settings as $key => $setting ) {
				if ( ! isset( $setting['type'] ) ) {
					continue;
				}

				$class = $setting['class'] ?? '';
				$value = $instance[ $key ] ?? ( $setting['std'] ?? '' );

				switch ( $setting['type'] ) {
					case 'text':
						?>
						<p>
							<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo wp_kses_post( $setting['label'] ); ?></label>
													<?php
                            // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
													?>
							<input class="widefat <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="text" value="<?php echo esc_attr( $value ); ?>"/>
						</p>
						<?php
						break;

					case 'number':
						?>
						<p>
							<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $setting['label'] ); ?></label>
							<input class="widefat <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="number" step="<?php echo esc_attr( $setting['step'] ); ?>" min="<?php echo esc_attr( $setting['min'] ); ?>" max="<?php echo esc_attr( $setting['max'] ); ?>" value="<?php echo esc_attr( $value ); ?>"/>
						</p>
						<?php
						break;

					case 'select':
						?>
						<p>
							<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $setting['label'] ); ?></label>
							<select class="widefat <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>">
								<?php foreach ( $setting['options'] as $option_key => $option_value ) : ?>
									<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( $option_key, $value ); ?>><?php echo esc_html( $option_value ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<?php
						break;

					case 'textarea':
						?>
						<p>
							<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $setting['label'] ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></label>
							<textarea class="widefat <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" cols="20" rows="3"><?php echo esc_textarea( $value ); ?></textarea>
							<?php if ( isset( $setting['desc'] ) ) : ?>
								<small><?php echo esc_html( $setting['desc'] ); ?></small>
							<?php endif; ?>
						</p>
						<?php
						break;

					case 'checkbox':
						?>
						<p>
							<input class="checkbox <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="checkbox" value="1" <?php checked( $value, 1 ); ?> />
							<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $setting['label'] ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?></label>
						</p>
						<?php
						break;

					// Default: run an action.
					default:
						break;
				}
			}
		}

		/**
		 * Output the html at the start of a widget.
		 *
		 * @param array $args     Arguments.
		 * @param array $instance Instance.
		 */
		public function widget_start( $args, $instance ) {
			echo wp_kses_post( $args['before_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			$title = '';

			if ( isset( $this->settings, $this->settings['title'], $this->settings['title']['std'] ) ) {
				$title = $this->settings['title']['std'];
			}

			if ( isset( $instance['title'] ) ) {
				$title = $instance['title'];
			}

			$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

			if ( $title ) {
                //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wp_kses_post($args['before_title']) . esc_html( $title ) . wp_kses_post($args['after_title']);
			}
		}

		/**
		 * Output the html at the end of a widget.
		 *
		 * @param array $args Arguments.
		 */
		public function widget_end( $args ) {
			echo wp_kses_post($args['after_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
