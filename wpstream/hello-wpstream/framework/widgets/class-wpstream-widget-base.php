<?php
/**
 * Widget base
 *
 * Shared parent for the theme's classic widgets. Subclasses declare a
 * `$settings` schema (an array of field definitions keyed by option name) and
 * inherit generic implementations of the WP_Widget lifecycle: `update()`
 * sanitizes submitted values by field type, `form()` renders the matching admin
 * controls, and `widget_start()`/`widget_end()` print the sidebar wrappers plus
 * the optional title.
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
			// Start from the previously saved values and overwrite per field.
			$instance = $old_instance;

			// Nothing to sanitize when the subclass declared no schema.
			if ( empty( $this->settings ) ) {
				return $instance;
			}

			// Loop settings and get values to save.
			foreach ( $this->settings as $key => $setting ) {
				// Skip schema entries that do not declare an input type.
				if ( ! isset( $setting['type'] ) ) {
					continue;
				}

				// Format the value based on settings type.
				switch ( $setting['type'] ) {
					case 'number':
						// Coerce to a non-negative integer.
						$instance[ $key ] = absint( $new_instance[ $key ] );

						// Clamp up to the configured minimum, when one is set.
						if ( isset( $setting['min'] ) && '' !== $setting['min'] ) {
							$instance[ $key ] = max( $instance[ $key ], $setting['min'] );
						}

						// Clamp down to the configured maximum, when one is set.
						if ( isset( $setting['max'] ) && '' !== $setting['max'] ) {
							$instance[ $key ] = min( $instance[ $key ], $setting['max'] );
						}
						break;
					case 'textarea':
						// Allow post-level HTML, trimmed and unslashed.
						$instance[ $key ] = wp_kses( trim( wp_unslash( $new_instance[ $key ] ) ), wp_kses_allowed_html( 'post' ) );
						break;
					case 'checkbox':
						// Store a strict 0/1 flag from the presence of the value.
						$instance[ $key ] = empty( $new_instance[ $key ] ) ? 0 : 1;
						break;
					default:
						// Text-like fields: sanitize, or fall back to the default.
						$instance[ $key ] = isset( $new_instance[ $key ] ) ? sanitize_text_field( $new_instance[ $key ] ) : $setting['std'];
						break;
				}
			}

			// Return the fully sanitized instance for storage.
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
			// No schema means there is no admin form to draw.
			if ( empty( $this->settings ) ) {
				return;
			}

			// Emit one control per declared setting.
			foreach ( $this->settings as $key => $setting ) {
				// Skip schema entries with no input type.
				if ( ! isset( $setting['type'] ) ) {
					continue;
				}

				// Optional extra CSS class for the input.
				$class = $setting['class'] ?? '';
				// Current value, falling back to the field's declared default.
				$value = $instance[ $key ] ?? ( $setting['std'] ?? '' );

				// Render the control markup that matches this field type.
				switch ( $setting['type'] ) {
					case 'text':
						?>
						<!-- Single-line text field. -->
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
						<!-- Numeric field with step/min/max constraints. -->
						<p>
							<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $setting['label'] ); ?></label>
							<input class="widefat <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" type="number" step="<?php echo esc_attr( $setting['step'] ); ?>" min="<?php echo esc_attr( $setting['min'] ); ?>" max="<?php echo esc_attr( $setting['max'] ); ?>" value="<?php echo esc_attr( $value ); ?>"/>
						</p>
						<?php
						break;

					case 'select':
						?>
						<!-- Dropdown built from the field's options map. -->
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
						<!-- Multi-line text area, with an optional description below. -->
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
						<!-- On/off checkbox; pre-checked when the stored value is 1. -->
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
			// Print the sidebar's opening wrapper markup.
			echo wp_kses_post( $args['before_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// Resolve the title: schema default first, then the saved instance.
			$title = '';

			// Seed with the "title" field's declared default, if present.
			if ( isset( $this->settings, $this->settings['title'], $this->settings['title']['std'] ) ) {
				$title = $this->settings['title']['std'];
			}

			// A saved instance title overrides the default.
			if ( isset( $instance['title'] ) ) {
				$title = $instance['title'];
			}

			// Let other code filter the final title (WP core convention).
			$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

			// Only output the title block when a title actually exists.
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
			// Print the sidebar's closing wrapper markup.
			echo wp_kses_post($args['after_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
