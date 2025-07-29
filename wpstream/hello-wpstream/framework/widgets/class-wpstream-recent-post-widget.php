<?php
/**
 * Recent post widget
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
			$widget_ops = array(
				'description' => __( 'Your site&#8217;s most recent Posts.', 'hello-wpstream' ),
			);
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
			$default_title = __( 'Recent Posts', 'hello-wpstream' );
			$title         = ( ! empty( $instance['title'] ) ) ? $instance['title'] : $default_title;
			$number        = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 3;

			$r = new WP_Query(
				array(
					'posts_per_page'      => $number,
					'no_found_rows'       => true,
					'post_status'         => 'publish',
					'ignore_sticky_posts' => true,
				)
			);

			if ( ! $r->have_posts() ) {
				return;
			}
			?>

			<?php echo wp_kses_post( $args['before_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<?php
			if ( $title ) {
                //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo wp_kses_post($args['before_title']) . esc_html( $title ) .wp_kses_post( $args['after_title']);
			}
			?>

			<ul class="wpstream-recent-post">
				<?php
				while ( $r->have_posts() ) {
					echo '<li class="">';
						$r->the_post();
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
				wp_reset_postdata();
				?>
			</ul>

			<?php

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
			$instance           = $old_instance;
			$instance['title']  = sanitize_text_field( $new_instance['title'] );
			$instance['number'] = (int) $new_instance['number'];

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
			$title  = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
			$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 3;
			?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'hello-wpstream' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Number of posts to show:', 'hello-wpstream' ); ?></label>
				<input class="tiny-text" id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="number" step="1" min="1" value="<?php echo esc_attr( $number ); ?>" size="3" />
			</p>
			<?php
		}
	}
}
