<?php
/**
 * Recent coments widget
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wpstream_Recent_Comments_Widget' ) ) {
	/**
	 * Widget class for displaying recent comments.
	 *
	 * This widget displays the most recent comments on the site.
	 *
	 * @since 2.8.0
	 */
	class Wpstream_Recent_Comments_Widget extends Wpstream_Widget_Base {
		/**
		 * Sets up a new widget instance.
		 *
		 * @since 2.8.0
		 */
		public function __construct() {
			$widget_ops = array(
				'description' => __( 'Your site&#8217;s most recent comments.', 'hello-wpstream' ),
			);
			parent::__construct( 'wpstream-recent-comments', __( 'Wpstream Recent Comments', 'hello-wpstream' ), $widget_ops );
		}

		/**
		 * Outputs the content for the current widget instance.
		 *
		 * @param array $args     Display arguments including 'before_title', 'after_title',
		 *                        'before_widget', and 'after_widget'.
		 * @param array $instance Settings for the current widget instance.
		 *
		 * @since       2.8.0
		 * @since       5.4.0 Creates a unique HTML ID for the `<ul>` element
		 *              if more than one instance is displayed on the page.
		 */
		public function widget( $args, $instance ) {
			static $first_instance = true;

			$default_title = __( 'Recent Comments', 'hello-wpstream' );
			$title         = ( ! empty( $instance['title'] ) ) ? $instance['title'] : $default_title;
			$number        = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 3;

			$comments = get_comments(
				apply_filters(
					'widget_comments_args',
					array(
						'number'      => $number,
						'status'      => 'approve',
						'post_status' => 'publish',
					),
					$instance
				)
			);

			if ( ! is_array( $comments ) || empty( $comments ) ) {
				return;
			}

			echo wp_kses_post($args['before_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			if ( $title ) {
				echo wp_kses_post($args['before_title']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo esc_html( $title );
				echo wp_kses_post($args['after_title']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			$recent_comments_id = ( $first_instance ) ? 'wpstream-recent-comments' : "wpstream-recent-comments-{$this->number}";
			$first_instance     = false;

			echo '<ul id="' . esc_attr( $recent_comments_id ) . '" class="wpstream-recent-comments d-flex flex-column">';

			foreach ( (array) $comments as $comment ) { ?>
				<li class="wpstream-recent-comment d-flex align-items-start">
					<img class="wpstream-recent-comment-author-img rounded-circle object-fit-cover"
						src="<?php echo esc_url( wpstream_get_author_profile_image_url_by_author_id( $comment->user_id, 48 ) ); ?>"
						alt="<?php echo esc_attr( get_comment_author( $comment ) ); ?>">

					<div class="wpstream-recent-comment-body">
						<p class="d-flex flex-wrap m-0">
							<span class="wpstream-recent-comment-author "><?php echo esc_html( get_comment_author( $comment ) ); ?></span>
							<span class="wpstream-recent-comment-date text-gray"><?php echo esc_html( wpstream_get_published_duration_by_date_time( $comment->comment_date ) ); ?></span>
						</p>
						<p class="wpstream-recent-comment-text m-0">
							<?php echo esc_html( get_comment_text( $comment ) ); ?>
						</p>
						<p class="m-0">
							<span class="text-gray"><?php echo esc_html_x( 'In', 'widget', 'hello-wpstream' ); ?></span>
							<a class="" href="<?php echo esc_url( get_comment_link( $comment ) ); ?>">
								<?php echo esc_html( get_the_title( $comment->comment_post_ID ) ); ?>
							</a>
						</p>
					</div>
				</li>

				<?php
			}

			echo '</ul>';

			echo wp_kses_post ( $args['after_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Handles updating settings for the current widget instance.
		 *
		 * @param array $new_instance New settings for this instance as input by the user via WP_Widget::form().
		 * @param array $old_instance Old settings for this instance.
		 *
		 * @return array Updated settings to save.
		 * @since 2.8.0
		 */
		public function update( $new_instance, $old_instance ) {
			$instance           = $old_instance;
			$instance['title']  = sanitize_text_field( $new_instance['title'] );
			$instance['number'] = absint( $new_instance['number'] );

			return $instance;
		}

		/**
		 * Outputs the settings form for the widget.
		 *
		 * @param array $instance Current settings.
		 *
		 * @since 2.8.0
		 */
		public function form( $instance ) {
			$title  = isset( $instance['title'] ) ? $instance['title'] : '';
			$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 3;
			?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'hello-wpstream' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
						value="<?php echo esc_attr( $title ); ?>"/>
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Number of comments to show:', 'hello-wpstream' ); ?></label>
				<input class="tiny-text"
						id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="number" step="1" min="1"
						value="<?php echo absint( $number ); ?>" size="3"/>
			</p>
			<?php
		}
	}
}
