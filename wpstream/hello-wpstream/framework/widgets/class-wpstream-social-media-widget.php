<?php
/**
 * Social media widget
 *
 * Registers a classic WordPress widget that renders a row of social-network
 * icon links. The admin form exposes one URL field per supported network; the
 * front-end output prints only the networks the site owner filled in, each as
 * an SVG icon wrapped in an anchor.
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
			// Register with WP_Widget: id base, admin title, and options (description).
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
			// Only render when the widget has at least one saved value.
			if ( ! empty( $instance ) ) {
				// Print the theme/sidebar-provided opening wrapper markup.
				echo wp_kses_post($args['before_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>

				<!-- Flex row that holds one <li> per configured social network. -->
				<ul class="wpstream-social-media-list d-flex align-items-center flex-wrap justify-content-start">

					<?php
					// Walk every saved network => url pair for this widget instance.
					foreach ( $instance as $network => $url ) {
						// Skip networks the site owner left blank.
						if ( ! empty( $url ) ) {
							// Per-network CSS hook, e.g. "wpstream-facebook".
							$icon_class = 'wpstream-' . $network;
							?>
							<!-- Icon link for a single network; opens in a new tab. -->
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
				// Print the theme/sidebar-provided closing wrapper markup.
				echo wp_kses_post( $args['after_widget']); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		/**
		 * Outputs the settings form for the widget.
		 *
		 * @param array $instance The current values of the widget instance.
		 */
		public function form( $instance ) {
			// Render one URL input row for every supported network.
			foreach ( $this->networks as $network => $label ) {
				// Pre-fill with the saved URL, or empty string when unset.
				$url = ! empty( $instance[ $network ] ) ? $instance[ $network ] : '';
				?>
				<!-- Labelled URL field for a single network. -->
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
			// Rebuild the stored instance from scratch on every save.
			$instance = array();

			// Keep only the networks that were submitted with a value.
			foreach ( $this->networks as $network => $label ) {
				if ( ! empty( $new_instance[ $network ] ) ) {
					// Sanitize each URL before persisting it.
					$instance[ $network ] = esc_url( $new_instance[ $network ] );
				}
			}

			// Hand the cleaned values back to WordPress for storage.
			return $instance;
		}
	}
}
