<?php
/**
 * Video widget
 *
 * Classic widget that lists the theme's video posts (live events, VOD, and
 * bundles). The admin form (inherited from Wpstream_Widget_Base) lets the owner
 * choose a title, which post type to show, and how many items; the front-end
 * runs a WP_Query and renders each result through the theme's video card
 * partial.
 *
 * @package wpstream-theme
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wpstream_Video_List_Widget' ) ) {
	/**
	 * Widget for displaying a list of video.
	 *
	 * @since 2.8.0
	 */
	class Wpstream_Video_List_Widget extends Wpstream_Widget_Base {

		/** @var array Selectable video post types, keyed by post type slug => label. */
		public $post_types = array();

		/**
		 * Constructor.
		 */
		public function __construct() {
			// Video post types the widget can list (used for the type dropdown).
			$this->post_types = array(
				'wpstream_product'     => esc_html__( 'Live Events', 'hello-wpstream' ),
				'wpstream_product_vod' => esc_html__( 'Video on Demand', 'hello-wpstream' ),
				'wpstream_bundles'     => esc_html__( 'Video Bundles', 'hello-wpstream' ),
			);

			// Field schema consumed by the base class's form()/update().
			$this->settings = array(
				'title'     => array(
					'type'  => 'text',
					'std'   => __( 'Video List', 'hello-wpstream' ),
					'label' => __( 'Title', 'hello-wpstream' ),
				),
				'post_type' => array(
					'type'    => 'select',
					'std'     => 'all',
					'label'   => __( 'Video item type', 'hello-wpstream' ),
					'options' => array_merge( array( 'all' => esc_html__( 'All', 'hello-wpstream' ) ), $this->post_types ),
				),
				'number'    => array(
					'type'  => 'number',
					'step'  => 1,
					'min'   => 1,
					'max'   => '',
					'std'   => 5,
					'label' => __( 'Number of video to show', 'hello-wpstream' ),
				),

			);

			// Register with WP_Widget: id base, admin title, and options.
			parent::__construct(
				'wpstream-video-list',
				esc_html__( 'Wpstream Video list', 'hello-wpstream' ),
				array(
					'description' => esc_html__( 'A list of video.', 'hello-wpstream' ),
				)
			);
		}

		/**
		 * Query the videos and return them.
		 *
		 * @param array $instance Widget instance.
		 *
		 * @return WP_Query
		 */
		public function get_video( $instance ) {
			
			// Prefer the saved post type; sanitize the stored value.
			if (isset($instance['post_type'])) {
				$post_type = sanitize_text_field($instance['post_type']);
			} else {
				// No saved choice: fall back to the "all" option, or the literal "all".
				if(isset($this->settings['post_type']['all'])){
					$post_type = $this->settings['post_type']['all'];
				}else{
					$post_type ='all';
				}
				
			}
			// Number of items to show, defaulting to the field's std value.
			$number    = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : $this->settings['number']['std'];

			// Unknown/"all" selection: query every supported video post type.
			if ( ! isset( $this->post_types[ $post_type ] ) ) {
				$post_type = array_keys( $this->post_types );
			}

;

			return new WP_Query(
				array(
					'posts_per_page' => $number,
					'post_status'    => 'publish',
					'post_type'      => $post_type,
				)
			);
		}

		/**
		 * Output widget.
		 *
		 * @param array $args Arguments.
		 * @param array $instance Widget instance.
		 *
		 * @see WP_Widget
		 */
		public function widget( $args, $instance ) {

			// Run the configured query for this widget instance.
			$videos = $this->get_video( $instance );

			// Only render the widget when the query returned posts.
			if ( $videos && $videos->have_posts() ) {
				// Sidebar wrapper + optional title.
				$this->widget_start( $args, $instance );

				// Open the list container.
				echo '<ul class="wpstream-video-list-widget">';

				// Emit one list item per matched video post.
				while ( $videos->have_posts() ) {
					echo '<li>';
					// Set up the global post so template tags work inside the card.
					$videos->the_post();
					// Pick the card partial for the current item type.
					$unit_card_type = wpstream_video_item_card_selector();
					// Render the selected card partial from the theme.
					include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;
					echo '</li>';
				}

				// Close the list container.
				echo '</ul>';

				// Sidebar closing wrapper.
				$this->widget_end( $args );
			}

			// Restore the global post after our custom loop.
			wp_reset_postdata();
		}
	}
}
