<?php
/**
 * Template Name: WPStream OvenLiveKit Broadcaster
 *
 * Full-page browser broadcaster. Loads OvenLiveKit (WebRTC/WHIP publisher),
 * captures the user's camera/microphone, and streams to the channel's WHIP URL.
 * Access is gated to logged-in users who can publish, and requires a valid
 * `channel_id` query var whose post meta supplies the OBS/WHIP credentials.
 *
 * @package    Wpstream
 * @subpackage Wpstream/templates
 */

// Security check
// Block direct file access outside of WordPress.
if (!defined('ABSPATH')) {
	exit;
}

// Must be logged in to open the broadcaster at all.
if (!is_user_logged_in()) {
	wp_die(__('You do not have sufficient permissions to access this page.', 'wpstream'));
}

// Get channel ID from URL parameter
// The channel to broadcast to is passed as a query var; abort if absent.
$channel_id = get_query_var('channel_id');
if (empty($channel_id)) {
	wp_die(__('No channel ID specified.', 'wpstream'));
}

// Ownership gate: only the channel's author (or an admin) may open the
// broadcaster and receive its RTMP/WHIP publishing credentials. This replaces
// the old broad publish_posts check, which both let any author-level user load
// another broadcaster's credentials for an arbitrary channel id AND wrongly
// blocked legitimate subscriber-streamers who own their own channel.
if ( ! wpstream_can_manage_channel( get_current_user_id(), intval( $channel_id ) ) ) {
	wp_die(__('You do not have sufficient permissions to access this page.', 'wpstream'));
}

// Get stream information from post meta
// Pull the publishing credentials stored against the channel post.
$obs_uri = get_post_meta($channel_id, 'obs_uri', true);      // RTMP/OBS ingest URI
$obs_stream = get_post_meta($channel_id, 'obs_stream', true); // OBS stream key
$whip_url = get_post_meta($channel_id, 'whipUrl', true);      // WebRTC WHIP endpoint

// Detect whether this page was opened as part of the onboarding wizard flow.
$onboarding    = isset( $_GET['onboarding'] ) ? sanitize_text_field( wp_unslash( $_GET['onboarding'] ) ) : '';
$is_onboarding = ( $onboarding === '1' );

// A WHIP URL is mandatory for WebRTC publishing; abort if it is missing.
if (empty($whip_url)) {
	wp_die(__('WHIP URL not available for this channel.', 'wpstream'));
}

// Enqueue the broadcaster stylesheet (cache-busted by file mtime).
wp_enqueue_style(
	'wpstream-broadcaster-css',
	WPSTREAM_PLUGIN_DIR_URL . 'public/css/broadcaster.css',
	array(),
	filemtime(WPSTREAM_PLUGIN_PATH . 'public/css/broadcaster.css')
);

// Enqueue the broadcaster JS (loaded in the footer, cache-busted by mtime).
wp_enqueue_script(
	'wpstream-broadcaster-new',
	WPSTREAM_PLUGIN_DIR_URL . 'public/js/broadcaster.js',
	array(),
	filemtime(WPSTREAM_PLUGIN_PATH . 'public/js/broadcaster.js'),
	true
);

