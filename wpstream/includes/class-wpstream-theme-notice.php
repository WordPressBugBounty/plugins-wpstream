<?php
/**
 * Handle theme update notice
 */
class WPStream_Theme_Notice {

	private $theme_slug = 'hello-wpstream';
	private $notice_option = 'wpstream_theme_notice_dismissed';
	private $nonce_action = 'wpstream_dismiss_notice';

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
	}

	public function display_admin_notice() {
		if ( !$this->should_display_notice() ) {
			return;
		}

		$theme_data = $this->get_theme_update_data();
		if ( !$theme_data ) {
			return;
		}

		$this->render_notice( $theme_data );
	}

	private function should_display_notice() {
		return !get_option( $this->notice_option ) &&
			current_user_can( 'update_themes' ) &&
			!wp_doing_ajax();
	}

	private function get_theme_update_data() {
		$theme = wp_get_theme( $this->theme_slug );
		if ( !$theme->exists() ) {
			return false;
		}

		$updates = get_site_transient( 'update_themes' );
		if ( !isset( $updates->response[$this->theme_slug] ) ) {
			return false;
		}

		return array(
			'current_version' => $theme->get( 'Version' ),
			'new_version'     => $updates->response[$this->theme_slug]['new_version'],
		);
	}


	public function render_notice( $data ) {
		include plugin_dir_path( __FILE__ ) . 'templates/wpstream-theme-update-notice.php';
	}
}