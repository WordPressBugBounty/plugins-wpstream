<?php
/**
 * Video widget
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

		public $post_types = array();

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->post_types = array(
				'wpstream_product'     => esc_html__( 'Live Events', 'hello-wpstream' ),
				'wpstream_product_vod' => esc_html__( 'Video on Demand', 'hello-wpstream' ),
				'wpstream_bundles'     => esc_html__( 'Video Bundles', 'hello-wpstream' ),
			);

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
			
			if (isset($instance['post_type'])) {
				$post_type = sanitize_text_field($instance['post_type']);
			} else {
				if(isset($this->settings['post_type']['all'])){
					$post_type = $this->settings['post_type']['all'];
				}else{
					$post_type ='all';
				}
				
			}
			$number    = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : $this->settings['number']['std'];

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

			$videos = $this->get_video( $instance );

			if ( $videos && $videos->have_posts() ) {
				$this->widget_start( $args, $instance );

				echo '<ul class="wpstream-video-list-widget">';

				while ( $videos->have_posts() ) {
					echo '<li>';
					$videos->the_post();
					$unit_card_type = wpstream_video_item_card_selector();
					include WPSTREAM_PLUGIN_PATH . 'hello-wpstream/' . $unit_card_type;
					echo '</li>';
				}

				echo '</ul>';

				$this->widget_end( $args );
			}

			wp_reset_postdata();
		}
	}
}