// Pass server-side config and localized strings to the broadcaster script.
wp_localize_script(
	'wpstream-broadcaster-new',
	'wpstream_broadcaster_vars',
	array(
		'ajax_url'              => admin_url('admin-ajax.php'),                     // admin-ajax endpoint
		'nonce'                 => wp_create_nonce('wpstream_broadcaster_nonce'),   // CSRF token for AJAX calls
		'plugin_url'            => plugin_dir_url(__FILE__),                        // base URL for asset lookups
		'obs_uri'               => $obs_uri,                                       // RTMP ingest URI
		'obs_stream'            => $obs_stream,                                     // OBS stream key
        'channel_id'            => $channel_id,                                     // channel being broadcast to
		'is_channel_live'       => get_post_meta($channel_id, 'status', true),      // current live status flag
		'whip_url'              => get_post_meta($channel_id, 'whipUrl', true),     // WebRTC WHIP publish endpoint
		'no_video_audio_access' => esc_html__('We couldn’t access your camera or microphone. Please allow permissions and reload the page.', 'wpstream'),
		'no_audio_access'       => esc_html__('We couldn’t access your microphone. Please allow permissions and reload the page.', 'wpstream'),
		'no_video_access'       => esc_html__('We couldn’t access your camera. Please allow permissions and reload the page.', 'wpstream'),
        'channel_off'           => esc_html__('Invalid event. Your live event may have expired or its credentials are incorrect.', 'wpstream'),
        'not_enough_traffic'    => sprintf(
                esc_html__('Not enough streaming traffic to broadcast. Please %supgrade your subscription%s for extra resources.', 'wpstream'),
                '<a href="https://wpstream.net/pricing/" target="_blank">',
                '</a>'
        ),
        'is_onboarding'         => $is_onboarding  // whether we are inside the onboarding wizard
	)
);

