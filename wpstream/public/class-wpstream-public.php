<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://wpstream.net
 * @since      3.0.1
 *
 * @package    Wpstream
 * @subpackage Wpstream/public
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public-facing functionality of the plugin.
 *
 * This class wires up everything WpStream needs on the front end:
 *  - Registering/enqueuing the player and streaming CSS/JS (and their
 *    localized i18n + config data).
 *  - WooCommerce "My Account" endpoints (event-list, video-list, start-streaming)
 *    and menu items.
 *  - Shortcodes ([wpstream_player], [wpstream_go_live], list
 *    shortcodes) and the matching Visual Composer / WPBakery `vc_map` definitions.
 *  - Search/archive/sidebar filter callbacks that teach the theme about the
 *    plugin's custom post types (wpstream_product, wpstream_product_vod, wpstream_bundles).
 *  - The HLS/DRM key delivery endpoints for live and VOD playback, including the
 *    remote key fetch (cached in transients) and the WooCommerce purchase/subscription
 *    entitlement gate that protects paid content.
 *  - Low-level request plumbing: CORS preflight response and the session cookie
 *    used to bind DRM key requests to a viewer.
 *
 * @package    Wpstream
 * @subpackage Wpstream/public
 * @author     wpstream <office@wpstream.net>
 */
class Wpstream_Public {

    
        
        /**
         * Store plugin main class to allow public access.
         *
         * @since    20180622
         * @var object      The main class.
         */
        public $main;
    /**
     * The ID of this plugin.
     *
     * @since    3.0.1
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    3.0.1
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    3.0.1
     * @param      string    $plugin_name       The name of the plugin.
     * @param      string    $version    The version of this plugin.
     * @param      object    $plugin_main       The main plugin class, kept for access to helpers (player, quota_manager, etc.).
     */
    public function __construct( $plugin_name, $version ,$plugin_main) {
        // Keep a reference to the main plugin object so its sub-objects (player, quota manager) are reachable.
        $this->main         = $plugin_main;
        // Remember the plugin slug/name.
        $this->plugin_name  = $plugin_name;
        // Remember the plugin version (used for cache-busting enqueues).
        $this->version      = $version;

    }

