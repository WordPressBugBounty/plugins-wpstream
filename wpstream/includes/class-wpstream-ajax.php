<?php

class WpStream_Ajax {

	/**
	 * Store plugin main class to allow public access.
	 *
	 * @since    20180622
	 * @var object      The main class.
	 */
	public $main;

	/**
	 * Constructor.
	 *
	 * @param object $plugin_main The main class.
	 */
	public function __construct( $plugin_main ) {
		$this->main = $plugin_main;

		add_action( 'wp_ajax_wpstream_get_videos_list',  [$this,'wpstream_get_videos_list'] );
	}

	/**
	 * Get videos list from WPStream API.
	 */
	public function wpstream_get_videos_list() {
		check_ajax_referer( 'wpstream_onboarding_video_list_nonce', 'security' );

		$token = $this->main->wpstream_live_connection->wpstream_get_token();
		$videos_list = $this->main->wpstream_live_connection->wpstream_get_videos();

		// cleanup any previous echo before sending json
		ob_end_clean();

		if ( $token ) {
			echo json_encode( array(
				'success' => true,
				'videos' => $videos_list,
			));
		} else {
			echo json_encode( array(
				'success' => false,
				'error' => 'Token not found',
			));
		}
		die();
	}
}