// When launched from onboarding, also load the wizard's page script + telemetry vars.
if ( $is_onboarding ) {
	wp_enqueue_script('wpstream-on-boarding-page-js', plugin_dir_url( __DIR__  ) .'admin/js/wpstream-onboarding-page.js',array(),  WPSTREAM_PLUGIN_VERSION, true);
	wp_localize_script( 'wpstream-on-boarding-page-js', 'wpstream_onboarding_page_vars',
		array(
			'admin_url'      => get_admin_url(),
			'request_url'    => WPSTREAM_CLICK,
			'wps_user'       => get_option('wpstream_api_username_from_token'),
			'current_page'   => 'broadcaster',
			'plugin_version' => WPSTREAM_PLUGIN_VERSION,
		)
	);
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?php esc_html_e('WpStream Broadcaster', 'wpstream'); ?></title>

	<?php wp_head(); ?>

	<!-- Load required libraries in correct order -->
	<script src="https://cdn.jsdelivr.net/npm/underscore@1.12.0/underscore-min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/ovenlivekit@latest/dist/OvenLiveKit.min.js"></script>
</head>
<body class="wpstream-broadcaster-page">
<!-- Top bar: WpStream logo (links home) and a static section label. -->
<header class="broadcaster-header">
	<div class="header-container">
		<div class="header-logo">
			<a href="<?php echo esc_url(home_url('/')); ?>">
				<img src="<?php echo esc_url(WPSTREAM_PLUGIN_DIR_URL . 'img/wpstream-logo.svg'); ?>" alt="WpStream Logo">
			</a>
		</div>
		<nav class="header-nav">
			<span class="nav-item">Browser Broadcaster</span>
		</nav>
	</div>
</header>

<!-- JS injects status/error banners into this container. -->
<div id="messageContainer"></div>

<!-- Main layout: live preview on the left, capture settings panel on the right. -->
<div class="broadcaster-container">
	<div class="wrapper">
		<!-- Local camera preview, expand toggle, and LIVE/Connecting indicators. -->
		<div class="video-container">
            <button id="videoExpandToggle" class="video-expand-toggle" type="button" aria-label="<?php esc_attr_e('Toggle Full View', 'wpstream'); ?>" title="<?php esc_attr_e('Toggle Full View', 'wpstream'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
            </button>
            <div id="videoLiveIndicator" class="video-live-indicator">
                <span id="videoLiveIndicatorLive" class="badge badge-pill badge-danger" style="display:none;"><?php esc_html_e('LIVE', 'wpstream'); ?></span>
                <span id="videoLiveIndicatorError" class="badge badge-pill badge-warning" style="display:none;"><?php esc_html_e('Connecting...', 'wpstream'); ?></span>
            </div>
            <!-- Muted, autoplaying local video element the capture stream is attached to. -->
            <div class="video-wrapper">
                <div id="wpstream-pre-load-spinner" class="wpstream-pre-load-spinner"></div>
			    <video id="localVideo" autoplay muted playsinline></video>
            </div>
		</div>

		<!-- Settings panel: start/stop controls, connection status, and device pickers. -->
		<div class="settings-panel" id="settingsPanel">
			<div>
				<?php
				// Nonce consumed by the AJAX call that starts the live event.
				$ajax_nonce = wp_create_nonce( "wpstream_start_event_nonce" );
				print '<input type="hidden" id="wpstream_start_event_nonce" value="'.$ajax_nonce.'">';
				?>
				<!-- Start begins publishing; Stop (hidden until live) ends the broadcast. -->
				<div class="controls-container">
					<button id="startBroadcast" class="button start-broadcast" disabled><?php esc_html_e('Start Broadcast', 'wpstream'); ?></button>
					<button id="stopBroadcast" class="button stop-broadcast hidden"><?php esc_html_e('Stop Broadcast', 'wpstream'); ?></button>
				</div>
				<!-- Text + colored dot reflecting the current connection state. -->
				<div class="status-container">
					<div>
						<span class="status-indicator" id="statusIndicator"></span>
						<span id="statusText"><?php esc_html_e('Not connected', 'wpstream'); ?></span>
					</div>
				</div>
			</div>

			<!-- Device selection row: camera and microphone pickers with on/off toggles. -->
			<div class="settings-row media-row">
				<!-- Video source picker; JS fills the <select> with enumerated cameras. -->
				<div class="settings-group">
					<label for="videoDevice"><?php esc_html_e('Video Source', 'wpstream'); ?></label>
					<div class="controls-group">
						<select id="videoDevice">
							<option selected></option>
						</select>
						<button id="videoToggle" class="control-button">
							<img alt="" class="noll" id="video-off" src="<?php echo esc_url(WPSTREAM_PLUGIN_DIR_URL . 'img/videocam-32px.svg'); ?>">
							<img alt="" class="noll" id="video-on" src="<?php echo esc_url(WPSTREAM_PLUGIN_DIR_URL . 'img/videocam-off-32px.svg'); ?>" style="display:none;">
						</button>
					</div>
				</div>

				<!-- Audio source picker; JS fills the <select> with enumerated microphones. -->
				<div class="settings-group">
					<label for="audioDevice"><?php esc_html_e('Audio Source', 'wpstream'); ?></label>
                    <div class="controls-group">
                        <select id="audioDevice">
                            <option selected></option>
                        </select>
                        <button id="audioToggle" class="control-button">
                            <img alt="" class="noll" id="audio-off" src="<?php echo esc_url( WPSTREAM_PLUGIN_DIR_URL . 'img/mic-32px.svg' ); ?>">
                            <img alt="" class="noll" id="audio-on" src="<?php echo esc_url( WPSTREAM_PLUGIN_DIR_URL . 'img/mic-off-32px.svg' ); ?>" style="display:none;">
                        </button>
                    </div>
				</div>
			</div>

			<!-- Resolution row: preset capture sizes passed to getUserMedia constraints. -->
			<div class="settings-row">
				<div class="settings-group">
					<label for="videoQuality"><?php esc_html_e('Video Resolution', 'wpstream'); ?></label>
					<select id="videoQuality">
						<option selected value="default">Default</option>
						<option value="fhd"><?php esc_html_e('1920x1080', 'wpstream'); ?></option>
						<option value="hd"><?php esc_html_e('1280x720', 'wpstream'); ?></option>
						<option value="square"><?php esc_html_e('800x600', 'wpstream'); ?></option>
						<option value="vga"><?php esc_html_e('640x360', 'wpstream'); ?></option>
					</select>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- wp_footer() prints footer scripts, including the enqueued broadcaster.js. -->
<?php wp_footer(); ?>
</body>
</html>