    /**
     * Register/enqueue the stylesheets for the public-facing side of the site.
     *
     * Only the core plugin CSS is enqueued site-wide; the player CSS is
     * registered here and enqueued at player render time (see
     * wpstream_enqueue_player_assets()), and the integrations CSS only loads
     * when BuddyPress is present. The player-skin file is cache-busted with
     * its own filemtime so skin tweaks show up immediately.
     *
     * @since    3.0.1
     * @return   void
     */
    public function enqueue_styles() {

            // Core plugin front-end styles (cards, lists, notices — can appear on any page).
            wp_enqueue_style('wpstream-style',          plugin_dir_url( __FILE__ ) .'/css/wpstream_style.css',array(), WPSTREAM_PLUGIN_VERSION, 'all' );
            // Base Video.js player CSS — registered only; player render sites enqueue it.
            wp_register_style('video-js.min',           plugin_dir_url( __FILE__ ).'css/video-js.css', array(), WPSTREAM_PLUGIN_VERSION, 'all');
            // WpStream player skin on top of Video.js; registered only, filemtime cache-buster.
            wp_register_style(
				'videojs-wpstream-player',
				plugin_dir_url( __FILE__ ).'css/videojs-wpstream.css',
				array(),
				WPSTREAM_PLUGIN_VERSION . '.' . filemtime( plugin_dir_path(__FILE__) . 'css/videojs-wpstream.css' ),
				'all'
			);
            // BuddyBoss/BuddyPress integration styling — only useful when BuddyPress runs.
            if ( class_exists( 'BuddyPress' ) ) {
                wp_enqueue_style('wpstream-integrations',   plugin_dir_url( __DIR__ ) .'integrations/css/integrations.css',array(), WPSTREAM_PLUGIN_VERSION, 'all' );
            }

    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * Registers/enqueues Video.js and its quality-selector + logo plugins, the
     * WpStream player bootstrap/controls, and the
     * start-streaming and integrations scripts. Each script is paired with a
     * `wp_localize_script` payload that ships translated UI strings, feature
     * flags and a per-page player status nonce down to the browser.
     *
     * @since    3.0.1
     * @return   void
     */
    public function enqueue_scripts() {

		// Register the VideoJS script
        // Enqueuing is happing directly wherever is used
        // Register (not enqueue) the bundled core Video.js library; templates enqueue it on demand.
        wp_register_script('video.min',              plugin_dir_url( __FILE__ ) . 'js/vendor/video.min.js', array(), '8.20.0', true);

        // Quality selector dependency (Video.js plugin)
        // Bundled Video.js contrib "quality levels" plugin, required by the WpStream quality selector below.
        wp_register_script(
            'videojs-contrib-quality-levels',
            plugin_dir_url( __FILE__ ) . 'js/vendor/videojs-contrib-quality-levels.min.js',
            array('video.min'),
            '4.0.0',
            true
        );

        // WpStream quality selector (Video.js 8 compatible)
        // WpStream's own quality-selector UI; filemtime cache-buster keeps the served file fresh.
        wp_register_script(
            'wpstream-quality-selector',
            plugin_dir_url( __FILE__ ) . 'js/wpstream-quality-selector.js',
            array('video.min', 'videojs-contrib-quality-levels'),
            WPSTREAM_PLUGIN_VERSION . '.' . filemtime(plugin_dir_path(__FILE__) . 'js/wpstream-quality-selector.js'),
            true
        );

		// Register the VideoJS Logo plugin script
        // Bundled Video.js "logo" plugin used to overlay the configurable player watermark.
        // Registered only — it is a dependency of wpstream-player, so it loads exactly
        // where the player loads.
	    wp_register_script(
			'videojs-logo',
		    plugin_dir_url( __FILE__ ) . 'js/vendor/videojs-logo.min.js',
			array('video.min'),
			'3.0.0',
			true
	    );

                // YouTube tech for Video.js, used when a channel restreams from YouTube.
                wp_register_script('youtube.min',
                                  plugin_dir_url( __FILE__ ).'js/youtube.min.js',
                                  array('video.min'),
                                  WPSTREAM_PLUGIN_VERSION, true);

                // Player bootstrap: sets up the Video.js instance before wpstream-player runs.
                wp_register_script(
                    'wpstream-player-bootstrap',
                    plugin_dir_url( __FILE__ ) . 'js/wpstream-player-bootstrap.js',
                    array(),
                    WPSTREAM_PLUGIN_VERSION . '.' . filemtime(plugin_dir_path(__FILE__) . 'js/wpstream-player-bootstrap.js'),
                    true
                );
              
                // Main WpStream player script (live status polling, state messages, chat glue).
                // videojs-logo is a hard dependency: the player calls player.logo() for the
                // configurable watermark, so it must load wherever the player loads.
                wp_register_script(
					'wpstream-player',
					plugin_dir_url( __FILE__ ).'js/wpstream-player.js',
                    array('video.min','wpstream-quality-selector','wpstream-player-bootstrap','videojs-logo'),
                    WPSTREAM_PLUGIN_VERSION . '.' . filemtime(plugin_dir_path(__FILE__) . 'js/wpstream-player.js'),
	                true
                );

				// Player custom controls layer.
				wp_register_script(
					'wpstream-player-controls',
					plugin_dir_url( __FILE__ ) . 'js/player-controls.js',
					array( 'jquery', 'video.min' ),
					WPSTREAM_PLUGIN_VERSION . '.' . filemtime(plugin_dir_path(__FILE__) . 'js/player-controls.js'),
					true
				);
			    // Hand the controls script its translated live-UI status strings.
			    wp_localize_script(
				    'wpstream-player-controls',
				    'wpstreamLiveUiConfig',
				    $this->wpstream_get_player_i18n_config()
			    );

				// Resolve adaptive bitrate through the Channel Settings Module.
				$abr_enabled = false;
				$post_id = get_the_ID();
				$post_meta = $post_id ? $this->main->channel_settings->read( $post_id )['options'] : array();
				if ( ! empty( $post_meta ) && isset( $post_meta['adaptive_bitrate'] ) && 1 === (int) $post_meta['adaptive_bitrate'] ) {
					$abr_enabled = true;
				}
				// Localize the main player script with UI strings, player theme/logo, streamify flag, status nonce and ABR flag.
				wp_localize_script( 'wpstream-player', 'wpstream_player_vars', $this->wpstream_get_player_vars_config( $abr_enabled ) );
                
                // The built-in SockJS/WebSocket chat client was removed (issue #110):
                // live chat is provided by the Better Messages integration in the
                // hello-wpstream theme, so no chat_lib assets are registered anymore.

                // Bundled QR generator (SEC-05): the Larix QR is built locally so the
                // RTMP URL + secret stream key never reach a third-party image service.
                wp_register_script('wpstream-qrcode-generator', plugin_dir_url( __FILE__ ) . 'js/vendor/qrcode-generator.js', array(), '1.4.4', true);
                wp_register_script('wpstream-qr',               plugin_dir_url( __FILE__ ) . 'js/wpstream-qr.js', array('wpstream-qrcode-generator'), WPSTREAM_PLUGIN_VERSION, true);

                // Register the start-streaming (channel on/off) script, cache-busted by its file mtime.
                // Depends on wpstream-qr so wpstreamRenderQr() is defined before this runs.
                // Registered only — wpstream_live_stream_unit_wrapper() enqueues it when the
                // go-live unit actually renders on a page.
                $modified_start_streaming_file_time = gmdate( 'YmdHi', filemtime( WPSTREAM_PLUGIN_PATH . 'public/js/start_streaming.js' ) );
                wp_register_script('wpstream-start-streaming',   plugin_dir_url( __FILE__ ) .'js/start_streaming.js',array('wpstream-qr'), $modified_start_streaming_file_time, true);
				// Cached-only streaming feature flags (see wpstream_get_start_streaming_localization_flags).
				$streaming_localization_flags = $this->wpstream_get_start_streaming_localization_flags();
                // Localize the start-streaming script with all the translated button labels, tooltips and warnings.
                wp_localize_script('wpstream-start-streaming', 'wpstream_start_streaming_vars',
                    array( 
                        'admin_url'             =>  get_admin_url(),
                        'loading_url'           =>  WPSTREAM_PLUGIN_DIR_URL.'/img/loading.gif',
                        'download_mess'         =>  esc_html__('Click to download!','wpstream'),
                        'uploading'             =>  esc_html__('We are uploading your file.Do not close this window!','wpstream'),
                        'upload_complete2'      =>  esc_html__('Upload Complete! You can upload another file!','wpstream'),
                        'not_accepted'          =>  esc_html__('The file is not an accepted video format','wpstream'),
                        'upload_complete'       =>  esc_html__('Upload Complete!','wpstream'),
                        'upload_failed'         =>  esc_html__('Upload Failed!','wpstream'),
                        'upload_failed2'        =>  esc_html__('Upload Failed! Please Try again!','wpstream'),
                        'no_band'               =>  esc_html__('Not enough streaming data.','wpstream'),
                        'no_band_no_store'      =>  esc_html__('Not enough streaming data or storage.','wpstream'),
                        
                        'start_streaming_action'=>  esc_html__('TURNING ON','wpstream'),
                        'stop_streaming_action' =>  esc_html__('TURNING OFF','wpstream'),
                        'start_streaming'       =>  esc_html__('TURN ON','wpstream'),
                        'stop_streaming'        =>  esc_html__('TURN OFF','wpstream'),
	                    'failed_fetching'       =>  esc_html__('Failed to get channel info. Please try again.','wpstream'),
                        'turned_on_tooltip'     =>  esc_html__('Channel is now OFF. Click to turn ON.','wpstream'),
                        'turned_off_tooltip'    =>  esc_html__('Click to turn channel off. This will interrupt any ongoing broadcast.','wpstream'),                     
                        'turning_on_tooltip'    =>  esc_html__('Turning a channel on may take 1-2 minutes or more. Please be patient.','wpstream'),
                        'turning_off_tooltip'   =>  esc_html__('This may take a few minutes.','wpstream'),
                        'error1'                =>  esc_html__('You don\'t have enough data to start a new event!','wpstream'),
                        'failed_event_creation' =>  esc_html__('Failed to start the channel. Please try again in a few minutes.','wpstream'),
                        'channel_turning_on'    =>  esc_html__('Channel is turning on','wpstream'),
                        'channel_turning_off'   =>  esc_html__('Channel is turning off','wpstream'),
                        'channel_on'            =>  esc_html__('Channel is ON','wpstream'),
                        'channel_off'           =>  esc_html__('Channel is OFF','wpstream'),
                        'turn_off_confirm'      =>  esc_html__('ARE YOU SURE you\'d like to TURN OFF the channel now? '.PHP_EOL.PHP_EOL.'Channels TURN OFF automatically after 1 hour of inactivity (no active broadcast).'.PHP_EOL.PHP_EOL.'Manual TURN OFF is only useful if you require to change the channel settings immediately.'.PHP_EOL.PHP_EOL.'Statistics may be unavailable or incomplete for up to an hour.'.PHP_EOL.PHP_EOL.'If your channel is configured with Auto TURN ON, it will turn back on as soon as there is a broadcast.','wpstream'),
                        'is_basic_streaming'      => $streaming_localization_flags['is_basic_streaming'],
                        'use_streaming_hours'     => $streaming_localization_flags['use_streaming_hours'],
                        'basic_streaming_warning' => esc_html__(
	                        'You’ve used all available broadcast or viewer hours.' . PHP_EOL . PHP_EOL .
	                        'Some live channel features will be limited, including recording, viewer count, browser broadcasting, and content protection.' . PHP_EOL . PHP_EOL .
	                        'Top up your resources or upgrade your plan to use all features, or choose OK to start anyway.',
	                        'wpstream'
                        ),
                        'basic_streaming_warning_traffic' => esc_html__(
	                        'Your account is now in BASIC STREAMING mode.' . PHP_EOL . PHP_EOL .
	                        'Instead of offloading to the WpStream Cloud, this mode relies on WordPress and hosting resources to process and deliver video. In some WP environments, streaming may be unreliable.' . PHP_EOL .
	                        'Certain features, such as recording, viewer count, browser broadcasting, and content protection are unavailable.' . PHP_EOL . PHP_EOL .
	                        '- To take advantage of all features, please choose Cancel and upgrade your plan.' . PHP_EOL .
	                        '- Otherwise, choose OK to start your channel with these limitations.' . PHP_EOL . PHP_EOL .
	                        'ARE YOU SURE you want to continue with Basic Streaming?',
	                        'wpstream'
                        ),
                        'broadcaster_url'       => esc_url( esc_url(home_url('/broadcaster-page/') ) ),
                   
                    ));
                

                    // The integrations script only carries BuddyBoss/BuddyPress glue
                    // (profile channel picker, timeline player) — skip it entirely when
                    // BuddyPress is not running. start_streaming.js guards its call with
                    // typeof, so the missing global is safe.
                    if ( class_exists( 'BuddyPress' ) ) {
                        // Build the integrations payload for the BuddyBoss JS.
                        $integrations_array=array(
                            'admin_url'             =>  get_admin_url(),
                            // CSRF nonce for the BuddyBoss AJAX handlers (select channel / timeline player).
                            'buddy_nonce'           =>  wp_create_nonce( 'wpstream_buddyboss' ),
                            'is_buddyboss'          =>  'yes',
                        );

                        // Enqueue and localize the integrations script (stable plugin-version cache-buster).
                        wp_enqueue_script('wpstream-integrations',   plugin_dir_url( __DIR__  ) .'integrations/js/integrations.js',array(),  WPSTREAM_PLUGIN_VERSION, true);
                        wp_localize_script('wpstream-integrations', 'wpstream_integrations_vars', $integrations_array );
                    }


                // Admin stylesheet reused on the front end by the go-live unit (channel card,
                // broadcast modal). Registered only — the unit wrapper enqueues it at render time.
                wp_register_style( 'wpstream_front_style', plugin_dir_url( __DIR__ ) . 'admin/css/wpstream-admin.css', array(), WPSTREAM_PLUGIN_VERSION, 'all' );

	    // Theme-side helper script (comment form validation, watch-later, messaging).
	    // Registered for on-demand use; enqueued site-wide only under the hello-wpstream
	    // theme (parent or child), whose templates rely on it everywhere. Other themes get
	    // it at render time from the markup that needs it (watch-later icons, contact form).
	    wp_register_script( 'wpstream-plugin-scripts', WPSTREAM_PLUGIN_DIR_URL . '/hello-wpstream/js/wpstream-plugin-script.js', array( 'jquery' ), '1.0', true );
	    if ( 'hello-wpstream' === get_template() || 'hello-wpstream' === get_stylesheet() ) {
		    wp_enqueue_script( 'wpstream-plugin-scripts' );
	    }

	    // Localize that helper with the AJAX URL, login state and translated form-validation messages.
	    wp_localize_script(
		    'wpstream-plugin-scripts',
		    'wpstreamPluginScriptsVars',
		    array(
			    'ajaxurl' 				=> 	admin_url( 'admin-ajax.php' ), // WordPress AJAX URL.
			    'watch_later_nonce'		=>	wp_create_nonce( 'wpstream_watch_later' ), // CSRF nonce for the watch-later toggle.
			    'processing'			=>	esc_html__('sending...','wpstream'),
			    'send_mess' 			=>  esc_html__('Send Message','wpstream'),
			    'is_user_logged_in' 	=> 	is_user_logged_in() ? '1' : '0',
			    'comment_text_empty' 	=>  esc_html__('Please type your comment.','wpstream'),
			    'comment_author_empty' 	=> 	esc_html__('Please enter your name.', 'wpstream'),
			    'comment_email_empty' 	=> 	esc_html__('Please enter your email.', 'wpstream'),
			    'comment_email_invalid' => esc_html__('Please enter a valid email address.', 'wpstream'),
			    'gdpr_agree' 			=> esc_html__('You need to agree with GDPR terms.', 'wpstream'),
		    )
	    );
    }


	/**
	 * Build the `wpstream_player_vars` object for the main player script.
	 *
	 * @param bool $abr_enabled Whether adaptive bitrate is on for the current page's channel.
	 * @return array Filtered config with canonical nonces.
	 */
	public function wpstream_get_player_vars_config( $abr_enabled = false ) {
		// Everything the main player script reads from `wpstream_player_vars`.
		$config = array(
			'admin_url'                         => get_admin_url(),
			'chat_not_connected'                =>  esc_html__('Inactive Channel - Chat is disabled.','wpstream'),

			'server_up'                         =>  esc_html__('The live stream is paused and may resume shortly.','wpstream'),

			// Translate the literal default only; a user-saved custom message is escaped as-is (variable strings can't go through gettext).

			'wpstream_player_state_stopped_msg' =>  esc_html( wpstream_not_live_message() ),

			'wpstream_player_state_init_msg'    =>  esc_html__('The live stream has not yet started','wpstream'),

			'wpstream_player_state_startup_msg' =>  esc_html__('The live stream is starting...','wpstream'),

			'wpstream_player_state_paused_msg'  =>  esc_html__('The live stream is paused','wpstream'),

			'wpstream_player_state_ended_msg'   =>  esc_html__('The live stream has ended','wpstream'),

			'wpstream_player_state_error_msg'   =>  esc_html__('Something went wrong','wpstream'),

			'wpstream_player_theme'             => get_option('wpstream_video_player_theme'),

			'playerLogoSettings'                => array(

				'imageUrl' => $this->main->wpstream_player->wpstream_get_video_player_logo( get_the_ID() ),

				'position' => get_option( 'wpstream_player_logo_position', 'top-left' ),

				'opacity'  => get_option('wpstream_player_logo_opacity', '100'),

			),

			'wpstream_is_streamify_user'        => $this->main->wpstream_player->wpstream_is_streamify_user( get_the_ID() ),

			// Nonce the player uses when AJAX-polling live status; verified server-side on each status check.

			'player_check_status_nonce' => wp_create_nonce( 'wpstream_player_check_status_nonce'),

			'is_abr_enabled'                   => $abr_enabled,
			/**
			 * Filter how often the player polls the channel status when no
			 * WebSocket is available, in seconds. Clamped to [1, 300].
			 *
			 * @since 4.14.0
			 *
			 * @param int $seconds Poll interval; default 30.
			 */
			'status_poll_interval'             => wpstream_tunable( 'wpstream_player_status_poll_interval', 30, 5 * MINUTE_IN_SECONDS ),

			// Gates the player's diagnostic console logging: silent in production,

			// verbose only when the site opts into SCRIPT_DEBUG.

			'debug'                            => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,

		);
		return $this->filter_player_js_config( $config, 'wpstream_player_vars' );
	}

	/**
	 * Run a localized player object through `wpstream_player_js_config` with
	 * every nonce withheld.
	 *
	 * Keys whose name contains `nonce` are removed before the filter and the
	 * canonical values re-applied afterwards, so a callback can neither read
	 * nor replace them.
	 *
	 * @param array  $config      Localized object.
	 * @param string $object_name JS object name (`wpstream_player_vars`, `wpstreamLiveUiConfig`).
	 * @return array Filtered object with canonical nonces.
	 */
	private function filter_player_js_config( array $config, $object_name ) {
		$is_nonce = static function ( $key ) {
			return false !== stripos( (string) $key, 'nonce' );
		};
		$nonces = array_filter( $config, $is_nonce, ARRAY_FILTER_USE_KEY );

		/**
		 * Filter a localized player config object.
		 *
		 * Nonce keys are not in `$config` and cannot be set: the canonical
		 * values are re-applied after the filter.
		 *
		 * @since 4.14.0
		 *
		 * @param array  $config      Object without its nonce keys.
		 * @param string $object_name JS object name (`wpstream_player_vars` or `wpstreamLiveUiConfig`).
		 */
		$filtered = (array) apply_filters( 'wpstream_player_js_config', array_diff_key( $config, $nonces ), $object_name );

		// Drop any nonce key the callback set and restore the canonical ones.
		$filtered = array_filter( $filtered, static function ( $key ) use ( $is_nonce ) {
			return ! $is_nonce( $key );
		}, ARRAY_FILTER_USE_KEY );
		return array_merge( $filtered, $nonces );
	}

	/**
	 * Build the `wpstreamLiveUiConfig` object for the player controls script:
	 * the translated live-UI status strings plus an isThemeActive flag.
	 *
	 * @return array Filtered config.
	 */
	public function wpstream_get_player_i18n_config() {
		// Each entry maps a player state to the localized, escaped message shown to viewers.
		$config = array(
			'wpstream_player_state_stopped_msg'      => esc_html( wpstream_not_live_message() ),
			'wpstream_player_state_init_msg'         => esc_html__( 'The live stream has not yet started', 'wpstream' ),
			'wpstream_player_state_startup_msg'      => esc_html__( 'The live stream is starting...', 'wpstream' ),
			'wpstream_player_state_paused_msg'       => esc_html__( 'The live stream is paused', 'wpstream' ),
			'wpstream_player_state_ended_msg'        => esc_html__( 'The live stream has ended', 'wpstream' ),
			'wpstream_player_state_error_msg'        => esc_html__( 'Something went wrong', 'wpstream' ),
			'wpstream_player_offair_default_msg'     => esc_html__( 'Live stream is not on air.', 'wpstream' ),
			'wpstream_player_max_viewers_msg'        => esc_html__( 'Max viewers reached. Please wait', 'wpstream' ),
			'wpstream_player_max_viewers_wait_msg'   => esc_html__( 'Max viewers reached. Please wait for %d to leave', 'wpstream' ),
			'wpstream_player_invalid_session_msg'    => esc_html__( 'Invalid session. Please refresh and start playback again.', 'wpstream' ),
			'isThemeActive'                          => get_template() === 'hello-wpstream',
		);
		return $this->filter_player_js_config( $config, 'wpstreamLiveUiConfig' );
	}

	/**
	 * Whether the current account's live plan is metered in streaming hours.
	 *
	 * @return bool True when the resolved live quota pack uses streaming hours.
	 */
	public function wpstream_is_use_streaming_hours() {
		// Resolve the live quota pack for the start-channel action.
		$pack_details = $this->main->quota_manager->get_live_quota_data( 'wpstream_start_channel' );
		// Ask the quota manager whether that pack is measured in streaming hours.
		return $this->main->quota_manager->uses_streaming_hours( $pack_details );
	}

	/**
	 * Cached-only flags for start_streaming.js (no API on cold cache).
	 * Skips work entirely for visitors who cannot stream.
	 *
	 * @return array{is_basic_streaming: bool, use_streaming_hours: bool}
	 */
	public function wpstream_get_start_streaming_localization_flags() {
		// Skip all quota work for logged-out visitors or users who cannot stream: return inert flags.
		if ( ! is_user_logged_in() || ! $this->main->wpstream_check_user_can_stream() ) {
			return array(
				'is_basic_streaming'  => false,
				'use_streaming_hours' => false,
			);
		}

		// Otherwise return the cached streaming UI flags (no live API call on a cold cache).
		return $this->main->quota_manager->get_streaming_ui_flags_from_cache();
	}
        
      
        /**
     * add custom end points for woocomerce
     *
     * @since     3.0.1
     * @return    nothing
        */
        public function wpstream_my_custom_endpoints() {
            // Register the "My Videos" account endpoint on the site root and pages.
            add_rewrite_endpoint( 'video-list', EP_ROOT | EP_PAGES );
            // Register the "My Live Streams" account endpoint on the site root and pages.
            add_rewrite_endpoint( 'event-list', EP_ROOT | EP_PAGES );
        }

	/**
	 * Remove specific wpstream filters.
	 *
	 * Used to detach the plugin's content/product hooks (e.g. on pages where the
	 * default player injection is not wanted).
	 *
	 * @return void
	 */
	function wpstream_remove_wpstream_filter() {
		// Access the global plugin instance for its main object.
		global $wpstream_plugin;

		if ( class_exists( 'Wpstream_Player' ) ) {
			// Instantiate the Wpstream_Player class if it exists.
			$pstream_player = new Wpstream_Player( $wpstream_plugin->main );
			// Remove filters applied by wpstream.
			// Drop the title/content filter that prepends player markup.
			remove_filter( 'the_content', 'wpstream_filter_the_title' );
			// Drop the "already bought" notice that renders before a single WooCommerce product.
			remove_filter( 'woocommerce_before_single_product', array( $pstream_player, 'wpstream_user_logged_in_product_already_bought' ) );
		}
	}

        /**
     * add custom query vars
     *
     * @since     3.0.1
     * @param     array $vars Existing public query vars.
     * @return    array Query vars with the plugin's account endpoints appended.
        */
        public function wpstream_my_custom_query_vars( $vars ) {
            // Make the video-list endpoint readable as a query var.
            $vars[] = 'video-list';
            // Make the event-list endpoint readable as a query var.
            $vars[] = 'event-list';
            return $vars;
        }


        /**
     * Hust flush rewrite rules
     *
     * @since     3.0.1
     * 
     */
        public function wpstream_custom_flush_rewrite_rules() {
            // Rebuild WordPress rewrite rules so the newly registered endpoints resolve.
            flush_rewrite_rules();
        }


        /**
     * Add new sections in woocomerce account
     *
     * @since     3.0.1
     * @param     array $items Existing WooCommerce My Account menu items (endpoint => label).
     * @return    array Menu items with the plugin's entries inserted just before logout.
    */
    public function wpstream_custom_my_account_menu_items( $items ) {
        // Theme may provide extra endpoints (start-streaming, watch-later); only add those if present.
        $has_theme_endpoints    = function_exists( 'wpstream_theme_my_custom_endpoints' );

        // Keep WooCommerce defaults and remove only plugin-specific entries before rebuilding plugin order.
        unset( $items['event-list'], $items['video-list'], $items['start-streaming'], $items['watch-later'] );

        // Build the ordered list of plugin menu entries.
        $custom_items               = array();
	    $custom_items['event-list'] = __( 'My Live Streams', 'wpstream' );
	    $custom_items['video-list'] = __( 'My Videos', 'wpstream' );
	    if ( $has_theme_endpoints ) {
		    // Theme-provided endpoints only appear when the theme registered them.
		    $custom_items['start-streaming'] = esc_html__( 'Start Streaming', 'wpstream' );
            $custom_items['watch-later'] = esc_html__( 'Watch Later', 'wpstream' );
        }

	    // Nothing to add: hand the menu back untouched.
	    if ( empty( $custom_items ) ) {
            return $items;
        }

        // Insert custom items before logout to preserve WooCommerce/account plugin menu entries.
        $result = array();
        foreach ( $items as $endpoint => $label ) {
            // Splice the plugin entries in right before the logout link.
            if ( 'customer-logout' === $endpoint ) {
                foreach ( $custom_items as $custom_endpoint => $custom_label ) {
                    $result[ $custom_endpoint ] = $custom_label;
                }
            }

            // Preserve the original item order.
            $result[ $endpoint ] = $label;
        }

        // No logout entry existed to anchor on; append the plugin items at the end.
        if ( ! isset( $items['customer-logout'] ) ) {
            foreach ( $custom_items as $custom_endpoint => $custom_label ) {
                $result[ $custom_endpoint ] = $custom_label;
            }
        }

        return $result;
}


	/**
	 * Function that adds additional post types for the search template
	 *
	 * @param $post_types_array
	 * @return array
	 */
	public function wpstream_search_template_add_item_post_type( $post_types_array ) {
		// Append the plugin's product/VOD/bundle post types so they surface in the search template.
		return array_merge( $post_types_array, array( 'wpstream_product_vod', 'wpstream_product', 'product', 'wpstream_bundles' ) );
	}

	/**
	 * Function that changes the sidebar id based on the post type
	 *
	 * @param $sidebar_id
	 * @return mixed|string
	 */
	public function wpstream_sidebar_id_by_post_type( $sidebar_id ) {
		// Determine the post type of the item currently being rendered.
		$current_post_type = get_post_type( get_the_ID() );

		// VOD items get the dedicated VOD sidebar.
		if( $current_post_type == 'wpstream_product_vod' ) {
			return 'sidebar-vod';
		}
		// WooCommerce products get the products sidebar.
		if( $current_post_type == 'product' ) {
			return 'sidebar-products';
		}
		// Live channels get the live sidebar.
		if( $current_post_type == 'wpstream_product' ) {
			return 'sidebar-live';
		}
		// Anything else keeps the incoming sidebar id.
		return $sidebar_id;
	}

	/**
	 * Function to add new items to the header search dropdown post type list
	 *
	 * @param $search_list_values
	 * @return array
	 */
	public function wpstream_header_search_values( $search_list_values ) {
		// Add labelled entries for the plugin post types to the header search dropdown.
		return array_merge($search_list_values, [
			'wpstream_product'     => esc_html__( 'Live Events', 'wpstream' ),
			'wpstream_product_vod' => esc_html__( 'Video on Demand', 'wpstream' ),
			'wpstream_bundles'     => esc_html__( 'Video Bundles', 'wpstream' ),
		]);
	}

	/**
	 * Function to add new items to the category archive query
	 *
	 * @param $post_types
	 * @return array
	 */
	public function wpstream_extend_category_archive_query_filter_callback( $post_types ) {
		// Include the plugin post types in category archive queries.
		return array_merge( $post_types, array( 'product', 'wpstream_bundles', 'wpstream_product_vod', 'wpstream_product' ) );
	}

	/**
	 * Function to add new labels to the list of taxonomies
	 *
	 * @param $taxonomy_labels
	 * @return mixed
	 */
	public function wpstream_archives_lists_taxonomy_labels_callback( $taxonomy_labels ) {
		// Provide human-readable labels for each plugin post type in the archives list.
		$taxonomy_labels['product'] = esc_html__( 'Video Products', 'wpstream' );
		$taxonomy_labels['wpstream_bundles'] = esc_html__( 'Bundles', 'wpstream' );
		$taxonomy_labels['wpstream_product'] = esc_html__( 'Free Events', 'wpstream' );
		$taxonomy_labels['wpstream_product_vod'] = esc_html__( 'Free Vod', 'wpstream' );
		return $taxonomy_labels;
	}

	/**
	 * Function to add new labels to the list of taxonomies for author archive
	 *
	 * @param $taxonomy_labels
	 * @return mixed
	 */
	public function wpstream_author_archive_list_taxonomy_labels_callback( $taxonomy_labels ) {
		// Same labels as the archives list, applied to the author archive.
		$taxonomy_labels['product'] = esc_html__( 'Video Products', 'wpstream' );
		$taxonomy_labels['wpstream_bundles'] = esc_html__( 'Bundles', 'wpstream' );
		$taxonomy_labels['wpstream_product_vod'] = esc_html__( 'Free Vod', 'wpstream' );
		$taxonomy_labels['wpstream_product'] = esc_html__( 'Free Events', 'wpstream' );
		return $taxonomy_labels;
	}

	/**
	 * Function to add new post type to the vod attached to the channel
	 *
	 * @param $post_type_array
	 * @return array
	 */
	public function wpstream_vod_attached_to_channel( $post_type_array ) {
		// Allow VOD items to be listed among the recordings attached to a channel.
		return array_merge( $post_type_array, array( 'wpstream_product_vod' ) );
	}

	/**
	 * Function to add new post type to the additional post type content
	 *
	 * @param $post_type_list
	 * @return array
	 */
	public function wpstream_additional_content_post_type_callback( $post_type_list ) {
		// Add the plugin post types to the "additional content" list.
		return array_merge( $post_type_list, array( 'wpstream_product_vod', 'wpstream_product', 'wpstream_bundle_bcks' ) );
	}

	/**
	 * Add the plugin post types to the author-content post type list.
	 *
	 * @param array $post_type_list Existing post types.
	 * @return array Post types with the plugin types appended.
	 */
	public function wpstream_post_author_content_post_type_list_callback( $post_type_list ) {
		// Include live/VOD/bundle post types when listing an author's content.
		return array_merge( $post_type_list, array( 'wpstream_product_vod', 'wpstream_product', 'wpstream_bundles' ) );
	}

	/**
	 * Verb shown before the date on an author's simple content listing, per post type.
	 *
	 * @param string $message   Default message.
	 * @param string $post_type Post type being rendered.
	 * @return mixed|string The post-type-appropriate action verb, or the default.
	 */
	public function wpstream_author_content_simple_post_type_message_callback( $message, $post_type ) {
		// Pick the action verb that matches the content type.
		switch ( $post_type ) {
			case 'wpstream_product_vod':
				// VOD items were "Published".
				$message = esc_html__( 'Published ', 'wpstream' );
				break;
			case 'wpstream_product':
				// Live channels "Started streaming".
				$message = esc_html__( 'Started streaming ', 'wpstream' );
				break;
			case 'wpstream_bundles':
				// Bundles were "Added".
				$message = esc_html__( 'Added ', 'wpstream' );
				break;
		}
		return $message;
	}

	/**
	 * Verb shown before the date on an author's content listing, per post type.
	 *
	 * @param string $message   Default message.
	 * @param string $post_type Post type being rendered.
	 * @return mixed|string The post-type-appropriate action verb, or the default.
	 */
	public function wpstream_author_content_post_type_message_callback( $message, $post_type ) {
		// Pick the action verb that matches the content type.
		switch ( $post_type ) {
			case 'wpstream_product_vod':
				// VOD items were "Published".
				$message = esc_html__( 'Published ', 'wpstream' );
				break;
			case 'wpstream_product':
				// Live channels "Started streaming".
				$message = esc_html__( 'Started streaming ', 'wpstream' );
				break;
			case 'wpstream_bundles':
				// Bundles were "Added".
				$message = esc_html__( 'Added ', 'wpstream' );
				break;
		}
		return $message;
	}

	/**
	 * Function to show the sidebar for the post type
	 *
	 * @param $default
	 * @param $post_type
	 * @return bool|mixed
	 */
	public function wpstream_show_sidebar_for_post_type_callback( $default, $post_type ) {
		// Each post type reads its own Customizer toggle to decide whether a sidebar shows.
		switch ( $post_type ) {
			case 'page':
				return get_theme_mod( 'wpstream_page_sidebar', true );
			case 'wpstream_product_vod':
				return get_theme_mod( 'wpstream_video_on_demand_sidebar', true );
			case 'wpstream_product':
			case 'wpstream_bundles':
				// Live channels and bundles share the "free to view live" sidebar toggle.
				return get_theme_mod( 'wpstream_free_to_view_live_sidebar', true );
			case 'product':
				return get_theme_mod( 'wpstream_product_details_page_sidebar', true );
			default:
				// Unknown types keep the caller's default.
				return $default;
		}
	}

	/**
	 * Function to add new post types to the video episodes
	 *
	 * @param $post_type
	 * @return array
	 */
	public function wpstream_video_episodes_post_type_callback( $post_type ) {
		// Treat live, VOD and WooCommerce products as valid "video episode" post types.
		return array_merge( $post_type, array( 'wpstream_product', 'wpstream_product_vod', 'product' ) );
	}

	/**
	 * Function to add new post types to the vod episodes
	 *
	 * @param $post_type
	 * @return array
	 */
	public function wpstream_video_past_broadcast_post_type_callback( $post_type ) {
		// Past broadcasts are stored as VOD items.
		return array_merge( $post_type, array( 'wpstream_product_vod' ) );
	}

	/**
	 * Function to return the label for the additional content based on the post type
	 *
	 * @param string $label     Default label.
	 * @param string $post_type Post type being rendered.
	 * @return array|string "watching" for live channels, "views" for other media, or the default for posts.
	 */
	public function wpstream_additional_content_post_type_label_callback( $label, $post_type ) {
		// Regular posts keep the incoming label.
		if ( 'post' === $post_type ) {
			return $label;
		} elseif ( 'wpstream_product' === $post_type ) {
			// Live channels count concurrent viewers ("watching").
			return __( 'watching', 'wpstream' );
		} else {
			// Everything else counts total "views".
			return __( 'views', 'wpstream' );
		}
	}

        /**
     * Add new endpoint
     *
     * @since     3.0.1
    */
        public function wpstream_custom_endpoint_content_event_list() {
            // Render the "My Live Streams" account tab from its template.
            include plugin_dir_path( __DIR__ ).'templates/myaccount/event_list.php';
        }


        /**
     * Add new endpoint
     *
     * @since     3.0.1
    */
        public function wpstream_custom_endpoint_video_list() {
            // Render the "My Videos" account tab from its template.
            include plugin_dir_path( __DIR__ ).'templates/myaccount/video_list.php';
        }

        
        
        
     
        
        /**
     * register shortcodes
     *
     * @since     3.0.1
         * 
    */
        public function wpstream_shortcodes(){
            // [wpstream_player] renders the in-page player for a given product/user id.
            add_shortcode('wpstream_player',        array($this,'wpstream_insert_player_inpage_local') );
            // [wpstream_chat] was removed with the built-in chat (issue #110).
            // [wpstream_go_live] renders the start-streaming (channel on/off) unit.
            add_shortcode('wpstream_go_live',                array($this,'wpstream_start_streaming_shortocde') );

            // [wpstream_list_media_channels] lists live channels (bypasses the page builder wrapper).
            add_shortcode('wpstream_list_media_channels', array($this,'wpstream_media_list_bakery_bypass') );
            // [wpstream_list_media_vod] lists VOD items (bypasses the page builder wrapper).
            add_shortcode('wpstream_list_media_vod', array($this,'wpstream_media_list_bakery_vod_bypass') );
        }
        
        
        /**
     * Register WpStream elements with Visual Composer / WPBakery and add the
     * classic-editor (TinyMCE) shortcode buttons.
     *
     * Each vc_map() call describes one draggable element (player, chat, lists,
     * start-streaming) and its editable params. The TinyMCE buttons are only
     * added for users who can edit content and use the visual editor.
     *
     * @since     3.0.1
     * @return    void
    */

        public function wpstream_bakery_shortcodes(){
            // register shortcodes for visual composer
            // Only map anything when Visual Composer / WPBakery is present.
            if( function_exists('vc_map') ):

                // Map: Start Streaming button element.
                vc_map(
                    array(
                       "name" => esc_html__( "WpStream Start Streaming Button","wpstream"),
                       "base" => "wpstream_go_live",
                       "class" => "",
                       "category" => esc_html__( 'WpStream','wpstream'),
                       'admin_enqueue_js' => array(get_template_directory_uri().'/vc_extend/bartag.js'),
                       'admin_enqueue_css' => array(get_template_directory_uri().'/vc_extend/bartag.css'),
                       'weight'=>100,
                       'icon'   =>'',
                       'description'=>esc_html__( 'Insert WpStream Start Streaming Button','wpstream'),
                       "params" => array(
                            array(
                                "type" => "textfield",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "Product/Free Product Id","wpstream"),
                                "param_name" => "id",
                                "value" => "",
                                "description" => esc_html__( "If you leave this option blank we will stream on the first free/paid channel for this user","wpstream")
                            ),
                          

                       )
                    )
                );
            
            
            
                // Map: standard Player element.
                vc_map(
                    array(
                       "name" => esc_html__( "WpStream Player","wpstream"),
                       "base" => "wpstream_player",
                       "class" => "",
                       "category" => esc_html__( 'WpStream','wpstream'),
                       'admin_enqueue_js' => array(get_template_directory_uri().'/vc_extend/bartag.js'),
                       'admin_enqueue_css' => array(get_template_directory_uri().'/vc_extend/bartag.css'),
                       'weight'=>100,
                       'icon'   =>'',
                       'description'=>esc_html__( 'Insert WpStream Player','wpstream'),
                       "params" => array(
                            array(
                                "type" => "textfield",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "Product/Free Product Id","wpstream"),
                                "param_name" => "id",
                                "value" => "0",
                                "description" => esc_html__( "Add here the live stream id or the video id","wpstream")
                            ),  
                           array(
                                "type" => "textfield",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "User Id","wpstream"),
                                "param_name" => "user_id",
                                "value" => "",
                                "description" => esc_html__( "We will use the first channel of this user id(product id will be ignored.).","wpstream")
                            ),

                       )
                    )
                );


                // Shared dropdown options reused by the channel/VOD list maps below.
                // Free vs paid filter.
                $media_catalog = $this->main->media_list->catalog( 'live_channel' );
                $free_paid_type=array(
                   0 => $media_catalog['choices']['access']['free'],
                   1 => $media_catalog['choices']['access']['paid']
                );

                // "Only show live channels" yes/no.
                $live_settings=array(

                    'no'  => $media_catalog['choices']['active_only'][false],
                    'yes' => $media_catalog['choices']['active_only'][true],
                );

                // Ordering options.
                $order_by_id=array(
                    0 => $media_catalog['choices']['order']['date_asc'],
                    1 => $media_catalog['choices']['order']['date_desc'],
                    2 => $media_catalog['choices']['order']['title_asc'],
                    3 => $media_catalog['choices']['order']['title_desc'],
                );

                // Map: Channel List element.
                vc_map(
                    array(
                       "name" => esc_html__( "WpStream Channel List","wpstream"),
                       "base" => "wpstream_list_media_channels",
                       "class" => "",
                       "category" => esc_html__( 'WpStream','wpstream'),
                       'admin_enqueue_js' => array(get_template_directory_uri().'/vc_extend/bartag.js'),
                       'admin_enqueue_css' => array(get_template_directory_uri().'/vc_extend/bartag.css'),
                       'weight'=>100,
                       'icon'   =>'',
                       'description'=>esc_html__( ' List wpstream channels','wpstream'),
                       "params" => array(

                      
                           
                            array(
                                "type" => "dropdown",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "Show Free or Paid Media ?","wpstream"),
                                "param_name" => "product_type_free_paid",
                                "value" => $free_paid_type,
                                "description" => esc_html__( "What type of media(free/paid) ","wpstream")
                            ),

                            array(
                                "type" => "dropdown",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "Only show active channels","wpstream"),
                                "param_name" => "product_show_live",
                                "value" => $live_settings,
                                "description" => esc_html__( "Only show channels that are live streaming right now.","wpstream")
                            ),
                            array(
                                 "type" => "textfield",
                                 "holder" => "div",
                                 "class" => "",
                                 "heading" => esc_html__( "Number of Items per Page","wpstream"),
                                 "param_name" => "media_number",
                                 "value" => (string) $media_catalog['defaults']['items_per_page'],
                                 "description" => esc_html__( "How many items will be displayed per page","wpstream")
                             ),

                            array(
                                 "type" => "textfield",
                                 "holder" => "div",
                                 "class" => "",
                                 "heading" => esc_html__( "Link Label for free items","wpstream"),
                                 "param_name" => "free_label",
                                 "value" => $media_catalog['builder_defaults']['free_label'],
                                 "description" => esc_html__( "Link Label for free items'","wpstream")
                             ),

                            array(
                                "type" => "dropdown",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "Order by","wpstream"),
                                "param_name" => "order_by",
                                "value" => $order_by_id,
                                "description" => esc_html__( "Order type","wpstream")
                            ),


                       )
                    )
                );
                     
                     
                   // Map: VOD List element.
                   vc_map(
                    array(
                       "name" => esc_html__( "WpStream VOD List","wpstream"),
                       "base" => "wpstream_list_media_vod",
                       "class" => "",
                       "category" => esc_html__( 'WpStream','wpstream'),
                       'admin_enqueue_js' => array(get_template_directory_uri().'/vc_extend/bartag.js'),
                       'admin_enqueue_css' => array(get_template_directory_uri().'/vc_extend/bartag.css'),
                       'weight'=>100,
                       'icon'   =>'',
                       'description'=>esc_html__( ' List wpstream video on demand','wpstream'),
                       "params" => array(

                      
                           
                            array(
                                "type" => "dropdown",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "Show Free or Paid Media ?","wpstream"),
                                "param_name" => "product_type_free_paid",
                                "value" => $free_paid_type,
                                "description" => esc_html__( "What type of media(free/paid) ","wpstream")
                            ),

                          
                            array(
                                 "type" => "textfield",
                                 "holder" => "div",
                                 "class" => "",
                                 "heading" => esc_html__( "Number of Items per Page","wpstream"),
                                 "param_name" => "media_number",
                                 "value" => (string) $media_catalog['defaults']['items_per_page'],
                                 "description" => esc_html__( "How many items will be displayed per page","wpstream")
                             ),

                            array(
                                 "type" => "textfield",
                                 "holder" => "div",
                                 "class" => "",
                                 "heading" => esc_html__( "Link Label for free items","wpstream"),
                                 "param_name" => "free_label",
                                 "value" => $media_catalog['builder_defaults']['free_label'],
                                 "description" => esc_html__( "Link Label for free items'","wpstream")
                             ),

                            array(
                                "type" => "dropdown",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "Order by","wpstream"),
                                "param_name" => "order_by",
                                "value" => $order_by_id,
                                "description" => esc_html__( "Order type","wpstream")
                            ),


                       )
                    )
                );   

                
            endif;


            // add shorcotes to editor interface
            // Only wire the TinyMCE buttons for users who can edit posts or pages.
            if (!current_user_can('edit_posts') && !current_user_can('edit_pages')) {
                return;
            }

            // Register the TinyMCE plugin + toolbar button only when the user is on the visual editor.
            if (get_user_option('rich_editing') == 'true') {
                add_filter('mce_external_plugins', array( $this,'wpstream_add_plugin') );
                add_filter('mce_buttons_2', array($this,'wpstream_register_button') );
            }
        }
        
        /**
     * list channels - shortcode function
     *
     * Adapts the [wpstream_list_media_channels] shortcode attributes and hands
     * them to the shared Elementor list renderer.
     *
     * @since     3.0.1
     * @param     array $attributes Raw shortcode attributes.
     * @return    string Rendered channel-list HTML.
    */

        public function wpstream_media_list_bakery_bypass($attributes){
			$attributes = shortcode_atts(
				array(
					'media_number'                       => 3,
                    'product_type_free_paid'             => '0',
                    'product_show_live'                  => 'no',
                    'free_label'                         =>  '',
                    'order_by'                           =>  '0'
				),
				is_array( $attributes ) ? $attributes : array()
			);
			return $this->main->media_list->render( 'live_channel', $this->filter_media_list_shortcode_atts( $attributes, 'live_channel' ) );
        }
        
        /**
     * list vod - shortcode function
     *
     * Adapts the [wpstream_list_media_vod] shortcode attributes and hands them
     * to the shared Elementor list renderer.
     *
     * @since     3.0.1
     * @param     array $attributes Raw shortcode attributes.
     * @return    string Rendered VOD-list HTML.
    */

        public function wpstream_media_list_bakery_vod_bypass($attributes){
			$attributes = shortcode_atts(
				array(
                    'media_number'                       => 3,
                    'product_type_free_paid'             => '0',
                    'product_show_live'                  => 'no',
                    'free_label'                         =>  '',
                    'order_by'                           =>  '0'
				),
				is_array( $attributes ) ? $attributes : array()
			);
			return $this->main->media_list->render( 'vod', $this->filter_media_list_shortcode_atts( $attributes, 'vod' ) );
        }

	/**
	 * Run the normalised attributes of a Media List shortcode through its filter.
	 *
	 * @param array  $attributes Attributes after shortcode_atts() defaults.
	 * @param string $kind       `live_channel` or `vod`.
	 * @return array
	 */
	private function filter_media_list_shortcode_atts( array $attributes, $kind ) {
		/**
		 * Filter the attributes of the [wpstream_list_media_channels] and
		 * [wpstream_list_media_vod] shortcodes before the list renders.
		 *
		 * @since 4.14.0
		 *
		 * @param array  $attributes Shortcode attributes with defaults applied.
		 * @param string $kind       `live_channel` or `vod`.
		 */
		return (array) apply_filters( 'wpstream_media_list_shortcode_atts', $attributes, $kind );
	}
    
        /**
     * [wpstream_player] shortcode - render the in-page player.
     *
     * @since     3.0.1
     * @param     array       $attributes Shortcode attributes ('id' = product id, 'user_id' = fallback owner).
     * @param     string|null $content    Enclosed content (unused).
     * @return    string Buffered player HTML.
    */


        public function wpstream_insert_player_inpage_local($attributes, $content = null){
                // Initialise the target product id and output buffer.
                $product_id     =   '';
                $return_string  =   '';
                // Merge shortcode attributes with defaults.
                $attributes =   shortcode_atts(
                    array(
                        'id'                       => 0,
                        'user_id'                  => 0,
                    ), $attributes) ;


                // Sanitize the explicit product id.
                if ( isset($attributes['id']) ){
                    $product_id = intval( $attributes['id'] );
                }

                // Sanitize the optional user id.
                if ( isset($attributes['user_id']) ){
                    $user_id = intval( $attributes['user_id'] );
                }

                // If no product id was given but a user id was, use that user's first channel.
                if(intval($product_id)==0 && $user_id!=0 ){
                    $product_id= $this->main->wpstream_player_retrieve_first_id($user_id);
                }

                // Capture the player markup into the return string.
                ob_start();
                $this->main->wpstream_player->wpstream_video_player_shortcode($product_id);
                $return_string= ob_get_contents();
                ob_end_clean();

                return $return_string;
        }

          
        /**
     * [wpstream_go_live] shortcode - render the start-streaming (channel on/off) unit.
     *
     * @since     3.7
     * @param     array       $attributes Shortcode attributes ('id' = channel/product id).
     * @param     string|null $content    Enclosed content (unused).
     * @return    string Buffered start-streaming unit HTML.
    */


        public function wpstream_start_streaming_shortocde($attributes, $content = null){
                // Initialise the target product id and output buffer.
                $product_id     =   '';
                $return_string  =   '';

                // Merge shortcode attributes with defaults.
                $attributes =   shortcode_atts(
                    array(
                        'id'                       => 0,
                    ), $attributes) ;


                // Sanitize the channel/product id.
                if ( isset($attributes['id']) ){
                    $product_id=intval($attributes['id']);
                }


                // Capture the front-end streaming unit markup from the main plugin object.
                ob_start();
                    global $wpstream_plugin;
                    $wpstream_plugin->wpstream_live_stream_unit_wrapper(   $product_id,'front' );
                    $return_string= ob_get_contents();
                ob_end_clean();

                return $return_string;
        }



        /**
     * register shortcodes - add buttons in js (mce_external_plugins filter).
     *
     * @since     3.0.1
     * @param     array $plugin_array Registered TinyMCE external plugins.
     * @return    array Plugins with the WpStream editor plugin added.
    */

        public function wpstream_add_plugin($plugin_array) {
            $plugin_array['wpstream_player'] = plugin_dir_url( __FILE__ ). '/js/shortcodes.js';
            return $plugin_array;
        }
         
        /**
     * register shortcodes - add buttons (mce_buttons_2 filter).
     *
     * @since     3.0.1
     * @param     array $buttons Existing TinyMCE toolbar buttons.
     * @return    array Buttons with the WpStream shortcode buttons appended.
    */
        public function wpstream_register_button($buttons) {
            array_push($buttons, "|", "wpstream_player");
            return $buttons;
        }


        
        /**
     * Answer CORS preflight (OPTIONS) requests.
     *
     * Responds to a browser preflight with the allowed methods/headers and exits,
     * so cross-origin key/API calls can proceed.
     *
     * @since     3.0.1
     * @return    void
    */

        public function wpstream_cors_check_and_response(){
            // Only handle the preflight OPTIONS verb; other methods fall through.
            if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
                // Advertise the permitted methods and headers, then send an empty 200 and stop.
                header('Access-Control-Allow-Methods: POST, GET');
                header('Access-Control-Allow-Headers: Authorization');
                header('Access-Control-Max-Age: 1');  //1728000
                header("Content-Length: 0");
                header("Content-Type: text/plain charset=UTF-8");
                exit(0);
            }
        }
        
        /**
     * Seed the per-visitor session id used to bind DRM key requests.
     *
     * When session encryption is enabled globally, ensures a PHP session exists
     * and assigns a unique 'wpstream_id' - but not during an actual key request
     * (livedrm/voddrm/keys2), so the id is minted on a normal page view first.
     *
     * @since     3.0.1
     * @return    void
    */

        public function wpstream_set_cookies(){
            // Read the global streaming channel options.
            $local_event_options =   get_option('wpstream_user_streaming_global_channel_options') ;

            // Only proceed when session-based encryption is switched on.
            if( !isset($local_event_options['ses_encrypt']) || intval($local_event_options['ses_encrypt'])!=1 )    {
                return;
            }

            // Cron and REST requests never render a player nor request a key: no session at all.
            if ( wp_doing_cron() || ( defined('REST_REQUEST') && REST_REQUEST ) ) {
                return;
            }

            // Key-delivery requests only READ the previously minted id: load the
            // session data and release the file lock in one step, never minting.
            if( isset( $_GET[ 'wpstream_livedrm' ]) || isset( $_GET[ 'wpstream_voddrm' ])  || isset( $_REQUEST[ 'keys2' ]) ) {
                if (session_status() == PHP_SESSION_NONE) {
                    session_start( array( 'read_and_close' => true ) );
                }
                return;
            }

            // Ordinary page view: mint the id once, then close the session
            // immediately — holding the lock open would serialize every
            // concurrent request from this visitor for the whole page load.
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            if( !isset($_SESSION['wpstream_id']) ){
                $_SESSION['wpstream_id']= uniqid();
            }
            session_write_close();
        }
        
        /**
         * editd 4.0
         *
     * HLS key-delivery endpoint for LIVE streams.
     *
     * Fired on the wpstream_livedrm request. Resolves the stream name to a post,
     * then either serves the key for a free event, or - for paid events - only
     * after verifying the logged-in user bought the product or holds an active
     * subscription. Free-event and paid-event post lookups are cached in
     * transients; the remote key itself is fetched and cached by the DRM Key
     * Delivery Module. The compatibility Adapter emits the returned result.
     *
     * Note: when session encryption is on, a missing session id aborts the request.
     *
     * @since     3.0.1
     * @return    void Emits the key and exits, or returns early when no livedrm request.
    */


	public function wpstream_live_streaming_key() {
		$this->wpstream_emit_drm_key_request( 'live', 'wpstream_livedrm' );
	}

	/**
     * Return restream (YouTube/Twitch) RTMP URLs for a third-party publisher.
     *
     * Fired on the thirdkeys request. Looks up the post whose live_event_carnat2
     * meta matches the supplied key and returns its configured YouTube/Twitch
     * RTMP targets as JSON. Emits '{}' when nothing matches. Prints and dies.
     *
     * @since     3.0.1
     * @return    void Emits JSON and exits, or returns early when no thirdkeys request.
    */


        public function wpstream_live_streaming_key_for_3rdparty(){

            // Only act when a non-empty thirdkeys request is present.
            if( isset( $_REQUEST[ 'thirdkeys' ]) && $_REQUEST[ 'thirdkeys' ]!='' ) {

                // Sanitize the supplied third-party key.
                $thirdkeys         =   esc_html($_REQUEST[ 'thirdkeys' ]);

                //live_event_carnat2

                // Find the live/product post whose live_event_carnat2 meta matches the key.
                $args = array(
                    'post_type'      => array('product','wpstream_product'),
                    'post_status'    => 'publish',
                    'meta_query'     =>array(
                                        array(
                                        'key'      => 'live_event_carnat2',
                                        'value'    => $thirdkeys,
                                        'compare'  => '=',
                                        ),
                        ),


                );


                $media_list= new WP_Query($args);
                if($media_list->have_posts()){
                    // Return the first match's restream URLs as JSON.
                    while($media_list->have_posts()):$media_list->the_post();

                        // Collect this post's YouTube and Twitch RTMP targets.
                        $media_id       =   get_the_ID();
                        $replay_array   =   array(
                           // '', // fb will be here
                            stripslashes( get_post_meta($media_id,'wpstream_youtube_rtmp',true )),
                            stripslashes( get_post_meta($media_id,'wpstream_twich_rtmp',true) ),
                        );

                        // Emit the URLs as JSON and stop.
                        $reply_final=array('rtmp_urls'=>$replay_array);
                        header('Content-Type: application/json;charset=utf-8');
                        print json_encode($reply_final,JSON_UNESCAPED_SLASHES);
                        die();


                    endwhile;
                }else{
                    // No match: return an empty JSON object.
                    print'{}';
                    die('');
                }

            }

         }
        
          
         
    /**
     * HLS key-delivery endpoint for VOD (video-on-demand) items.
     *
     * Fired on the wpstream_voddrm request. Mirrors the live endpoint: resolves
     * the decryption key index to a post, serves the base64-decoded key for a
     * free VOD, or - for paid VOD - only after verifying the logged-in user
     * bought the product or holds an active subscription. Post lookups are
     * cached in transients. Prints the key and dies.
     *
     * Note: when session encryption is on, a missing session id aborts the request.
     *
     * @since     3.0.1
     * @return    void Emits the key and exits, or returns when no voddrm request.
    */
	public function wpstream_live_streaming_key_vod() {
		$this->wpstream_emit_drm_key_request( 'vod', 'wpstream_voddrm' );
	}

	/**
	 * Translate one legacy WordPress request into the DRM Key Delivery Interface.
	 *
	 * @param string $kind      `live` or `vod`.
	 * @param string $parameter Legacy GET parameter name.
	 * @return void
	 */
	private function wpstream_emit_drm_key_request( $kind, $parameter ) {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( 'GET' !== $method || ! isset( $_GET[ $parameter ] ) || ! is_scalar( $_GET[ $parameter ] ) ) {
			return;
		}

		$identifier = sanitize_text_field( wp_unslash( (string) $_GET[ $parameter ] ) );
		if ( '' === $identifier ) {
			return;
		}

		$result = $this->main->drm_key_delivery->deliver(
			array(
				'kind'        => $kind,
				'identifier'  => $identifier,
				'has_session' => isset( $_SESSION['wpstream_id'] ),
			)
		);
		if ( ! is_array( $result ) || empty( $result['handled'] ) ) {
			return;
		}

		status_header( isset( $result['status'] ) ? intval( $result['status'] ) : 200 );
		foreach ( (array) ( $result['headers'] ?? array() ) as $name => $value ) {
			header( $name . ': ' . $value, true );
		}
		print isset( $result['body'] ) ? $result['body'] : '';
		die();
	}
     
       
    


         
        
        
        
        
        
        
        
        
         
        /**
     * Open a wrapper div around WooCommerce product content, keyed to purchase state.
     *
     * Emits a different wrapper id depending on whether the logged-in viewer has
     * bought the current product, so CSS/JS can show or gate the content.
     *
     * @since     3.0.1
     * @return    void Echoes an opening <div>.
    */


        public function wpstream_non_image_content_wrapper_start() {
            // Only wrap for logged-in viewers (purchase check needs a user).
            if ( is_user_logged_in() ) {
                global $product;
                $current_user   =   wp_get_current_user();
                // Resolve the current WooCommerce product.
                $product        =   wc_get_product();

                if($product){
                    $product_id = $product->get_id();
                    // Buyers get the standard wrapper; non-buyers get the "no buy" wrapper.
                    if ( wc_customer_bought_product( $current_user->user_email, $current_user->ID, $product_id) ){
                        echo '<div id="wpstream_product_wrap">';
                    }else{
                        echo '<div id="wpstream_product_wrap_no_buy">';
                    }
                }
            }

        }

        /**
     * Close the wrapper opened by wpstream_non_image_content_wrapper_start().
     *
     * The closing div is currently commented out (left intentionally).
     *
     * @since     3.0.1
     * @return    void
    */


        function wpstream_non_image_content_wrapper_end() {
           // echo '</div>';
        }
        
      
        /**
     * Append the WpStream access message to the WooCommerce thank-you page.
     *
     * @since     3.12
     * @param     mixed    $var   Existing thank-you text passed by the filter.
     * @param     WC_Order $order The completed order.
     * @return    string The ordered-items access message.
    */

        function wpstream_thankyou_extra($var,$order){
            // Reuse the shared message builder for the order.
            return $this->wpstream_get_ordered_items($order);
        }
        
          
        /**
     * Print the WpStream access message inside WooCommerce order emails.
     *
     * @since     3.12
     * @param     WC_Order $order         The order the email is for.
     * @param     bool     $sent_to_admin Whether the email is going to the admin.
     * @param     bool     $plain_text    Whether the email is plain text.
     * @param     WC_Email $email         The email object.
     * @return    void Echoes the access message.
    */
        function wpstream_email_order_details($order, $sent_to_admin, $plain_text, $email){
            // Output the shared message followed by a line break.
            print $this->wpstream_get_ordered_items($order).'</br>';
        }
        
        
             
        /**
     * Compose the access message shown for a completed order / thank-you page.
     *
     * Builds a comma-separated list of purchased item links and substitutes it
     * into the configurable thank-you template - but only for streamable items
     * (live stream, VOD or subscription); other product types get a generic
     * "order received" message instead.
     *
     * @since     3.12
     * @param     WC_Order $order The order to describe.
     * @return    string The access or generic confirmation message.
    */

        function wpstream_get_ordered_items($order){

            // WooCommerce templates pass WC_Order|false (core order-received.php)
            // or null (thankyou.php no-order branch) - bail to the generic
            // message before dereferencing the order.
            if ( ! $order instanceof WC_Order ) {
                return esc_html__( 'Thank you. Your order has been received', 'wpstream' );
            }

            // Start from the admin-configured thank-you template (contains the {item_link} placeholder).
            $message    =  esc_html( get_option('wpstream_product_thankyou','Thanks for your purchase. You can access your item at any time by visiting the following page: {item_link}')) ;
            $list       =   '';
            $product_id = 0;

            // Build a comma-separated list of links to each purchased product.
            foreach( $order->get_items() as $line_item ) {
                // get_product() returns false for a deleted product or a line item
                // with no real product (e.g. the WooCommerce email-preview dummy
                // order carries product_id 0) - skip those instead of fataling.
                $product = $line_item->get_product();
                if ( ! $product ) {
                    continue;
                }
                $list .= '<a href="'.esc_url( $product->get_permalink() ).'">'.esc_html( $product->get_title() ).'</a>, ';
                $product_id = $product->get_id();
            }
            // Trim the trailing separator and inject the list into the template.
            $list       = trim($list,', ');
            $message    = str_replace('{item_link}', $list, $message);

            // No resolvable product (empty order, all products deleted, or the
            // email-preview dummy order): fall back to the generic confirmation.
            $product = $product_id ? wc_get_product( $product_id ) : false;
            if ( ! $product ) {
                return esc_html__( 'Thank you. Your order has been received', 'wpstream' );
            }

            // Streamable purchases (live stream, VOD, subscription) keep the
            // access message; anything else gets a generic confirmation.
            // get_type() resolves from the product_type term SLUG, so it stays
            // correct even when a site renames the term's display name.
            $product_type = $product->get_type();
            if ( in_array( $product_type, array( 'live_stream', 'video_on_demand', 'subscription' ), true ) ) {
                return $message;
            }

            return esc_html__( 'Thank you. Your order has been received', 'wpstream' );


        }
}
