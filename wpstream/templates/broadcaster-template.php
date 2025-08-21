<?php
/**
 * Template Name: WPStream OvenLiveKit Broadcaster
 */

// Security check
if (!defined('ABSPATH')) {
	exit;
}

// Only allow logged-in users with appropriate permissions
if (!is_user_logged_in() || !current_user_can('publish_posts')) {
	wp_die(__('You do not have sufficient permissions to access this page.', 'wpstream'));
}

// Get channel ID from URL parameter
$channel_id = get_query_var('channel_id');
if (empty($channel_id)) {
	wp_die(__('No channel ID specified.', 'wpstream'));
}

// Get stream information from post meta
$obs_uri = get_post_meta($channel_id, 'obs_uri', true);
$obs_stream = get_post_meta($channel_id, 'obs_stream', true);
$whip_url = get_post_meta($channel_id, 'whipUrl', true);
$channel_id = get_post_meta($channel_id, 'channel_id', true);

if (empty($whip_url)) {
	wp_die(__('WHIP URL not available for this channel.', 'wpstream'));
}

wp_enqueue_style(
	'wpstream-broadcaster-css',
	WPSTREAM_PLUGIN_DIR_URL . 'public/css/broadcaster.css',
	array(),
	filemtime(WPSTREAM_PLUGIN_PATH . 'public/css/broadcaster.css')
);

wp_enqueue_script(
	'wpstream-broadcaster-new',
	WPSTREAM_PLUGIN_DIR_URL . 'public/js/broadcaster.js',
	array(),
	filemtime(WPSTREAM_PLUGIN_PATH . 'public/js/broadcaster.js'),
	true
);

wp_localize_script(
	'wpstream-broadcaster-new',
	'wpstream_broadcaster_vars',
	array(
		'ajax_url'              => admin_url('admin-ajax.php'),
		'nonce'                 => wp_create_nonce('wpstream_broadcaster_nonce'),
		'plugin_url'            => plugin_dir_url(__FILE__),
		'obs_uri'               => $obs_uri,
		'obs_stream'            => $obs_stream,
        'channel_id'            => $channel_id,
		'is_channel_live'       => get_post_meta($channel_id, 'status', true),
		'whip_url'              => get_post_meta($channel_id, 'whipUrl', true),
		'no_video_audio_access' => esc_html('We couldn’t access your camera or microphone. Please allow permissions and reload the page.', 'wpstream'),
		'no_audio_access'       => esc_html('We couldn’t access your microphone. Please allow permissions and reload the page.', 'wpstream'),
		'no_video_access'       => esc_html('We couldn’t access your camera. Please allow permissions and reload the page.', 'wpstream'),
        'channel_off'           => esc_html('Error: This event is no longer active.', 'wpstream'),
	)
)

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
<header class="broadcaster-header">
	<div class="header-container">
		<div class="header-logo">
			<a href="<?php echo esc_url(home_url('/')); ?>">
				<img src="<?php echo esc_url(WPSTREAM_PLUGIN_DIR_URL . 'img/wpstream_logo_0.svg'); ?>" alt="WpStream Logo">
			</a>
		</div>
		<nav class="header-nav">
			<span class="nav-item">Browser Broadcaster</span>
		</nav>
	</div>
</header>

<div class="broadcaster-container">
	<div class="wrapper">
		<div class="video-container">
			<video id="localVideo" autoplay muted playsinline></video>
		</div>

		<div class="settings-panel" id="settingsPanel">
			<div>
				<div class="controls-container">
					<button id="startBroadcast" class="button start-broadcast" disabled><?php esc_html_e('Start Broadcast', 'wpstream'); ?></button>
					<button id="stopBroadcast" class="button stop-broadcast hidden"><?php esc_html_e('Stop Broadcast', 'wpstream'); ?></button>
				</div>
				<div class="status-container">
					<div>
						<span class="status-indicator" id="statusIndicator"></span>
						<span id="statusText"><?php esc_html_e('Not connected', 'wpstream'); ?></span>
					</div>
				</div>
			</div>

			<div class="settings-row media-row">
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

			<div class="settings-row">
				<div class="settings-group">
					<label for="videoQuality"><?php esc_html_e('Video Resolution', 'wpstream'); ?></label>
					<select id="videoQuality">
						<option selected value="default">Default</option>
						<option value="fhd"><?php esc_html_e('1920x1080', 'wpstream'); ?></option>
						<option value="hd"><?php esc_html_e('1280x720', 'wpstream'); ?></option>
						<option value="square"><?php esc_html_e('800x600', 'wpstream'); ?></option>
						<option value="vga"><?php esc_html_e('640x480', 'wpstream'); ?></option>
					</select>
				</div>
			</div>

			<div id="messageContainer"></div>
		</div>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>