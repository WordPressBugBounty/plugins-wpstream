<?php
/**
 * Recent post widget
 *
 * Classic WP_Widget (via Wpstream_Widget_Base) that lists the site's most
 * recent published posts, rendering each through the theme's blog card
 * template part. Provides the front-end render, the settings-save handler and
 * the admin form.
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define the widget class only once.
if ( ! class_exists( 'Wpstream_Recent_Post_Widget' ) ) {
	/**
	 * Widget for displaying the most recent posts.
	 *
	 * @since 2.8.0
	 */
	class Wpstream_Recent_Post_Widget extends Wpstream_Widget_Base {

		/**
		 * Sets up a new widget instance.
		 *
		 * @since 2.8.0
		 */
		public function __construct() {
			// Widget metadata shown in the admin widgets screen.
			$widget_ops = array(
				'description' => __( 'Your site&#8217;s most recent Posts.', 'hello-wpstream' ),
			);
			// Register the widget id base, admin title and options.
			parent::__construct( 'wpstream-recent-posts', __( 'Wpstream Recent Posts', 'hello-wpstream' ), $widget_ops );
		}

		/**
		 * Outputs the content for the current widget instance.
		 *
		 * @since 2.8.0
		 *
		 * @param array $args     Display arguments including 'before_title', 'after_title',
		 *                        'before_widget', and 'after_widget'.
		 * @param array $instance Settings for the current widget instance.
		 */
		public function widget( $args, $instance ) {
			// Resolve the widget title (falls back to a default label).
			$default_title = __( 'Recent Posts', 'hello-wpstream' );
			$title         = ( ! empty( $instance['title'] ) ) ? $instance['title'] : $default_title;
			// Number of posts to display (defaults to 3).
			$number        = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 3;

			// Query the latest published posts (skip found-rows and sticky handling for speed).
			$r = new WP_Query(
				array(
					'posts_per_page'      => $number,
					'no_found_rows'       => true,
					'post_status'         => 'publish',
					'ignore_sticky_posts' => true,
				)
			);

			// Nothing to render if there are no posts.
			if ( ! $r->have_posts() ) {
				return;
			}
			?>

			<!-- Output the theme's opening widget wrapper. -->
			<?php echo wp_kses_post( $args['before_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<?php
			// Render the title wrapped in the theme's before/after title markup.
			if ( $title ) {
                //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wp_kses_post($args['before_title']) . esc_html( $title ) .wp_kses_post( $args['after_title']);
			}
			?>

			<!-- Recent posts list; each item rendered via the blog card template part. -->
			<ul class="wpstream-recent-post">
				<?php
				// Loop each post and output it through the blog-card template part.
				while ( $r->have_posts() ) {
					echo '<li class="">';
						$r->the_post();
						// Render the post using the "widget" variant of the blog card, no category shown.
						get_template_part(
							'template-parts/single/cards/blog-card-v1',
							'',
							array(
								'type'          => 'widget',
								'class'         => '',
								'show_category' => false,
							)
						);
					echo '</li>';
				}
				// Restore the main query's post data after the custom loop.
				wp_reset_postdata();
				?>
			</ul>

			<?php

			// Output the theme's closing widget wrapper.
			echo wp_kses_post($args['after_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Handles updating the settings for the current widget instance.
		 *
		 * @since 2.8.0
		 *
		 * @param array $new_instance New settings for this instance as input by the user via
		 *                            WP_Widget::form().
		 * @param array $old_instance Old settings for this instance.
		 * @return array Updated settings to save.
		 */
		public function update( $new_instance, $old_instance ) {
			// Start from the old settings, then overwrite with sanitized new values.
			$instance           = $old_instance;
			$instance['title']  = sanitize_text_field( $new_instance['title'] );
			$instance['number'] = (int) $new_instance['number'];

			// Return the sanitized settings to be persisted.
			return $instance;
		}

		/**
		 * Outputs the settings form for the widget.
		 *
		 * @since 2.8.0
		 *
		 * @param array $instance Current settings.
		 */
		public function form( $instance ) {
			// Current values (or sensible defaults) for the form fields.
			$title  = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
			$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 3;
			?>
			<!-- Title text field. -->
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'hello-wpstream' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>

			<!-- Number-of-posts numeric field (minimum 1). -->
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Number of posts to show:', 'hello-wpstream' ); ?></label>
				<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="number" step="1" min="1" value="<?php echo esc_attr( $number ); ?>" size="3" />
			</p>
			<?php
		}
	}
}
