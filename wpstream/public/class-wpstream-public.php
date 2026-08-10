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

/**
 * The public-facing functionality of the plugin.
 *
 * This class wires up everything WpStream needs on the front end:
 *  - Registering/enqueuing the player, chat and streaming CSS/JS (and their
 *    localized i18n + config data).
 *  - WooCommerce "My Account" endpoints (event-list, video-list, start-streaming)
 *    and menu items.
 *  - Shortcodes ([wpstream_player], [wpstream_chat], [wpstream_go_live], list
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
     * Register the stylesheets for the public-facing side of the site.
     *
     * Loads the plugin's own front-end CSS, the Video.js player CSS, and the
     * integrations stylesheet. The player-skin file is cache-busted with its
     * own filemtime so skin tweaks show up immediately.
     *
     * @since    3.0.1
     * @return   void
     */
    public function enqueue_styles() {

            // Core plugin front-end styles.
            wp_enqueue_style('wpstream-style',          plugin_dir_url( __FILE__ ) .'/css/wpstream_style.css',array(), WPSTREAM_PLUGIN_VERSION, 'all' );
            // Base Video.js player CSS.
            wp_enqueue_style('video-js.min',            plugin_dir_url( __FILE__ ).'css/video-js.css', array(), WPSTREAM_PLUGIN_VERSION, 'all');
            // WpStream player skin on top of Video.js; version suffixed with filemtime for cache-busting.
            wp_enqueue_style(
				'videojs-wpstream-player',
				plugin_dir_url( __FILE__ ).'css/videojs-wpstream.css',
				array(),
				WPSTREAM_PLUGIN_VERSION . '.' . filemtime( plugin_dir_path(__FILE__) . 'css/videojs-wpstream.css' ),
				'all'
			);
            // Third-party integrations (e.g. BuddyBoss) styling.
            wp_enqueue_style('wpstream-integrations',   plugin_dir_url( __DIR__ ) .'integrations/css/integrations.css',array(), WPSTREAM_PLUGIN_VERSION, 'all' );

    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * Registers/enqueues Video.js and its quality-selector + logo plugins, the
     * WpStream player bootstrap/controls, the chat client libraries, and the
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
        // Register (not enqueue) the core Video.js library from the CDN; templates enqueue it on demand.
        wp_register_script('video.min',              'https://vjs.zencdn.net/8.20.0/video.min.js', WPSTREAM_PLUGIN_VERSION, true);

        // Quality selector dependency (Video.js plugin)
        // Video.js contrib "quality levels" plugin, required by the WpStream quality selector below.
        wp_register_script(
            'videojs-contrib-quality-levels',
            'https://cdn.jsdelivr.net/npm/videojs-contrib-quality-levels@4.0.0/dist/videojs-contrib-quality-levels.min.js',
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

		// Enqueue the VideoJS Logo plugin script
        // Video.js "logo" plugin used to overlay the configurable player watermark.
	    wp_enqueue_script(
			'videojs-logo',
		    'https://cdn.jsdelivr.net/npm/videojs-logo@latest/dist/videojs-logo.min.js',
			array('video.min'),
			'3.0.0',
			true
	    );

        // Ensure quality selector scripts are available wherever wpstream-player runs
        // Force-enqueue the quality-level plugin and selector so every player page has them.
        wp_enqueue_script('videojs-contrib-quality-levels');
        wp_enqueue_script('wpstream-quality-selector');

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
                wp_register_script(
					'wpstream-player',
					plugin_dir_url( __FILE__ ).'js/wpstream-player.js',
                    array('video.min','wpstream-quality-selector','wpstream-player-bootstrap'),
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

				// Determine whether adaptive bitrate is enabled for the current post from its event options meta.
				$abr_enabled = false;
				$post_meta = get_post_meta( get_the_ID(), 'local_event_options', true );
				if ( !empty($post_meta) && isset($post_meta['adaptive_bitrate']) && $post_meta['adaptive_bitrate'] == 1 ) {
					$abr_enabled = true;
				}
				// Localize the main player script with UI strings, player theme/logo, streamify flag, status nonce and ABR flag.
				wp_localize_script('wpstream-player', 'wpstream_player_vars',
					array(
						'admin_url'                         =>  get_admin_url(),
						'chat_not_connected'                =>  esc_html__('Inactive Channel - Chat is disabled.','wpstream'),
						'server_up'                         =>  esc_html__('The live stream is paused and may resume shortly.','wpstream'),
						'wpstream_player_state_stopped_msg' =>  esc_html__(get_option('wpstream_you_are_not_live','We are not live at this moment'),'wpstream'),
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
					)
				);
                
                // WordPress-bundled jQuery UI pieces used by the front end.
                wp_enqueue_script( 'jquery-ui-autocomplete' );
                wp_enqueue_script( "jquery-effects-core");

                // Chat client libraries (SockJS transport, emoji, linkify, material UI, and the chat client itself).
                wp_register_script( 'sockjs-0.3.min', plugin_dir_url( __FILE__ ) . '/chat_lib/sockjs-0.3.min.js', array('jquery'), true );
                wp_register_script( 'emojione.min.js',plugin_dir_url( __FILE__ ). '/chat_lib/emojione.min.js', array('jquery'), true );
            
                wp_register_script( 'jquery.linkify.min.js', plugin_dir_url( __FILE__ ). '/chat_lib/jquery.linkify.min.js', array('jquery'), true );
                wp_register_script( 'ripples.min.js',plugin_dir_url( __FILE__ ). '/chat_lib/ripples.min.js', array('jquery'), true );
                wp_register_script( 'material.min.js"', plugin_dir_url( __FILE__ ). '/chat_lib/material.min.js', array('jquery'), true );
                wp_register_script( 'chat.js', plugin_dir_url( __FILE__ ). '/chat_lib/chat.js', array('jquery'), true );
              
                // Pass the chat client the "we are not live" message. The object
                // name must be a valid JS identifier (was 'chat-js-vars', which
                // parsed as a subtraction and threw at runtime).
                wp_localize_script('chat.js', 'wpstream_chat_vars',
                array(
                    'we_are_not_live'             =>    esc_html( get_option('wpstream_you_are_not_live','We are not live at this moment')),
                ));

                // Chat-related stylesheets (registered here, enqueued where the chat widget renders).
                wp_register_style( 'chat.css',plugin_dir_url( __FILE__ ).'/chat_lib/css/chat.css', array(), '1.0', 'all');
                wp_register_style( 'ripples.css',plugin_dir_url( __FILE__ ).'/chat_lib/css/ripples.css', array(), '1.0', 'all');
                wp_register_style( 'emojione.min.css',plugin_dir_url( __FILE__ ).'/chat_lib/css/emojione.min.css', array(), '1.0', 'all');

                
                // Bundled QR generator (SEC-05): the Larix QR is built locally so the
                // RTMP URL + secret stream key never reach a third-party image service.
                wp_register_script('wpstream-qrcode-generator', plugin_dir_url( __FILE__ ) . 'js/vendor/qrcode-generator.js', array(), '1.4.4', true);
                wp_register_script('wpstream-qr',               plugin_dir_url( __FILE__ ) . 'js/wpstream-qr.js', array('wpstream-qrcode-generator'), WPSTREAM_PLUGIN_VERSION, true);

                // Enqueue the start-streaming (channel on/off) script, cache-busted by its file mtime.
                // Depends on wpstream-qr so wpstreamRenderQr() is defined before this runs.
                $modified_start_streaming_file_time = gmdate( 'YmdHi', filemtime( WPSTREAM_PLUGIN_PATH . 'public/js/start_streaming.js' ) );
                wp_enqueue_script('wpstream-start-streaming',   plugin_dir_url( __FILE__ ) .'js/start_streaming.js',array('wpstream-qr'), $modified_start_streaming_file_time, true);
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
                        'no_band'               =>  esc_html__('Not enough streaming data.','wpsteam'),
                        'no_band_no_store'      =>  esc_html__('Not enough streaming data or storage.','wpsteam'),
                        
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
                

                    // Build the integrations payload; flag BuddyBoss/BuddyPress presence so the JS can adapt.
                    $integrations_array=array(
                        'admin_url'             =>  get_admin_url(),
                    );
                    if (class_exists('BuddyPress')) {
                        $integrations_array['is_buddyboss']='yes';
                    }

                    // Enqueue and localize the integrations script (cache-busted per request via time()).
                    wp_enqueue_script('wpstream-integrations',   plugin_dir_url( __DIR__  ) .'integrations/js/integrations.js?v='.time(),array(),  WPSTREAM_PLUGIN_VERSION, true);
                    wp_localize_script('wpstream-integrations', 'wpstream_integrations_vars', $integrations_array );


                // Reuse the admin stylesheet on the front end for shared components.
                wp_enqueue_style( 'wpstream_front_style', plugin_dir_url( __DIR__ ) . 'admin/css/wpstream-admin.css', array(), WPSTREAM_PLUGIN_VERSION, 'all' );

	    // Theme-side helper script (comment form / messaging) shared with the hello-wpstream theme.
	    wp_enqueue_script( 'wpstream-plugin-scripts', WPSTREAM_PLUGIN_DIR_URL . '/hello-wpstream/js/wpstream-plugin-script.js', array( 'jquery' ), '1.0', true );

	    // Localize that helper with the AJAX URL, login state and translated form-validation messages.
	    wp_localize_script(
		    'wpstream-plugin-scripts',
		    'wpstreamPluginScriptsVars',
		    array(
			    'ajaxurl' 				=> 	admin_url( 'admin-ajax.php' ), // WordPress AJAX URL.
			    'processing'			=>	esc_html('sending...','hello-wpstream'),
			    'send_mess' 			=>  esc_html__('Send Message','hello-wpstream'),
			    'is_user_logged_in' 	=> 	is_user_logged_in() ? '1' : '0',
			    'comment_text_empty' 	=>  esc_html__('Please type your comment.','hello-wpstream'),
			    'comment_author_empty' 	=> 	esc_html__('Please enter your name.', 'hello-wpstream'),
			    'comment_email_empty' 	=> 	esc_html__('Please enter your email.', 'hello-wpstream'),
			    'comment_email_invalid' => esc_html__('Please enter a valid email address.', 'hello-wpstream'),
			    'gdpr_agree' 			=> esc_html__('You need to agree with GDPR terms.', 'hello-wpstream'),
		    )
	    );
    }


	/**
	 * Build the translated live-UI status strings passed to the player controls script.
	 *
	 * @return array Map of player state keys to their (escaped) display messages, plus
	 *               an isThemeActive flag telling the JS whether the hello-wpstream theme is in use.
	 */
	public function wpstream_get_player_i18n_config() {
		// Each entry maps a player state to the localized, escaped message shown to viewers.
		return array(
			'wpstream_player_state_stopped_msg'      => esc_html(
				get_option(
					'wpstream_you_are_not_live',
					__( 'We are not live at this moment', 'wpstream' )
				)
			),
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
		    $custom_items['start-streaming'] = esc_html__( 'Start Streaming', 'wpstream-theme' );
            $custom_items['watch-later'] = esc_html__( 'Watch Later', 'wpstream-theme' );
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
			'wpstream_product'     => esc_html__( 'Live Events', 'hello-wpstream' ),
			'wpstream_product_vod' => esc_html__( 'Video on Demand', 'hello-wpstream' ),
			'wpstream_bundles'     => esc_html__( 'Video Bundles', 'hello-wpstream' ),
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
		$taxonomy_labels['product'] = esc_html__( 'Video Products', 'hello-wpstream' );
		$taxonomy_labels['wpstream_bundles'] = esc_html__( 'Bundles', 'hello-wpstream' );
		$taxonomy_labels['wpstream_product'] = esc_html__( 'Free Events', 'hello-wpstream' );
		$taxonomy_labels['wpstream_product_vod'] = esc_html__( 'Free Vod', 'hello-wpstream' );
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
		$taxonomy_labels['product'] = esc_html__( 'Video Products', 'hello-wpstream' );
		$taxonomy_labels['wpstream_bundles'] = esc_html__( 'Bundles', 'hello-wpstream' );
		$taxonomy_labels['wpstream_product_vod'] = esc_html__( 'Free Vod', 'hello-wpstream' );
		$taxonomy_labels['wpstream_product'] = esc_html__( 'Free Events', 'hello-wpstream' );
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
	 * Add new endpoint
	 *
	 * @since     3.0.1
	*/
		public function wpstream_custom_endpoint_start_streaming() {
			include plugin_dir_path( __DIR__ ).'woocommerce/myaccount/start_streaming.php'; // Render the "Start Streaming" My Account tab from its template.
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
				$message = esc_html__( 'Published ', 'hello-wpstream' );
				break;
			case 'wpstream_product':
				// Live channels "Started streaming".
				$message = esc_html__( 'Started streaming ', 'hello-wpstream' );
				break;
			case 'wpstream_bundles':
				// Bundles were "Added".
				$message = esc_html__( 'Added ', 'hello-wpstream' );
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
				$message = esc_html__( 'Published ', 'hello-wpstream' );
				break;
			case 'wpstream_product':
				// Live channels "Started streaming".
				$message = esc_html__( 'Started streaming ', 'hello-wpstream' );
				break;
			case 'wpstream_bundles':
				// Bundles were "Added".
				$message = esc_html__( 'Added ', 'hello-wpstream' );
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
			return __( 'watching', 'hello-wpstream' );
		} else {
			// Everything else counts total "views".
			return __( 'views', 'hello-wpstream' );
		}
	}

        /**
     * Add new endpoint
     *
     * @since     3.0.1
    */
        public function wpstream_custom_endpoint_content_event_list() {
            // Render the "My Live Streams" account tab from its template.
            include plugin_dir_path( __DIR__ ).'woocommerce/myaccount/event_list.php';
        }


        /**
     * Add new endpoint
     *
     * @since     3.0.1
    */
        public function wpstream_custom_endpoint_video_list() {
            // Render the "My Videos" account tab from its template.
            include plugin_dir_path( __DIR__ ).'woocommerce/myaccount/video_list.php';
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
           // add_shortcode('wpstream_list_products', array($this,'wpstream_list_products_function') );
            // [wpstream_chat] renders the chat widget for a live stream.
            add_shortcode('wpstream_chat',          array($this,'wpstream_chat_function') );
            // [wpstream_player_low_latency] renders the low-latency player variant.
            add_shortcode('wpstream_player_low_latency', array($this,'wpstream_insert_player_inpage_low_latency') );
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
                       "name" => esc_html__( "WpStream Start Streaming Button","wpestate"),
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
                                "heading" => esc_html__( "Product/Free Product Id","wpestate"),
                                "param_name" => "id",
                                "value" => "",
                                "description" => esc_html__( "If you leave this option blank we will stream on the first free/paid channel for this user","wpestate")
                            ),
                          

                       )
                    )
                );
            
            
            
                // Map: Chat element.
                vc_map(
                    array(
                       "name" => esc_html__( "WpStream Chat - Beta Version","wpestate"),
                       "base" => "wpstream_chat",
                       "class" => "",
                       "category" => esc_html__( 'WpStream','wpstream'),
                       'admin_enqueue_js' => array(get_template_directory_uri().'/vc_extend/bartag.js'),
                       'admin_enqueue_css' => array(get_template_directory_uri().'/vc_extend/bartag.css'),
                       'weight'=>100,
                       'icon'   =>'',
                       'description'=>esc_html__( 'Insert WpStream Chat','wpstream'),
                       "params" => array(
                            array(
                                "type" => "textfield",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "Live Stream Id","wpestate"),
                                "param_name" => "id",
                                "value" => "0",
                                "description" => esc_html__( "Add here the live stream id","wpestate")
                            ),

                       )
                    )
                );
            
            
       
                // Map: standard Player element.
                vc_map(
                    array(
                       "name" => esc_html__( "WpStream Player","wpestate"),
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
                                "heading" => esc_html__( "Product/Free Product Id","wpestate"),
                                "param_name" => "id",
                                "value" => "0",
                                "description" => esc_html__( "Add here the live stream id or the video id","wpestate")
                            ),  
                           array(
                                "type" => "textfield",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "User Id","wpestate"),
                                "param_name" => "user_id",
                                "value" => "",
                                "description" => esc_html__( "We will use the first channel of this user id(product id will be ignored.).","wpestate")
                            ),

                       )
                    )
                );

                
                // Map: low-latency Player element (private beta).
                vc_map(
                    array(
                       "name" => esc_html__( "WpStream Player - Low Latency - Private Beta / Requires Approval","wpestate"),
                       "base" => "wpstream_player_low_latency",
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
                                "heading" => esc_html__( "Product/Free Product Id","wpestate"),
                                "param_name" => "id",
                                "value" => "0",
                                "description" => esc_html__( "Add here the live stream id or the video id","wpestate")
                            ),
                             array(
                                "type" => "textfield",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "User Id","wpestate"),
                                "param_name" => "user_id",
                                "value" => "",
                                "description" => esc_html__( "We will use the first channel of this user id(product id will be ignored.).","wpestate")
                            ),

                       )
                    )
                );


                // Dropdown options for the products-list media type control.
                $product_type=array(
                    '0' =>  __('Both','wpstream'),
                    '1' =>  __('Live Event','wpstream'),
                    '2' =>  __('Video on demand','wpstream')
                );

                // Map: legacy Products List element.
                vc_map(
                    array(
                       "name" => esc_html__( "WpStream Products List","wpestate"),
                       "base" => "wpstream_list_products",
                       "class" => "",
                       "category" => esc_html__( 'WpStream','wpstream'),
                       'admin_enqueue_js' => array(get_template_directory_uri().'/vc_extend/bartag.js'),
                       'admin_enqueue_css' => array(get_template_directory_uri().'/vc_extend/bartag.css'),
                       'weight'=>100,
                       'icon'   =>'',
                       'description'=>esc_html__( ' List wpstream products','wpstream'),
                       "params" => array(

                            array(
                                "type" => "dropdown",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "Media type","wpestate"),
                                "param_name" => "product_type",
                                "value" => $product_type,
                                "description" => esc_html__( "What type of media(free/paid) ","wpestate")
                            ),

                            array(
                                 "type" => "textfield",
                                 "holder" => "div",
                                 "class" => "",
                                 "heading" => esc_html__( "Media number","wpestate"),
                                 "param_name" => "media_number",
                                 "value" => "",
                                 "description" => esc_html__( "No of media ","wpestate")
                             ),



                       )
                    )
                );
                
                
                // Shared dropdown options reused by the channel/VOD list maps below.
                // Free vs paid filter.
                $free_paid_type=array(

                   0 =>  esc_html__('Free','wpstream'),
                   1 =>  esc_html__('Paid','wpstream')
                );

                // "Only show live channels" yes/no.
                $live_settings=array(

                    'no'=>esc_html__('no','wpstream'),
                    'yes'=>esc_html__('yes','wpstream'),
                );

                // Ordering options.
                $order_by_id=array(

                    0=>esc_html('By date - ASC','wpstream'),
                    1=>esc_html('By date - DESC','wpstream'),
                    2=>esc_html('By title - ASC','wpstream'),
                    3=>esc_html('By title - DESC','wpstream'),
                );

                // Map: Channel List element.
                vc_map(
                    array(
                       "name" => esc_html__( "WpStream Channel List","wpestate"),
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
                                "heading" => esc_html__( "Show Free or Paid Media ?","wpestate"),
                                "param_name" => "product_type_free_paid",
                                "value" => $free_paid_type,
                                "description" => esc_html__( "What type of media(free/paid) ","wpestate")
                            ),

                            array(
                                "type" => "dropdown",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "Only show active channels","wpestate"),
                                "param_name" => "product_show_live",
                                "value" => $live_settings,
                                "description" => esc_html__( "Only show channels that are live streaming right now.","wpestate")
                            ),
                            array(
                                 "type" => "textfield",
                                 "holder" => "div",
                                 "class" => "",
                                 "heading" => esc_html__( "Number of Items per Page","wpestate"),
                                 "param_name" => "media_number",
                                 "value" => "3",
                                 "description" => esc_html__( "How many items will be displayed per page","wpestate")
                             ),

                            array(
                                 "type" => "textfield",
                                 "holder" => "div",
                                 "class" => "",
                                 "heading" => esc_html__( "Link Label for free items","wpestate"),
                                 "param_name" => "free_label",
                                 "value" => esc_html__('Watch now!','wpstream'),
                                 "description" => esc_html__( "Link Label for free items'","wpestate")
                             ),

                            array(
                                "type" => "dropdown",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "Order by","wpestate"),
                                "param_name" => "order_by",
                                "value" => $order_by_id,
                                "description" => esc_html__( "Order type","wpestate")
                            ),


                       )
                    )
                );
                     
                     
                   // Map: VOD List element.
                   vc_map(
                    array(
                       "name" => esc_html__( "WpStream VOD List","wpestate"),
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
                                "heading" => esc_html__( "Show Free or Paid Media ?","wpestate"),
                                "param_name" => "product_type_free_paid",
                                "value" => $free_paid_type,
                                "description" => esc_html__( "What type of media(free/paid) ","wpestate")
                            ),

                          
                            array(
                                 "type" => "textfield",
                                 "holder" => "div",
                                 "class" => "",
                                 "heading" => esc_html__( "Number of Items per Page","wpestate"),
                                 "param_name" => "media_number",
                                 "value" => "3",
                                 "description" => esc_html__( "How many items will be displayed per page","wpestate")
                             ),

                            array(
                                 "type" => "textfield",
                                 "holder" => "div",
                                 "class" => "",
                                 "heading" => esc_html__( "Link Label for free items","wpestate"),
                                 "param_name" => "free_label",
                                 "value" => esc_html__('Watch now!','wpstream'),
                                 "description" => esc_html__( "Link Label for free items'","wpestate")
                             ),

                            array(
                                "type" => "dropdown",
                                "holder" => "div",
                                "class" => "",
                                "heading" => esc_html__( "Order by","wpestate"),
                                "param_name" => "order_by",
                                "value" => $order_by_id,
                                "description" => esc_html__( "Order type","wpestate")
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

            // Normalize shortcode attributes against defaults.
             $attributes =   shortcode_atts(
                array(
                    'media_number'                       => 3,
                    'product_type_free_paid'             => '0',
                    'product_show_live'                  => 'no',
                    'free_label'                         =>  '',
                    'order_by'                           =>  '0'
                ), $attributes) ;

            // Force channel/live product type (1) for this list.
            $attributes['product_type']=1;

            // Translate the human "Paid"/"Free" values into the numeric flags the list function expects.
            if( $attributes['product_type_free_paid']=='Paid'){
                $attributes['product_type_free_paid']=1;
            }
            if( $attributes['product_type_free_paid']=='Free'){
                $attributes['product_type_free_paid']=0;
            }

            // When order_by is the default 0, resolve it against the label map (kept for backward compatibility).
            if($attributes['order_by']==0){
                $order_by_id=array(

                    0=>esc_html('By date - ASC','wpstream'),
                    1=>esc_html('By date - DESC','wpstream'),
                    2=>esc_html('By title - ASC','wpstream'),
                    3=>esc_html('By title - DESC','wpstream'),
                );

                $attributes['order_by']= array_search($attributes['order_by'], $order_by_id);
            }
            // Delegate rendering to the shared Elementor list function on the main plugin object.
            global $wpstream_plugin;
            return  $wpstream_plugin->wpstream_media_list_elementor_function(   $attributes );

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

            // Normalize shortcode attributes against defaults.
             $attributes =   shortcode_atts(
                array(
                    'media_number'                       => 3,
                    'product_type_free_paid'             => '0',
                    'product_show_live'                  => 'no',
                    'free_label'                         =>  '',
                    'order_by'                           =>  '0'
                ), $attributes) ;

            // Force VOD product type (2) for this list.
            $attributes['product_type']=2;

            // Translate the human "Paid"/"Free" values into the numeric flags the list function expects.
            if( $attributes['product_type_free_paid']=='Paid'){
                $attributes['product_type_free_paid']=1;
            }
            if( $attributes['product_type_free_paid']=='Free'){
                $attributes['product_type_free_paid']=0;
            }

            // When order_by is the default 0, resolve it against the label map (kept for backward compatibility).
            if($attributes['order_by']==0){
                $order_by_id=array(

                    0=>esc_html('By date - ASC','wpstream'),
                    1=>esc_html('By date - DESC','wpstream'),
                    2=>esc_html('By title - ASC','wpstream'),
                    3=>esc_html('By title - DESC','wpstream'),
                );

                $attributes['order_by']= array_search($attributes['order_by'], $order_by_id);
            }
            // Delegate rendering to the shared Elementor list function on the main plugin object.
            global $wpstream_plugin;
            return  $wpstream_plugin->wpstream_media_list_elementor_function(   $attributes );

        }
    
        /**
     * [wpstream_chat] shortcode - render the chat widget for a live stream.
     *
     * @since     3.0.1
     * @param     array       $attributes Shortcode attributes ('id' = live stream/product id).
     * @param     string|null $content    Enclosed content (unused).
     * @return    string HTML for the chat wrapper.
    */
        public function wpstream_chat_function($attributes, $content = null){
            // Initialise the target product id and the buffer we return.
            $product_id     =   '';
            $return_string  =   '';
            // Merge shortcode attributes with defaults.
            $attributes =   shortcode_atts(
                array(
                    'id'                       => 0,
                ), $attributes) ;


            // Resolve the stream/product id from the shortcode.
            if ( isset($attributes['id']) ){
                $product_id=$attributes['id'];
            }


            // Open the chat wrapper element.
            $return_string.= '<div class="wpstream_plugin_chat_wrapper">';
            // Capture the chat wrapper markup printed by the player object into the return string.
            ob_start();
                $this->main->wpstream_player->wpstream_chat_wrapper($product_id);
                $return_string.= ob_get_contents();
            ob_end_clean();
            // Close the wrapper.
            $return_string.='</div>';
            // Emit the JS/config that connects the browser to the chat server for this stream.
            $this->main->wpstream_player->wpstream_connect_to_chat($product_id);

            return $return_string;
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
                    $product_id= $this->main->wpstream_player_retrive_first_id($user_id);
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
     * [wpstream_player_low_latency] shortcode - render the low-latency player.
     *
     * @since     3.0.1
     * @param     array       $attributes Shortcode attributes ('id' = product id, 'user_id' = fallback owner).
     * @param     string|null $content    Enclosed content (unused).
     * @return    string Buffered low-latency player HTML.
    */


        public function wpstream_insert_player_inpage_low_latency($attributes, $content = null){
                // Initialise the target product id and output buffer.
                $product_id     =   '';
                $return_string  =   '';
                // Merge shortcode attributes with defaults.
                $attributes =   shortcode_atts(
                    array(
                        'id'                       => 0,
                         'user_id'                  => 0,
                    ), $attributes) ;


                // Read the explicit product id.
                if ( isset($attributes['id']) ){
                    $product_id=$attributes['id'];
                }


                // Sanitize the optional user id.
                if ( isset($attributes['user_id']) ){
                    $user_id = intval( $attributes['user_id'] );
                }


                // If no product id was given but a user id was, use that user's first channel.
                if(intval($product_id)==0 && $user_id!=0){
                    $product_id= $this->main->wpstream_player_retrive_first_id($user_id);
                }

                // Capture the low-latency player markup into the return string.
                ob_start();
                $this->main->wpstream_player->wpstream_video_player_shortcode_low_latency($product_id);
                $return_string= ob_get_contents();
                ob_end_clean();

                return $return_string;
        }

        
        
        /**
     * list products - shortcode function (legacy [wpstream_list_products]).
     *
     * @since     3.0.1
     * @param     array       $atts    Shortcode attributes ('media_number', 'product_type').
     * @param     string|null $content Enclosed content (unused).
     * @return    string HTML list of matching products.
    */

        public function wpstream_list_products_function($atts, $content=null){

                // Initialise locals.
                $media_number     = "";
                $product_type     = "";
                // Merge shortcode attributes with defaults.
                $attributes = shortcode_atts(
                        array(
                                'media_number' =>   '4',
                                'product_type' =>   __('Free Live Channel','wpstream'),

                        ), $atts);

                // Read how many items to show.
                if ( isset($attributes['media_number']) ){
                    $media_number=$attributes['media_number'];
                }

                // Read the requested product type label.
                if ( isset($attributes['product_type']) ){
                    $product_type=$attributes['product_type'];
                }

                // Map the label to the internal type flag: 1 = free live channel, 2 = free video.
                if($product_type== __('Free Live Channel','wpstream') ){
                    $product_type=1;
                }else{
                    $product_type=2;
                }

                // Accumulator for the generated list markup.
                $return_string="";



                // Query published wpstream_product items of the requested product type.
                $args = array(
                    'post_type'      => 'wpstream_product',
                    'post_status'    => 'publish',
                    'meta_query'     =>array(
                                        array(
                                        'key'      => 'wpstream_product_type',
                                        'value'    => $product_type,
                                        'compare'  => '=',
                                        ),
                        ),
                    'posts_per_page' =>$media_number,
                    'page'          => 1
                );


                $media_list= new WP_Query($args);

                // Pick the "see" link label based on the product type.
                if($product_type==1){
                    $see_product= __('See Free Live Chanel','wpstream');
                }else{
                    $see_product =__('See Free Video','wpstream');
                }



                // Build one product unit (thumbnail, title link, "see" link) per matched post.
                while($media_list->have_posts()):$media_list->the_post();
                    $return_string.='<div class="wpstream_product_unit">'
                    .'<div class="product_image" style="background-image:url('.wp_get_attachment_thumb_url(get_post_thumbnail_id()).')"></div>'
                    .'<a href="'.get_permalink().'" class="product_title" >'.get_the_title().'</a>'
                    .'<a href="'.get_permalink().'"class="see_product">'.$see_product.'</a>'
                    .'</div>';
                endwhile;

                // Restore the main query/post globals after the custom loop.
                wp_reset_postdata();
                wp_reset_query();


                // Wrap and return the accumulated markup.
                return   '<div class="shortcode_list_wrapper">'.$return_string.'</div>';

        }

        
        
        /**
     * register shortcodes - add buttons in js (mce_external_plugins filter).
     *
     * @since     3.0.1
     * @param     array $plugin_array Registered TinyMCE external plugins.
     * @return    array Plugins with the WpStream editor plugin added.
    */

        public function wpstream_add_plugin($plugin_array) {
            // Point each of the plugin's TinyMCE buttons at the shortcodes.js editor plugin.
            $plugin_array['wpstream_player']                = plugin_dir_url( __FILE__ ). '/js/shortcodes.js';
            $plugin_array['wpstream_list_products']         = plugin_dir_url( __FILE__ ). '/js/shortcodes.js';
            $plugin_array['wpstream_list_products_channels']= plugin_dir_url( __FILE__ ). '/js/shortcodes.js';
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
            // Append a separator + each WpStream shortcode button to the second toolbar row.
            array_push($buttons, "|", "wpstream_player");
            array_push($buttons, "|", "wpstream_list_products");
            array_push($buttons, "|", "wpstream_list_products_channels");
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
            if(isset($local_event_options['ses_encrypt']) && intval($local_event_options['ses_encrypt'])==1 )    {


                // Start a PHP session if one is not already active.
                if (session_status() == PHP_SESSION_NONE) {
                   session_start();
                }

                // Skip on key-delivery requests; only mint the id on ordinary page loads.
                if( !isset( $_REQUEST[ 'wpstream_livedrm' ]) && !isset( $_REQUEST[ 'wpstream_voddrm' ])  && !isset( $_REQUEST[ 'keys2' ]) ) {

                    // Assign a unique session id the first time it is missing.
                    if( !isset($_SESSION['wpstream_id']) ){

                        $_SESSION['wpstream_id']= uniqid();
                    }
                }
            }
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
     * transients; the remote key itself is fetched/cached in
     * wpstream_get_encryption_key_remonting(). Prints the key and dies.
     *
     * Note: when session encryption is on, a missing session id aborts the request.
     *
     * @since     3.0.1
     * @return    void Emits the key and exits, or returns early when no livedrm request.
    */


        public function wpstream_live_streaming_key(){

            // Read the global streaming channel options (holds the session-encryption flag).
            $local_event_options =   get_option('wpstream_user_streaming_global_channel_options') ;

            // Only act when a live DRM key request is present and non-empty.
            if( isset( $_REQUEST[ 'wpstream_livedrm' ]) && $_REQUEST[ 'wpstream_livedrm' ]!=''  ) {

                // When session encryption is enabled, require an established session id.
                if(isset($local_event_options['ses_encrypt']) && intval($local_event_options['ses_encrypt'])==1 )    {

                    // No session id -> refuse the key.
                    if( !isset( $_SESSION['wpstream_id'] ) ){
                        session_write_close ();die('no session');

                    }
                }


                // Sanitize the requested stream key and split off the stream name (before the first '-').
                $streamname_received    =   esc_html($_REQUEST[ 'wpstream_livedrm' ]);
                $stream_key_array       =   explode('-', $streamname_received);

                $streamname             =   $stream_key_array[0];
                // Current viewer (used for the paid-event entitlement check below).
                $current_user           =   wp_get_current_user();

                // Try the cached list of free events matching this stream name.
                $event_list_free_posts =    get_transient(  'free_event_streamName_'.$streamname ) ;

                // Reset any query state left over before running our own lookups.
                wp_reset_postdata();
                wp_reset_query();
            
              
                // On a cache miss, query free (wpstream_product) events by stream_name and cache the ids for 60s.
                if ( false === $event_list_free_posts ) {

                    $args_free = array(
                        'posts_per_page'    => -1,
                        'cache_results'             =>  false,
                        'update_post_meta_cache'    =>  false,
                        'update_post_term_cache'    =>  false,
                        'post_type'         => 'wpstream_product',
                        'post_status'       => 'publish',
                        'meta_query'        =>      array(
                                                        array(
                                                        'key'     => 'stream_name',
                                                        'value'   => $streamname,
                                                        'compare' => '=',
                                                        )
                                                    ),
                        'fields'=>'ids',
                    );

                    $event_list_free        =   new WP_Query($args_free);
                    $event_list_free_posts  =   $event_list_free->posts;
                    set_transient(  'free_event_streamName_'.$streamname, $event_list_free->posts ,60);
                }

                if ( !empty($event_list_free_posts )  ){
                    ////////////////////////////////////////////////////////////
                    // when we have a free event
                    ////////////////////////////////////////////////////////////
                    // Free event: no purchase check needed. Fetch the (remote, cached) key for the first match.
                    $the_id                     =   $event_list_free_posts[0];
                    $show_id                    =   $the_id;
                    $get_key                    =   $this->wpstream_get_encryption_key_remonting($show_id,$streamname_received);

                    // Allow the client to cache the key briefly.
                    $seconds_to_cache = 301;
                    $ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
                    header("Expires: $ts");
                    header("Pragma: cache");
                    header("Cache-Control: max-age=$seconds_to_cache");


                    // Emit the key and stop.
                    print $get_key;
                    die();


                }else{
                    ////////////////////////////////////////////////////////////
                    //  this is for paid products
                    ////////////////////////////////////////////////////////////

                    // Paid path: viewer must be logged in with a real user id.
                    if ( is_user_logged_in() && intval($current_user->ID)!=0 ) {

                            // Try the cached list of paid products (live_stream/subscription) for this stream name.
                            $event_list_paid_posts  =    get_transient(  'paid_event_streamName_'.$streamname ) ;



                            // On a cache miss, query matching WooCommerce products and cache the ids for 60s.
                            if ( false === $event_list_paid_posts ) {
                                $args = array(
                                    'posts_per_page'    => -1,
                                    'post_type'         => 'product',
                                    'post_status'       => 'publish',
                                    'meta_query' => array(
                                        array(
                                                'key'     => 'stream_name',
                                                'value'   => $streamname,
                                                'compare' => '=',
                                        ),
                                    ),
                                    'tax_query'         => array(
                                                'relation'  => 'AND',
                                                array(
                                                    'taxonomy'  =>  'product_type',
                                                    'field'     =>  'slug',
                                                    'terms'     =>  array('live_stream','subscription')
                                                )
                                            ),
                                     'fields'=>'ids',
                                );


                                $event_list             = new WP_Query($args);
                                $event_list_paid_posts  = $event_list->posts;

                                set_transient(  'paid_event_streamName_'.$streamname, $event_list->posts ,60);
                            }

                            // Only continue if a paid product matched.
                            if ( !empty($event_list_paid_posts )  ){

                                $the_id     =    $event_list_paid_posts[0];
                                $show_id    =   $the_id;


                                // Determine subscription entitlement (defaults to none).
                                $is_valid_subscription=0;
                                // Active WooCommerce subscription for this product?
                                if(class_exists ('WC_Subscription')){
                                    $is_valid_subscription = wcs_user_has_subscription( $current_user->ID, $show_id ,'active');
                                }


                                // Site-wide "global subscription" model overrides per-product checks.
                                if(function_exists('wpstream_check_global_subscription_model')){
                                    if( wpstream_check_global_subscription_model() ){
                                        $is_valid_subscription=1;// this is global subscription
                                    }
                                }


                                // Plugin-level global subscription check for this specific product.
                                if( $this->main->wpstream_player->wpstream_in_plugin_check_global_subscription_model($show_id) ){
                                    $is_valid_subscription=1;// this is global subscription
                                }


                                // Entitlement gate: viewer must have bought the product or hold a valid subscription.
                                if( wc_customer_bought_product( $current_user->email, $current_user->ID, $show_id) || $is_valid_subscription==1 ){
                                    // Entitled: fetch the (remote, cached) key.
                                    $get_key = $this->wpstream_get_encryption_key_remonting($show_id,$streamname_received);

                                    // Allow the client to cache the key briefly.
                                    $seconds_to_cache = 302;
                                    $ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
                                    header("Expires: $ts");
                                    header("Pragma: cache");
                                    header("Cache-Control: max-age=$seconds_to_cache");

                                    // Emit the key and stop.
                                    print $get_key;
                                    die();

                                }else{
                                    // Logged in but no purchase/subscription: deny.
                                    exit('live - no ticket ');
                                }

                            } else{
                                // No paid product matched this stream name.
                                exit('live - no event');
                            }

                        }else{
                            // Paid content requested by a logged-out viewer: deny.
                            exit('live - user not log or anwser');
                        }

                }
                // Fell through: neither a free nor an entitled paid event.
                exit('no free or paid event');

            }else{

                // No live DRM request on this page load.
                return;
            }

        }
         
         
         
             
         /**
     * Fetch (and cache) the remote HLS encryption key for a live stream.
     *
     * The key is retrieved from the per-post 'hls_key_retrieval_url' remote
     * endpoint and cached in a 30s transient keyed by show id, so repeated
     * requests do not hit the remote service on every segment.
     *
     * @since     3.0.1
     * @param     int    $show_id             Post id whose key retrieval URL/meta to use.
     * @param     string $streamname_received Full requested stream key appended to the URL.
     * @return    string The encryption key body (empty string on a failed remote call).
    */

        public function wpstream_get_encryption_key_remonting ($show_id,$streamname_received){
            // Serve the cached key when present.
            $get_key = get_transient( $show_id.'_api20_streamName' );


            // Cache miss: fetch the key from the post's remote retrieval URL.
            if ( false ===  $get_key  ) {

              // Build the remote URL from post meta plus the requested stream key.
              $url = get_post_meta($show_id,'hls_key_retrieval_url',true).'/'.$streamname_received;

                    $get= wp_remote_get( $url );

                    // Use the response body on success; otherwise store an empty key.
                    if(is_array($get)){
                        $get_key = $get['body'];
                    }else{
                       $get_key='';
                    }

                    // Cache the fetched key for 30 seconds.
                    set_transient(  $show_id.'_api20_streamName', $get_key, 30 );
            }
            return $get_key;
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
    public function wpstream_live_streaming_key_vod(){
        // Read global options (session-encryption flag) and the current viewer.
        $local_event_options =   get_option('wpstream_user_streaming_global_channel_options') ;
        $current_user        =   wp_get_current_user();



        // Only act when a non-empty VOD DRM key request is present.
        if( isset( $_REQUEST[ 'wpstream_voddrm' ]) && $_REQUEST[ 'wpstream_voddrm' ]!=''  ) {

            // When session encryption is enabled, require an established session id.
            if(isset($local_event_options['ses_encrypt']) && intval($local_event_options['ses_encrypt'])==1 )    {

                // No session id -> clear state and refuse the key.
                if( !isset( $_SESSION['wpstream_id'] ) ){
                    unset($_SESSION['wpstream_id']);
                    session_write_close ();session_register_shutdown();
                    die('no session');
                }
            }

            // Sanitize the requested decryption key index and try the cached free-VOD lookup.
            $hlsDecryptionKeyIndex  = esc_html($_REQUEST[ 'wpstream_voddrm' ]);
            $vod_list_free_posts    = get_transient(  'vod_decryption_key_index_'.$hlsDecryptionKeyIndex ) ;

            // On a cache miss, query free VOD posts by key index and cache the ids for 60s.
            if ( false === $vod_list_free_posts ) {
                $args_free = array(
                    'posts_per_page'    => -1,
                    'cache_results'             =>  false,
                    'update_post_meta_cache'    =>  false,
                    'update_post_term_cache'    =>  false,
                    'post_type'         => 'wpstream_product_vod',
                    'post_status'       => 'publish',
                    'meta_query'        =>      array(
                                                    array(
                                                    'key'     => 'hlsDecryptionKeyIndex',
                                                    'value'   => $hlsDecryptionKeyIndex,
                                                    'compare' => '=',
                                                    )
                                                ),
                    'fields'=>'ids',
                );
            
                $event_list_free = new WP_Query($args_free);

                $vod_list_free_posts= $event_list_free->posts;
                set_transient(  'vod_decryption_key_index_'.$hlsDecryptionKeyIndex, $event_list_free->posts ,60);
            }// end check transient



            if ( !empty($vod_list_free_posts )  ){
                ////////////////////////////////////////////////////////////
                // when we have a free event
                ////////////////////////////////////////////////////////////
                // Free VOD: read the key straight from post meta (stored base64).
                $the_id                     =   $vod_list_free_posts[0];
                $get_key                    =   get_post_meta($the_id,'hlsDecryptionKey',true);

                // Allow the client to cache the key briefly.
                $seconds_to_cache = 301;
                $ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
                header("Expires: $ts");
                header("Pragma: cache");
                header("Cache-Control: max-age=$seconds_to_cache");


                // Emit the decoded key and stop.
                print base64_decode( $get_key );
                die();


            }else{
                ////////////////////////////////////////////////////////////
                //  this is for paid products
                ////////////////////////////////////////////////////////////
                // Paid path: viewer must be logged in with a real user id.
                if ( is_user_logged_in() && intval($current_user->ID)!=0 ) {
                    // Sanitize the key index again and try the cached paid-VOD lookup.
                    $hlsDecryptionKeyIndex  = esc_html($_REQUEST[ 'wpstream_voddrm' ]);
                    $event_list_paid_posts  =    get_transient(  'paid_vod_key_index_'.$hlsDecryptionKeyIndex ) ;


                    // On a cache miss, query matching paid VOD products and cache the ids for 60s.
                    if ( false === $event_list_paid_posts ) {
                        $args = array(
                            'posts_per_page'    => -1,
                            'post_type'         => 'product',
                            'post_status'       => 'publish',
                            'meta_query' => array(
                                array(
                                        'key'     => 'hlsDecryptionKeyIndex',
                                        'value'   => $hlsDecryptionKeyIndex,
                                        'compare' => '=',
                                ),
                            ),
                            'tax_query'         => array(
                                        'relation'  => 'AND',
                                        array(
                                            'taxonomy'  =>  'product_type',
                                            'field'     =>  'slug',
                                            'terms'     =>  array('video_on_demand','subscription')
                                        )
                                    ),
                             'fields'=>'ids',
                        );


                        $event_list = new WP_Query($args);
                        $vod_list_paid_posts = $event_list->posts;

                        set_transient(  'paid_vod_key_index_'.$hlsDecryptionKeyIndex, $event_list->posts ,60);
                    }


                    // Only continue if a paid VOD product matched.
                    if ( !empty($vod_list_paid_posts )  ){

                        $the_id     =    $vod_list_paid_posts[0];
                        $show_id    =   $the_id;


                        // Determine subscription entitlement (defaults to none).
                        $is_valid_subscription=0;
                        // Active WooCommerce subscription for this product?
                        if(class_exists ('WC_Subscription')){
                            $is_valid_subscription = wcs_user_has_subscription( $current_user->ID, $show_id ,'active');
                        }


                        // Site-wide "global subscription" model overrides per-product checks.
                        if(function_exists('wpstream_check_global_subscription_model')){
                            if( wpstream_check_global_subscription_model() ){
                                $is_valid_subscription=1;// this is global subscription
                            }
                        }


                        // Plugin-level global subscription check for this specific product.
                        if( $this->main->wpstream_player->wpstream_in_plugin_check_global_subscription_model($show_id) ){
                            $is_valid_subscription=1;// this is global subscription
                        }


                        // Entitlement gate: viewer must have bought the product or hold a valid subscription.
                        if( wc_customer_bought_product( $current_user->email, $current_user->ID, $show_id) || $is_valid_subscription==1 ){

                            // Entitled: read the base64 key from post meta.
                            $get_key                    =   get_post_meta($show_id,'hlsDecryptionKey',true);

                            // Allow the client to cache the key briefly.
                            $seconds_to_cache = 302;
                            $ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
                            header("Expires: $ts");
                            header("Pragma: cache");
                            header("Cache-Control: max-age=$seconds_to_cache");

                            // Emit the decoded key and stop.
                            print base64_decode( $get_key );
                            die();

                        }else{
                            // Logged in but no purchase/subscription: deny.
                            exit('vod - no ticket ');
                        }

                    } else{
                        // No paid VOD matched this key index.
                        exit('vod - no item');
                    }

                }else{
                    // Paid VOD requested by a logged-out viewer: deny.
                    exit('vod - user not log or no answer');
                }


            }



        }


    }
     
       
    


         
    /**
     * Legacy VOD key retrieval by filename (currently disabled).
     *
     * The remote-fetch implementation below is commented out and the method
     * returns immediately; kept as a placeholder/reference.
     *
     * @param  string $filename VOD filename the key would be fetched for.
     * @return void
     */
    public function wpstream_get_vod_key($filename){
            // Early return: remote key retrieval is disabled (see commented-out body below).
            return;
//           global $wpstream_plugin;
//            $vod_key = get_transient("vod_key".$filename);
//            if(false===$vod_key){
//                $token  = $wpstream_plugin->wpstream_live_connection->wpstream_get_token();
//                $domain = parse_url ( get_site_url() );
//
//                $values_array=array(
//                    "filename"           =>  $filename,
//                );
//                $url            =   WPSTREAM_CLUBLINKSSL."://www.".WPSTREAM_CLUBLINK."/wp-json/rcapi/v1/uservodkey/get/?access_token=".$token;
//
//
//                $arguments = array(
//                    'method'        => 'GET',
//                    'timeout'       => 45,
//                    'redirection'   => 5,
//                    'httpversion'   => '1.0',
//                    'blocking'      => true,
//                    'headers'       => array(),
//                    'body'          => $values_array,
//                    'cookies'       => array()
//                );
//                $response       = wp_remote_post($url,$arguments);
//                $received_data  = json_decode( wp_remote_retrieve_body($response) ,true);
//
//
//                if( isset($response['response']['code']) && $response['response']['code']=='200'){
//                    set_transient("vod_key".$filename,$received_data,120);
//                    return ($received_data);
//                }else{     
//                    return 'failed connection';
//                }
//            }else{
//                return $vod_key;
//            }

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
