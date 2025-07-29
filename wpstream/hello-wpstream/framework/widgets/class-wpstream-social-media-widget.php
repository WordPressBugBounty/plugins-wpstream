<?php
/**
 * Social media widget
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wpstream_Social_Media_Widget' ) ) {
	/**
	 * Widget for displaying social media links with icons.
	 */
	class Wpstream_Social_Media_Widget extends Wpstream_Widget_Base {
		/**
		 * Array of social media networks and their labels.
		 *
		 * @var array
		 */
		public $networks = array(
			'facebook'     => 'Facebook',
			'whatsapp'     => 'WhatsApp',
			'telegram'     => 'Telegram',
			'tiktok'       => 'TikTok',
			'rss'          => 'Rss',
			'x_twitter'    => 'X(Twitter)',
			'dribbble'     => 'Dribbble',
			'linkedin'     => 'LinkedIn',
			'pinterest'    => 'Pinterest',
			'youtube'      => 'YouTube',
			'vimeo'        => 'Vimeo',
			'instagram'    => 'Instagram',
			'fourthsquare' => 'FourthSquare',
			'wechat'       => 'WeChat',
		);

		/**
		 * Construct
		 */
		public function __construct() {
			parent::__construct(
				'wpstream_social_media_widget',
				esc_html__( 'Wpstream Social Media Widget', 'hello-wpstream' ),
				array(
					'description' => esc_html__( 'Display social media links with icons.', 'hello-wpstream' ),
				)
			);
		}

		/**
		 * Outputs the content of the widget.
		 *
		 * @param array $args     Widget arguments.
		 * @param array $instance Saved values from database.
		 */
		public function widget( $args, $instance ) {
			if ( ! empty( $instance ) ) {
				echo wp_kses_post($args['before_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>

				<ul class="wpstream-social-media-list d-flex align-items-center flex-wrap justify-content-start">

					<?php
					foreach ( $instance as $network => $url ) {
						if ( ! empty( $url ) ) {
							$icon_class = 'wpstream-' . $network;
							?>
							<li>
								<a class="d-flex align-items-center justify-content-center rounded-circle"
									href="<?php echo esc_attr( $url ); ?>" class="<?php echo esc_attr( $icon_class ); ?>"
									target="_blank" rel="nofollow noopener noreferrer">
									<?php echo wpstream_theme_get_svg_icon( $network . '.svg' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</a>
							</li>
							<?php
						}
					}
					?>

				</ul>

				<?php
				echo wp_kses_post( $args['after_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		/**
		 * Outputs the settings form for the widget.
		 *
		 * @param array $instance The current values of the widget instance.
		 */
		public function form( $instance ) {
			foreach ( $this->networks as $network => $label ) {
				$url = ! empty( $instance[ $network ] ) ? $instance[ $network ] : '';
				?>
				<p>
					<label for="<?php echo esc_attr( $this->get_field_id( $network ) ); ?>">
						<?php echo esc_html( $label ); ?> <?php esc_html_e( 'Link', 'hello-wpstream' ); ?>:</label>
					<input class="widefat"
							id="<?php echo esc_attr( $this->get_field_id( $network ) ); ?>"
							name="<?php echo esc_attr( $this->get_field_name( $network ) ); ?>" type="url"
							value="<?php echo esc_url( $url ); ?>">
				</p>
				<?php
			}
		}

		/**
		 * Updates the widget settings.
		 *
		 * @param array $new_instance The new instance of settings.
		 * @param array $old_instance The old instance of settings.
		 * @return array Updated instance of settings.
		 */
		public function update( $new_instance, $old_instance ) {
			$instance = array();

			foreach ( $this->networks as $network => $label ) {
				if ( ! empty( $new_instance[ $network ] ) ) {
					$instance[ $network ] = esc_url( $new_instance[ $network ] );
				}
			}

			return $instance;
		}
	}
}
