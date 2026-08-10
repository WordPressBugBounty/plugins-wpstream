<?php
/**
 * Recent coments widget
 *
 * Classic WP_Widget (via Wpstream_Widget_Base) that lists the site's most
 * recent approved comments, each with the commenter's avatar, name, relative
 * date, excerpt and a link to the commented post. Provides the front-end
 * render, the settings-save handler and the admin form.
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define the widget class only once.
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
			// Widget metadata shown in the admin widgets screen.
			$widget_ops = array(
				'description' => __( 'Your site&#8217;s most recent comments.', 'hello-wpstream' ),
			);
			// Register the widget id base, admin title and options.
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
			// Tracks whether this is the first rendered instance (for a unique <ul> id).
			static $first_instance = true;

			// Resolve the widget title (falls back to a default label).
			$default_title = __( 'Recent Comments', 'hello-wpstream' );
			$title         = ( ! empty( $instance['title'] ) ) ? $instance['title'] : $default_title;
			// Number of comments to display (defaults to 3).
			$number        = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : 3;

			// Fetch the most recent approved comments on published posts (args filterable).
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

			// Nothing to show if there are no comments.
			if ( ! is_array( $comments ) || empty( $comments ) ) {
				return;
			}

			// Output the theme's opening widget wrapper.
			echo wp_kses_post($args['before_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// Render the widget title, wrapped in the theme's before/after title markup.
			if ( $title ) {
				echo wp_kses_post($args['before_title']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo esc_html( $title );
				echo wp_kses_post($args['after_title']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}

			// Use a stable id for the first instance; suffix later instances to keep ids unique.
			$recent_comments_id = ( $first_instance ) ? 'wpstream-recent-comments' : "wpstream-recent-comments-{$this->number}";
			$first_instance     = false;

			// Open the comments list.
			echo '<ul id="' . esc_attr( $recent_comments_id ) . '" class="wpstream-recent-comments d-flex flex-column">';

			// Render one list item per comment.
			foreach ( (array) $comments as $comment ) { ?>
				<!-- Single recent-comment row: avatar on the left, body on the right. -->
				<li class="wpstream-recent-comment d-flex align-items-start">
					<!-- Commenter avatar (48px), resolved from the author id. -->
					<img class="wpstream-recent-comment-author-img rounded-circle object-fit-cover"
						src="<?php echo esc_url( wpstream_get_author_profile_image_url_by_author_id( $comment->user_id, 48 ) ); ?>"
						alt="<?php echo esc_attr( get_comment_author( $comment ) ); ?>">

					<!-- Comment body: author, relative date, excerpt and post link. -->
					<div class="wpstream-recent-comment-body">
						<p class="d-flex flex-wrap m-0">
							<!-- Comment author display name. -->
							<span class="wpstream-recent-comment-author "><?php echo esc_html( get_comment_author( $comment ) ); ?></span>
							<?php
								// Show a relative "time ago" label when the helper exists.
								if ( function_exists( 'wpstream_get_published_duration_by_date_time ' ) ) {
									echo '<span class="wpstream-recent-comment-date text-gray">' . esc_html( wpstream_get_published_duration_by_date_time( $comment->comment_date ) ) . '</span>';
								}
							?>
						</p>
						<!-- The comment text/excerpt. -->
						<p class="wpstream-recent-comment-text m-0">
							<?php echo esc_html( get_comment_text( $comment ) ); ?>
						</p>
						<!-- "In <post title>" link back to the commented post. -->
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

			// Close the comments list.
			echo '</ul>';

			// Output the theme's closing widget wrapper.
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
			// Start from the old settings, then overwrite with sanitized new values.
			$instance           = $old_instance;
			$instance['title']  = sanitize_text_field( $new_instance['title'] );
			$instance['number'] = absint( $new_instance['number'] );

			// Return the sanitized settings to be persisted.
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
			// Current values (or sensible defaults) for the form fields.
			$title  = isset( $instance['title'] ) ? $instance['title'] : '';
			$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 3;
			?>
			<!-- Title text field. -->
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'hello-wpstream' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text"
						value="<?php echo esc_attr( $title ); ?>"/>
			</p>

			<!-- Number-of-comments numeric field (minimum 1). -->
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
