<?php
/**
 * Admin notice that tells the site owner when the bundled "hello-wpstream"
 * companion theme has an update available.
 *
 * The class hooks into `admin_notices`, decides whether a notice is warranted
 * (permission + not already dismissed + an update actually pending), gathers
 * the current/new version numbers from the theme update transient, and renders
 * the notice via a template partial.
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles rendering of the "hello-wpstream theme update available" admin notice.
 */
class WPStream_Theme_Notice {

	/** @var string Slug of the companion theme whose updates we announce. */
	private $theme_slug = 'hello-wpstream';

	/** @var string Option key that records the notice was dismissed by the user. */
	private $notice_option = 'wpstream_theme_notice_dismissed';

	/** @var string Nonce action used when the notice is dismissed. */
	private $nonce_action = 'wpstream_dismiss_notice';

	/**
	 * Register the notice on the admin_notices hook.
	 */
	public function __construct() {
		// Print the notice (if applicable) whenever WordPress renders admin notices.
		add_action( 'admin_notices', array( $this, 'display_admin_notice' ) );
		// Remember the dismissal sent by the notice's close button.
		add_action( 'wp_ajax_wpstream_dismiss_notice', array( $this, 'dismiss_notice' ) );
	}

	/**
	 * AJAX handler for the notice's close button: records the dismissal so
	 * the notice stops appearing on every admin page.
	 *
	 * @return void Ends the request with a JSON answer.
	 */
	public function dismiss_notice() {
		// CSRF gate: the notice prints this nonce next to its close button.
		if ( ! check_ajax_referer( $this->nonce_action, 'nonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		// Only users who are shown the notice may dismiss it for the site.
		if ( ! current_user_can( 'update_themes' ) ) {
			wp_send_json_error( null, 403 );
		}
		update_option( $this->notice_option, 1, false );
		wp_send_json_success();
	}

	/**
	 * Gatekeeper callback for admin_notices: render the notice only when it is
	 * eligible and there is genuine update data to show.
	 */
	public function display_admin_notice() {
		// Bail early if the notice should not be shown in the current context.
		if ( !$this->should_display_notice() ) {
			return;
		}

		// Fetch the theme's current/new version pair; false means no update pending.
		$theme_data = $this->get_theme_update_data();
		if ( !$theme_data ) {
			return;
		}

		// All checks passed, so output the notice markup.
		$this->render_notice( $theme_data );
	}

	/**
	 * Decide whether the notice is allowed to appear.
	 *
	 * @return bool True when not previously dismissed, the user can update
	 *              themes, and this is not an AJAX request.
	 */
	private function should_display_notice() {
		return !get_option( $this->notice_option ) &&        // not dismissed before
			current_user_can( 'update_themes' ) &&           // user has the capability
			!wp_doing_ajax();                                // not during an AJAX call
	}

	/**
	 * Resolve the companion theme's installed and available versions.
	 *
	 * @return array|false Array with 'current_version'/'new_version', or false
	 *                     if the theme is missing or has no pending update.
	 */
	private function get_theme_update_data() {
		// The companion theme must actually be installed on the site.
		$theme = wp_get_theme( $this->theme_slug );
		if ( !$theme->exists() ) {
			return false;
		}

		// WordPress caches pending theme updates in this site transient.
		$updates = get_site_transient( 'update_themes' );
		if ( !isset( $updates->response[$this->theme_slug] ) ) {
			return false;
		}

		// Hand back the installed version and the version WordPress is offering.
		return array(
			'current_version' => $theme->get( 'Version' ),
			'new_version'     => $updates->response[$this->theme_slug]['new_version'],
		);
	}


	/**
	 * Render the notice markup from its template partial.
	 *
	 * @param array $data Version data made available to the included template.
	 */
	public function render_notice( $data ) {
		// $data is in scope for the template to print current/new versions.
		include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/admin/wpstream-theme-update-notice.php';
	}
}