<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://wpstream.net
 * @since      3.0.1
 *
 * @package    Wpstream
 * @subpackage Wpstream/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * This is the admin "god class": it wires up the whole wp-admin surface of the
 * plugin. Responsibilities include registering admin CSS/JS, building the
 * top-level WpStream menu and its sub-pages (Credentials, Channels, Recordings,
 * Settings, Quick Start), rendering channel/settings/onboarding screens and
 * their modal dialogs, defining the per-event global streaming options, hooking
 * into WooCommerce to register the custom stream/VOD product types and their
 * pricing/metabox behaviour, rendering post metaboxes, driving the multipart S3
 * upload AJAX endpoints, and emitting the various admin notices (plugin update,
 * cache flush, theme, etc.). Many methods echo HTML directly.
 *
 * @package    Wpstream
 * @subpackage Wpstream/admin
 * @author     wpstream <office@wpstream.net>
 */
class Wpstream_Admin {
        
    
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
     * @var array Definitions of the per-event "global" streaming options (record,
     *            view count, autoplay, encryption, etc.). Each entry holds a
     *            translated label, a help "details" string, and a default value.
     *            Populated lazily on `init` by load_global_event_options().
     */
         public   $global_event_options ;





    /**
     * Initialize the class and set its properties.
     *
     * @since  3.0.1
     * @param  string $plugin_name The name (ID) of this plugin.
     * @param  string $version     The version of this plugin.
     * @param  object $plugin_main The main plugin class instance, kept for public access.
     * @return void
     */
    public function __construct( $plugin_name, $version,$plugin_main ) {

        // Remember the plugin identity and the main class for later use.
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->main = $plugin_main;

        // Build the translatable global event options once WP (and its i18n) is ready.
        add_action('init', array($this, 'load_global_event_options'));
    }

    /**
     * Populate $global_event_options with the translatable per-event settings.
     *
     * Deferred to the `init` hook so the esc_html__() translations resolve
     * against a loaded text domain.
     *
     * @return void
     */
    public function load_global_event_options() {
        // Each key maps to a label/details/default triple used when rendering the event option toggles.
        $this->global_event_options = array(
            'record'        => array(
                'name'      => esc_html__('Record Live Stream','wpstream'),
                'details'   => esc_html__('If enabled, live streams will be recorded and saved to your library.','wpstream'),
                'defaults'  => 'no',
            ),
            'view_count'    => array(
                'name'      => esc_html__('Display Viewer Count','wpstream'),
                'details'   => esc_html__('If enabled, the live viewer count will show up in the player.','wpstream'),
                'defaults'  => 'yes',
            ),
            'domain_lock'   => array(
                'name'      => esc_html__('Lock To Website','wpstream'),
                'details'   => sprintf ( esc_html__('If enabled, live video will only display on %1$s, otherwise it can show up on any website.','wpstream'),get_bloginfo('wpurl') ),
                'defaults'  => 'no',
            ),
            'autoplay'      => array(
                'name'      => esc_html__('Autoplay','wpstream'),
                'details'   => esc_html__('If enabled, live video will attempt to start playing automatically. This is only achievable in some browsers.','wpstream'),
                'defaults'  => 'yes',
            ),
            'mute'          => array(
                'name'      => esc_html__('Start Muted','wpstream'),
                'details'   => esc_html__('If enabled, live video will start muted. This may increase the rate of autoplay in some browsers. ','wpstream'),
                'defaults'  => 'no',
            ),
            'low_latency'   => array(
                'name'      => esc_html__('Low Latency (beta)','wpstream'),
                'details'   => esc_html__('Shortens the live delay between streamer and viewers. Useful for interactive applications like gaming, auctions, trading etc. Low latency may worsen the viewer experience on some devices.','wpstream'),
                'defaults'  => 'no',
            ),
            'adaptive_bitrate'   => array(
                'name'           => esc_html__('Adaptive Bitrate (beta)','wpstream'),
                'details'        => esc_html__('Ensures a smooth and uninterrupted viewing experience by adjusting video quality for viewers with reduced network speed or device capabilities.','wpstream'),
                'defaults'  =>  'no',
            ),
            'encrypt'   =>array(
                'name'      =>  esc_html__('Encrypt Live Stream','wpstream'),
                'details'   =>  esc_html__('If enabled, video data will be encrypted. Enabling encryption may lead to reduced website performance under certain configurations. Encrypted video may not display in all browsers.','wpstream'),
                'defaults'  =>  'no',
            ),
            'ses_encrypt'=>array(
                'name'      =>  esc_html__('Use Sessions with Encryption','wpstream'),
                'details'   =>  esc_html__('If enabled, encryption key distribution will be checked against valid user sessions. Setting may malfunction or lead to reduced website performance under certain configurations. ','wpstream'),
                'defaults'  =>  'no',
            ),
//            'autostart'    =>array(
//                'name'      =>  esc_html__('Auto TURN ON','wpstream'),
//                'details'   =>  esc_html__('If enabled, channel will TURN ON automatically when broadcasting with an External Streaming App (RTMP Encoder/Broadcaster)','wpstream'),
//                'defaults'  =>  'no',
//            ),
        );
    }

    /**
     * Register and enqueue the plugin's admin-area stylesheets.
     *
     * Hooked (via Wpstream_Loader) to admin_enqueue_scripts.
     *
     * @since  3.0.1
     * @return void
     */
    public function enqueue_styles() {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Wpstream_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Wpstream_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */
        // Google "Roboto" webfont used across the admin UI.
        wp_enqueue_style( 'wpstream-roboto', "https://fonts.googleapis.com/css?family=Roboto:300,400,500,600,700,900&display=swap&subset=latin-ext" );
        // Main admin stylesheet; filemtime() is used as the cache-busting version.
        wp_enqueue_style(
                $this->plugin_name,
                plugin_dir_url( __FILE__ ) . 'css/wpstream-admin.css',
                array(),
                filemtime(plugin_dir_path(__FILE__) . 'css/wpstream-admin.css' ),
                'all'
            );

        // Onboarding-specific styles, again cache-busted by file modification time.
        wp_enqueue_style(
	'wpstream-on-boarding-css',
			plugin_dir_url( __FILE__ ) . 'css/wpstream-admin-onboarding.css',
			array(),
			filemtime(plugin_dir_path(__FILE__) . 'css/wpstream-admin-onboarding.css'),
		);

        // Ensure the WP media library scripts/styles are available (for logo/image pickers) if not already loaded.
        if (!did_action('wp_enqueue_media')) {
            wp_enqueue_media();
        }
    }

    /**
     * Register, enqueue and localize the plugin's admin-area JavaScript.
     *
     * Enqueues jQuery UI helpers, the fileupload lib, the admin control/upload
     * scripts, the start-streaming and settings scripts, and the onboarding
     * bundles. Each script is fed a localized vars array (translated strings,
     * URLs, nonces, feature flags). Some bundles are only loaded on specific
     * admin screens. Hooked (via Wpstream_Loader) to admin_enqueue_scripts.
     *
     * @since  3.0.1
     * @return void
     */
    public function enqueue_scripts() {

                // jQuery UI widgets used by the settings/date pickers and sliders.
                wp_enqueue_script("jquery-ui-slider");
                wp_enqueue_script("jquery-ui-datepicker");
                // Blueimp-style file upload library backing the recordings/VOD uploader.
                wp_enqueue_script('jquery.fileupload',   plugin_dir_url( __FILE__ ) .'js/jquery.fileupload.js?v='.time(),array(), WPSTREAM_PLUGIN_VERSION, true);

                // Shared admin utility helpers, then the main admin control script that drives uploads and channel actions.
                wp_enqueue_script( 'wpstream-admin-utils',  plugin_dir_url( __FILE__ ) .'js/utils/admin_utils.js?v='.time(),array(),  WPSTREAM_PLUGIN_VERSION, true);
                wp_enqueue_script('wpstream-admin-control',   plugin_dir_url( __FILE__ ) .'js/admin_control.js?v='.time(),array(),  WPSTREAM_PLUGIN_VERSION, true);
                // Hand the control script all of its translated status strings, the admin URL, and the multipart upload nonce.
                wp_localize_script('wpstream-admin-control', 'wpstream_admin_control_vars',
                    array( 
                        'admin_url'                => get_admin_url(),
                        'multipart_upload_nonce'   => wp_create_nonce( 'wpstream_multipart_upload_nonce' ),
                        'loading_url'              => WPSTREAM_PLUGIN_DIR_URL.'/img/loading.gif',
                        'download_mess'            => esc_html__('Click to download!','wpstream'),
                        'uploading'                => esc_html__('We are uploading your file. Do not close this window!','wpstream'),
                        'upload_complete2'         => esc_html__('Upload Complete! You can upload another file!','wpstream'),
                        'not_accepted'             => esc_html__('The file is not an accepted video format','wpstream'),
                        'upload_complete'          => esc_html__('Upload Complete!','wpstream'),
                        'no_band'                  => esc_html__('Not enough streaming data.','wpsteam'),
                        'no_band_no_store'         => esc_html__('Not enough streaming data or storage.','wpsteam'),
                        'no_streaming_hours'       => esc_html__('Not enough streaming hours.','wpstream'),
                        'exceeding_limit'          => esc_html__('File size exceeds 5GB. Initiating multipart upload...','wpsteam'),
                        'upload_failed'            => esc_html__('Upload Failed!','wpstream'),
                        'upload_failed2'           => esc_html__('Upload Failed! Please Try again!','wpstream'),
                        'choose_a_file'            => esc_html__('Choose a file&hellip;','wpstream'),
                        'preparing_multipart'      => esc_html__('Preparing multipart upload...','wpstream'),
                        'uploading_part'           => esc_html__('Uploading part {part} of {total}...','wpstream'),
                        'upload_failed_part'       => esc_html__('Failed to upload part {part}. Please try again.','wpstream'),
                        'completing_upload'        => esc_html__('Completing upload. Please wait...','wpstream'),
                        'upload_failed_part_retry' => esc_html__('Failed to upload part {part}. Retrying...','wpstream'),
                        'choose_recording'         => esc_html__( 'Choose Recording', 'wpstream' ),
                        'select_recording'         => esc_html__( 'Please select a recording from the list', 'wpstream' ),
                        'invalid_response'         => esc_html__('Invalid response from server. Missing required upload data.', 'wpstream'),
                        'video_processing'         => esc_html__( 'The video is still processing', 'wpstream' ),
                        'file_name_text'           => esc_html__('File Name:','wpstream'),
                        'channel_create_error'     => esc_html__('Something did not work. Please try again.', 'wpstream'),
                        'select_caption_file'      => esc_html__('Select .vtt Captions File', 'wpstream'),
                        'select_button'            => esc_html__('Select', 'wpstream'),
                        'remove_button'            => esc_html__('Remove', 'wpstream'),
                        'use_streaming_hours'      => $this->wpstream_get_start_streaming_localization_flags()['use_streaming_hours'],
                    ));
                
                // Recordings list script + its localized labels and the "create VOD from recording" links (free vs paid depending on WooCommerce).
                wp_enqueue_script('wpstream-recordings-videos-list',   plugin_dir_url( __FILE__ ) .'js/recordings_videos_list.js?v='.time(),array(),  WPSTREAM_PLUGIN_VERSION, true);
                wp_localize_script( 'wpstream-recordings-videos-list', 'wpstream_recordings_videos_list_vars',
                    array(
                        'delete_file' => esc_html__('Delete file', 'wpstream'),
                        'download'           => esc_html__( 'Download', 'wpstream'),
                        'download_available' => esc_html__( 'Click to download! The url will work for the next 20 minutes!', 'wpstream'),
                        'add_free_video_url' => esc_url( admin_url( 'post-new.php?post_type=wpstream_product_vod') . '&new_video_name=' ),
                        'create_ftv_vod'     => esc_html__( 'Create new Free-To-View VOD from this recording' , 'wpstream' ),
                        'woocommerce_exists' => class_exists( 'WooCommerce' ),
                        'add_paid_video_url' => esc_url( admin_url( 'post-new.php?post_type=product').'&new_video_name=' ),
                        'create_ptv_vod'     => esc_html__( 'Create new Pay-Per-View VOD from this recording' , 'wpstream' ),
                    )
                );

                // Bundled QR generator (SEC-05): the Larix QR is built locally so the RTMP URL +
                // secret stream key never reach a third-party image service. Registered here too
                // because the admin loads start_streaming.js independently of the public enqueue.
                wp_register_script('wpstream-qrcode-generator', plugin_dir_url( __DIR__ ) . 'public/js/vendor/qrcode-generator.js', array(), '1.4.4', true);
                wp_register_script('wpstream-qr',               plugin_dir_url( __DIR__ ) . 'public/js/wpstream-qr.js', array('wpstream-qrcode-generator'), WPSTREAM_PLUGIN_VERSION, true);

                // Shared public start/stop-streaming script (reused in admin), cache-busted by its file mtime.
                // Depends on wpstream-qr so wpstreamRenderQr() is defined before this runs.
                $modified_start_streaming_file_time = gmdate( 'YmdHi', filemtime( WPSTREAM_PLUGIN_PATH . 'public/js/start_streaming.js' ) );
                wp_enqueue_script('wpstream-start-streaming_admin',   plugin_dir_url( __DIR__  ) .'public/js/start_streaming.js', array('wpstream-qr'), $modified_start_streaming_file_time, true);
				// Feature flags (basic-streaming / streaming-hours mode) that tailor the confirm dialogs and warnings.
				$streaming_localization_flags = $this->wpstream_get_start_streaming_localization_flags();
                wp_localize_script('wpstream-start-streaming_admin', 'wpstream_start_streaming_vars',
                    array( 
                        'admin_url'             =>  get_admin_url(),
                        'loading_url'           =>  WPSTREAM_PLUGIN_DIR_URL.'/img/loading.gif',
                        'download_mess'         =>  esc_html__('Click to download!','wpstream'),
                        'uploading'             =>  esc_html('We are uploading your file.Do not close this window!','wpstream'),
                        'upload_complete2'      =>  esc_html('Upload Complete! You can upload another file!','wpstream'),
                        'not_accepted'          =>  esc_html('The file is not an accepted video format','wpstream'),
                        'upload_complete'       =>  esc_html('Upload Complete!','wpstream'),
                        'no_band'               =>  esc_html('Not enough streaming data.','wpsteam'),
                        'no_band_no_store'      =>  esc_html('Not enough streaming data or storage.','wpsteam'),

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
                        'turn_off_confirm' => esc_html__(
                            'ARE YOU SURE you\'d like to TURN OFF the channel now? ' . PHP_EOL . PHP_EOL .
                            '- Channels TURN OFF automatically after 1 hour of inactivity (no active broadcast).' . PHP_EOL . 
                            '- Manual TURN OFF is only useful if you require to change the channel settings immediately.' . PHP_EOL . 
                            '- If your channel is configured with Auto TURN ON, it will turn back on as soon as there is a broadcast.',
                            'wpstream'
                        ),
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
                        'broadcaster_url'   => esc_url( esc_url(home_url('/broadcaster-page/') ) ),
                        'is_onboarding'     => isset($_GET['onboard']) && $_GET['onboard'] === 'yes' ? 'yes' : 'no',
                    ));

                // Settings-page script with its save/logo-picker strings and the broadcaster page URL.
                wp_enqueue_script('wpstream-settings',   plugin_dir_url( __DIR__  ) .'/admin/js/wpstream_settings.js?v='.time(),array(),  WPSTREAM_PLUGIN_VERSION, true);
                wp_localize_script('wpstream-settings', 'wpstream_settings_vars', array(
                        'error_message'     => esc_html__( 'Failed to save settings. Please try again.', 'wpstream'),
                        'choose_image_text' => esc_html__( 'Choose Logo Image', 'wpstream'),
                        'select_image_text' => esc_html__( 'Select Image', 'wpstream'),
                        'update_successful' => esc_html__( 'Update Successful.', 'wpstream'),
                        'update_failed'     => esc_html__( 'Something went wrong. Try again.', 'wpstream'),
                        'broadcaster_url'   => esc_url( esc_url(home_url('/broadcaster-page/') ) ),
                ));


                    // Optional ?branch= override (e.g. beta onboarding flow), sanitized from the query string.
                    $branch = isset($_GET['branch']) ? sanitize_text_field( wp_unslash( $_GET['branch'] ) ) : '';
                    // Core onboarding wizard script + its navigation URLs.
                    wp_enqueue_script('wpstream-on-boarding-js',plugin_dir_url( __DIR__  ) .'/admin/js/wpstream-onboarding2.js',array(),  WPSTREAM_PLUGIN_VERSION, true);
                    wp_localize_script('wpstream-on-boarding-js', 'wpstreamonboarding_js_vars',
                        array( 
                            'admin_url'  => get_admin_url(),
                            'plugin_url' => get_dashboard_url().'/plugins.php',
                            'upload_url' => get_dashboard_url().'admin.php?page=wpstream_recordings',
                            'branch'     => $branch
                    ));

                    // Identify the current admin screen so screen-specific bundles load only where needed.
                    $current_screen=get_current_screen();
					// enqueue the file only on the on-boarding page and wpstream_product post type
					// True on the dedicated Quick Start / onboarding admin page.
					$is_wpstream_onboarding_page = $current_screen->base ==='wpstream_page_wpstream_onboard';
                    // True on a product edit screen reached from the onboarding flow (?onboard=yes).
                    $is_wpstream_onboarding_post_type_page = isset($_GET['onboard']) && $_GET['onboard'] === 'yes';
					// Load the onboarding-page bundle in either onboarding context.
					$onboarding_visible = $is_wpstream_onboarding_page || $is_wpstream_onboarding_post_type_page;
					if( $onboarding_visible) {
						// Onboarding telemetry/page script; current_page distinguishes wizard vs post-edit context.
						wp_enqueue_script('wpstream-on-boarding-page-js', plugin_dir_url( __DIR__  ) .'admin/js/wpstream-onboarding-page.js',array(),  WPSTREAM_PLUGIN_VERSION, true);
						wp_localize_script( 'wpstream-on-boarding-page-js', 'wpstream_onboarding_page_vars',
							array(
								'admin_url'      => get_admin_url(),
								'request_url'    => WPSTREAM_CLICK,
								'wps_user'       => get_option('wpstream_api_username_from_token'),
								'current_page'   => $is_wpstream_onboarding_post_type_page ? 'post_edit' : 'onboarding',
								'plugin_version' => WPSTREAM_PLUGIN_VERSION,
								'branch'         => $branch,
							)
						);
					}

                    // On the credentials/channels/recordings/onboard screens, load the quota widget updater.
                    if ( in_array( $current_screen->base, ['toplevel_page_wpstream_credentials', 'wpstream_page_wpstream_live_channels', 'wpstream_page_wpstream_recordings', 'wpstream_page_wpstream_onboard'] ) ) {
                        wp_enqueue_script( 'wpstream-user-quota-update', plugin_dir_url( __DIR__  ) . 'admin/js/wpstream-user-quota.js', array(), WPSTREAM_PLUGIN_VERSION, true );
                        wp_localize_script( 'wpstream-user-quota-update', 'wpstream_user_quota_vars', array(
                                'admin_url' => get_admin_url()
                        ));
                    }

	    // Add localized variables for broadcaster
	    // Provide the broadcaster script (registered elsewhere) with its AJAX URL and nonce.
	    wp_localize_script('wpstream-broadcaster', 'wpstream_broadcaster_vars', array(
		    'ajax_url' => admin_url('admin-ajax.php'),
		    'nonce' => wp_create_nonce('wpstream_broadcaster_nonce'),
		    'plugin_url' => plugin_dir_url(__FILE__),

	    ));

        }
         
        
        /**
     * Add Plugin Administation menu
     *
     * Registers the top-level "WpStream" menu and its sub-pages (Credentials,
     * All Channels, Recordings, Settings, Quick Start), each mapped to a render
     * callback on this class and gated to the `administrator` capability.
     *
     * @since  3.0.1
     * @return void
     */
        public function wpstream_manage_admin_menu() {

            // Top-level menu; its page defaults to the Credentials screen and uses the WpStream icon at position 20.
            add_menu_page( __('WpStream','wpestream'), __('WpStream ','wpstream'), 'administrator', 'wpstream_credentials', array($this,'wpstream_set_wpstream_credentials'), WPSTREAM_PLUGIN_DIR_URL.'img/wpstream-icon-menu_2.png',20 );
            // Credentials sub-page (same slug as the parent so it is the default view).
            add_submenu_page( 'wpstream_credentials', __('WpStream Credentials','wpestream'),          __('Credentials','wpestream'),          'administrator', 'wpstream_credentials',      array($this,'wpstream_set_wpstream_credentials') );
            // All Channels listing.
            add_submenu_page( 'wpstream_credentials', __('WpStream Live Channels','wpestream'),         __('All Channels','wpestream'),   'administrator', 'wpstream_live_channels',    array( $this,'wpstream_new_general_set'));
            // Recordings / media management.
            add_submenu_page( 'wpstream_credentials', __('WpStream Recordings','wpestream'), __('Recordings','wpestream'),  'administrator', 'wpstream_recordings',   array($this,'wpstream_media_management'));
            // Global plugin settings.
            add_submenu_page( 'wpstream_credentials', __('WpStream Settings','wpestream'),         __('Settings','wpestream'),  'administrator', 'wpstream_settings',   array($this,'wpstream_settings'));

            // Quick Start / onboarding entry point.
            add_submenu_page( 'wpstream_credentials', __('WpStream Quick Start','wpestream'),         __('WpStream Quick Start','wpestream'),  'administrator', 'wpstream_onboard',   array($this,'wpstream_pre_onboard_display'));




        }

           
     
        
        /**
        * Shows events wpstream
        *
        * Renders the "All Channels" admin screen: the Pay-Per-View (WooCommerce
        * `product`) channel list, the Free-To-View (`wpstream_product`) list, the
        * quota/pack summary, and the "no channels" call-to-action. Each channel is
        * drawn via wpstream_live_stream_unit(). Echoes HTML directly.
        *
        * @since  3.0.1
        * @return void
        */
        public function wpstream_new_general_set() {

            // Assume no channels until a query proves otherwise.
            $no_channel=1;

            // Presence check for WooCommerce Subscriptions (branch intentionally left empty here).
            if(class_exists ('WC_Subscription')){

            }

            //event_passed
            // Query published PPV products that have not yet "passed" (event_passed != 1) and are of type live_stream/subscription.
            $args = array(
                'posts_per_page'    => -1,
                'post_type'         => 'product',
                'post_status'       => 'publish',
                'meta_query'        =>      array(
                                                array(
                                                        'key'     => 'event_passed',
                                                        'value'   => 1,
                                                        'compare' => '!=',
                                                )
                                            ),

                'tax_query'         => array(
                                        'relation'  => 'AND',
                                        array(
                                            'taxonomy'  =>  'product_type',
                                            'field'     =>  'slug',
                                            'terms'     => array('live_stream','subscription')
                                        )
                                    ),
            );



            // Run the PPV channel query and load the current live-event map + quota/pack data for the user.
            $event_list = new WP_Query($args);
            global $live_event_for_user;
            $live_event_for_user    = $this->main->wpstream_live_connection->wpstream_get_live_event_for_user();
            $pack_details           = $this->main->quota_manager->get_live_quota_data( 'wpstream_new_general_set' );

            // Print the quota/pack summary header for the user.
            $this->main->show_user_data($pack_details);
            if( $event_list->have_posts()){


                // Section header + "create new PPV channel" link.
                print '<div class="pack_details_wrapper_transparent">
                <h3>'.__('Your Pay-Per-View Channel List','wpstream').'</h3>';


                $link_new   =   admin_url('post-new.php?post_type=product').'&new_stream='. rawurlencode('new');

                print '<a href="'.esc_url($link_new).'"  class="wpstream_create_new_product_link">'.esc_html__('Create new Pay-Per-View channel.','wpstream').'</a>';
                print '</div>';

                // Open the grid that will hold each channel card.
                print '<div style="clear: both;"></div><div class="event_list_wrapper">';

                    // Loop every matched PPV product.
                    while ($event_list->have_posts()): $event_list->the_post();

                        // Gather the product id, whether a subscription is flagged as a live event, and its product type.
                        $the_id                     =   get_the_ID();
                        $is_subscription_live_event =   esc_html(get_post_meta($the_id,'_subscript_live_event',true));
                        $term_list                  =   wp_get_post_terms($the_id, 'product_type');

                        // Skip plain subscriptions that are not marked as live events.
                        if( $term_list[0]->name=='subscription' && $is_subscription_live_event=='no'){
                            continue;
                        }

                        // Render the channel card for this product.
                        $this->wpstream_live_stream_unit($the_id);

                    endwhile;

                // Close the grid; at least one PPV channel exists.
                print'</div>';
                $no_channel=1;
            }else{
                // No PPV channels found.
                $no_channel=0;
            }


            // Nonce hidden input used by the JS "start event" action.
            $ajax_nonce = wp_create_nonce( "wpstream_start_event_nonce" );
            print '<input type="hidden" id="wpstream_start_event_nonce" value="'.$ajax_nonce.'">';

            // Current user context (user's own live_shows meta collected but not directly used below).
            $current_user       =   wp_get_current_user();
            $allowded_html      =   array();
            $userID             =   $current_user->ID;
            $user_live_streams  =   get_user_meta($userID,'live_shows');


            // Restore the main query after the custom WP_Query loop.
            wp_reset_postdata();




            // free

            // Query all published Free-To-View channels (custom `wpstream_product` post type).
            $args_free = array(
                'posts_per_page'    => -1,
                'post_type'         => 'wpstream_product',
                'post_status'       => 'publish',


            );
            $event_list_free = new WP_Query($args_free);


            if( $event_list_free->have_posts()){
                // Section header + "create new free channel" link.
                print '<div class="pack_details_wrapper_transparent">
                <h3>'.__('Free-To-View Channels','wpstream').'</h3>';

                $link_new = admin_url('post-new.php?post_type=wpstream_product');
                print '<a href="'.esc_url($link_new).'" class="wpstream_create_new_product_link">'.esc_html__('Create new Free-To-View channel.','wpstream').'</a>';
                print '</div>';
                // Open the free-channel grid.
                print '<div style="clear: both;"></div><div class="event_list_wrapper">';

                    // Loop each free channel.
                    while ($event_list_free->have_posts()): $event_list_free->the_post();


                        $the_id =   get_the_ID();

                        // Render only channels whose event has not passed.
                        if( get_post_meta ($the_id,'event_passed',true)!=1){
                            $this->wpstream_live_stream_unit($the_id);
                        }

                    endwhile;

                // Close the grid and emit the shared modal background + error modal markup.
                print'</div><div class="wpstream_modal_background"></div>';
                print '<div class="wpstream_error_modal_notification"><div class="wpstream_error_content">er2</div>
                <div class="wpstream_error_ok wpstream_button" type="button">'.esc_html__('Close','wpstream').'</div>
                </div>';
                $no_channel=1;
            }else{
                // No free channels found.
                $no_channel=0;
            }



     
                // "No channels" call-to-action: build the two create links.
                $link_new_paid = admin_url('post-new.php?post_type=product').'&new_stream='. rawurlencode('new');
                $link_new_free = admin_url('post-new.php?post_type=wpstream_product');
                print '<div class="no_events_warning"> ';
                // Warn when the user has zero PPV channels.
                if($event_list->found_posts==0){
                    print '<div class="no_events_warning_mes">'.__('* You do not have any Pay-Per-View channels!','wpstream').'</div>';
                }
                // Warn when the user has zero free channels.
                if($event_list_free->found_posts==0){
                    print '<div class="no_events_warning_mes">'. __('* You do not have any free channels!','wpstream').'</div>';
                }

                // Offer buttons to create a free or paid channel.
                print '<a href="'.esc_url($link_new_free).'" class="wpstream_no_chanel_add_channel">'.esc_html__('Add new Free-To-View channel ','wpstream').'</a>';
                print '<a href="'.esc_url($link_new_paid).'" class="wpstream_no_chanel_add_channel">'.esc_html__('Add new Pay-Per-View channel ','wpstream').'</a>';

                print '</div>';




        }
        // end   wpstream_new_general_set


        /**
        * Social share
        *
        * Outputs the social-sharing icon row (Facebook, Twitter/X, Pinterest,
        * WhatsApp, LinkedIn, Reddit, email) for a given channel/post. Echoes HTML.
        *
        * @since  3.0.1
        * @param  int $the_id Post/product ID whose permalink and title are shared.
        * @return void
        */
        public function wpstream_social_share($the_id){
                // Match the current request scheme so share URLs are not mixed-content.
                $protocol       =   is_ssl() ? 'https' : 'http';
                // Featured image (used as the Pinterest "media") and the canonical permalink/title.
                $pinterest      =   wp_get_attachment_image_src(get_post_thumbnail_id($the_id), 'full');
                $link           =   esc_url ( get_permalink($the_id) );
                $title          =   get_the_title($the_id);
                // Pre-encode the Twitter status text and the mailto subject/body query.
                $twiter_status  =   urlencode( $title.' '.$link);
                $email_link     =   'subject='.urlencode ( $title ) .'&body='. urlencode( esc_url($link));

                // Pre-build the Facebook sharer URL.
                $facebook_link = esc_html($protocol).'://www.facebook.com/sharer.php?u='. esc_url($link) .'&amp;t='. urlencode(get_the_title());

                // Below: HTML markup for the share bar; each <a> targets one social network's share endpoint.
                ?>
                <div class="wpstream_social_share_wrapper">
       
                    <a href="<?php print esc_url( $facebook_link); ?>" target="_blank" class="social_facebook wpstream_sharing_social">
                        <span class="dashicons dashicons-facebook-alt"></span>
                    </a>
                
                    <a href="<?php print esc_html($protocol);?>://twitter.com/intent/tweet?text=<?php echo esc_html($twiter_status); ?>" class="social_tweet wpstream_sharing_social" target="_blank">
                        <span class="dashicons dashicons-twitter"></span>
                    </a>

                    <a href="<?php print esc_html($protocol);?>://pinterest.com/pin/create/button/?url=<?php echo esc_url($link); ?>&amp;media=<?php if (isset( $pinterest[0])){ echo esc_url($pinterest[0]); }?>&amp;description=<?php echo urlencode(get_the_title()); ?>" target="_blank" class="social_pinterest wpstream_sharing_social">
                        <span class="dashicons dashicons-pinterest"></span>
                    </a>
              
                    <a href="<?php print esc_html($protocol);?>://api.whatsapp.com/send?text=<?php echo urlencode( get_the_title().' '. esc_url( $link )); ?>" class="social_whatsup wpstream_sharing_social" target="_blank">
                        <span class="dashicons dashicons-whatsapp"></span>
                    </a>

                    <a href="<?php print esc_html($protocol);?>://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode(esc_url($link)); ?>" class="social_linkedin wpstream_sharing_social" target="_blank">
                        <span class="dashicons dashicons-linkedin"></span>
                    </a>

                    <a href="<?php print esc_html($protocol);?>:///www.reddit.com/submit?url==<?php echo urlencode(esc_url($link)); ?>" class="social_linkedin wpstream_sharing_social" target="_blank">
                        <span class="dashicons dashicons-reddit"></span>
                    </a>

                    <a href="mailto:email@email.com?<?php echo trim(esc_html($email_link));?>" data-action="share email"  class="social_email wpstream_sharing_social">
                      <span class="dashicons dashicons-email-alt"></span>
                    </a>

                    <div class="wpstream_modal_explanations"> <?php print esc_html__('Spread the word! To let people know about your channel, click on the corresponding icon and share on the social platforms of your choice. The more the merrier!','wpstream'); ?> </div>
                    <?php                  
                print '</div>';
        }
        
        
        
        
        /**
        * Shows event unit card in admin
        *
        * Renders one channel "card" (thumbnail, title, ON/OFF button, and the
        * webcam / external-app / stats / settings / edit / view / share icon
        * controls) plus its settings/share/broadcast modals. Bails with a notice
        * if the user lacks the administrator capability or streaming permission.
        * Echoes HTML.
        *
        * @since  3.0.1
        * @param  int    $the_id   Channel product/post ID.
        * @param  string $is_front Non-empty (e.g. 'front') when rendered on the front end.
        * @return void
        */
        public function wpstream_live_stream_unit($the_id,$is_front=''){
            global $live_event_for_user;
            global $wpstream_plugin;
            $current_user       =   wp_get_current_user();

            // Non-admins get a "not allowed" notice in the admin (back-end) context.
            if( !current_user_can('administrator')){
                if($is_front=='' ){
                    print '<div class="event_list_unit">';
                    esc_html_e('You are not allowed to broadcast.','wpstream');
                    print '</div>';
                    return;
                }

            }

            // Enforce the plugin's own streaming-permission gate as well.
            if( !$this->main->wpstream_check_user_can_stream()  ){
                print '<div class="event_list_unit">';
                esc_html_e('You are not allowed to broadcast','wpstream');
                print '</div>';
                return;
            }


            // Flag this card as "live" if the channel currently has an active event.
            $live_class='';
            if(isset($live_event_for_user[$the_id])) {
                $live_class=" wpstream_show_started";
            }


            // Thumbnail: the post's featured image, or the plugin logo as a fallback.
            if(has_post_thumbnail($the_id)){
                $thumb  =   get_the_post_thumbnail_url($the_id,'thumbnail');
            }else{
                $thumb= plugin_dir_url( dirname( __FILE__ ) ). 'img/plugin-logo.png';
            }

            // Default streaming display state (hidden data, empty OBS credentials/stats URL).
            $pending_streaming_class            =   'hide_stream_data';
            $external_software_streaming_class  =   '';
            $obs_uri                            =   '';
            $obs_stream                         =   '';
            $live_data_url                      =   '';

            // On the front end, lazily fetch the live-event map if it was not preloaded.
            if( $live_event_for_user=='' && $is_front=='front' ){
                $live_event_for_user    =    $this->main->wpstream_live_connection->wpstream_get_live_event_for_user();
            }
            // Default the status/button to spinners; resolved below based on live state.
            $channel_status = '<div class="spinner" style="visibility: visible"></div>';
            $button_status  = '<div class="spinner" style="visibility: visible"></div>';
            if(is_array($live_event_for_user) && isset($live_event_for_user[$the_id])) {
                // Channel is live: reveal stream data and grab the QoS/stats URL.
                $pending_streaming_class        =   'pending_trigger';
                $live_data_url                  =   get_post_meta($the_id,'qos_url',true);
              //  $channel_status                 =   esc_html__('Channel is on','wpstream');
            } else {
                // Channel is off: show OFF status and a TURN ON button.
                $channel_status                 = esc_html__('Channel is OFF','wpstream');
                $button_status                  = esc_html__('TURN ON','wpstream');
            }

            // Load the channel's server id and broadcasting credentials/URLs.
            $server_id      =   get_post_meta($the_id,'server_id',true);
            $obs_uri        =   get_post_meta($the_id,'obs_uri',true);
            $obs_stream     =   get_post_meta($the_id,'obs_stream',true);
            $webcaster_url  =   get_post_meta($the_id,'webcaster_url',true);
            $rtmp_ip_uri    =   '';

            // Emit the start-event nonce, then open the card wrapper with all its data-* attributes.
            $ajax_nonce = wp_create_nonce( 'wpstream_start_event_nonce' );
            print '<input type="hidden" id="wpstream_start_event_nonce" value="'.$ajax_nonce.'">';
            print '<div class="event_list_unit '.$live_class.' '.$pending_streaming_class.' event_unit_style_'.esc_attr($is_front).'"  data-show-id="'.intval($the_id).'" data-server-id="'.$server_id.'" data-server-url="'.$rtmp_ip_uri.'"">';

                // Status line, notification slot, thumbnail and (trimmed) title with the channel ID.
                print '<div class="wpstream_channel_status">'.$channel_status.'</div>';

                print '<div class="server_notification"></div>';

                print '<div class="event_thumb_wrapper" style="background-image:url('.$thumb.')"></div>';

                print '<div class="event_title" data-prodid="'.$the_id.'">'.wp_trim_words(get_the_title($the_id),10);


                    /*    print '***</br>'.get_post_meta($the_id,'obs_uri',true) .'***</br>'.
                        get_post_meta($the_id,'obs_stream',true).'***</br>'.   
                        get_post_meta($the_id,'broadcast_url',true);
                    */
                    print '<div class="wpstream_channel_item_id">'.esc_html( '#ID' ).' '.$the_id.'</div>';
                print '</div>';
            

                // Primary ON/OFF toggle button (carries the start-event nonce).
                print '<div class="start_event wpstream_button wpstream_tooltip_wrapper"  data-show-id="'.$the_id.'"  data-nonce="' . esc_attr( $ajax_nonce ) . '" > ' . $button_status;
                    print '<div class="wpstream_tooltip">'.esc_html__('Channel is now OFF. Click to turn ON.','wpestream').'</div>';
                print '</div>';

                // Column 1: broadcasting entry points (webcam, external app, live stats).
                print '<div class="wpstream_options_col1 wpstream_stream_browser_wrapper">';

                    // Webcam ("go live in browser") icon button.
                    print '<div class="wpstream_inactive_icon start_webcaster wpstream_stream_browser wpstream-button-icon wpstream_tooltip_wrapper"  data-webcaster-url="'.$webcaster_url.'" data-show-id="'.$the_id.'"">';

                        print '<div class="wpstream_tooltip_disabled">'.esc_html__('Turn ON the channel to go live.','wpestream').'</div>';
                        print '<div class="wpstream_tooltip">'.esc_html__('Go live with your webcam','wpestream').'</div>';

                        print '<svg width="41" height="51" viewBox="0 0 41 51" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M40.7646 44.2989C39.8782 41.4216 38.3361 38.7814 36.181 36.4522C35.7274 35.9619 35.1629 35.3518 34.4858 34.8356C34.0448 34.4996 33.4262 34.5281 33.0182 34.9035C29.358 38.2714 25.2628 39.9088 20.4979 39.9088H20.4853C15.7103 39.9059 11.6117 38.264 7.95648 34.889C7.52404 34.4896 6.85874 34.484 6.41953 34.8764C3.30565 37.6569 1.20501 40.9206 0.175434 44.5778C-0.276291 46.1833 0.15652 47.8497 1.33328 49.0353C2.22709 49.9362 3.39869 50.4173 4.61958 50.4173C5.0357 50.4173 5.45813 50.3613 5.87645 50.247C10.147 49.081 14.0772 48.4108 17.8916 48.1983C21.1954 48.014 24.4465 48.1323 27.5541 48.5495C29.9559 48.8718 32.4237 49.3888 35.0996 50.1299C36.823 50.6076 38.459 50.1562 39.7059 48.86C40.9268 47.5901 41.2933 46.0132 40.7651 44.2994L40.7646 44.2989ZM38.0572 47.274C37.3825 47.9754 36.6367 48.1823 35.7092 47.9257C32.9321 47.1565 30.3638 46.6187 27.8578 46.2827C24.6079 45.8465 21.2117 45.723 17.7643 45.9151C13.7874 46.1365 9.70183 46.8319 5.27439 48.041C4.43435 48.2706 3.5673 48.0403 2.9568 47.425C2.35932 46.8227 2.14793 46.0108 2.37712 45.1975C3.21567 42.2171 4.7982 39.6162 7.2018 37.2734C11.0641 40.5378 15.5276 42.193 20.4845 42.196H20.4978C25.4557 42.196 29.9251 40.5386 33.7967 37.2667C34.024 37.4904 34.254 37.7359 34.5032 38.0059C36.4236 40.0816 37.7951 42.4256 38.5799 44.9728C38.8603 45.8811 38.6941 46.6125 38.0581 47.274L38.0572 47.274ZM20.4954 30.7538C13.8088 30.7523 8.54664 25.4128 8.54379 18.6256C8.5412 12.2624 13.9961 6.83353 20.3776 6.84872C27.1094 6.86467 32.4526 12.1652 32.4488 18.8231C32.4447 25.4243 27.1038 30.7547 20.4954 30.7538V30.7538ZM29.4401 18.8383C29.5559 13.9512 25.4358 9.8445 20.5347 9.83671C15.5301 9.82892 11.6488 13.9279 11.5328 18.5667C11.4086 23.5202 15.344 27.7103 20.3381 27.7926C25.5542 27.8783 29.554 23.5758 29.4401 18.8383V18.8383ZM20.4917 25.1342C17.2131 25.2436 14.1541 22.4349 14.1567 18.8924C14.1593 15.2627 16.9642 12.4191 20.5858 12.4662C24.0109 12.5111 26.8269 15.2466 26.8362 18.8471C26.8451 22.4293 23.782 25.2436 20.4917 25.1342V25.1342ZM18.5119 13.9982C17.3006 13.9908 15.6202 15.656 15.6295 16.8543C15.6328 17.3209 15.961 17.661 16.3983 17.6513C17.5973 17.6246 19.2281 16.0169 19.2696 14.8205C19.2881 14.2742 19.0371 14.0016 18.5119 13.9982L18.5119 13.9982ZM20.4964 0.568359C10.4428 0.568359 2.26333 8.74761 2.26333 18.8014C2.26333 28.8551 10.4426 37.0345 20.4964 37.0345C30.5502 37.0345 38.7295 28.8553 38.7295 18.8014C38.7291 8.7478 30.5502 0.568359 20.4964 0.568359V0.568359ZM20.4964 33.3886C12.4528 33.3886 5.90919 26.8449 5.90919 18.8014C5.90919 11.3105 11.585 5.12093 18.8624 4.30631C18.8561 4.36009 18.8528 4.41423 18.8528 4.46912C18.8468 5.37368 19.6112 6.16064 20.4927 6.15846C21.3636 6.15586 22.1283 5.39706 22.142 4.52143C22.1431 4.44874 22.1372 4.3768 22.1279 4.30596C29.4067 5.11929 35.0849 11.3092 35.0849 18.802C35.0842 26.8456 28.5404 33.3892 20.4968 33.3892L20.4964 33.3886Z" fill="black"/>
                        </svg>';
                    print '</div>';


                    print '<div class="wpstream_inactive_icon wpstream_stream_pro wpstream-button-icon wpstream-trigger-modal wpstream_tooltip_wrapper"  data-modal="wpestate_broadcast_modal"   data-show-id="'.$the_id.'"">';
                      
                        print '<div class="wpstream_tooltip_disabled">'.esc_html__('Turn ON the channel to go live.','wpestream').'</div>'; 
                        print '<div class="wpstream_tooltip">'.esc_html__('Go Live with external streaming app','wpestream').'</div>'; 

                        print '<svg width="51" height="38" viewBox="0 0 51 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M48.1266 13.9634L41.891 17.5343V13.6438C41.891 10.8723 39.6525 8.63384 36.8811 8.63384H33.1504L30.1124 3.62393C28.7268 1.3324 26.2751 0 23.6103 0H10.2863C8.84722 0 7.67471 1.17251 7.67471 2.6116C7.67471 4.05068 8.84722 5.22319 10.2863 5.22319H23.6103C24.4631 5.22319 25.2093 5.64961 25.6888 6.3957L27.0744 8.63413L5.00991 8.63376C2.23843 8.63376 0 10.8722 0 13.6437V32.9901C0 35.7616 2.23843 38 5.00991 38H36.9342C39.7057 38 41.9441 35.7616 41.9441 32.9901V29.0461L48.1797 32.6169C49.2991 33.2564 50.6846 32.4571 50.6846 31.1778L50.6842 15.4022C50.6311 14.123 49.2455 13.3237 48.1261 13.9632L48.1266 13.9634ZM38.1603 32.9368C38.1603 33.6297 37.574 34.1625 36.9345 34.1625L5.01029 34.1629C4.31733 34.1629 3.78458 33.5766 3.78458 32.9372V13.5907C3.78458 12.8978 4.37086 12.365 5.01029 12.365H36.9345C37.6275 12.365 38.1603 12.9513 38.1603 13.5907V32.9368Z" fill="black"/>
                            <path d="M22.4917 21.585H9.70066C8.84784 21.585 8.15527 22.2779 8.15527 23.1304V28.247C8.15527 29.0998 8.84823 29.7923 9.70066 29.7923H22.4917C23.3445 29.7923 24.0371 29.0994 24.0371 28.247V23.1304C24.0371 22.2775 23.3445 21.585 22.4917 21.585Z" fill="black"/>
                        </svg>';
                    print '</div>';

                   
    
                    
                    print '<a href="'.esc_url($live_data_url).'" target="_blank" class="wpstream_inactive_icon wpstream_live_data wpstream_statistics_channel wpstream-button-icon wpstream_tooltip_wrapper"   data-show-id="'.$the_id.'" >';
                        
                        print '<div class="wpstream_tooltip_disabled">'.esc_html__('Turn ON the channel to see live stats.','wpestream').'</div>'; 
                        print '<div class="wpstream_tooltip">'.esc_html__('Live Statistics','wpestream').'</div>'; 

                        print'<svg width="50" height="42" viewBox="0 0 50 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M48.9001 9.02344H40.3342C39.7288 9.02344 39.2344 9.51791 39.2344 10.1233V40.4275C39.2344 41.033 39.7288 41.5274 40.3342 41.5274H48.9001C49.5055 41.5274 50 41.0329 50 40.4275V10.1233C50 9.51791 49.5055 9.02344 48.9001 9.02344V9.02344Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M35.821 22.4067H27.2551C26.6497 22.4067 26.1553 22.9012 26.1553 23.5066V40.4277C26.1553 41.0332 26.6497 41.5276 27.2551 41.5276H35.821C36.4264 41.5276 36.9209 41.0332 36.9209 40.4277V23.5066C36.9209 22.9012 36.4264 22.4067 35.821 22.4067V22.4067Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M22.7439 0H14.178C13.5726 0 13.0781 0.494478 13.0781 1.09989V40.4275C13.0781 41.0329 13.5726 41.5274 14.178 41.5274H22.7439C23.3493 41.5274 23.8438 41.0329 23.8438 40.4275V1.09989C23.8438 0.49447 23.3493 0 22.7439 0V0Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M9.66573 15.2559H1.09987C0.49445 15.2559 -2.24584e-05 15.7512 -2.24584e-05 16.3558V40.4285C-2.24584e-05 41.0331 0.495325 41.5284 1.09987 41.5284H9.66573C10.2703 41.5284 10.7656 41.034 10.7656 40.4285V16.3558C10.7656 15.7503 10.2711 15.2559 9.66573 15.2559V15.2559Z" fill="black"/>
                        </svg>';
                    print '</a>';
                print '</div>';

                // Column 2: management controls (settings + edit only for back-end admins, then view/share).
                print '<div class="wpstream_options_col2 wpstream_show_settings_wrapper">';
                    if($is_front==''){
                        // Settings modal trigger (disabled while the channel is ON).
                        print '<div class="wpstream_show_settings wpstream-button-icon wpstream-trigger-modal wpstream_tooltip_wrapper"   data-modal="wpestate_settings_modal" data-show-id="'.$the_id.'" value="'.esc_html__('Settings','wpstream').'">';

                            print '<div class="wpstream_tooltip_disabled">'.esc_html__('
                            Turn OFF the channel to change its settings.','wpestream').'</div>';
                            print '<div class="wpstream_tooltip">'.esc_html__('Channel Settings','wpestream').'</div>';

                            print '<svg width="50" height="50" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M49.22 21.8648C48.82 21.6099 45.665 19.7247 44.415 19.15L42.8651 15.4C43.3251 14.1601 44.2099 10.6849 44.3652 10.0798H44.3649C44.4911 9.52035 44.3213 8.93514 43.9149 8.52987L41.47 6.09995C41.0658 5.69189 40.4795 5.52168 39.9201 5.65003C39.4601 5.75501 35.92 6.64994 34.5999 7.15012L30.8499 5.60021C30.3048 4.40012 28.4699 1.32529 28.1351 0.795199V0.79485C27.8337 0.301688 27.298 0.00069643 26.7201 0H23.2798C22.7057 0.000348769 22.1718 0.294715 21.8648 0.779859C21.6098 1.1799 19.7247 4.33487 19.15 5.58487L15.4 7.13478C14.1601 6.67476 10.6849 5.78996 10.0798 5.63469V5.63504C9.52035 5.50878 8.93513 5.67864 8.52987 6.08496L6.09995 8.52988C5.69189 8.9341 5.52168 9.52042 5.65003 10.0798C5.75501 10.5398 6.64994 14.0799 7.15012 15.4L5.60021 19.15C4.40012 19.6951 1.32529 21.53 0.795198 21.8648H0.79485C0.301688 22.1662 0.00069643 22.7019 0 23.2798V26.7149C0.000348751 27.2893 0.294715 27.8233 0.779858 28.1299C1.1799 28.3849 4.33486 30.27 5.58487 30.8448L7.13478 34.5948C6.67475 35.8347 5.78996 39.3099 5.63469 39.915H5.63504C5.50878 40.4747 5.67863 41.06 6.08496 41.4649L8.51487 43.8948V43.8951C8.9191 44.3032 9.50541 44.4731 10.0648 44.3451C10.5248 44.2401 14.0649 43.3451 15.385 42.845L19.135 44.3949C19.6801 45.595 21.515 48.6698 21.8498 49.1999C22.1525 49.6997 22.6956 50.0035 23.2798 50H26.7149C27.2893 49.9996 27.8233 49.7053 28.1299 49.2201C28.3849 48.8201 30.27 45.6651 30.8447 44.4151L34.5947 42.8652C35.8347 43.3252 39.3098 44.21 39.9149 44.3653V44.3649C40.4747 44.4912 41.0599 44.3213 41.4649 43.915L43.8948 41.4851H43.8951C44.3032 41.0809 44.473 40.4945 44.345 39.9352C44.2401 39.4751 43.3451 35.9351 42.8449 34.615L44.3949 30.865C45.5949 30.3198 48.6698 28.485 49.1999 28.1501C49.6997 27.8474 50.0034 27.3044 49.9999 26.7201V23.2799C50.0045 22.7047 49.7087 22.1683 49.2201 21.8649L49.22 21.8648ZM24.9995 35.8845C22.1099 35.882 19.3399 34.7314 17.2985 32.6866C15.2572 30.6414 14.1118 27.8694 14.1146 24.9798C14.1171 22.0903 15.2676 19.3199 17.3125 17.2785C19.3577 15.2372 22.1297 14.0921 25.0193 14.0946C27.9088 14.0974 30.6792 15.2477 32.7205 17.2928C34.7619 19.338 35.907 22.1101 35.9045 24.9996C35.8978 27.8879 34.7462 30.6557 32.7021 32.6964C30.6576 34.7367 27.8876 35.8834 24.9994 35.8845H24.9995Z" fill="black"/>
                                </svg>
                            </div>';
                  

                        // Edit-post link for the channel (admin only).
                        print '<a href="'.get_edit_post_link($the_id).'" class="wpstream_edit_channel wpstream-button-icon wpstream_tooltip_wrapper" target="_blank"  data-show-id="'.$the_id.'"">';
                            print '<div class="wpstream_tooltip">'.esc_html__('Edit Channel','wpestream').'</div>';
                            print '<svg width="50" height="50" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M44.8203 1.79549C42.4263 -0.598498 38.5591 -0.598498 36.1651 1.79549L4.67494 33.2246C4.55203 33.3475 4.49079 33.4088 4.42954 33.5317C4.42954 33.5317 4.42954 33.5929 4.36829 33.5929C4.30705 33.7159 4.24539 33.7771 4.24539 33.9V33.9613L0.0711091 47.5886C-0.113044 48.264 0.0711092 48.9389 0.500659 49.4301C0.868966 49.7984 1.29852 49.9826 1.78973 49.9826C1.97388 49.9826 2.15804 49.9826 2.34219 49.9213L15.9085 45.747H15.9697C16.0926 45.6858 16.2151 45.6858 16.2768 45.6241C16.2768 45.6241 16.338 45.6241 16.338 45.5629C16.4609 45.5016 16.5834 45.44 16.6451 45.3175L48.0742 13.8884C50.4682 11.4944 50.4682 7.62715 48.0742 5.23316L44.8203 1.79549Z" fill="black"/>
                        </svg>';
                        print '</a>';
                    }

                    // Public "view channel" link (always shown).
                    print '<a href="'.get_permalink($the_id).'" target="_blank" class="wpstream_view_channel wpstream-button-icon wpstream_tooltip_wrapper"   data-show-id="'.$the_id.'"">';
                        print '<div class="wpstream_tooltip">'.esc_html__('View Channel','wpestream').'</div>';
                        print '<svg width="50" height="32" viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18.6201 15.2828H21.8752C21.8752 14.069 22.8681 13.0207 24.1373 13.0207V9.76562C21.1032 9.76562 18.6201 12.2487 18.6201 15.2828Z" fill="black"/>
                            <path d="M24.855 0C12.9378 0 3.28272 10.9792 0.579292 14.3447C-0.193097 15.2826 -0.193097 16.662 0.579292 17.6553C3.28282 21.0208 12.9378 32 24.855 32C36.7722 32 46.4273 21.0208 49.1307 17.6553C49.9031 16.7174 49.9031 15.338 49.1307 14.3447C46.4275 10.9792 36.7722 0 24.855 0V0ZM24.855 25.8205C19.4482 25.8205 15.0344 21.4067 15.0344 15.9999C15.0344 10.5931 19.4482 6.17927 24.855 6.17927C30.2618 6.17927 34.6756 10.5931 34.6756 15.9999C34.6756 21.4067 30.2618 25.8205 24.855 25.8205Z" fill="black"/>
                        </svg>';
                    print '</a>';


                    // Share modal trigger.
                    print '<div class="wpstream_share_channel wpstream-button-icon wpstream-trigger-modal wpstream_tooltip_wrapper"   data-modal="wpestate_share_modal"   data-show-id="'.$the_id.'"">';
                        print '<div class="wpstream_tooltip">'.esc_html__('Share Channel','wpestream').'</div>';
                        print '<svg width="44" height="50" viewBox="0 0 44 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20.0777 20.5087L24.3481 17.9427C26.6432 16.5645 29.4554 16.2443 31.96 17.198C33.443 17.7642 35.1166 17.9363 36.852 17.6105C40.3163 16.9521 43.1162 14.1831 43.8115 10.7248C45.0606 4.51597 39.7751 -0.910703 33.5907 0.12835C30.2924 0.682061 27.5604 3.16211 26.5942 6.36168C26.4405 6.86646 26.3418 7.35835 26.2804 7.84466C25.9482 10.5028 24.2498 12.8044 21.9547 14.1827L19.3458 15.7456C17.1061 17.0869 14.3986 17.1792 11.9494 16.2748C10.9956 15.9241 9.96159 15.7271 8.88507 15.7271C3.39004 15.7271 -0.960663 20.7607 0.184097 26.4527C0.879447 29.911 3.67936 32.68 7.14364 33.3384C8.87902 33.6706 10.5525 33.492 12.0356 32.9259C14.5402 31.9721 17.3461 32.2919 19.6475 33.6706L23.906 36.2241C25.346 37.0855 26.2195 38.6424 26.2195 40.3159V40.3403C26.2195 45.8229 31.2287 50.1611 36.9018 49.0472C40.3602 48.3703 43.1415 45.583 43.8244 42.1246C44.9383 36.451 40.6 31.4423 35.1175 31.4423C34.0406 31.4423 33.007 31.6394 32.0532 31.9901C29.604 32.8944 26.9029 32.8022 24.6627 31.4608L20.0721 28.7038C18.6445 27.8484 17.7706 26.304 17.7706 24.6364V24.5875C17.7642 22.9208 18.6441 21.37 20.0777 20.5085L20.0777 20.5087Z" fill="black"/>
                        </svg>';
                    print '</div>';
                print '</div>';

                // Emit the hidden per-channel modals (settings, share, broadcast) referenced by the triggers above.
                $this->wpstream_display_modal_seetings($the_id);
                $this->wpstream_display_modal_share($the_id);
                $this->wpstream_display_modal_broadcast($the_id,$external_software_streaming_class,$obs_uri,$obs_stream);

            // Close the card wrapper.
            print '</div>';

        }




        /**
        * Shows event unit card in admin - theme version
        *
        * Front-end/theme variant of wpstream_live_stream_unit(): same controls
        * (webcam, external app, stats, settings, edit, view, share, ON/OFF) but
        * wrapped in theme-specific markup and with visible text labels under each
        * icon. Echoes HTML.
        *
        * @since  3.0.1
        * @param  int    $the_id   Channel product/post ID.
        * @param  string $is_front Non-empty (e.g. 'front') when rendered on the front end.
        * @return void
        */
        public function wpstream_live_stream_unit_for_theme($the_id,$is_front=''){
            global $live_event_for_user;
            global $wpstream_plugin;
            $current_user       =   wp_get_current_user();

            // Non-admins get a "not allowed" notice in the back-end context.
            if( !current_user_can('administrator')){
                if($is_front=='' ){
                    print '<div class="event_list_unit">';
                    esc_html_e('You are not allowed to broadcast.','wpstream');
                    print '</div>';
                    return;
                }

            }

            // Enforce the plugin's streaming-permission gate.
            if( !$this->main->wpstream_check_user_can_stream()  ){
                print '<div class="event_list_unit">';
                esc_html_e('You are not allowed to broadcast','wpstream');
                print '</div>';
                return;
            }


            // Mark the card "live" when the channel currently has an active event.
            $live_class='';
            if(isset($live_event_for_user[$the_id])) {
                $live_class=" wpstream_show_started";
            }


            // Thumbnail: featured image or plugin-logo fallback.
            if(has_post_thumbnail($the_id)){
                $thumb  =   get_the_post_thumbnail_url($the_id,'thumbnail');
            }else{
                $thumb= plugin_dir_url( dirname( __FILE__ ) ). 'img/plugin-logo.png';
            }

            // Default streaming state and empty broadcasting credentials/URLs.
            $pending_streaming_class            =   'hide_stream_data';
            $external_software_streaming_class  =   '';
            $obs_uri                            =   '';
            $obs_stream                         =   '';
            $live_data_url                      =   '';

            // Lazily load the live-event map on the front end if not preloaded.
            if( $live_event_for_user=='' && $is_front=='front' ){
                $live_event_for_user    =    $this->main->wpstream_live_connection->wpstream_get_live_event_for_user();
            }

            // Default the button to a spinner (WP admin spinner gif) and no status text.
            $spinner_url = admin_url('images/spinner-2x.gif');
            $button_status = '<div class="spinner" style="background-image: url(' . $spinner_url . ')"></div>';
            $channel_status = '';
            if(is_array($live_event_for_user) && isset($live_event_for_user[$the_id])) {
                // Channel live: reveal data and grab the stats URL.
                $pending_streaming_class        =   'pending_trigger';
                $live_data_url                  =   get_post_meta($the_id,'qos_url',true);
              //  $channel_status                 =   esc_html__('Channel is on','wpstream');
            } else {
                // Channel off: show OFF status and a TURN ON button.
                $channel_status                 = esc_html__('Channel is OFF','wpstream');
                $button_status                  = esc_html__('TURN ON', 'wpstream');
            }

            // Load the channel's server id and broadcasting credentials/URLs.
            $server_id      =   get_post_meta($the_id,'server_id',true);
            $obs_uri        =   get_post_meta($the_id,'obs_uri',true);
            $obs_stream     =   get_post_meta($the_id,'obs_stream',true);
            $webcaster_url  =   get_post_meta($the_id,'webcaster_url',true);
            $rtmp_ip_uri    =   '';

            // Open the theme card wrapper with its data-* attributes.
            print '<div class="wpstream_theme_event_list_unit event_list_unit '.$live_class.' '.$pending_streaming_class.' event_unit_style_'.esc_attr($is_front).'"  data-show-id="'.intval($the_id).'" data-server-id="'.$server_id.'" data-server-url="'.$rtmp_ip_uri.'"">';

                // Status line (hidden when empty), notification slot, thumbnail and title with the channel ID.
                print '<div class="wpstream_channel_status" style="' . ($channel_status ? '' : 'display: none') . '">'.$channel_status.'</div>';

                print '<div class="server_notification"></div>';

                print '<div class="wpstream_theme_event_thumb_wrapper">';
                    print '<div class="event_thumb_wrapper" style="background-image:url('.$thumb.')"></div>';
                    print '<div class="event_title" data-prodid="'.$the_id.'">'.wp_trim_words(get_the_title($the_id),10);
                        print '<div class="wpstream_channel_item_id">'.esc_html('#ID','wpstream').' '.$the_id.'</div>';
                    print '</div>';
                print '</div>';

                // Control bar: column 1 = broadcasting entry points (webcam, external app, live stats).
                print '<div class="wpstream_theme_control_bar_wrapper">';


                print '<div class="wpstream_options_col1 wpstream_stream_browser_wrapper">';
                   
                    print '<div class="wpstream_inactive_icon start_webcaster wpstream_stream_browser wpstream-button-icon wpstream_tooltip_wrapper"  data-webcaster-url="'.$webcaster_url.'" data-show-id="'.$the_id.'"">';

                        print '<div class="wpstream_tooltip_disabled">'.esc_html__('Turn ON the channel to go live.','wpestream').'</div>'; 
                        print '<div class="wpstream_tooltip">'.esc_html__('Go live with your webcam','wpestream').'</div>'; 

                        print '<svg width="41" height="51" viewBox="0 0 41 51" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M40.7646 44.2989C39.8782 41.4216 38.3361 38.7814 36.181 36.4522C35.7274 35.9619 35.1629 35.3518 34.4858 34.8356C34.0448 34.4996 33.4262 34.5281 33.0182 34.9035C29.358 38.2714 25.2628 39.9088 20.4979 39.9088H20.4853C15.7103 39.9059 11.6117 38.264 7.95648 34.889C7.52404 34.4896 6.85874 34.484 6.41953 34.8764C3.30565 37.6569 1.20501 40.9206 0.175434 44.5778C-0.276291 46.1833 0.15652 47.8497 1.33328 49.0353C2.22709 49.9362 3.39869 50.4173 4.61958 50.4173C5.0357 50.4173 5.45813 50.3613 5.87645 50.247C10.147 49.081 14.0772 48.4108 17.8916 48.1983C21.1954 48.014 24.4465 48.1323 27.5541 48.5495C29.9559 48.8718 32.4237 49.3888 35.0996 50.1299C36.823 50.6076 38.459 50.1562 39.7059 48.86C40.9268 47.5901 41.2933 46.0132 40.7651 44.2994L40.7646 44.2989ZM38.0572 47.274C37.3825 47.9754 36.6367 48.1823 35.7092 47.9257C32.9321 47.1565 30.3638 46.6187 27.8578 46.2827C24.6079 45.8465 21.2117 45.723 17.7643 45.9151C13.7874 46.1365 9.70183 46.8319 5.27439 48.041C4.43435 48.2706 3.5673 48.0403 2.9568 47.425C2.35932 46.8227 2.14793 46.0108 2.37712 45.1975C3.21567 42.2171 4.7982 39.6162 7.2018 37.2734C11.0641 40.5378 15.5276 42.193 20.4845 42.196H20.4978C25.4557 42.196 29.9251 40.5386 33.7967 37.2667C34.024 37.4904 34.254 37.7359 34.5032 38.0059C36.4236 40.0816 37.7951 42.4256 38.5799 44.9728C38.8603 45.8811 38.6941 46.6125 38.0581 47.274L38.0572 47.274ZM20.4954 30.7538C13.8088 30.7523 8.54664 25.4128 8.54379 18.6256C8.5412 12.2624 13.9961 6.83353 20.3776 6.84872C27.1094 6.86467 32.4526 12.1652 32.4488 18.8231C32.4447 25.4243 27.1038 30.7547 20.4954 30.7538V30.7538ZM29.4401 18.8383C29.5559 13.9512 25.4358 9.8445 20.5347 9.83671C15.5301 9.82892 11.6488 13.9279 11.5328 18.5667C11.4086 23.5202 15.344 27.7103 20.3381 27.7926C25.5542 27.8783 29.554 23.5758 29.4401 18.8383V18.8383ZM20.4917 25.1342C17.2131 25.2436 14.1541 22.4349 14.1567 18.8924C14.1593 15.2627 16.9642 12.4191 20.5858 12.4662C24.0109 12.5111 26.8269 15.2466 26.8362 18.8471C26.8451 22.4293 23.782 25.2436 20.4917 25.1342V25.1342ZM18.5119 13.9982C17.3006 13.9908 15.6202 15.656 15.6295 16.8543C15.6328 17.3209 15.961 17.661 16.3983 17.6513C17.5973 17.6246 19.2281 16.0169 19.2696 14.8205C19.2881 14.2742 19.0371 14.0016 18.5119 13.9982L18.5119 13.9982ZM20.4964 0.568359C10.4428 0.568359 2.26333 8.74761 2.26333 18.8014C2.26333 28.8551 10.4426 37.0345 20.4964 37.0345C30.5502 37.0345 38.7295 28.8553 38.7295 18.8014C38.7291 8.7478 30.5502 0.568359 20.4964 0.568359V0.568359ZM20.4964 33.3886C12.4528 33.3886 5.90919 26.8449 5.90919 18.8014C5.90919 11.3105 11.585 5.12093 18.8624 4.30631C18.8561 4.36009 18.8528 4.41423 18.8528 4.46912C18.8468 5.37368 19.6112 6.16064 20.4927 6.15846C21.3636 6.15586 22.1283 5.39706 22.142 4.52143C22.1431 4.44874 22.1372 4.3768 22.1279 4.30596C29.4067 5.11929 35.0849 11.3092 35.0849 18.802C35.0842 26.8456 28.5404 33.3892 20.4968 33.3892L20.4964 33.3886Z" fill="black"/>
                        </svg>';
                        esc_html_e('Webcam','wpstream');
                    print '</div>';


                    print '<div class="wpstream_inactive_icon wpstream_stream_pro wpstream-button-icon wpstream-trigger-modal wpstream_tooltip_wrapper"  data-modal="wpestate_broadcast_modal"   data-show-id="'.$the_id.'"">';
                      
                        print '<div class="wpstream_tooltip_disabled">'.esc_html__('Turn ON the channel to go live.','wpestream').'</div>'; 
                        print '<div class="wpstream_tooltip">'.esc_html__('Go Live with external streaming app','wpestream').'</div>'; 

                        print '<svg width="51" height="38" viewBox="0 0 51 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M48.1266 13.9634L41.891 17.5343V13.6438C41.891 10.8723 39.6525 8.63384 36.8811 8.63384H33.1504L30.1124 3.62393C28.7268 1.3324 26.2751 0 23.6103 0H10.2863C8.84722 0 7.67471 1.17251 7.67471 2.6116C7.67471 4.05068 8.84722 5.22319 10.2863 5.22319H23.6103C24.4631 5.22319 25.2093 5.64961 25.6888 6.3957L27.0744 8.63413L5.00991 8.63376C2.23843 8.63376 0 10.8722 0 13.6437V32.9901C0 35.7616 2.23843 38 5.00991 38H36.9342C39.7057 38 41.9441 35.7616 41.9441 32.9901V29.0461L48.1797 32.6169C49.2991 33.2564 50.6846 32.4571 50.6846 31.1778L50.6842 15.4022C50.6311 14.123 49.2455 13.3237 48.1261 13.9632L48.1266 13.9634ZM38.1603 32.9368C38.1603 33.6297 37.574 34.1625 36.9345 34.1625L5.01029 34.1629C4.31733 34.1629 3.78458 33.5766 3.78458 32.9372V13.5907C3.78458 12.8978 4.37086 12.365 5.01029 12.365H36.9345C37.6275 12.365 38.1603 12.9513 38.1603 13.5907V32.9368Z" fill="black"/>
                            <path d="M22.4917 21.585H9.70066C8.84784 21.585 8.15527 22.2779 8.15527 23.1304V28.247C8.15527 29.0998 8.84823 29.7923 9.70066 29.7923H22.4917C23.3445 29.7923 24.0371 29.0994 24.0371 28.247V23.1304C24.0371 22.2775 23.3445 21.585 22.4917 21.585Z" fill="black"/>
                        </svg>';
                        esc_html_e('External App','wpstream');
                    print '</div>';

                   
    
                    
                    print '<a href="'.esc_url($live_data_url).'" target="_blank" class="wpstream_inactive_icon wpstream_live_data wpstream_statistics_channel wpstream-button-icon wpstream_tooltip_wrapper"   data-show-id="'.$the_id.'" >';
                        
                        print '<div class="wpstream_tooltip_disabled">'.esc_html__('Turn ON the channel to see live stats.','wpestream').'</div>'; 
                        print '<div class="wpstream_tooltip">'.esc_html__('Live Statistics','wpestream').'</div>'; 

                        print'<svg width="50" height="42" viewBox="0 0 50 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M48.9001 9.02344H40.3342C39.7288 9.02344 39.2344 9.51791 39.2344 10.1233V40.4275C39.2344 41.033 39.7288 41.5274 40.3342 41.5274H48.9001C49.5055 41.5274 50 41.0329 50 40.4275V10.1233C50 9.51791 49.5055 9.02344 48.9001 9.02344V9.02344Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M35.821 22.4067H27.2551C26.6497 22.4067 26.1553 22.9012 26.1553 23.5066V40.4277C26.1553 41.0332 26.6497 41.5276 27.2551 41.5276H35.821C36.4264 41.5276 36.9209 41.0332 36.9209 40.4277V23.5066C36.9209 22.9012 36.4264 22.4067 35.821 22.4067V22.4067Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M22.7439 0H14.178C13.5726 0 13.0781 0.494478 13.0781 1.09989V40.4275C13.0781 41.0329 13.5726 41.5274 14.178 41.5274H22.7439C23.3493 41.5274 23.8438 41.0329 23.8438 40.4275V1.09989C23.8438 0.49447 23.3493 0 22.7439 0V0Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M9.66573 15.2559H1.09987C0.49445 15.2559 -2.24584e-05 15.7512 -2.24584e-05 16.3558V40.4285C-2.24584e-05 41.0331 0.495325 41.5284 1.09987 41.5284H9.66573C10.2703 41.5284 10.7656 41.034 10.7656 40.4285V16.3558C10.7656 15.7503 10.2711 15.2559 9.66573 15.2559V15.2559Z" fill="black"/>
                        </svg>';
                        esc_html_e('Statistics','wpstream');
                    print '</a>';
                print '</div>';

                // Column 2: management controls (settings + edit only for back-end admins, then view/share).
                print '<div class="wpstream_options_col2 wpstream_show_settings_wrapper">';
                    if($is_front==''){
                        // Settings modal trigger (disabled while the channel is ON).
                        print '<div class="wpstream_show_settings wpstream-button-icon wpstream-trigger-modal wpstream_tooltip_wrapper"   data-modal="wpestate_settings_modal" data-show-id="'.$the_id.'" value="'.esc_html__('Settings','wpstream').'">';
                            
                            print '<div class="wpstream_tooltip_disabled">'.esc_html__('Turn OFF the channel to change its settings.','wpestream').'</div>'; 
                            print '<div class="wpstream_tooltip">'.esc_html__('Channel Settings','wpestream').'</div>'; 

                            print '<svg width="50" height="50" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M49.22 21.8648C48.82 21.6099 45.665 19.7247 44.415 19.15L42.8651 15.4C43.3251 14.1601 44.2099 10.6849 44.3652 10.0798H44.3649C44.4911 9.52035 44.3213 8.93514 43.9149 8.52987L41.47 6.09995C41.0658 5.69189 40.4795 5.52168 39.9201 5.65003C39.4601 5.75501 35.92 6.64994 34.5999 7.15012L30.8499 5.60021C30.3048 4.40012 28.4699 1.32529 28.1351 0.795199V0.79485C27.8337 0.301688 27.298 0.00069643 26.7201 0H23.2798C22.7057 0.000348769 22.1718 0.294715 21.8648 0.779859C21.6098 1.1799 19.7247 4.33487 19.15 5.58487L15.4 7.13478C14.1601 6.67476 10.6849 5.78996 10.0798 5.63469V5.63504C9.52035 5.50878 8.93513 5.67864 8.52987 6.08496L6.09995 8.52988C5.69189 8.9341 5.52168 9.52042 5.65003 10.0798C5.75501 10.5398 6.64994 14.0799 7.15012 15.4L5.60021 19.15C4.40012 19.6951 1.32529 21.53 0.795198 21.8648H0.79485C0.301688 22.1662 0.00069643 22.7019 0 23.2798V26.7149C0.000348751 27.2893 0.294715 27.8233 0.779858 28.1299C1.1799 28.3849 4.33486 30.27 5.58487 30.8448L7.13478 34.5948C6.67475 35.8347 5.78996 39.3099 5.63469 39.915H5.63504C5.50878 40.4747 5.67863 41.06 6.08496 41.4649L8.51487 43.8948V43.8951C8.9191 44.3032 9.50541 44.4731 10.0648 44.3451C10.5248 44.2401 14.0649 43.3451 15.385 42.845L19.135 44.3949C19.6801 45.595 21.515 48.6698 21.8498 49.1999C22.1525 49.6997 22.6956 50.0035 23.2798 50H26.7149C27.2893 49.9996 27.8233 49.7053 28.1299 49.2201C28.3849 48.8201 30.27 45.6651 30.8447 44.4151L34.5947 42.8652C35.8347 43.3252 39.3098 44.21 39.9149 44.3653V44.3649C40.4747 44.4912 41.0599 44.3213 41.4649 43.915L43.8948 41.4851H43.8951C44.3032 41.0809 44.473 40.4945 44.345 39.9352C44.2401 39.4751 43.3451 35.9351 42.8449 34.615L44.3949 30.865C45.5949 30.3198 48.6698 28.485 49.1999 28.1501C49.6997 27.8474 50.0034 27.3044 49.9999 26.7201V23.2799C50.0045 22.7047 49.7087 22.1683 49.2201 21.8649L49.22 21.8648ZM24.9995 35.8845C22.1099 35.882 19.3399 34.7314 17.2985 32.6866C15.2572 30.6414 14.1118 27.8694 14.1146 24.9798C14.1171 22.0903 15.2676 19.3199 17.3125 17.2785C19.3577 15.2372 22.1297 14.0921 25.0193 14.0946C27.9088 14.0974 30.6792 15.2477 32.7205 17.2928C34.7619 19.338 35.907 22.1101 35.9045 24.9996C35.8978 27.8879 34.7462 30.6557 32.7021 32.6964C30.6576 34.7367 27.8876 35.8834 24.9994 35.8845H24.9995Z" fill="black"/>
                                </svg>
                            </div>';
                  

                        print '<a href="'.get_edit_post_link($the_id).'" class="wpstream_edit_channel wpstream-button-icon wpstream_tooltip_wrapper" target="_blank"  data-show-id="'.$the_id.'"">';
                            print '<div class="wpstream_tooltip">'.esc_html__('Edit Channel','wpestream').'</div>';    
                            print '<svg width="50" height="50" viewBox="0 0 50 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M44.8203 1.79549C42.4263 -0.598498 38.5591 -0.598498 36.1651 1.79549L4.67494 33.2246C4.55203 33.3475 4.49079 33.4088 4.42954 33.5317C4.42954 33.5317 4.42954 33.5929 4.36829 33.5929C4.30705 33.7159 4.24539 33.7771 4.24539 33.9V33.9613L0.0711091 47.5886C-0.113044 48.264 0.0711092 48.9389 0.500659 49.4301C0.868966 49.7984 1.29852 49.9826 1.78973 49.9826C1.97388 49.9826 2.15804 49.9826 2.34219 49.9213L15.9085 45.747H15.9697C16.0926 45.6858 16.2151 45.6858 16.2768 45.6241C16.2768 45.6241 16.338 45.6241 16.338 45.5629C16.4609 45.5016 16.5834 45.44 16.6451 45.3175L48.0742 13.8884C50.4682 11.4944 50.4682 7.62715 48.0742 5.23316L44.8203 1.79549Z" fill="black"/>
                        </svg>';
                        print '</a>';
                    }

                    print '<a href="'.get_permalink($the_id).'" target="_blank" class="wpstream_view_channel wpstream-button-icon wpstream_tooltip_wrapper"   data-show-id="'.$the_id.'"">';
                        print '<div class="wpstream_tooltip">'.esc_html__('View Channel','wpestream').'</div>';  
                        print '<svg width="50" height="32" viewBox="0 0 50 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18.6201 15.2828H21.8752C21.8752 14.069 22.8681 13.0207 24.1373 13.0207V9.76562C21.1032 9.76562 18.6201 12.2487 18.6201 15.2828Z" fill="black"/>
                            <path d="M24.855 0C12.9378 0 3.28272 10.9792 0.579292 14.3447C-0.193097 15.2826 -0.193097 16.662 0.579292 17.6553C3.28282 21.0208 12.9378 32 24.855 32C36.7722 32 46.4273 21.0208 49.1307 17.6553C49.9031 16.7174 49.9031 15.338 49.1307 14.3447C46.4275 10.9792 36.7722 0 24.855 0V0ZM24.855 25.8205C19.4482 25.8205 15.0344 21.4067 15.0344 15.9999C15.0344 10.5931 19.4482 6.17927 24.855 6.17927C30.2618 6.17927 34.6756 10.5931 34.6756 15.9999C34.6756 21.4067 30.2618 25.8205 24.855 25.8205Z" fill="black"/>
                        </svg>';
                        esc_html_e('View','wpstream');
                    print '</a>';


                    print '<div class="wpstream_share_channel wpstream-button-icon wpstream-trigger-modal wpstream_tooltip_wrapper"   data-modal="wpestate_share_modal"   data-show-id="'.$the_id.'"">';
                        print '<div class="wpstream_tooltip">'.esc_html__('Share Channel','wpestream').'</div>';  
                        print '<svg width="44" height="50" viewBox="0 0 44 50" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20.0777 20.5087L24.3481 17.9427C26.6432 16.5645 29.4554 16.2443 31.96 17.198C33.443 17.7642 35.1166 17.9363 36.852 17.6105C40.3163 16.9521 43.1162 14.1831 43.8115 10.7248C45.0606 4.51597 39.7751 -0.910703 33.5907 0.12835C30.2924 0.682061 27.5604 3.16211 26.5942 6.36168C26.4405 6.86646 26.3418 7.35835 26.2804 7.84466C25.9482 10.5028 24.2498 12.8044 21.9547 14.1827L19.3458 15.7456C17.1061 17.0869 14.3986 17.1792 11.9494 16.2748C10.9956 15.9241 9.96159 15.7271 8.88507 15.7271C3.39004 15.7271 -0.960663 20.7607 0.184097 26.4527C0.879447 29.911 3.67936 32.68 7.14364 33.3384C8.87902 33.6706 10.5525 33.492 12.0356 32.9259C14.5402 31.9721 17.3461 32.2919 19.6475 33.6706L23.906 36.2241C25.346 37.0855 26.2195 38.6424 26.2195 40.3159V40.3403C26.2195 45.8229 31.2287 50.1611 36.9018 49.0472C40.3602 48.3703 43.1415 45.583 43.8244 42.1246C44.9383 36.451 40.6 31.4423 35.1175 31.4423C34.0406 31.4423 33.007 31.6394 32.0532 31.9901C29.604 32.8944 26.9029 32.8022 24.6627 31.4608L20.0721 28.7038C18.6445 27.8484 17.7706 26.304 17.7706 24.6364V24.5875C17.7642 22.9208 18.6441 21.37 20.0777 20.5085L20.0777 20.5087Z" fill="black"/>
                        </svg>';
                        esc_html_e('Share','wpstream');
                    print '</div>';
                print '</div>';


                    // ON/OFF toggle button placed after the control columns in the theme layout.
                    print '<div class="start_event wpstream_button wpstream_tooltip_wrapper"  data-show-id="'.$the_id.'" > '. $button_status;
                        print '<div class="wpstream_tooltip">'.esc_html__('Channel is now OFF. Click to turn ON.','wpestream').'</div>';
                    print '</div>';

                // Close the control bar wrapper.
                print '</div>';




                // Emit the hidden per-channel modals (settings, share, broadcast).
                $this->wpstream_display_modal_seetings($the_id);
                $this->wpstream_display_modal_share($the_id);
                $this->wpstream_display_modal_broadcast($the_id,$external_software_streaming_class,$obs_uri,$obs_stream);

            // Close the theme card wrapper.
            print '</div>';

        }


        /*
        *
        * Close modal button
        *
        */
        // Outputs the small "X" element used to dismiss the modal dialogs.
        public function wpstream_close_modal_button(){
            print '<div class="wpstream_close_modal"></div>';
        }

        /**
         * Render the "edit settings for this channel" toggle plus the per-channel
         * event-options block on the product edit screen.
         *
         * When the toggle is off, the site-wide Default Channel Settings apply;
         * when on, the local per-channel options are used. In basic-stream mode a
         * warning is shown and the toggle is disabled. Echoes HTML.
         *
         * @param  bool $is_basic_stream_mode True to render the basic-streaming warning and disable the toggle.
         * @return void
         */
        public function wpstream_local_event_options_toggle( $is_basic_stream_mode = false ) {
            // Read whether this post uses the global options and any stored per-channel overrides.
            $use_global_event_options       = get_post_meta(get_the_ID(), 'use_global_event_options', true );
            $local_event_options            = get_post_meta( get_the_ID(), 'local_event_options', true );
            // The local-options toggle is "on" when local options exist and global isn't forced, or global is explicitly 0.
            $use_local_event_options_enabled = ( is_array( $local_event_options ) && empty( $use_global_event_options ) ) ||
                ( !empty($use_global_event_options) && intval( $use_global_event_options ) === 0 );

            // In basic-streaming mode, prepend the limitation notice.
            if ( $is_basic_stream_mode ) {
				$this->wpstream_basic_stream_mode_message();
            }

            // Toggle row: label/description on the left, a switch checkbox on the right.
            print '<div class="wpstream_local_event_options_toggle_wrapper">';
                print '<div class="wpstream_local_event_options_toggle_info">';
                    print '<label for="local_event_options_enabled" class="wpstream_local_event_options_label">'.esc_html__('Edit settings for this channel','wpstream').'</label>';
                    print '<span>'.sprintf(esc_html__('When is OFF, the settings from %s will be applied','wpstream'), '<a href="' . admin_url('admin.php?page=wpstream_settings&tab=default_options') . '" target="_blank">'.esc_html__('Default Channel Settings','wpstream').'</a>').'</span>';
                print '</div>';
                // The switch checkbox reflects the current toggle state and is disabled in basic-stream mode.
                print '<label class="wpstream_switch">';
                    print '<input id="local_event_options_enabled" type="checkbox" class="wpstream_local_event_options_toggle" ' . ($use_local_event_options_enabled === true ? 'checked' : '') . ' ' . ( $is_basic_stream_mode ? 'disabled' : '' ) . '>';
                    print '<span class="wpstream_slider round"></span>';
                print '</label>';
            print '</div>';
        }

		/**
		 * Print the basic-streaming-mode notice shown above the channel settings.
		 *
		 * Wording differs depending on whether the account is out of streaming
		 * hours vs. simply on a basic plan; both variants link to pricing. Echoes HTML.
		 *
		 * @return void
		 */
		public function wpstream_basic_stream_mode_message() {
			print '<div class="basic-mode-notice">';
			// Out-of-hours variant: offer to top up or upgrade.
			if ( $this->wpstream_is_use_streaming_hours() ) {
					print sprintf(
						wp_kses(
							__(
							'You’ve used all available broadcast or viewer hours. Default channel settings will be used for now. To edit channel settings, <a href="%s" target="_blank">top up</a> your resources or <a href="%s" target="_blank">upgrade</a> your plan.',
							'wpstream'
							 ),
							 array( 'a' => array(
								'href'   => array(),
								'target' => array()
								)
							 )
						),
						esc_url( 'https://wpstream.net/pricing/' ),
						esc_url( 'https://wpstream.net/pricing/' )
					);
			} else {
				// Plain basic-mode variant: offer to upgrade.
				print sprintf(
					wp_kses(
						__(
						'You are currently in Basic Streaming Mode. The default channel settings will be used instead. Please <a href="%s" target="_blank">upgrade</a> your plan to edit the channel settings.',
						'wpstream'
						 ),
						 array(
							'a' => array(
								'href' => array()
							)
						 )
					),
					esc_url( 'https://wpstream.net/pricing/' )
				);
			}
			print '</div>';
		}

        /*
        *
        * Display modal settings
        *
        */
        /**
         * Render the per-channel "Channel Settings" modal (settings toggle plus
         * the streaming option controls). Echoes HTML.
         *
         * @param  int $the_id Channel post/product ID.
         * @return void
         */
        public function wpstream_display_modal_seetings($the_id){
            // Whether the account is in basic-streaming mode (disables/locks some options).
            $is_basic_stream_mode = $this->wpstream_is_basic_streaming_mode();

            // Modal shell with close button and title carrying the channel ID.
            print '<div class="wpstream_modal_form wpestate_settings_modal">';
                $this->wpstream_close_modal_button();
                print '<h3>';
                printf( esc_html__('Channel Settings (#ID %s)','wpstream'),$the_id);
                print '</h3>';

                // The "edit settings for this channel" toggle.
                $this->wpstream_local_event_options_toggle( $is_basic_stream_mode );

                // Resolve whether local per-channel options are in effect (same rule as the toggle).
                $local_event_options            = get_post_meta($the_id,'local_event_options',true);
                $use_global_event_options       = get_post_meta($the_id, 'use_global_event_options',true);
                $is_local_event_options_enabled = ( is_array( $local_event_options ) && empty( $use_global_event_options ) ) ||
                    ( !empty($use_global_event_options) && intval( $use_global_event_options ) === 0 );

                // Options that do not apply to the per-channel (local) context.
                $local_array_exclude=array('ses_encrypt','vod_domain_lock','vod_encrypt');

                // Render the option controls; the 4th arg disables them when local options are off.
                print '<div class="wpstream_event_streaming_local">';
					$this->user_streaming_global_channel_options(
						'',
						$local_event_options,
						$local_array_exclude,
						!$is_local_event_options_enabled,
						$is_basic_stream_mode
					);
                print '</div>';
            print '</div>';
        }

        
        /*
        *
        * Display share settings
        *
        */
        /**
         * Render the per-channel "Share your Channel" modal (social-share bar). Echoes HTML.
         *
         * @param  int $the_id Channel post/product ID.
         * @return void
         */
        public function wpstream_display_modal_share($the_id){
            // Modal shell with close button, title and the social-share icon row.
            print '<div class="wpstream_modal_form wpestate_share_modal">';
                $this->wpstream_close_modal_button();
                print '<h3>'.esc_html__('Share your Channel','wpstream').'</h3>';
                $this->wpstream_social_share($the_id);
            print '</div>';
        }


        /* 
        *
        * Display broadcast modal 
        *
        */

        /**
         * Render the "Go Live with External Streaming App" modal: an encoder
         * picker (OBS, StreamYard, Restream, vMix, Wirecast, XSplit, Larix) plus
         * one settings panel per encoder showing the RTMP server URI and stream
         * key. Echoes HTML.
         *
         * @param  int    $the_id                           Channel post/product ID.
         * @param  string $external_software_streaming_class Extra CSS class flag (unused in body).
         * @param  string $obs_uri                          RTMP server/ingest URI for the channel.
         * @param  string $obs_stream                       RTMP stream key for the channel.
         * @return void
         */
        public function wpstream_display_modal_broadcast($the_id,$external_software_streaming_class,$obs_uri,$obs_stream){
            // Modal shell with close button, title and instructions.
            print '<div class="wpstream_modal_form wpestate_broadcast_modal">';
                $this->wpstream_close_modal_button();
                print '<h3>'.esc_html__('Go Live with External Streaming App','wpstream').'</h3>';

                print '<div class="wpstream_modal_explanations">'.esc_html__('Please choose your RTMP encoder/broadcaster','wpstream').'</div>';

                // Dropdown selecting which encoder's settings panel to show (driven by JS).
                print '<select class="wpstream_external_broadcast_options">';
                    print '<option value="wpstream_obs_settings">'.esc_html('OBS','wpstream').'</option>';  
                    print '<option value="wpstream_streamyard_settings">'.esc_html('StreamYard','wpstream').'</option>';    
                    print '<option value="wpstream_restream_settings">'.esc_html('Restream','wpstream').'</option>';   
                    print '<option value="wpstream_wimx_settings">'.esc_html('vMix','wpstream').'</option>';  
                    print '<option value="wpstream_wirecast_settings">'.esc_html('Wirecast','wpstream').'</option>';    
                    print '<option value="wpstream_xplit_settings">'.esc_html('XSplit','wpstream').'</option>';              
                    print '<option value="wpstream_larix_settings">'.esc_html('Larix Broadcaster','wpstream').'</option>';                    
        
                print '</select>';

                // Emit every encoder's settings panel; JS toggles the visible one.
                $this->wpstream_obs_settings($obs_uri,$obs_stream);
                $this->wpstream_streamyard_settings($obs_uri,$obs_stream);
                $this->wpstream_restream_settings($obs_uri,$obs_stream);
                $this->wpstream_wmix_settings($obs_uri,$obs_stream);
                $this->wpstream_wirecast_settings($obs_uri,$obs_stream);
                $this->wpstream_xplit_settings($obs_uri,$obs_stream);
                $this->wpstream_larix_settings($obs_uri,$obs_stream);

            print '</div>';
        }

        


        /*
        *
        * Display OBS settings 
        *
        */

        /**
         * Render the OBS encoder settings panel (server URI + stream key + steps).
         * Shown by default (display:block). Echoes HTML.
         *
         * @param  string $obs_uri    RTMP server/ingest URI.
         * @param  string $obs_stream RTMP stream key.
         * @return void
         */
        public function wpstream_obs_settings($obs_uri,$obs_stream){

            print '<div class="external_software_streaming wpstream_obs_settings" style="display:block;">';

                // Server URI and stream key, each with a "copy" affordance.
                print '<div class="external_software_streaming_details">';
                    print '<div class="event_list_unit_notificationx"><strong>'.esc_html__('Server:').' </strong>';
                    print '<div class="wpstream_live_uri_text">' . $obs_uri.'</div>
                    <div class="copy_live_uri">'.__('copy','wpstream').'</div>';

                    print '<div class="event_list_stream_key_wrap"><strong>'.__('Stream Key:').' </strong>
                    <div class="wpstream_live_key_text">'. $obs_stream.'</div><div class="copy_live_key">'.__('copy','wpstream').'</div></div>';
                    print '</div>';
                
                print'</div>';
                
                print ' <div class="wpstream_modal_explanations">';
                  print '<ul>
                  <li>1. Click Settings in the OBS Window and then Select Stream.</li>
                  <li>2. Choose Custom Streaming Server in the Stream Type dropdown menu.</li>
                  <li>3. In the URL box, type/paste your Server.</li>
                  <li>4. In the Stream key, type/paste your Stream key.</li>
                  <li>5. Save changes.Close the Settings window and click on the "Start Streaming" button in the main window of OBS.</li></ul>';
                  
                print '</div>';           
            
                
            print'</div>';  
        }

        /*
        *
        * Display StreamYard settings 
        *
        */

        /**
         * Render the StreamYard encoder settings panel (RTMP URL + stream key + steps). Echoes HTML.
         *
         * @param  string $obs_uri    RTMP server/ingest URI.
         * @param  string $obs_stream RTMP stream key.
         * @return void
         */
        public function wpstream_streamyard_settings($obs_uri,$obs_stream){

            print '<div class="external_software_streaming wpstream_streamyard_settings">';

                // Server URI and stream key, each with a "copy" affordance.
                print '<div class="external_software_streaming_details">';
                    print '<div class="event_list_unit_notificationx"><strong>'.esc_html__('RTMP server URL:').' </strong>';
                    print '<div class="wpstream_live_uri_text">' . $obs_uri.'</div>
                    <div class="copy_live_uri">'.__('copy','wpstream').'</div>';

                    print '<div class="event_list_stream_key_wrap"><strong>'.__('Stream key:').' </strong>
                    <div class="wpstream_live_key_text">'. $obs_stream.'</div><div class="copy_live_key">'.__('copy','wpstream').'</div></div>';
                    print '</div>';
                
                print'</div>';
                
                print ' <div class="wpstream_modal_explanations">';
                  print '<ul>
                  <li>1. Set up your destination by going to your StreamYard account.</li>
                  <li>2. Choose "Custom RTMP" and add the RTMP server URL and Stream key from WpStream.</li>
                  <li>3. Go to "Broadcasts" then "create a broadcast".</li>
                  <li>4. Enter Live Studio.</li>
                  <li>5. Adjust all your preferred settings and Click on "Go Live".</li></ul>';

                print '</div>';           
            
                
            print'</div>';  
        }



        /*
        *
        * Display StreamYard settings 
        *
        */

        /**
         * Render the Restream encoder settings panel (RTMP URL + stream key + steps). Echoes HTML.
         *
         * @param  string $obs_uri    RTMP server/ingest URI.
         * @param  string $obs_stream RTMP stream key.
         * @return void
         */
        public function wpstream_restream_settings($obs_uri,$obs_stream){

            print '<div class="external_software_streaming wpstream_restream_settings">';

                // Server URI and stream key, each with a "copy" affordance.
                print '<div class="external_software_streaming_details">';
                    print '<div class="event_list_unit_notificationx"><strong>'.esc_html__('RTMP URL:').' </strong>';
                    print '<div class="wpstream_live_uri_text">' . $obs_uri.'</div>
                    <div class="copy_live_uri">'.__('copy','wpstream').'</div>';

                    print '<div class="event_list_stream_key_wrap"><strong>'.__('Stream key:').' </strong>
                    <div class="wpstream_live_key_text">'. $obs_stream.'</div><div class="copy_live_key">'.__('copy','wpstream').'</div></div>';
                    print '</div>';
                
                print'</div>';
                
                print ' <div class="wpstream_modal_explanations">';
                  print '<ul>
                  <li>1. Go to your Restream account and set up a destination or channel.</li>
                  <li>2. Choose "Custom RTMP" and add the RTMP URL and Stream Key from WpStream.</li>
                  <li>3. Click on "Add Channel".</li>
                  <li>4. Enter Live Studio.</li>
                  <li>5. Adjust your preferred settings and Go Live.</li></ul>';
                
                print '</div>';           
            
                
            print'</div>';  
        }



        /*
        *
        * Display Xplit settings 
        *
        */


        /**
         * Render the XSplit encoder settings panel (RTMP URL + stream key + steps).
         * Hidden by default (display:none). Echoes HTML.
         *
         * @param  string $obs_uri    RTMP server/ingest URI.
         * @param  string $obs_stream RTMP stream key.
         * @return void
         */
        public function wpstream_xplit_settings($obs_uri,$obs_stream){
            print '<div class="external_software_streaming wpstream_xplit_settings" style="display:none;">';
                // Server URI and stream key, each with a "copy" affordance.
                print '<div class="external_software_streaming_details">';
                    print '<div class="event_list_unit_notificationx"><strong>'.esc_html__('RTMP Url:').' </strong>';
                    print '<div class="wpstream_live_uri_text">' . $obs_uri.'</div>
                    <div class="copy_live_uri">'.__('copy','wpstream').'</div>';

                    print '<div class="event_list_stream_key_wrap"><strong>'.__('Stream Key:').' </strong>
                    <div class="wpstream_live_key_text">'. $obs_stream.'</div><div class="copy_live_key">'.__('copy','wpstream').'</div></div>';
                    print '</div>';
                
                print'</div>';
                
                print ' <div class="wpstream_modal_explanations">';
                    print '<ul>
                    <li>1. Click Broadcast in the XSplit Window and then click "Set up a new output".</li>
                    <li>2. Choose Custom RTMP in the Set up a new output dropdown menu.</li>
                    <li>3. In the URL box, type/paste your RTMP Url.</li>
                    <li>4. In the Stream key, type/paste your Stream key.</li>
                    <li>5. Save changes and click on the "Stream" button in the main window.</li></ul>';
                print '</div>';  

            print '</div>';
        }



        /*
        *
        * Display WireCast settings 
        *
        */


        /**
         * Render the Wirecast encoder settings panel (address + stream + steps).
         * Hidden by default (display:none). Echoes HTML.
         *
         * @param  string $obs_uri    RTMP server/ingest URI (shown as "Address").
         * @param  string $obs_stream RTMP stream key (shown as "Stream").
         * @return void
         */
        public function wpstream_wirecast_settings($obs_uri,$obs_stream){
            print '<div class="external_software_streaming wpstream_wirecast_settings" style="display:none;">';

            // Server address and stream, each with a "copy" affordance.
            print '<div class="external_software_streaming_details">';
                print '<div class="event_list_unit_notificationx"><strong>'.esc_html__('Address:').' </strong>';
                print '<div class="wpstream_live_uri_text">' . $obs_uri.'</div>
                <div class="copy_live_uri">'.__('copy','wpstream').'</div>';

                print '<div class="event_list_stream_key_wrap"><strong>'.__('Stream:').' </strong>
                <div class="wpstream_live_key_text">'. $obs_stream.'</div><div class="copy_live_key">'.__('copy','wpstream').'</div></div>';
                print '</div>';
            
            print'</div>';
            
            print ' <div class="wpstream_modal_explanations">';
                print '<ul>
                <li>1. Click Output on the top of the screen and then Select Output Settings.</li>
                <li>2. In the destination, choose RTMP Server and click OK.</li>
                <li>3. In the Address box, type/paste your Address.</li>
                <li>4. In the Stream box, type/paste your Stream key.</li>
                <li>5. Click on OK to save changes just click the Output on the top of the screen and then Start/Stop Broadcasting.</li></ul>';
            print '</div>';  

            print '</div>';
        }



        /*
        *
        * Display Larix settings 
        *
        */


        /**
         * Render the Larix Broadcaster (mobile) settings panel: a combined RTMP
         * string, a QR code image (populated by JS), and, on mobile, a deep-link
         * button to launch Larix. Hidden by default. Echoes HTML.
         *
         * @param  string $obs_uri    RTMP server/ingest URI.
         * @param  string $obs_stream RTMP stream key.
         * @return void
         */
        public function wpstream_larix_settings($obs_uri,$obs_stream){
            print '<div class="external_software_streaming wpstream_larix_settings" style="display:none;">';
                // Larix takes a single combined RTMP URL (server + key).
                $larix_rtmp=$obs_uri.$obs_stream;
                // RTMP value (filled in by JS) with a "copy" affordance.
                print '<div class="external_software_streaming_details">';
                    print '<div class="event_list_unit_notificationx"><strong>'.esc_html__('RTMP:').'</strong>';
                    print '<div class="wpstream_live_uri_text wpstream_larix_rtmp"></div>
                    <div class="copy_live_uri">'.__('copy','wpstream').'</div>';

                    print '</div>';

                print'</div>';

                // Instructions: scan QR, configure manually, or (mobile only) tap the launch button.
                print ' <div class="wpstream_modal_explanations">';
                    print '<ul>
                    <li>A. Scan the QR code.</li>
                    <li>or</li>
                    <li>B. Manually configure Larix with the above RTMP</li>';
                if(wp_is_mobile() ){
                    print '
                    <li>or</li>
                    <li>C. Click on the button below </li></ul>';
                }


                print '</div>';


                // QR image (src set by JS) and, on mobile, the Larix deep-link button.
                print '<img class="print_qrcode" src="" />';
                if(wp_is_mobile() ){
                    print '<a href="" class="wpstream_start_with_larix_mobile" >Start Streaming with Larix</a>';
                }
               

                
            print '</div>';
        }




        /*
        *
        * Display Wmix settings 
        *
        */


        /**
         * Render the vMix encoder settings panel (URL + stream key + steps).
         * Hidden by default (display:none). Echoes HTML.
         *
         * @param  string $obs_uri    RTMP server/ingest URI.
         * @param  string $obs_stream RTMP stream key.
         * @return void
         */
        public function wpstream_wmix_settings($obs_uri,$obs_stream){
            print '<div class="external_software_streaming wpstream_wimx_settings" style="display:none;">';

            // Server URL and stream key, each with a "copy" affordance.
            print '<div class="external_software_streaming_details">';
                print '<div class="event_list_unit_notificationx"><strong>'.esc_html__('URL:').' </strong>';
                print '<div class="wpstream_live_uri_text">' . $obs_uri.'</div>
                <div class="copy_live_uri">'.__('copy','wpstream').'</div>';

                print '<div class="event_list_stream_key_wrap"><strong>'.__('Stream Key:').' </strong>
                <div class="wpstream_live_key_text">'. $obs_stream.'</div><div class="copy_live_key">'.__('copy','wpstream').'</div></div>';
                print '</div>';
            
            print'</div>';
            
            print ' <div class="wpstream_modal_explanations">';
                print '<ul>
                <li>1. Click on the gear icon near the stream button on the bottom.</li>
                <li>2. Choose a custom RTMP Server in destination.</li>
                <li>3. In the URL box, type/paste your URL.</li>
                <li>4. In the Stream key, type/paste your Stream key.</li>
                <li>5. Save changes. You can start streaming by clicking on the stream button at the bottom of the dashboard.</li></ul>';
            print '</div>';  
           
            print '</div>';
        }


       
        /*
        * Set Settings
        *
        *
        */
        /**
         * Render the WpStream Settings admin screen and persist submitted values.
         *
         * On POST it verifies the nonce, then stores each field as a `wpstream_*`
         * option (with special handling for the streamer role and the per-tab
         * global event options), and flushes rewrite rules. It then builds the
         * tabbed settings definition array and renders the form. Echoes HTML.
         *
         * @return void
         */
        public function wpstream_settings(){

            // Admin-only screen.
            if( !current_user_can('administrator') ){
                die('Only for administrators');
            }


            // Handle a settings submission.
            if($_SERVER['REQUEST_METHOD'] === 'POST'){
                // CSRF protection.
                if(  !wp_verify_nonce($_POST['wpstream-settings-nonce'],'wpstream-settings-nonce') ){
                    die('Security check');
                }

                $allowed_html   =   array();
                $exclude_array  =   array();
                $allowed_html   =   array();



                // If the "channel type" section was shown but no streamer role was chosen, clear the stored role.
                if( isset($_POST['user_streaming_channel_type_hidden']) && intval($_POST['user_streaming_channel_type_hidden'])==1  && !isset($_POST['stream_role'])    ){
                     update_option( sanitize_key('wpstream_stream_role'), '' );
                }

                // Persist every posted field (except the submit button) as a sanitized wpstream_* option.
                foreach($_POST as $variable=>$value){
                    if ($variable!='submit'){
                        if (!in_array($variable, $exclude_array) ){
                            update_option( sanitize_key('wpstream_'.$variable), sanitize_text_field ($value) );
                        }

                        // Streamer role is stored raw (may be an array of roles) rather than sanitized as text.
                        if($variable=='stream_role'){
                           update_option( sanitize_key('wpstream_stream_role'), $value );
                        }

                    }
                }


                // On the "default options" tab, fold the per-option checkboxes into a single 1/0 map.
                if( isset($_GET['tab']) && $_GET['tab']=='default_options' ){
                    $event_settings=array();
                    foreach($this->global_event_options as $key=>$option){
                        $event_settings[$key]='';
                        if(isset($_POST['wpstream_event_set_'.$key]) && $_POST['wpstream_event_set_'.$key]=='on'){
                            $event_settings[$key]=1;
                        }else{
                            $event_settings[$key]=0;
                        }
                    }
                    update_option('wpstream_user_streaming_global_channel_options',$event_settings);
                }




                // reset permalinkgs
                // Slug settings can change permalinks, so drop and rebuild the rewrite rules.
                global $wp_rewrite;

                update_option( "rewrite_rules", FALSE );
                $wp_rewrite->flush_rules( true );

            }

            // Definition of every settings field, keyed and grouped by 'tab' (general, subscription, messages, default options, VOD defaults, support).
            $wpstream_settings_array =array(
                1   =>  array(
                            'tab'       =>  'general_options',
                            'label'     =>  esc_html__('Slug for free video/channel pages ','wpstream'),
                            'name'      =>  'free_media_slug',
                            'type'      =>  'text',
                            'details'   =>  esc_html__('This will replace the default "wpstream" of all your free video/channel urls. Special characters like "&" are not permitted. To have your new slug show up you need to re-save the "Permalinks Settings" under Settings -> Permalinks, even if not making any changes.','wpstream'),
                        ),

                 'free_vod_slug'   =>  array(
                            'tab'       =>  'general_options',
                            'label'     =>  esc_html__('Slug for free VOD pages ','wpstream'),
                            'name'      =>  'free_media_slug_vod',
                            'type'      =>  'text',
                            'details'   =>  esc_html__('This will replace the default "wpstream_vod" of all your free VOD urls. Special characters like "&" are not permitted. To have your new slug show up you need to re-save the "Permalinks Settings" under Settings -> Permalinks, even if not making any changes.','wpstream'),
                        ),
                
                2 => array(
                            'tab'       =>  'general_options',
                            'label'     =>  esc_html__('Non-Admin User Roles Allowed to Broadcast','wpstream'),
                            'name'      =>  'stream_role',
                            'type'      =>  'user_roles',
                            'details'   =>  esc_html__('These types of users can stream via frontend shortcodes / blocks. Single individual channels are automaticlally created for streaming by non-admins.','wpstream'),
                       
                        ),
                3  =>  array(
                            'tab'       =>  'general_options',
                            'label'     =>  esc_html__('Non Admin Streamers Channel Type.','wpstream'),
                            'name'      =>  'user_streaming_channel_type',
                            'type'      =>  'select',
                            'select_values'=>array(
                                'free'  =>  esc_html__('Free Live Channel','wpstream'),
                                'paid'  =>  esc_html__('Pay-Per-View','wpstream')
                            ),
                            'details'   =>  esc_html__('Choose whether the channels assigned to non-admins are free-for-all or pay-per-view (WooCommerce product).','wpstream'),
                        ),
                
                4  =>  array(
                            'tab'       =>  'general_options',
                            'label'     =>  esc_html__('Default Pay-Per-View Price','wpstream'),
                            'name'      =>  'user_streaming_default_price',
                            'type'      =>  'text',
                            'details'   =>  esc_html__('Default price of pay-per-view channels assigned to non-admins.','wpstream'),
                        ),
                
               
                
                6  =>  array(
                            'tab'       =>  'subscription_options',
                            'label'     =>  esc_html__('Use Global Subscription Mode','wpstream'),
                            'name'      =>  'global_sub',
                            'type'      =>  'slidertoogle',
                            'details'   =>  esc_html__('If enabled, a client can access all the media products (live and VOD) by purchasing a single subscription. The "WooCommerce Subscriptions" plugin is required.','wpstream'),
                        ),
                
                7  =>  array(
                            'tab'       =>  'subscription_options',
                            'label'     =>  esc_html__('Subscription ID for Global Subscription Mode','wpstream'),
                            'name'      =>  'global_sub_id',
                            'type'      =>  'text',
                            'details'   =>  esc_html__('ID of the subscription product to be purchased for global access to media. All non-subscription video products that are not already attached to a subscription will be accessible to users that have purchased it.','wpstream'),
                        ),
                8  =>  array(
                            'tab'       =>  'messages_options',
                            'label'     =>  esc_html__('PPV not logged in message','wpstream'),
                            'name'      =>  'product_not_login',
                            'type'      =>  'text',
                            'details'   =>  esc_html__('This message will be displayed on top of the media player for pay-per-view items when user is not logged in.','wpstream'),
                            'default'   =>  esc_html__('You must be logged in to watch this video.','wpstream'),
                        ),
                9  =>  array(
                            'tab'       =>  'messages_options',
                            'label'     =>  esc_html__('PPV not purchased message','wpstream'),
                            'name'      =>  'product_not_bought',
                            'type'      =>  'text',
                            'details'   =>  esc_html__('This message will be displayed on top of the media player for common pay-per-view items that have not been purchased.','wpstream'),
                            'default'   =>  esc_html__('You did not yet purchase this item.','wpstream'),
                        ),
                 10  =>  array(
                            'tab'       =>  'messages_options',
                            'label'     =>  esc_html__('Subscription not purchased message','wpstream'),
                            'name'      =>  'product_not_subscribe',
                            'type'      =>  'text',
                            'details'   =>  esc_html__('This message will be displayed on top of the media player for subscription-type pay-per-view items that have not been purchased.','wpstream'),
                            'default'   =>  esc_html__(' You did not yet subscribe to this item.','wpstream'),
                        ),
                11  =>  array(
                            'tab'       =>  'messages_options',
                            'label'     =>  esc_html__('Thank you message','wpstream'),
                            'name'      =>  'product_thankyou',
                            'type'      =>  'text',
                            'details'   =>  esc_html__('This message will be displayed on the thank you page (after purchase) and the confirmation email.','wpstream'),
                            'default'   =>  esc_html__('Thanks for your purchase. You can access your item at any time by visiting the following page: {item_link}','wpstream'),
                        ),
                
                12  =>  array(
                            'tab'       =>  'messages_options',
                            'label'     =>  esc_html__('Subscription Active message','wpstream'),
                            'name'      =>  'subscription_active',
                            'type'      =>  'text',
                            'details'   =>  esc_html__('This message will be displayed on subscription product page.','wpstream'),
                            'default'   =>  esc_html__('Your Subscription is Active','wpstream'),
                        ),
                13  =>  array(
                    'tab'       =>  'messages_options',
                    'label'     =>  esc_html__('You are not live message','wpstream'),
                    'name'      =>  'you_are_not_live',
                    'type'      =>  'text',
                    'details'   =>  esc_html__('This message will be displayed in player.','wpstream'),
                    'default'   =>  esc_html__('We are not live at this moment','wpstream'),
                ),

                14 =>  array(
                            'tab'       =>  'general_options',
                            'label'     =>  esc_html__('Video player theme','wpstream'),
                            'name'      =>  'video_player_theme',
                            'type'      =>  'select',
                            'select_values'=>array(
                                'default'  =>  esc_html__('Default','wpstream'),
                                'city'  =>  esc_html__('City','wpstream'),
                                'forest'  =>  esc_html__('Forest','wpstream'),
                                'fantasy'  =>  esc_html__('Fantasy','wpstream'),
                                'sea' => esc_html__('Sea','wpstream'),
                            ),
                            'details'   =>  esc_html__('Choose the video player theme to have a different look for the player.','wpstream'),
                        ),
                'wpstream_player_logo' => array(
                        'tab' => 'general_options',
                        'name' => 'player_logo',
                        'label' => esc_html__('Logo for the video player','wpstream'),
                        'type' => 'image',
                        'details' => esc_html__('This logo will be displayed on the the video player.','wpstream'),
                        'default' => '',
                        'image_size' => 'thumbnail',
                ),
                // hide the video player logo opacity for now
//                'wpstream_player_logo_opacity' => array(
//                        'tab' => 'general_options',
//                        'name' => 'player_logo_opacity',
//                        'label' => esc_html__('Opacity of the video player logo','wpstream'),
//                        'type' => 'range',
//                        'details' => esc_html__('Set the opacity of the logo','wpstream'),
//                        'default' => '',
//                        'image_size' => 'thumbnail',
//                ),
                'wpsteram_player_logo_position' => array(
                        'tab' => 'general_options',
                        'name' => 'player_logo_position',
                        'label' => esc_html__('Position of the video player logo','wpstream'),
                        'type' => 'select',
                        'select_values'=>array(
                            'top-left'     =>  esc_html__('Top Left','wpstream'),
                            'top-right'    =>  esc_html__('Top Right','wpstream'),
                            'bottom-left'  =>  esc_html__('Bottom Left','wpstream'),
                            'bottom-right' =>  esc_html__('Bottom Right','wpstream'),
                        ),
                        'details' => esc_html__('Choose the position of the logo on the video player.','wpstream'),
                        'default' => '',
                ),


                
                99  =>  array(
                            'tab'       =>  'default_options',
                            'label'     =>  esc_html__('Events Options  ','wpstream'),
                            'name'      =>  'user_streaming_global_channel_options',
                            'type'      =>  'user_streaming_global_channel_options',
                            'details'   =>  esc_html__('Global Options for live events.','wpstream'),
                        ),

                100  =>  array(
                            'tab'       =>  'default_options_vod',
                            'label'     =>  esc_html__('Autoplay','wpstream'),
                            'name'      =>  'vod_autoplay',
                            'type'      =>  'slidertoogle',
                            'details'   =>  esc_html__('If enabled, video will attempt to start playing automatically. This is only achievable in some browsers.','wpstream'),
                        ),

               
                101  =>  array(
                            'tab'       =>  'default_options_vod',
                            'label'     =>  esc_html__('Start Muted','wpstream'),
                            'name'      =>  'vod_start_muted',
                            'type'      =>  'slidertoogle',
                            'details'   =>  esc_html__('If enabled, video will start muted. This may increase the rate of autoplay in some browsers.','wpstream'),
                        ),
                  
                102  =>  array(
                            'tab'       =>  'default_options_vod',
                            'label'     =>  esc_html__('Lock To Website','wpstream'),
                            'name'      =>  'vod_domain_lock',
                            'type'      =>  'slidertoogle',
                            'details'   =>sprintf ( esc_html__('If enabled, video will only display on %1$s, otherwise it can show up on any website.','wpstream'),get_bloginfo('wpurl') ),
                        ),
                        
                103  =>  array(
                    'tab'       =>  'default_options_vod',
                    'label'     =>  esc_html__('Encrypt Video','wpstream'),
                    'name'      =>  'vod_encrypt',
                    'type'      =>  'slidertoogle',
                    'details'   => esc_html__('If enabled, video data will be encrypted. Enabling encryption may lead to reduced website performance under certain configurations. Encrypted video may not display in all browsers.','wpstream'),
                ),
                      
                /*  'vod_domain_lock'    =>array(
                            'name'      =>  esc_html__('Video On Demand - Lock To Website','wpstream'),
                            'details'   =>  sprintf ( esc_html__('If enabled, VODS will only display on %1$s,  otherwise they can show up on any website.','wpstream'),get_bloginfo('wpurl') ),
                            'defaults'  =>  'no',
                        ),
                'vod_encrypt'   =>array(
                    'name'      =>  esc_html__('Encrypt Video on Demand','wpstream'),
                    'details'   =>  esc_html__('If enabled, video data will be encrypted. Enabling encryption may lead to reduced website performance under certain configurations. Encrypted video may not display in all browsers.','wpstream'),
                    'defaults'  =>  'no',
                ),*/

                104 => array(
                        'tab' => 'support_tab',
                        'label' => esc_html__('Logs','wpstream'),
                        'name' => 'logs',
                        'type' => 'logs_table',
                        'details' => esc_html__('This is the error log of the plugin.','wpstream'),
                )
                        
            );

                // Determine the active tab (defaults to General Options).
                $active_tab = 'general_options';
                if( isset( $_GET[ 'tab' ] ) ) {
                    $active_tab = $_GET[ 'tab' ];
                }


                // Open the settings panel and the form.
                print '<div class="theme_options_tab_wpstream" style="display:block;" >
                    <h1>'.__('WpStream Settings','wpstream').'</h1>
                    <form method="post" action="" >';

                // Tab navigation bar; the current tab gets the nav-tab-active class.
                print '<h2 class="nav-tab-wrapper">
                    <a href="?page=wpstream_settings&tab=general_options"       class="nav-tab '; echo $active_tab == 'general_options' ? 'nav-tab-active' : '';     echo '">'.esc_html__('General Options','wpstream').'</a>
                    <a href="?page=wpstream_settings&tab=default_options"       class="nav-tab '; echo $active_tab == 'default_options' ? 'nav-tab-active' : '';     echo '">'.esc_html__('Default Channel Settings','wpstream').'</a>
                    <a href="?page=wpstream_settings&tab=default_options_vod"   class="nav-tab '; echo $active_tab == 'default_options_vod' ? 'nav-tab-active' : ''; echo '">'.esc_html__('VOD Settings','wpstream').'</a>
                    <a href="?page=wpstream_settings&tab=subscription_options"  class="nav-tab '; echo $active_tab == 'subscription_options' ? 'nav-tab-active' : '';echo '">'.esc_html__('Subscription Options','wpstream').'</a>
                    <a href="?page=wpstream_settings&tab=messages_options"      class="nav-tab '; echo $active_tab == 'messages_options' ? 'nav-tab-active' : '';    echo '">'.esc_html__('Customize Messages','wpstream').'</a>
                    <a href="?page=wpstream_settings&tab=support_tab"           class="nav-tab '; echo $active_tab == 'support_tab' ? 'nav-tab-active' : '';         echo '">'.esc_html__('Support','wpstream').'</a>
                </h2>';
                $help_link='';

                // Basic-stream mode disables/annotates some default-channel controls.
                $is_basic_stream_mode = $this->wpstream_is_basic_streaming_mode();
                print '<div class="wpstream_option_wrapper">';

                                // Pick the contextual "Video Help" docs link for the active tab.
                                switch ($active_tab) {
                                    case 'general_options':
                                        $help_link='https://docs.wpstream.net/docs/general-settings/';
                                        break;
                                    case 'default_options':
                                        $help_link='https://docs.wpstream.net/docs/default-channel-settings/';
                                        break;
                                    case 'default_options_vod':
                                        $help_link='https://docs.wpstream.net/docs/vod-settings/';
                                        break;
                                    case 'subscription_options':
                                        $help_link='https://docs.wpstream.net/docs/subscription-options/';
                                        break;
                                    case 'messages_options':
                                        $help_link='https://docs.wpstream.net/docs/customize-messages/';
                                        break;
                                }

                                print '<div class="options_wrapper">';
                                // Render each field that belongs to the active tab.
                                foreach ($wpstream_settings_array as $key=>$option){
                                   // Skip fields from other tabs.
                                   if($option['tab']!=$active_tab){
                                       continue;
                                   }

                                   // Intro blurb (and basic-mode notice) shown above the global channel options control.
                                   if ( $option['type']=='user_streaming_global_channel_options' ) {
                                       print '<div class="default-channel-settings-info">';
                                       print esc_html__( 'These settings will apply to newly created channels; existing channels will not change settings if you change them here', 'wpstream');
                                       if ( $is_basic_stream_mode ) {
                                           $this->wpstream_basic_stream_mode_message();
                                       }
                                       print '</div>';

                                   }
                                   print '<div class="wpstream_option">';
                                            // Load this option's stored value.
                                            $options_value =   get_option('wpstream_'.$option['name']) ;


                                           // Render the appropriate control for this field's type.
                                           switch( $option['type'] ) {
                                            // Multi-select of WordPress user roles allowed to broadcast.
                                            case 'user_roles':
                                                print '<label for="'.$option['name'].'">'.$option['label'].'</label>';
                                                print $this->wpstream_select_user_roles($option['name'],$options_value);
                                                print '<div class="settings_details">'.$option['details'].'</div>';
                                                break;
                                            // The grid of default per-event streaming options.
                                            case 'user_streaming_global_channel_options':
                                                $exclude_array=array();
												$this->user_streaming_global_channel_options(
													$option['name'],
													$options_value,
													$exclude_array,
													$is_basic_stream_mode
												);
                                                break;
                                            // Plain text input; falls back to the field's default when unset.
                                            case 'text':
                                                if($options_value==''){
                                                    $options_value='';
                                                    if(isset($option['default'])){
                                                        $options_value=$option['default'];
                                                    }
                                                }

                                                print '<label for="'.$option['name'].'">'.$option['label'].'</label>';
                                                print '<input class="wpstream-text-input-setting" id="'.$option['name'].'" type="'.$option['type'].'" size="36"  name="'.$option['name'].'" value="'.esc_attr($options_value).'" />';
                                                print '<div class="settings_details">'.$option['details'].'</div>';
                                                break;
                                            // Dropdown built from the field's select_values map; a hidden mirror marks the field as present.
                                            case 'select':
                                                print '<label for="'.$option['name'].'">'.$option['label'].'</label>';
                                                print '<select id="'.$option['name'].'"  name="'.$option['name'].'"  >';
                                                    foreach($option['select_values'] as $key=>$value){
                                                        print '<option value="'.$key.'" ';
                                                        // Pre-select the currently stored value.
                                                        if( $key == esc_html($options_value) ){
                                                            print ' selected ';
                                                        }
                                                        print '>'.$value.'</option>';
                                                    }
                                                print '</select>';
                                                print '<input type="hidden" name="'.$option['name'].'_hidden" value="1" >';
                                                print '<div class="settings_details">'.$option['details'].'</div>';
                                                break;
                                            // On/off switch; a hidden 0-valued twin ensures "off" posts a value.
                                            case 'slidertoogle':
                                                print '<label for="'.$option['name'].'">'.$option['label'].'</label>';
                                                print '<div style="display: flex; gap: 25px; justify-content: space-between;">';
                                                print '<div class="settings_details">'.$option['details'].'</div>';
                                                print '<label class="wpstream_switch">
                                                      <input type="hidden" class="wpstream_event_option_itemc" value="0" name="'.$option['name'].'" >
                                                      <input type="checkbox" class="wpstream_event_option_itemc" value="1" name="'.$option['name'].'" ';
                                                if( intval($options_value) !==0 ){
                                                    print ' checked ';
                                                }
                                                print '> <span class="wpstream_slider round"></span>';
                                                print '</label>';
                                                print '</div>';
                                                break;
                                            // Media-library image picker with preview + upload/remove buttons.
                                            case 'image':
                                                $image_url = $options_value ? esc_url($options_value) : '';
                                                $has_image = !empty($image_url);

                                                print '<label for="' . $option['name'] . '">' . $option['label'] . '</label>';
                                                print '<div class="wpstream-image-upload-wrapper">';
                                                print '<input type="hidden" id="' . $option['name'] . '" name="' . $option['name'] . '" value="' . $image_url . '" />';

                                                // Preview area
                                                print '<div class="wpstream-image-preview" style="' . (!$has_image ? 'display:none;' : '') . '">';
                                                print '<img src="' . $image_url . '" alt="Preview" />';
                                                print '</div>';

                                                // Upload/remove buttons
                                                print '<div class="wpstream-image-upload-buttons">';
                                                print '<button type="button" class="wpstream-upload-image button">' . esc_html__('Upload Image', 'wpstream') . '</button>';
                                                print '<button type="button" class="wpstream-remove-image button" style="' . (!$has_image ? 'display:none;' : '') . '">' . esc_html__('Remove Image', 'wpstream') . '</button>';
                                                print '</div>';

                                                print '</div>';
                                                print '<div class="settings_details">' . $option['details'] . '</div>';
                                                break;
                                            // 0-100 range slider.
                                            case 'range':
                                                print '<label for="'.$option['name'].'">'.$option['label'].'</label>';
                                                print '<input class="wpstream-range-input" type="range" id="'.$option['name'].'" name="'.$option['name'].'" min="0" max="100" step="10" value="'.esc_attr($options_value).'" />';
                                                print '<div class="settings_details">'.$option['details'].'</div>';
                                                break;
                                            // Delegates to the Support tab renderer (plugin error log table).
                                            case 'logs_table':
                                                $this->wpstream_support_tab();
                                                break;
                                        }
                                   print '</div>';
                               }
                                print '</div>'; // options wrapper
                                // Contextual help link for tabs that have one.
                                if($help_link!==''){
                                    print '<div class="wpstream_options_help"><a href="'.esc_url($help_link).'" target="_blank" >'.esc_html__('Video Help','wpstream').'</a></div>';
                                }
                           print '</div>';


                                // Save button (hidden on the read-only Support tab).
                                if ( $active_tab != 'support_tab') {
                        print '<div class="wpstream-save-settings">';
                       print '<input type="submit" name="submit"  class="wpstream_button wpstream_button_action" value="'.__('Save Changes','wpstream').'" />';
                       print '<div class="spinner"></div>';
                       print '</div>';
                       }

                    // CSRF nonce for the submission handled at the top of this method.
                    print  '<input id="wpstream-settings-nonce" name="wpstream-settings-nonce" type="hidden" value="'.wp_create_nonce('wpstream-settings-nonce').'" /> ';
            print   '</form>';
        print '</div>';

         }

    /**
     * Get system information for support tab
     *
     * @return array System information
     */
    private function get_system_info() {
        global $wp_version;

        // Environment facts: PHP/WP version, debug flag, memory limit and the plugin version.
        $php_version = phpversion();
        $wp_version_info = $wp_version;
        $site_debug_mode = (defined('WP_DEBUG') && WP_DEBUG);
        $wp_memory_limit = WP_MEMORY_LIMIT;

        $wpstream_version = WPSTREAM_PLUGIN_VERSION;
        $wpstream_plugin_outdated = false;

        // Check if plugin is outdated
        // Mark the plugin outdated if WP's update transient lists an available update for it.
        $update_plugins = get_site_transient('update_plugins');
        if (isset($update_plugins->response['plugin/wpstream.php'])) {
            $wpstream_plugin_outdated = true;
        }

        // Check API status
        // API is "connected" when a WpStream cloud token is present.
        $api_status = false;
        if (method_exists($this->main->wpstream_live_connection, 'wpstream_get_token')) {
            $token = $this->main->wpstream_live_connection->wpstream_get_token();
            $api_status = !empty($token);
        }

        // Return the collected diagnostics for the Support tab to render.
        return array(
            'php_version' => $php_version,
            'wp_version' => $wp_version_info,
            'site_debug_mode' => $site_debug_mode,
            'wp_memory_limit' => $wp_memory_limit,
            'wpstream_version' => $wpstream_version,
            'wpstream_plugin_outdated' => $wpstream_plugin_outdated,
            'api_status' => $api_status
        );
    }

    /**
     * Render system information HTML
     *
     * Prints the Support-tab diagnostics table (PHP/WP versions, debug mode,
     * memory limit, plugin version + update button, API connection) with a
     * warning/OK dashicon per row. Echoes HTML.
     *
     * @return void
     */
    private function render_system_info() {
        // Pull the current environment diagnostics.
        $system_info = $this->get_system_info();
        // Below: diagnostics table; each row shows a value and a warning/OK indicator based on recommended thresholds.
        ?>

        <div class="wpstream-system-info">
            <h3><?php esc_html_e('System Information', 'wpstream'); ?></h3>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><strong><?php esc_html_e('PHP Version', 'wpstream'); ?></strong></td>
                        <td><?php echo esc_html($system_info['php_version']); ?></td>
                        <td>
                            <?php if (version_compare($system_info['php_version'], '7.4', '<')): ?>
                                <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                                <?php esc_html_e('We recommend PHP 7.4 or higher', 'wpstream'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('WordPress Version', 'wpstream'); ?></strong></td>
                        <td><?php echo esc_html($system_info['wp_version']); ?></td>
                        <td>
                            <?php if (version_compare($system_info['wp_version'], '5.6', '<')): ?>
                                <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                                <?php esc_html_e('We recommend WordPress 5.6 or higher', 'wpstream'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('WP Debug Mode', 'wpstream'); ?></strong></td>
                        <td><?php echo $system_info['site_debug_mode'] ? esc_html__('Enabled', 'wpstream') : esc_html__('Disabled', 'wpstream'); ?></td>
                        <td>
                            <?php if ($system_info['site_debug_mode']): ?>
                                <span class="dashicons dashicons-info" style="color: #00a0d2;"></span>
                                <?php esc_html_e('Debug mode should be disabled on production sites', 'wpstream'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('WP Memory Limit', 'wpstream'); ?></strong></td>
                        <td><?php echo esc_html($system_info['wp_memory_limit']); ?></td>
                        <td>
                            <?php
                            $memory_limit = wp_convert_hr_to_bytes($system_info['wp_memory_limit']);
                            if ($memory_limit < 64 * 1024 * 1024): // 64MB
                            ?>
                                <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                                <?php esc_html_e('We recommend at least 64MB', 'wpstream'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('WpStream Version', 'wpstream'); ?></strong></td>
                        <td><?php echo esc_html($system_info['wpstream_version']); ?></td>
                        <td style="display: flex; align-items: center; gap: 5px;">
                            <?php if ($system_info['wpstream_plugin_outdated']): ?>
                                <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                                <?php esc_html_e('Update available', 'wpstream'); ?>
                                <div class="update-button-wrapper">
                                    <button class="wpstream-update-plugin-button button button-primary" data-plugin="wpstream/wpstream.php">
                                        <?php esc_html_e('Update Now', 'wpstream'); ?>
                                    </button>
                                </div>
                            <?php else: ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('API Connection', 'wpstream'); ?></strong></td>
                        <td><?php echo $system_info['api_status'] ? esc_html__('Connected', 'wpstream') : esc_html__('Disconnected', 'wpstream'); ?></td>
                        <td>
                            <?php if (!$system_info['api_status']): ?>
                                <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                                <?php esc_html_e('API connection issue', 'wpstream'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render support tab content
     *
     * Prints the Support tab: the system-info table, a table of relevant active
     * plugins with update tooltips, and a table of the plugin's recent logs.
     * Echoes HTML.
     *
     * @return void
     */
    public function wpstream_support_tab() {
        // Below: Support tab markup (system info, active plugins table, recent logs table).
        ?>
        <div class="wrap">
            <div class="wpstream-support-tab-root">
                <?php $this->render_system_info(); ?>

                <div class="wpstream-plugins-table-container">
                    <h3><?php esc_html_e('Active Plugins', 'wpstream'); ?></h3>
                    <table class="widefat wpstream-plugins-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Plugin', 'wpstream'); ?></th>
                                <th><?php esc_html_e('Version', 'wpstream'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Build the relevant-plugins list; show a placeholder row when none are found.
                            $plugins_data = $this->wpstream_get_plugins_data();
                            if (empty($plugins_data)) {
                                echo '<tr><td colspan="3">' . esc_html__('No WPStream plugins found.', 'wpstream') . '</td></tr>';
                            } else {
                                // One row per plugin; append an "update available" tooltip when a newer version exists.
                                foreach ($plugins_data as $plugin) {
                                    echo '<tr>';
                                    echo '<td>' . esc_html($plugin['name']) . '</td>';
                                    echo '<td>';
                                    echo esc_html($plugin['version']);
                                    if ( isset($plugin['new_version']) ) {
                                        echo '<div class="wpstream-tooltip-container">';
                                        echo '<span class="dashicons dashicons-info wpstream-tooltip" title="' . esc_attr($plugin['new_version']) . '">';
                                        echo '</span>';
                                        echo '<div class="wpstream-custom-tooltip">' . sprintf(
                                            esc_html__('A new version is available: %s', 'wpstream'),
                                            esc_html($plugin['new_version'])
                                        ) . '</div>';
                                        echo '</div>';
                                    }
                                    echo  '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <div class="wpstream-logs-table-container">
                    <h3><?php esc_html_e('Recent Logs', 'wpstream'); ?></h3>
                    <table class="widefat wpstream-logs-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Time', 'wpstream'); ?></th>
                                <th><?php esc_html_e('Type', 'wpstream'); ?></th>
                                <th><?php esc_html_e('Description', 'wpstream'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Load the stored plugin logs; show a placeholder row when empty.
                            $logs = get_option('wpstream_logs');
                            if ( !is_array($logs) || empty($logs) ) {
                                echo '<tr><td colspan="3">' . esc_html__('No logs found.', 'wpstream') . '</td></tr>';
                            } else {
                                // One row per log entry (time, type, description).
                                foreach ($logs as $log) {
                                    echo '<tr>';
                                    echo '<td>' . esc_html(date('Y-m-d H:i:s', $log['timestamp'] ) ) . '</td>';
                                    echo '<td>' . esc_html($log['type']) . '</td>';
                                    echo '<td>' . esc_html($log['description']) . '</td>';
                                    echo '</tr>';
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
	 * Get plugins data.
	 *
	 * Collects name/version/active/update info for a fixed allow-list of plugins
	 * relevant to WpStream (WpStream, WooCommerce, Meta Box, One Click Demo
	 * Import, Better Messages), used by the Support tab.
	 *
	 * @return array List of plugin info arrays (name, version, path, active, needs_update, [new_version]).
	 */
	public function wpstream_get_plugins_data() {
		// Ensure get_plugins() is available in non-admin contexts.
		if (!function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Get all installed plugins
		$all_plugins = get_plugins();
		$update_data = get_site_transient('update_plugins');
		$all_plugins_info = [];

		// We want to get data only for the WpStream plugins
		$wpstream_plugins = array(
			'WpStream'      => array(
				'path' => 'wpstream/wpstream.php',
			),
			'WooCommerce' => array(
				'path' => 'woocommerce/woocommerce.php',
			),
			'Meta Box' => array(
				'path' => 'meta-box/meta-box.php',
			),
			'One Click Demo Import' => array(
				'path' => 'one-click-demo-import/one-click-demo-import.php',
			),
			'Better Messages' => array(
				'path' => 'bp-better-messages/bp-better-messages.php',
			),

		);

		// Build an info array for every installed plugin (filtered down afterwards).
		foreach ($all_plugins as $plugin_path => $plugin_data) {
			$is_active = is_plugin_active( $plugin_path );
			$has_update = isset( $update_data->response[$plugin_path] );

			$plugin_info = [
				'name' => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
				'path' => $plugin_path,
				'active' => $is_active ? 'Yes' : 'No',
				'needs_update' => $has_update ? 'Yes' : 'No'
			];

			// Record the offered version when an update is pending.
			if ($has_update) {
				$plugin_info['new_version'] = $update_data->response[$plugin_path]->new_version;
			}

			$all_plugins_info[] = $plugin_info;
		}

		// Filter out the elements from $all_plugins_info that are not in $wpstream_plugins
		foreach ( $all_plugins_info as $key => $plugin_info ) {
			// compare $plugin_info['path'] against the path property on each $wpstream_plugins element item
			// if the path is not in $wpstream_plugins, unset the element
			if ( !in_array( $plugin_info['path'], array_column( $wpstream_plugins, 'path' ) ) ) {
				unset( $all_plugins_info[$key] );
			}
		}

		return $all_plugins_info;
	}

    /**
     * Print a dismissible admin notice when a WpStream plugin update is pending.
     *
     * @return void
     */
    public function wpstream_render_outdated_plugin_notice() {
        // Only render when WP's update transient lists an update for the plugin.
        $has_update = get_site_transient('update_plugins');
        if (isset($has_update->response['plugin/wpstream.php'])) {
            // Below: warning notice linking to the WP updates page.
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php
                    printf(
                        /* translators: 1: Link to update page */
                        esc_html__('A new version of WpStream is available. Please update to the latest version for the best experience. Go to the %1$s to update now.', 'wpstream'),
                        '<a href="' . esc_url(admin_url('update-core.php')) . '">' . esc_html__('updates page', 'wpstream') . '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }


        /**
         * Set user roles
         *
         * Render the grid of per-event streaming option switches (from
         * $global_event_options). Reused for both the site-wide default settings
         * and the per-channel settings modal. Echoes HTML.
         *
         * @param  string $name                 Field name prefix/context (unused in body; kept for callers).
         * @param  array|string $value          Stored 1/0 map of option states (empty falls back to defaults).
         * @param  array|string $local_array    Option keys to EXCLUDE from this render (per-channel context).
         * @param  bool   $disabled             True to render the switches disabled.
         * @param  bool   $is_basic_stream_mode True to disable switches for basic-streaming accounts.
         * @return void
         */
		public function user_streaming_global_channel_options(
			$name,
			$value,
			$local_array='',
			$disabled = false,
			$is_basic_stream_mode = false
	) {

            // Render one switch per defined global event option.
            foreach($this->global_event_options as $key=>$option){

                // Skip options listed in the exclude array (used by the per-channel modal).
                if(  is_array($local_array) && !in_array($key,$local_array)){
                    print '<div class="wpstream_setting_event_unit_wrapper wpstream-setting-'.esc_attr($key).' ">';

                    print '<label for="'.$option['name'].'">'.$option['name'].'</label>';

                    print '<div style="display: flex; gap: 25px; justify-content: space-between;">';
                    print '<div class="settings_details">'.$option['details'].'</div>';
                    print '
                    <label class="wpstream_switch">
                      <input type="checkbox" class="wpstream_event_option_item" data-attr-ajaxname="'.esc_attr($key).'" name="wpstream_event_set_'.esc_attr($key).'" ';
                        // Check state: use the stored value if present, otherwise the option's default.
                        if( isset($value[$key]) ){
                            if( intval($value[$key]) !==0 ){
                                print ' checked ';
                            }
                        }else{
                            if($option['defaults']=='yes') {
                                print ' checked ';
                            }
                        }
                        // Disable the switch when requested or in basic-streaming mode.
                        if ( $disabled || $is_basic_stream_mode ) {
                            print ' disabled ';
                        }


                    print '> <span class="wpstream_slider round"></span>';
                    print '</label>';
                    print '</div>';


                print '</div>';
                }
            }


         }
         

       
      



         
         
        /*
         * Set user roles
         *
         * @since    3.0.1
         */
        /**
         * Build a multi-select of editable user roles (administrator excluded)
         * for the "roles allowed to broadcast" setting.
         *
         * @param  string $name  Field name (rendered as name="{name}[]").
         * @param  array|string $value Currently selected role keys ('' becomes an empty array).
         * @return string HTML <select> markup.
         */
        public function wpstream_select_user_roles($name,$value){
            // Normalize an empty stored value to an array for in_array() below.
            if($value==''){
                $value=array();
            }

            // All editable roles minus administrator (admins always can broadcast).
            $roles  =   get_editable_roles();
            $return =   '<select id="wpstream_user_roles" name="'.esc_html($name).'[]"  multiple>';
            unset( $roles['administrator'] );

            // One option per role, pre-selecting those already chosen.
            foreach ($roles as $key=>$role){
                $return .= '<option value="'.$key.'" ';
                if( in_array($key, $value) ){
                    $return .= ' selected ';
                }
                $return .= '>'.$role['name'].'</option>';
            }
            $return .=  '</select>';


            return $return;
        }
         
            
        /*
         * Set credential admin function
         *
         * @since    3.0.1
        */
        /**
         * Render the Credentials admin screen and save submitted API credentials.
         *
         * On POST it stores the WpStream username/password options and clears the
         * cached token/quota transients so the next request re-authenticates. It
         * then shows the connection status and the credentials form. Echoes HTML.
         *
         * @return void
         */
        public function wpstream_set_wpstream_credentials(){

            // Handle a credentials submission.
            if($_SERVER['REQUEST_METHOD'] === 'POST'){
                $allowed_html   =   array();
                $exclude_array  =   array();
                $allowed_html   =   array();

                // Persist only the recognised credential fields.
                foreach($_POST as $variable=>$value){
                    if ($variable!='submit'){
                        if (!in_array($variable, $exclude_array) ){
                            switch ( $variable ) {
                                case 'api_username':
                                    // Username is sanitized as text.
                                    update_option( sanitize_key('wpstream_api_username'), sanitize_text_field($value) );
                                    break;
                                case 'api_password':
                                    // Password stored raw (may contain characters sanitization would strip).
                                    update_option( sanitize_key('wpstream_api_password'), $value );
                                    break;
                            }
                        }
                    }
                }



                // Invalidate any cached token/quota so the new credentials are used immediately.
                update_option('wp_estate_token_expire',0);
                update_option('wp_estate_curent_token',' ');
                delete_transient( 'wpstream_token_api');
                delete_transient('wpstream_token_request_30');
                delete_transient('wpstream_request_pack_data_per_user_transient');
            }
       
    
            $allowed_html   =   array();


            // Field definitions for the credentials form (username + password).
            $wpstream_options_array =array(
                2   =>  array(
                            'label' =>  'WpStream.net Username or Email',
                            'name'  =>  'api_username',
                            'type'  =>  'text',
                        ),
                3   =>  array(
                            'label' =>  'WpStream.net Password',
                            'name'  =>  'api_password',
                            'type'  =>  'password',
                        ),

            );


            // Current auth token and quota data drive the connection-status banner below.
            $token        = $this->main->wpstream_live_connection->wpstream_get_token();
            $pack_details = $this->main->quota_manager->get_live_quota_data( 'wpstream_set_wpstream_credentials' );

            $this->main->show_user_data($pack_details);

            print   '<form method="post" action="" >';
                        print '<div  class="theme_options_tab_wpstream" style="display:block;" >
                                <h1>'.__('WpStream Credentials','wpstream').'</h1>';

                                // Connection status banner: no credentials, bad credentials, connected, or CURL failure.
                                if( get_option('wpstream_api_username')=='' ||  get_option('wpstream_api_password')== '' ){
                                    echo '<div class="api_not_conected wpstream_orange">';
                                        $admin_url_onboard=get_admin_url().'admin.php?page=wpstream_onboard';
                                        printf ( __('To connect your plugin, enter your WpStream credentials below or go <a href="%s" target="_blank">here</a> to create an account.','wpstream'),$admin_url_onboard);
                                    echo '</div>';

                                }else if($token==''){
                                    $text = get_option('wpstream_curl_failed') === "0" ?
                                        ' Incorrect username or password. Please check your credentials or go <a href="https://wpstream.net/my-account/edit-account/" target="_blank">here</a> to reset your password.' :
                                        'Not connected to WpStream. Please note the errors above and contact support.';
                                    echo '<div class="api_not_conected">'.__($text,'wpstream').'</div>';
                                }else if( $this->main->wpstream_live_connection->wpstream_client_check_api_status() ){
                                    echo '<div class="api_conected">'.__('Connected to WpStream.net!','wpstream').'</div>';
                                }else{
                                    echo '<div class="api_not_conected wpstream_brown">'.__('Failed to connect to WpStream.net. Please address CURL connectivity with your hosting provider.','wpstream').'</div>';
                                }
                                // Render each credential input pre-filled with its stored value.
                                print '<div class="wpstream_option_wrapper">';
                                    foreach ($wpstream_options_array as $key=>$option){
                                        print '<div class="wpstream_option">';

                                            $options_value =  esc_html( get_option('wpstream_'.$option['name'],'') );
                                            print '<label for="'.$option['name'].'">'.$option['label'].'</label>';
                                            print '<input id="'.$option['name'].'" type="'.$option['type'].'" size="36"  name="'.$option['name'].'" value="'.esc_html($options_value).'" />';

                                        print '</div>';
                                    }
                                print '</div>';


                            print '<input type="submit" name="submit"  class="wpstream_button wpstream_button_action" value="'.__('Save Changes','wpstream').'" />';

                            print '<h3>Video Tutorials</h3>';
                 
                            print '<a class="how_to_videos" target="_blank" href="https://youtu.be/9DQrxsKcpmQ">How to Live Stream to WordPress with OBS</a>';
                            print '<a class="how_to_videos" target="_blank" href="https://youtu.be/qMSjJCskAfM">How to Live Stream to WordPress in less than 3 Minutes</a>';                            
                            print '<a class="how_to_videos" target="_blank" href="https://youtu.be/h6myD_vhKcg">How to Live-Stream to WordPress using your iPhone</a>';
                            
                            print '<a style="margin-top:10px;" href="https://www.youtube.com/channel/UCIjItiJc4Z7aJApj3W6ArJA" target="_blank" class="how_to_videos">More Tutorials On Our YouTube Channel</a>';
                            

                        print '</div>';
            print   '</form>';

            // Quick-action links: create free / paid channel, or jump to the channels list.
            print '<div  class="theme_options_tab_wpstream" style="display:block;" >';
                $link_new = admin_url('admin.php?page=wpstream_live_channels');
                $link_new_paid = admin_url('post-new.php?post_type=product').'&new_stream='. rawurlencode('new');
                $link_new_free = admin_url('post-new.php?post_type=wpstream_product');


                print '<a href="'.esc_url($link_new_free).'" class="wpstream_no_chanel_add_channel">'.esc_html__('Create new Free-To-View channel','wpstream').'</a>';
                print '<a href="'.esc_url($link_new_paid).'" class="wpstream_no_chanel_add_channel">'.esc_html__('Create Pay-Per-View channel','wpstream').'</a>';
                print '<a href="'.esc_url($link_new).'"      class="wpstream_no_chanel_add_channel">'.esc_html('My Channels','wpstream').'</a>';        
            print '</div>';
   

    }


  
        /**
        * Media Management
        *
        * Render the Recordings admin screen: the quota summary, the upload widget,
        * and the list of existing recordings. Echoes HTML.
        *
        * @since  3.0.1
        * @return void
        */
        public function wpstream_media_management(){
            // Storage/streaming quota data for the summary header.
            $pack_details           =    $this->main->quota_manager->get_live_quota_data( 'wpstream_media_management' );

            $this->main->show_user_data($pack_details);


            // Upload widget section.
            print '<div id="wpstream_media_upload"><h3>'.__('Upload New Recording','wpstream').'</h3>'.$this->wpstream_present_media_upload().'</div>';

            // Existing recordings list section.
            print '<div id="wpstream_file_management"><h3 id="video_management_title">'.__('Your Recordings','wpstream').'</h3>'.$this->wpstream_present_file_management().'</div>';


        }


        
        
        /**
         * 
         * 
        * WpStream Pagination
        *
        * @since    3.0.1
            * 
            * 
        */ 
        
        /**
         * Build a numeric pager (first/prev/window/next/last) for list screens.
         *
         * @param  int $pages Total number of pages.
         * @param  int $range How many page links to show on each side of the current page.
         * @return string Pager HTML, or '' when there is a single page / no pages.
         */
        public function wpstream_pagination($pages , $range = 2) {
            $return='';
            // Total visible window width around the current page.
            $showitems = ($range * 2) + 1;
            // Current page from the query string (defaults to 1).
            $paged        =   ( isset( $_GET['paged'] ) ) ? intval($_GET['paged']) : 1;


            // Only render a pager when there is more than one page.
            if (1 != $pages && $pages != 0) {
                $return.= '<ul class="pagination wpstream_pagination">';
                // "Previous" arrow.
                $return.= "<li class=\"roundleft\"><a href='" . get_pagenum_link($paged - 1) . "'><</a></li>";

                $last_page = get_pagenum_link($pages);
                // Emit page-number links, but only those within the visible window (or all if they fit).
                for ($i = 1; $i <= $pages; $i++) {
                    if (1 != $pages && (!($i >= $paged + $range + 1 || $i <= $paged - $range - 1) || $pages <= $showitems )) {
                        if ($paged == $i) {
                            // Current page marker.
                            $return.=  '<li class="active"><a href="' . esc_url(get_pagenum_link($i)) . '" >' . $i . '</a><li>';
                        } else {
                            $return.=  '<li><a href="' . esc_url(get_pagenum_link($i)) . '" >' . $i . '</a><li>';
                        }
                    }
                }

                // "Next" target, clamped to the last page.
                $prev_page = get_pagenum_link($paged + 1);
                if (($paged + 1) > $pages) {
                    $prev_page = get_pagenum_link($paged);
                } else {
                    $prev_page = get_pagenum_link($paged + 1);
                }


                // "Next" and "Last" arrows, then close the list.
                $return.=  "<li class=\"roundright\"><a href='" . $prev_page . "'>></a><li>";
                $return.=  "<li class=\"roundright\"><a href='" . $last_page . "'>>><li>";
                $return.=  "</ul>";
            }
            return $return;
        }
        
        
        
        
        
  
        /**
         * Media upload
         *
         * Build the S3 direct-upload widget for new recordings: checks storage
         * quota and API connectivity, then renders a multipart form pre-populated
         * with the signed S3 upload fields.
         *
         * @since  3.0.1
         * @return string Upload widget HTML, or an alert/notice when unavailable.
         */
        public function wpstream_present_media_upload(){
            $to_return='';

            // Refuse when the account is out of storage/data quota.
            if ( ! $this->main->quota_manager->has_storage_quota( null, 'recordings_screen' ) ) {
                return '<div class="wpstream_upload_alert">'.esc_html__('You don\'t have enough cloud storage or data to upload a new item. Please delete some videos or upgrade your plan.','wpstream').'</div>';
            }

            // Request the signed S3 POST fields from the cloud API.
            $formInputs=$this->main->wpstream_live_connection->wpstream_get_signed_form_upload_data();

            // On failure, show either a "not connected" or an "out of quota" message.
            if( !$formInputs['success'] ){
                if ($formInputs['error'] == 'not_connected'){
                    $to_return.='<div class="wpstream_upload_container">'.esc_html__('Not connected. Please connect to WpStream to upload videos.','wpstream').'</div>';
                }
                else {
                    $to_return.='<div class="wpstream_upload_alert">'.esc_html__('You don\'t have enough cloud storage and data to upload a new item. Please delete some videos or upgrade your plan.','wpstream').'</div>';
                }
                return $to_return;
            }

            // Signed data obtained: render the direct-to-S3 upload form.
            if($formInputs['success'] ===true){

                  

                    $to_return.='<div class="wpstream_upload_container">';
                    $to_return.='<div id="wpstream_uploaded_mes">'.esc_html__('Please select or drop a video file. Do not close this window during the upload!','wpstream').'</div>';
                    $to_return.='<form action="https://wpstream-video.s3.amazonaws.com/"
                                  method="POST"
                                  enctype="multipart/form-data"
                                  data-singleFileUploads="true"
                                  data-limitMultiFileUploads="1"
                                  data-limitConcurrentUploads="1"
                                  class="direct-upload">';

                    $to_return.='<input id="wpstream_upload" type="file" class="inputfile inputfile-1" value="Pick a video file" name="file" multiple>';
                    $to_return.='<label for="wpstream_upload"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="17" viewBox="0 0 20 17"><path d="M10 0l-5.2 4.9h3.3v5.1h3.8v-5.1h3.3l-5.2-4.9zm9.3 11.5l-3.2-2.1h-2l3.4 2.6h-3.5c-.1 0-.2.1-.2.1l-.8 2.3h-6l-.8-2.2c-.1-.1-.1-.2-.2-.2h-3.6l3.4-2.6h-2l-3.2 2.1c-.4.3-.7 1-.6 1.5l.6 3.1c.1.5.7.9 1.2.9h16.3c.6 0 1.1-.4 1.3-.9l.6-3.1c.1-.5-.2-1.2-.7-1.5z"/></svg> <span id="wpstream_label_action">' . esc_html__('Choose a file&hellip;','wpstream') . '</span></label>';


                    $to_return.='<div class="wpstream_file_drop_color">';
                    $to_return.='<div class="wpstream_form_ex">'.esc_html__('Drop a video file here!','wpstream').'</div>';      
                    $to_return.='<div class="wpstream_form_ex_details">'.__('The Video File must be encoded with the following settings:<br>

                    Container: <strong>MP4</strong>,<br>
                    Video codec: <strong>H264</strong>,<br>
                    Audio codec: <strong>AAC</strong>.<br>
                    Media will fail to play if it does not follow the above settings. 
                    You may use a tool like MediaInfo to verify your file. Also you may convert it with specialized software like HandBrake.','wpstream').'<strong> '.esc_html__('Accepted file extensions: .mp4, .mov','wpstream').'</strong></div>';    
                    // Inject each signed S3 field as a hidden input so the browser POST is authorized.
                    if(is_array($formInputs)){
                        foreach ($formInputs['ref'] as $name => $value) {
                                $to_return.='<input type="hidden" name="'. $name.'" value="'.$value.'">';
                        }
                    }

                    $to_return.='
                    <div class="progress-bar-area"></div></div>
                    </form>';

                    $to_return.='</div>';
            }     
            
            return $to_return;

        }

   



        /**
         * Display movie list
         *
         * Fetch the account's recordings from the cloud API and render them:
         * still-processing ("pending") items first, then completed items with
         * delete/download links and "create VOD from this recording" actions.
         *
         * @since  3.0.1
         * @return string Recordings list HTML, or a notice when not connected / empty.
         */
        public function wpstream_present_file_management(){
                // Pull the raw recordings payload from the API.
                $video_list_raw = $this->main->wpstream_live_connection->wpstream_get_videos_from_api();

                // false means the API call failed / not connected.
                if ( $video_list_raw === false ) {
                    return '<div class="wpstream_upload_container">'.esc_html__('Not connected. Please connect to WpStream to upload videos.','wpstream').'</div>';
                }

                // Normalise the completed-items list.
                $video_list_raw_array = [];
                if( isset( $video_list_raw['items'] ) ){
                    $video_list_raw_array = $video_list_raw['items'];
                }

                // Sort completed items newest-first by their 'time' field.
                $keys = array_column( $video_list_raw_array, 'time' );
                array_multisort($keys, SORT_DESC , $video_list_raw_array);

                $to_return='';

                // show pending items
                // Render still-processing uploads with a "processing" badge.
                if ( key_exists( 'pending', $video_list_raw ) && is_array( $video_list_raw['pending'] ) ) {
                    foreach ( $video_list_raw['pending'] as $key => $video ) {
                        $video_size = intval($video['size']/1048576);
                        $video_name = esc_html($video['name']);
                        if($video_name!=''):
                            $to_return.='<div class="wpstream_video_wrapper">';
                                $to_return.='<div class="wpstream_video_title">';
                                $to_return.='<div class="wpstream_video_notice"></div></div>';
                                $to_return.='<div class="wpstream_video_title"><strong class="storage_file_name">'.esc_html__('File Name :','wpstream').'</strong>'.'<span class="storage_file_name_real">'.$video_name.'</span><span class="storage_file_size">'.$video_size.' MB </span></div>';
                                $to_return.='<div class="wpstream_video_pending">' . esc_html__( 'The video is still processing', 'wpstream') . '</div>';
                            $to_return.='</div>';
                        endif;

                    }
                }

                // show uploaded items
                // Render each completed recording with its size, delete/download controls and VOD-creation links.
                if( is_array($video_list_raw['items'] ) ) {
                    foreach ($video_list_raw_array as $key =>$video){
                        // Size in MB and the (escaped) file name.
                        $video_size = intval($video['size']/1048576);
                        $video_name = esc_html($video['name']);
                        if($video_name!=''):
                            $to_return.='<div class="wpstream_video_wrapper">';

                                $to_return.='<div class="wpstream_video_title">';
                                $to_return.='<div class="wpstream_video_notice"></div></div>';
                                $to_return.='<div class="wpstream_video_title"><strong class="storage_file_name">'.esc_html__('File Name:','wpstream').'</strong>'.'<span class="storage_file_name_real">'.$video_name.'</span><span class="storage_file_size">'.$video_size.' MB </span></div>';
                                // Delete control with a JS confirm prompt.
                                $to_return.=' <div class="wpstream_delete_media" ';
                                $to_return.=' onclick="return confirm(\' Are you sure you wish to delete '.$video_name.'?\')" data-filename="'.$video_name.'">'.esc_html__('delete file','wpstream').'</div>';
                                // Download trigger (JS fetches a time-limited signed URL) and the resulting link.
                                $to_return.='<div class="wpstream_get_download_link" data-filename="'.$video_name.'">'.esc_html__('download','wpstream').'</div>';
                                $to_return.='<a href="" class="wpstream_download_link">'.esc_html__('Click to download! The url will work for the next 20 minutes!','wpstream').'</a>';

                                // Pre-fill new-VOD links (free and, if WooCommerce present, paid) with this recording's name.
                                $add_free_video_url=admin_url('post-new.php?post_type=wpstream_product_vod').'&new_video_name='. rawurlencode($video_name);
                                $add_paid_video_url=admin_url('post-new.php?post_type=product').'&new_video_name='. rawurlencode($video_name);



                                $to_return .='<a class="create_new_free_video" href="'.esc_url($add_free_video_url).'">'.esc_html__('Create new Free-To-View VOD from this recording').'</a>';
                                if (class_exists('WooCommerce')) {
                                    $to_return .='<a class="create_new_ppv_video" href="'.esc_url($add_paid_video_url).'">'.esc_html__('Create new Pay-Per-View VOD from this recording').'</a>';
                                }

                            $to_return.='</div>';
                        endif;

                    }
                    $current_page= get_current_screen();
				}

                // no items to show
                // Empty-state message when there are neither completed nor pending items.
                if ( !is_array( $video_list_raw['items'] ) || ( key_exists( 'pending', $video_list_raw ) && !is_array( $video_list_raw['pending'] ) ) ) {
                    $to_return.= '<div class="wpstream_video_wrapper">'.esc_html__('You don\'t have any videos.','wpstream').'</div>';
               }
               return $to_return;
        }


         /**
         * Set defualt channels values
         *
         * On publish of a free channel, seed its per-channel `local_event_options`
         * from the site-wide default options if it has none yet. Hooked to the
         * post publish/save action.
         *
         * @since  3.0.1
         * @param  int     $post_id Post ID being published.
         * @param  WP_Post $post    Post object being published.
         * @return void
         */
        public function wpstream_publish_wpstream_product($post_id,$post){
            // Only applies to free channel posts.
            if( $post->post_type == 'wpstream_product' ){
                // Marker meta + flag that global options are the source of truth.
                update_post_meta ($post_id,'local_event_options_test','working_on_'.$post_id);
                update_post_meta ($post_id, 'use_global_event_options', true);
                $to_save_option=array();

                // Site-wide default event options and any existing per-channel overrides.
                $global_options= get_option('wpstream_user_streaming_global_channel_options');

                $local_events =  get_post_meta ($post_id ,'local_event_options',true) ;

                // Only seed when this channel has no local options yet.
                if( $local_events =='' ){
                    if( is_array($global_options) ){
                        // Copy each global option (sanitized) into the per-channel meta.
                        foreach($global_options as $key=>$value){
                            $to_save_option[sanitize_key($key)]=sanitize_text_field($value);
                        }

                        update_post_meta ($post_id,'local_event_options',$to_save_option);

                    }
                }


            }
        }

        /**
         * Create the remote WpStream cloud channel when a channel post is published.
         *
         * Runs for free (`wpstream_product`) and paid live-stream (`product` with
         * the live_stream type) posts, skipping autosaves/revisions and posts that
         * already have a remote channel. On success it stores the channelId and
         * embedUrl meta. Hooked to the post publish/save action.
         *
         * @param  int     $post_id Post ID being published.
         * @param  WP_Post $post    Post object being published.
         * @return void
         */
        public function wpstream_create_remote_channel_on_publish( $post_id, $post ) {
            // Determine whether this is a channel post that needs a cloud channel.
            $is_free_channel = $post->post_type == 'wpstream_product';
            $is_paid_channel = $post->post_type == 'product' && has_term( 'live_stream', 'product_type', $post_id );

            // Bail for non-channel post types.
            if ( !$is_free_channel && !$is_paid_channel ) {
                return;
            }

            // Skip autosaves and revisions (they are not real publishes).
            if ( defined( 'DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
                return;
            }
            if ( wp_is_post_revision( $post_id ) ) {
                return;
            }
            // Idempotency: skip if a remote channel was already created.
            if ( get_post_meta( $post_id, '_wpstream_remote_channel_created', true ) ) {
                return;
            }

            // Ask the cloud API to create the channel and log the raw response.
            $response = $this->main->wpstream_live_connection->wpstream_create_channel( $post_id );
            error_log('channel/create response for post ' . $post_id . ' is ' . print_r($response, true));

            // On success, persist the returned channel id + player embed URL and mark it created.
            if ( $response && ! empty( $response['success'] ) ) {
                if ( isset($response['channel_id']) && $response['channel_id'] !== '' ) {
                    update_post_meta( $post_id, 'channelId', $response['channel_id'] );
                    update_post_meta( $post_id, 'embedUrl', LIVE_PLAYER_URL_PREFIX . '?' . http_build_query( array( 'channelId' => $response['channel_id'] ) ) );
                    update_post_meta( $post_id, '_wpstream_remote_channel_created', 1 );
                }
            }
        }

        /**
         * save meta options
         *
         * Persist the allow-listed free channel / VOD meta fields on save for the
         * `wpstream_product` and `wpstream_product_vod` post types. Hooked to the
         * post save action.
         *
         * @since  3.0.1
         * @param  int     $post_id Post ID being saved.
         * @param  WP_Post $post    Post object being saved.
         * @return void
         */
        public function wpstream_free_product_update_post($post_id,$post){

            // Guard against non-post callers.
            if(!is_object($post) || !isset($post->post_type)) {
                return;
            }





            // Only handle the free channel and VOD post types.
            if( $post->post_type == 'wpstream_product' ||
                $post->post_type == 'wpstream_product_vod' ):

                // Meta keys this handler is allowed to write.
                $allowed_keys=array(
                    'wpstream_product_type',
                    'wpstream_free_video',
                    'wpstream_free_video_external',
                    'wpstream_closed_captions_file'
                );


                // Save each allow-listed, scalar POST field as sanitized post meta.
                foreach ($_POST as $key => $value) {
                    if( !is_array ($value) ){
                        if (in_array ($key, $allowed_keys)) {
                            $postmeta = sanitize_text_field ( $value );
                            update_post_meta($post_id, sanitize_key($key), $postmeta );
                        }
                    }
                }

            endif;

        }
        
        
         /**
         * save meta options
         *
         * Register the plugin's post metaboxes: VOD settings on `wpstream_product_vod`,
         * the video collection box on `wpstream_bundles`, and (for WooCommerce bundle
         * products) the video-collection options box on `product`. Hooked to add_meta_boxes.
         *
         * @since  3.0.1
         * @return void
         */
        public function add_wpstream_product_metaboxes() {
            global $post;
            $post_id = $post->ID;


            // VOD settings metabox and the bundle video-collection metabox.
            add_meta_box(  'add_wpstream_product_metaboxes-sectionid',  esc_html__( 'Video On Demand Settings', 'wpstream' ),array($this,'display_meta_options'),'wpstream_product_vod' ,'normal','default');
            add_meta_box( 'custom_metabox_video_collection',            esc_html__( 'Video Collection', 'wpstream' ), 'wpstream_bundle_custom_metabox_callback', 'wpstream_bundles', 'normal', 'high' );

            // For WooCommerce bundle products, add the video-collection options box on the product screen.
            if(function_exists('wc_get_product')):
                $product = wc_get_product( $post_id );
                if ( $product ) {

                    if ( $product->get_type() === 'wpstream_bundle' ) {
                        add_meta_box(
                            'wpstream_woo_custom_metabox',
                            esc_html__( 'Video Collection Options', 'wpstream' ),
                            'wpstream_bundle_custom_metabox_callback',
                            'product',
                            'normal',
                            'default'
                        );
                    }
                }
            endif;

        }
        
        
         /**
         * make woocomerce virtual products
         *
         * Force WpStream WooCommerce product types (live_stream, video_on_demand,
         * wpstream_bundle) to be virtual so they need no shipping. Hooked to save.
         *
         * @since  3.0.1
         * @param  int     $post_id Post ID being saved (unused; uses global $post).
         * @param  WP_Post $post    Post object (overwritten by global $post).
         * @return void
         */
        public function wpstream_make_product_virtual($post_id,$post){
            global $post;
            if(isset($post->ID)){
                // Only WooCommerce products are relevant.
                if ( $post->post_type !== 'product' ) return;
                // Mark WpStream product types as virtual.
                $term_list      =   wp_get_post_terms($post->ID, 'product_type');
                if( !empty($term_list) &&
                    isset($term_list[0]->name) &&
                    in_array($term_list[0]->name, ['live_stream', 'video_on_demand', 'wpstream_bundle'])
                ){
                    update_post_meta( $post->ID, '_virtual', 'yes' );
                }
            }
        }
        
        
        
        /**
         * render meta options
         *
         * Render the "Video On Demand Settings" metabox: media-type selector,
         * recording chooser, optional captions (.vtt) picker, and the self-hosted/
         * external video URL field. Echoes HTML.
         *
         * @since  3.0.1
         * @param  WP_Post $post Post being edited (overwritten by global $post).
         * @return void
         */
        public function display_meta_options( $post ) {
                // Nonce for the metabox save + use the global post object.
                wp_nonce_field( plugin_basename( __FILE__ ), 'estate_agent_noncename' );
                global $post;

                // Determine the pre-selected media type / video.
                $is_live               =    '';
                $is_video              =    '';
                $is_video_external     =    '';
                // When arriving from "create VOD from recording", preselect the recording.
                if( isset( $_GET['new_video_name']) && $_GET['new_video_name']!=''  ){
                    $is_video               =   ' selected ';
                    $wpstream_free_video    =   esc_html( $_GET['new_video_name']);
                }else{
                    // Otherwise read the saved type/video from post meta and select accordingly.
                    $wpstream_product_type  =    esc_html(get_post_meta($post->ID, 'wpstream_product_type', true));
                    $wpstream_free_video    =    esc_html(get_post_meta($post->ID, 'wpstream_free_video', true));

                    if($wpstream_product_type==1){
                        $is_live = ' selected ';
                    }

                    if($wpstream_product_type==2){
                        $is_video = ' selected ';
                    }

                    if($wpstream_product_type==3){
                        $is_video_external = ' selected ';
                    }
                }

                // Media-type dropdown (recording vs self-hosted/external).
                print'
                <p class="meta-options">
                    <label for="wpstream_product_type">'.__('Media Type:','wpstream').' </label><br />
                    <select id="wpstream_product_type" name="wpstream_product_type">
                        <option value="2" '.$is_video.'>'.__('Recording','wpstream').'</option>
                        <option value="3" '.$is_video_external.'>'.__('Self Hosted or External Video','wpstream').'</option>
                    </select>
                </p>        
                ';           


                // Fetch the account's recordings to populate the chooser.
                $video_list =  $this->main->wpstream_live_connection->wpstream_get_videos();


                // Recording chooser dropdown, pre-selecting the saved recording.
                print '<div class="meta-options video_free">';
                print '<p class="meta-option wpstream_free_video">';
                print '<label for="wpstream_free_video">'.__('Choose video:','wpstream').' </label><br />
                    <select id="wpstream_free_video" name="wpstream_free_video">';

                if( is_array( $video_list ) ) {
                    foreach ($video_list as $key=>$value){
                        print '<option value="'.$key.'"';
                        if($wpstream_free_video === $key){
                            print ' selected ';
                        }
                        print '>'.$value.'</option>';
                    }
                }
                print'</select>';
                print '</p> ';

                // Optional captions (.vtt) picker; hide the select button when a file is already set.
                $wpstream_closed_captions_file = get_post_meta($post->ID, 'wpstream_closed_captions_file', true);

                $button_style = $wpstream_closed_captions_file ? 'style="display:none;"' : '';

                print '<p class="meta-option wpstream_vod_captions_url">';
                print '<label for="wpstream_vod_captions_url_button">'.__('Captions file (optional):','wpstream').' </label><br />
                        <input type="hidden" id="wpstream_closed_captions_file" name="wpstream_closed_captions_file" value="'.esc_attr($wpstream_closed_captions_file).'" />
                        <input id="wpstream_vod_captions_url_button" type="button" class="upload_button button" value="'.esc_html__('Select .vtt Captions File','wpstream').'" '.$button_style.' />
                        <span class="wpstream_caption_file_display">'.( $wpstream_closed_captions_file ? esc_html( basename( $wpstream_closed_captions_file ) ) : '' ).'</span>';
                if ( $wpstream_closed_captions_file ) {
                    print '<input type="button" class="button wpstream_remove_caption" value="'.esc_html__('Remove','wpstream').'" style="margin-left: 5px;" />';
                }
                print '</p> ';
                print '</div>';

                // Self-hosted / external video URL field with a media-library select button.
                $wpstream_free_video_external=    esc_html(get_post_meta($post->ID, 'wpstream_free_video_external', true));
                print '<div class="meta-options1 video_free_external">
                        <label for="wpstream_free_video_external">'.__('Video:','wpstream').' </label><br />

                        <input id="wpstream_free_video_external" type="text" size="36" name="wpstream_free_video_external" value="'.$wpstream_free_video_external.'" />
                        <input id="wpstream_free_video_external_button" type="button"   size="40" class="upload_button button" value="'.esc_html__('Select Video','wpstream').'" />';
                        // Show the recording hint vs the external hint depending on the saved media type.
                        if($wpstream_product_type==2){
                            $show_recording='';
                            $show_external='style="display:none"';
                        }else{
                            $show_recording='style="display:none"';
                            $show_external='';
                        }
                        print '<p '.$show_recording.' class="wpstream_option_vod_source wpstream_show_recording">'.esc_html__('Choose one of your existing recordings.','wpstream').'</p>';
                        print '<p '. $show_external.' class="wpstream_option_vod_source wpstream_show_external">'.esc_html__('Upload a video from your computer or paste the URL of a YouTube/external video.','wpstream').'</p>';
                     
                print '</div> ';
        }
        
        
        
        
        
       
        
         /**
        * Add new product types to Woocommerce select product type
        *
        * Register the "Live Channel" and "Video On Demand" WooCommerce product
        * types in the product-type dropdown. Filter callback.
        *
        * @since  3.0.1
        * @param  array $types Existing product-type => label map.
        * @return array The map with the WpStream types added.
        */
        public function wpstream_add_products( $types ){
            $types[ 'live_stream' ]             = __( 'Live Channel','wpestream' );
            $types[ 'video_on_demand' ]         = __( 'Video On Demand','wpestream' );

            return $types;
        }

		/**
		 * Map the WpStream product-type slugs to their WC_Product subclasses.
		 * Filter callback for woocommerce_product_class.
		 *
		 * @param  string $classname     Default product class name.
		 * @param  string $product_type  The product type slug being instantiated.
		 * @return string The resolved product class name.
		 */
		public function wpstream_add_products_class( $classname, $product_type ) {
			if ( 'live_stream' === $product_type ) {
				$classname = 'WC_Product_Live_Stream';
			}
			if ( 'video_on_demand' === $product_type ) {
				$classname = 'WC_Product_Video_On_Demand';
			}
			return $classname;
		}

		/**
		 * Load the custom WC_Product subclass files when WooCommerce is active.
		 *
		 * @return void
		 */
		public function wpstream_add_custom_wc_products() {
			// Require the live-stream and VOD product classes (WooCommerce only).
			if(  class_exists( 'WooCommerce' ) ){
					require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc_product_live_stream.php';
					require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc_product_video_on_demand.php';
				}
		}

         /**
        * Js action to do when user pick live stream or video on demand
        *
        * @since    3.0.1
        */

        /**
         * Whether WpStream WooCommerce product UI should load for this product.
         *
         * Skips simple, course, and other non-WpStream types so LearnDash and
         * other integrations can use the General product data tab without conflict.
         *
         * @param int $post_id Product post ID. Uses global $post when 0.
         * @return bool
         */
        public function wpstream_is_wpstream_wc_product_context( $post_id = 0 ) {
            // Creating a new stream/VOD from a query-string action always counts.
            if ( isset( $_GET['new_stream'] ) || isset( $_GET['new_video_name'] ) ) {
                return true;
            }

            // Fall back to the current global post when no ID was passed.
            if ( ! $post_id ) {
                global $post;
                $post_id = ( $post && isset( $post->ID ) ) ? (int) $post->ID : 0;
            }

            // Need a product ID and WooCommerce to resolve the type.
            if ( ! $post_id || ! function_exists( 'wc_get_product' ) ) {
                return false;
            }

            $product = wc_get_product( $post_id );
            if ( ! $product ) {
                return false;
            }

            // True only for WpStream (and subscription) product types.
            return in_array(
                $product->get_type(),
                array( 'live_stream', 'video_on_demand', 'wpstream_bundle', 'subscription' ),
                true
            );
        }

        /**
         * WooCommerce product types that use the core Regular price field.
         *
         * @return string[]
         */
        public function wpstream_get_wc_product_types_with_pricing() {
            return array( 'live_stream', 'video_on_demand', 'wpstream_bundle' );
        }

        /**
         * Add show_if_* classes to the pricing panel for WpStream product types.
         *
         * WooCommerce renders .options_group.pricing with show_if_simple only. Custom
         * types rely on show_if_{type} classes so meta-boxes-product.js can toggle them
         * when the product type dropdown changes, including on unsaved new products.
         *
         * @param string $hook Admin screen hook suffix.
         */
        public function wpstream_enqueue_wc_product_pricing_visibility( $hook ) {
            // Only on the add/edit-post admin screens.
            if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
                return;
            }

            // Only on the WooCommerce product post type.
            $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
            if ( ! $screen || 'product' !== $screen->post_type ) {
                return;
            }

            // Need WooCommerce's product meta-boxes script to attach to.
            if ( ! wp_script_is( 'wc-admin-product-meta-boxes', 'registered' ) ) {
                return;
            }

            // Pass the WpStream pricing types to JS and add show_if_{type} classes so the pricing panel toggles correctly.
            $types_json = wp_json_encode( array_values( $this->wpstream_get_wc_product_types_with_pricing() ) );

            wp_add_inline_script(
                'wc-admin-product-meta-boxes',
                "jQuery( function( $ ) {
                    var wpstreamPricingProductTypes = {$types_json};
                    var \$pricing = $( '.options_group.pricing' );
                    var \$soldIndividually = $( '._sold_individually_field' ).parent();

                    wpstreamPricingProductTypes.forEach( function( type ) {
                        \$pricing.addClass( 'show_if_' + type );
                        \$soldIndividually.addClass( 'show_if_' + type );
                    } );

                    if ( wpstreamPricingProductTypes.indexOf( $( '#product-type' ).val() ) !== -1 ) {
                        \$pricing.show();
                        \$soldIndividually.show();
                    }
                } );"
            );
        }
         
        
        /**
        * Add custom classes to the product types
        *
        * Adjust the WooCommerce product-data tab visibility classes so WpStream
        * types hide the Shipping tab and show the Inventory tab. Filter callback.
        *
        * @since  3.0.1
        * @param  array $tabs WooCommerce product data tabs config.
        * @return array The modified tabs config.
        */
        public function wpstream_hide_attributes_data_panel( $tabs) {

            // Hide Shipping and show Inventory for WpStream product types.
            $tabs['shipping']['class'][] = 'hide_if_live_stream  hide_if_video_on_demand hide_if_wpstream_bundle';
            $tabs['inventory']['class'][] = 'show_if_live_stream  show_if_video_on_demand show_if_wpstream_bundle';

            return $tabs;
        }
        
        
           
        /**
        * Hide buy now on products if Netflix mode
        *
        * When global subscription ("Netflix") mode is on, mark WpStream media
        * products as not individually purchasable (access comes via the
        * subscription instead). Filter callback for is_purchasable.
        *
        * @since  3.12
        * @param  bool       $purchaseable_product_wpblog Current purchasable flag.
        * @param  WC_Product $product                     The product being checked.
        * @return bool False for WpStream media in global-sub mode, else the original flag.
        */
        public function  wpstream_hide_buy_now_subscription_mode( $purchaseable_product_wpblog,$product){
            $product_id=$product->get_id();

            $term_list              =       wp_get_post_terms($product_id, 'product_type');

            // Global subscription ("Netflix") mode flag.
            $subscription_model     =       intval( get_option('wpstream_global_sub','')) ;

            if($subscription_model==1){ // if we have Neflix mode
                // WpStream media types are not directly purchasable in this mode.
                if( $term_list[0]->name=='live_stream' || $term_list[0]->name=='video_on_demand' || $term_list[0]->name=='wpstream_bundle' ){
                    return false;
                }
            }

            return  $purchaseable_product_wpblog;
        }
        
        
        
        
        
        
         /**
        * Add custom fields to custom product types
        *
        * Render the extra WooCommerce "General" product fields for WpStream types:
        * the subscription-live flag, the recording/video chooser, and the
        * "attach to subscription" multi-select. Echoes HTML.
        *
        * @since  3.0.1
        * @return void
        */
        public function wpstream_add_custom_general_fields() {
            global $woocommerce, $post;

            // Only render for WpStream product contexts (avoids clashing with other product types).
            if ( ! $this->wpstream_is_wpstream_wc_product_context( isset( $post->ID ) ? (int) $post->ID : 0 ) ) {
                return;
            }
            // Subscription-based live channel toggle (only when WC Subscriptions is active).
            if(function_exists('wcs_user_has_subscription')){
                echo '<div class="options_group   show_if_subscription">';
                    woocommerce_wp_select( 
                        array( 
                            'id'      =>    '_subscript_live_event', 
                            'label'   =>    __( 'Is a subscription based live channel ?', 'woocommerce' ), 
                            'options' =>    array("yes"=>"yes","no"=>"no", "none" => "none")
                            )
                        );
                echo '</div>';
            }

            echo '<div class="options_group show_if_live_stream" style="border:none;"></div>';
            // VOD video chooser section.
            echo '<div class="options_group show_if_video_on_demand">';


                // Pre-selected video: from the new-video query arg, else the saved _movie_url meta.
                $selected='';
                if( isset( $_GET['new_video_name']) && $_GET['new_video_name']!=''  ){
                    $selected=esc_html($_GET['new_video_name']);
                }
                if($selected==''){
                   $selected= get_post_meta($post->ID,'_movie_url',true);
                }
                // Only administrators may assign videos.
                if( !current_user_can('administrator') ){
                    print '<div style="margin:10px;">'.esc_html('You need to be an administrator in order to assign videos','wpstream').'</div>';
                }else{
                    woocommerce_wp_select( 
                        array( 
                            'id'      =>    '_movie_url', 
                            'label'   =>    __( 'Choose video', 'woocommerce' ), 
                            'options' =>     $this->main->wpstream_live_connection->wpstream_get_videos(),
                            'selected'=>    true,
                            'value'    =>   $selected
                            )
                    );
                }   
                
              

            echo '</div>';
            
            // "Attach to subscription" multi-select (only when WC Subscriptions is active).
            if(function_exists('wcs_user_has_subscription')){
                $selected_sub='';
                echo '<div class="options_group show_if_video_on_demand show_if_live_stream">';
                    if( isset( $_GET['wpstream_parent_sub']) && $_GET['wpstream_parent_sub']!=''  ){
                        $selected_sub=esc_html($_GET['wpstream_parent_sub']);
                    }
                    if($selected_sub==''){
                       $selected_sub= get_post_meta($post->ID,'_wpstream_parent_sub',true);
                    }
                    woocommerce_wp_select( 
                    array( 
                        'id'      =>    '_wpstream_parent_sub', 
                        'name'    =>    '_wpstream_parent_sub[]',
                        'label'   =>    __( 'Attach to subscription', 'woocommerce' ), 
                        'options' =>     $this->wpstream_return_subscriptions_created(),
                        'selected'=>    true,
                        'value'   =>   $selected_sub,
                        'custom_attributes' => array('multiple' => 'multiple')
                        )
                );
                
                echo '</div>';
            
            }
        }
        
        
        /**
         * Build an id => title map of all WooCommerce subscription products,
         * for the "attach to subscription" selector. Includes a "none" option.
         *
         * @return array Map of subscription product ID => title (0 => 'none').
         */
        public function wpstream_return_subscriptions_created(){
            // Seed with the "none" choice.
            $return=array('0'=>'none');

            // Query all subscription-type products, title-ordered.
            $args  = array(
                    'post_type'      => 'product',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'tax_query' => array(
                        'relation' => 'AND',
                        array(
                                'taxonomy' => 'product_type',
                                'field'    => 'slug',
                                'terms'    => array( 'subscription'),
                        )
                    )
                );

            // Collect each subscription's id => title.
            $subscriptions = new WP_Query($args);
            if($subscriptions->have_posts()):
                while ($subscriptions->have_posts()): $subscriptions->the_post();
                    $return[ get_the_ID() ] = get_the_title();
                endwhile;
            endif;

            // Restore the main query and return the map.
            wp_reset_postdata();
            return $return;

        }
        
        
        
        
        

        /**
        * Save custom fields
        *
        * Persist the WpStream WooCommerce product fields (_movie_url,
        * _subscript_live_event, _wpstream_parent_sub) on save and reset the
        * event_passed flag. Hooked to the product save action.
        *
        * @since  3.0.1
        * @param  int $post_id Product ID being saved.
        * @return void
        */
        public function wpstream_add_custom_general_fields_save( $post_id ){
            // Only handle WpStream product contexts.
            if ( ! $this->wpstream_is_wpstream_wc_product_context( (int) $post_id ) ) {
                return;
            }

            // Meta keys this handler may write.
            $permited_values = array(
                '_movie_url',
                '_subscript_live_event',
                '_wpstream_parent_sub',

            );



            // Reset event_passed and save each allow-listed field.
            foreach($_POST as $key=>$value){
                update_post_meta( $post_id, 'event_passed', 0 );
                if( in_array($key, $permited_values) ){
                    if( !empty( $_POST[$key] ) ){
                        $key    =   sanitize_key($key);
                        $value  =   sanitize_text_field($_POST[$key]);

                        // The parent-subscription field is an array; sanitize each element.
                        if($key=='_wpstream_parent_sub'){
                            $value= $_POST[$key];
                            $value = array_map("sanitize_text_field", $value);

                        }
                        update_post_meta( $post_id, $key, $value );
                    }
                }
            }
            //die();

        }
        
         /**
        * Add to cart redirect
        *
        * Render the simple add-to-cart template for WpStream product types.
        *
        * @since  3.0.1
        * @return void
        */
        public function wpstream_add_to_cart() {
            wc_get_template( 'single-product/add-to-cart/simple.php' );
        }


        /**
        * Replace add to cart button
        *
        * For live_stream / video_on_demand products, swap the loop add-to-cart
        * button for a direct shop add-to-cart link. Filter callback.
        *
        * @since  3.0.1
        * @param  string     $button  Original button HTML.
        * @param  WC_Product $product The product (overwritten by global $product).
        * @return string The (possibly replaced) button HTML.
        */
        public function replacing_add_to_cart_button( $button, $product  ) {
            global $product;
            $product_type = $product->get_type();

            // Only WpStream media types get the custom link; others keep the default button.
            if($product_type==='live_stream' || $product_type=='video_on_demand'){
                return $button = '<a class="button" href="'.get_site_url().'/shop/?add-to-cart=' .$product->get_id(). '&quantity=1">' . __( 'Add to Cart', 'woocommerce' ) . '</a>';
            }else{
                return $button;
            }
        }
       

         /**
        * Admin notices
        *
        * Print global admin notices: (commented-out) WooCommerce-missing notice
        * and an error if the PHP cURL extension is unavailable, plus the dismiss
        * nonce. Echoes HTML.
        *
        * @since  3.0.1
        * @return void
        */
        public function wpstream_admin_notice() {
            global $pagenow;
            global $typenow;

            // Stored dismissed-notice flags.
            $wpstream_notices =  get_option('wpstream_notices');

            /*
            if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
                if( !is_array($wpstream_notices) ||
                !isset($wpstream_notices['wpstream_woo_notice']) ||
                ( isset($wpestate_notices['wpstream_woo_notice']) && $wpestate_notices['wpstream_woo_notice']!='yes')  ){

     
                print '<div class="notice wpstream_notices notice-error is-dismissible" data-notice-type="wpstream_woo_notice" >
                    <p>'.__( 'WpStream Pay-Per-View Live Streaming and VOD only works with WooCommerce - Please enable and activate the WooCommerce plugin if you want to monetize your Live Events or Recorded Videos', 'wpstream' ).'</p>
                </div>';
                }
            }
            */
            
            // Hard requirement: warn when the PHP cURL extension is missing.
            if( !in_array  ('curl', get_loaded_extensions())) {
                print '<div class="notice  notice-error is-dismissible">
                    <p>'.__( 'The php CURL library is not enabled on your server. WpStream plugin needs this library in order to work. Please address this issue with your hosting provider.', 'wpstream' ).'</p>
                </div>';
            }


            // Nonce used by the JS that records notice dismissals.
            $ajax_nonce = wp_create_nonce( "wpstream_notice_nonce" );
            print '<input type="hidden" id="wpstream_notice_nonce" value="'.esc_html($ajax_nonce).'"/>';

        }

        /**
        * Get plugin latest update release date from WordPress.org
        * @param $plugin_slug
        * @param $version
        *
        * @return bool
         */
        public function get_plugin_release_date( $plugin_slug, $version = null ) {
            // Query the WordPress.org plugins info API.
            $api_url = 'https://api.wordpress.org/plugins/info/1.0/' . $plugin_slug . '.json';
            $response = wp_remote_get( $api_url );

            // Bail on transport error.
            if ( is_wp_error( $response ) ) {
                return false;
            }

            // Decode the JSON body; bail if there is no versions list.
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( !$data || !isset( $data['versions'] ) ) {
                return false;
            }

            // no version provided, get the latest version
            if ( !$version ) {
                $version = $data['version'];
            }

            // check if the version exists in the versions array
            if ( !isset( $data['versions'][ $version ] ) ) {
                return false;
            }

            // Return the plugin's last-updated date (note: this is the plugin's overall last_updated, not per-version).
            if ( isset( $data['last_updated'] ) ) {
                return date('Y-m-d', strtotime( $data['last_updated'] ) );
            }

            return false;
        }

        /**
        * Adds notice for the WpStream update availability
        * when the update is not older than 30 days
         */
		public function wpstream_plugin_update_available_notice() {
			// Only show to users who can update plugins.
			if (!current_user_can('update_plugins')) {
				return;
			}

			// Look up a pending update for this plugin in WP's transient.
			$plugin_slug = 'wpstream/wpstream.php';
			$update_data = get_site_transient('update_plugins');

			if ( is_object( $update_data ) &&
				property_exists( $update_data, 'response' ) &&
				is_array($update_data->response) &&
				key_exists($plugin_slug, $update_data->response)
			) {
				$new_version = $update_data->response[$plugin_slug]->new_version;

                // Suppress the notice for very fresh releases (grace period).
                $release_date = $this->get_plugin_release_date( 'wpstream', $new_version );

                if ( $release_date ) {
                    $days_since_release = ( time() - strtotime( $release_date ) ) / DAY_IN_SECONDS;

                    // if there's an update newer than 7 days, do not show the notice
                    if ( $days_since_release < 7 ) {
                        return;
                    }
                }
				// Build the one-click update URL (nonce-protected) and print the notice.
				$update_url = wp_nonce_url(
					self_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($plugin_slug)),
					'upgrade-plugin_' . $plugin_slug
				);

				echo '<div class="notice notice-warning is-dismissible">';
				echo '<p><strong>' . __('WpStream Plugin Update Available', 'wpstream') . '</strong></p>';
				echo '<p>' . sprintf(
					__('Version %s is available. Please update to the latest version for new features and security improvements.', 'wpstream'),
					'<strong>' . esc_html($new_version) . '</strong>'
				) . '</p>';
				echo '<p><a href="' . esc_url($update_url) . '" class="button button-primary">' .
					 __('Update Now', 'wpstream') . '</a></p>';
				echo '</div>';
            }
        }

          /**
        * Admin notices
        *
        * AJAX handler that records a dismissed notice: marks the posted notice
        * type as 'yes' in the wpstream_notices option so it stops showing.
        *
        * @since  3.0.1
        * @return void Ends the request with die().
        */
        public function wpstream_update_cache_notice(){

            //check_ajax_referer( 'wpstream_notice_nonce', 'security'  );

            // Which notice was dismissed.
            $notice_type    =   esc_html($_POST['notice_type']);
            $notices        =   get_option('wp_stream_notices');

            // Normalise to an array.
            if(! is_array($notices) ){
                $notices=array();
            }

            // Flag this notice as dismissed and persist.
            $notices[$notice_type]='yes';

            update_option('wpstream_notices',$notices);
            die();
        }
        
       
        
        
        /**
        * Activate metaboxes for Streaming controls on sidebar
        *
        * Register the "Live Streaming" sidebar metabox on free channel posts, and
        * also on WooCommerce products that are live_stream (or a subscription
        * flagged as a live event). Hooked to add_meta_boxes.
        *
        * @since  3.0.1
        * @return void
        */
         public function wpstream_startstreaming_sidebar_meta() {
                global $post;
                $term_list                          =   wp_get_post_terms($post->ID, 'product_type');

                // Always add the sidebar controls to the free channel post type.
                add_meta_box(
                    'wpstream-sidebar-meta',
                    esc_html__('Live Streaming',  'wpstream'),
                    array($this,'wpstream_start_stream_meta'),
                    'wpstream_product',
                    'side',
                    'high'
                );

                // For WooCommerce products, add it only for live channels / subscription-live events.
                $is_subscription_live_event =   esc_html(get_post_meta($post->ID,'_subscript_live_event',true));
                if(!is_wp_error( $term_list )){
                    if( isset($term_list[0]->name) ){
                        if( $term_list[0]->name=='live_stream' ||  ($term_list[0]->name=='subscription' && $is_subscription_live_event=='yes' )  ){
                            add_meta_box('wpstream-sidebar-meta',       esc_html__('Live Streaming',  'wpstream'), array($this,'wpstream_start_stream_meta'), 'product', 'side', 'high');
                        }
                    }
                }

        }
        
        /**
        * edited 4.0
        *
        * Show Streaming controls on sidebar
        *
        * Render the sidebar metabox contents: for published channels, the live
        * stream unit card (plus a basic-streaming flag when out of data) and the
        * error modal; otherwise a "publish first" prompt. Echoes HTML.
        *
        * @since  3.0.1
        * @return void
        */
        public function wpstream_start_stream_meta(){
            // Preload the current live-event map for the card renderer.
            global $live_event_for_user;
            $live_event_for_user    =    $this->main->wpstream_live_connection->wpstream_get_live_event_for_user();

            global $post;
            $local_event_options = get_post_meta ($post->ID,'local_event_options','');



            // Streaming controls only make sense once the channel is published.
            if( get_post_status( $post->ID ) === 'publish' ) {
                // Start-event nonce for the card's ON/OFF button.
                $ajax_nonce = wp_create_nonce( "wpstream_start_event_nonce" );
                print '<input type="hidden" id="wpstream_start_event_nonce" value="'.$ajax_nonce.'">';

                 // Flag basic-streaming mode when the account has no data left.
                 $pack_details = $this->main->quota_manager->get_live_quota_data( 'wpstream_new_general_set' );
            if( isset($pack_details['available_data_mb'])){
                if ($pack_details['available_data_mb'] <= 0){
                    print '<input type="hidden" id="wpstream_basic_streaming" value="true">';
                }
            }

                // Render the channel card plus the shared modal background and error modal.
                $this->wpstream_live_stream_unit($post->ID);
                print '<div class="wpstream_modal_background"></div>';
                print '<div class="wpstream_error_modal_notification"><div class="wpstream_error_content">er1</div>
                <div class="wpstream_error_ok wpstream_button" type="button">'.esc_html__('Close','wpstream').'</div>
                </div>';
            } else {
                // Not published yet.
                esc_html_e('To Go Live, please publish your channel first !','wpstream');
            }
        }


    /**
     * Whether the account is currently limited to "basic streaming" mode.
     *
     * @return bool
     */
    public function wpstream_is_basic_streaming_mode(){
        return $this->main->quota_manager->is_basic_streaming_mode( null, 'wpstream_is_basic_streaming_mode' );
    }

    /**
     * Whether the account's plan meters streaming by hours (vs. data).
     *
     * @return bool
     */
    public function wpstream_is_use_streaming_hours() {
        $pack_details = $this->main->quota_manager->get_live_quota_data( 'wpstream_start_channel' );
        return $this->main->quota_manager->uses_streaming_hours( $pack_details );
    }

	/**
	 * Cached-only flags for start_streaming.js localization (no API on cold cache).
	 *
	 * @return array{is_basic_streaming: bool, use_streaming_hours: bool}
	 */
	public function wpstream_get_start_streaming_localization_flags() {
		return $this->main->quota_manager->get_streaming_ui_flags_from_cache();
	}



   /**
        * Register a hidden dashboard page used as the onboarding wizard endpoint.
        *
        * @return void
        */
        public function add_dashboard_page() {
            add_dashboard_page( '', '', 'administrator', 'wpstream-onboarding', '' );
        }




        /**
         * Bootstrap the full-screen onboarding wizard screen (non-AJAX requests).
         *
         * @return void
         */
        public function wpstream_load_onboarding_wizard() {

            // Check for wizard-specific parameter
            // Allow plugins to disable the onboarding wizard
            // Check if current user is allowed to save settings.


            // Don't load the interface if doing an ajax call.
            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                return;
            }

            // Ensure a current screen is set for the standalone wizard context.
            set_current_screen();

            // Remove an action in the Gutenberg plugin ( not core Gutenberg ) which throws an error.
            remove_action( 'admin_print_styles', 'gutenberg_block_editor_admin_print_styles' );

            // Hand off to the actual wizard renderer.
            $this->actual_load_onboarding_wizard();

        }


        /*
        * Add on boarding to footer
        */
        /**
         * On the onboarding admin page, print the onboarding modal into the footer.
         *
         * @return void
         */
        public function wpstream_admin_footer_onboarding(){
            if( isset($_GET['page']) &&  $_GET['page']==='wpstream_onboard') {
                $this->wpstream_onboard_display();
            }
        }
        

        /*
        * On Board Display
        *
        */
        /**
         * Render the Quick Start landing screen (logo, intro, "Start the Guide"
         * button and the script that opens the onboarding wizard). Echoes HTML.
         *
         * @return void
         */
        public function wpstream_pre_onboard_display(){
            // Quota summary header for the Quick Start page.
            $pack_details = $this->main->quota_manager->get_live_quota_data( 'wpstream_pre_onboard_display' );
            $this->main->show_user_data($pack_details);

            // Onboarding logo used in the intro card.
            $thumb= plugin_dir_url( dirname( __FILE__ ) ). 'img/logo_onboarding.svg';
            // Below: Quick Start intro markup plus a script that reveals the onboarding wizard modal.
            ?>
            <div id="wpstream-onboarding-root"></div>
                <div class="wpstream_quick_start_wrapper">
                    <img class="wpstream_onboarding_logo" src="<?php echo esc_url($thumb); ?>" />

                    <h1><?php print esc_html__('WpStream','wpstream').' <span class="header_special">'.esc_html__('Quick Start','wpstream').'</span>';?></h1>

                 
                        <p>
                            <?php esc_html_e('The quick start guide will help you set up Live Streaming, Video On Demand, and Monetization in a fun and interactive way. Give it a shot! ','wpstream');?>
                        </p>

                        <div id="wpstream_trigger_quick_start" class="wpstream_button wpstream_button_action"><?php esc_html_e('Start the Guide','wpstream');?></div>

                 

                </div>

                    
               
                    <script type="text/javascript">
                        //<![CDATA[
                            jQuery(document).ready(function(){
                                jQuery(".wpstream_on_boarding_wrapper").show();
                                jQuery(".wpstream_modal_background_onboard").show();
                            });
                        
                        //]]>
                    </script>

            <?php
               
        }        

        






        /*
        *
        * On Boarding Content
        *
        */
        /**
         * Emit the full onboarding wizard markup by rendering each step in order
         * (header, account, path choice, live/VOD branches, footer). Echoes HTML.
         *
         * @return void
         */
        public function wpstream_onboard_display() {
                // Render each wizard step sequentially into the modal.
                $this->onboarding_wizard_header();
                $this->wpstream_onboarding_step1();
                $this->wpstream_onboarding_step2();
                $this->wpstream_onboarding_step3_live_streaming();
                $this->wpstream_onboarding_step_3_A_live_streaming_free_view();
                $this->wpstream_onboarding_step_3_B_live_streaming_pay_per_view();
                $this->wpstream_onboarding_step4_vod();
                $this->wpstream_onboarding_step_4_free_vod();
                $this->wpstream_onboarding_step_4_ppv_vod();
                $this->onboarding_wizard_footer();
        }


        /*
        *
        * On Boarding Step 1 - the login/register
        *
        */
        /**
         * Onboarding step 1: WpStream account login/registration UI, including the
         * ALTCHA captcha widget (on HTTPS) and a hidden generated registration
         * password. Emits a marker div when a token already exists. Echoes HTML.
         *
         * @return void
         */
        public function wpstream_onboarding_step1(){

            // Login form field definitions (username + password).
            $wpstream_options_array =array(
                2   =>  array(
                            'label' =>  'WpStream.net Username or Email',
                            'name'  =>  'api_username',
                            'type'  =>  'text',
                        ),
                3   =>  array(
                            'label' =>  'WpStream.net Password',
                            'name'  =>  'api_password',
                            'type'  =>  'password',
                        ),

            );
            // Existing auth token (if already connected) checked at the end.
            $token          =   $this->main->wpstream_live_connection->wpstream_get_token();

            // Below: the account step markup (login form, register form, captcha, nonce).
            ?>
            <div class="wpstream_step_wrapper wpstream_step_1" id="wpstream_step_1">           
                <div class="wpstream_has_credential">
                    <h1><?php esc_html_e('WpStream Account','wpstream');?></h1>

                    <div class="wpstream_on_board_login_wrapper_explanations">
                        <?php esc_html_e('A WpStream account is required to make use of the plugin.','wpstream');?>
                    </div>


                        <div class="wpstream_check_account_status">
                            <?php esc_html_e( 'Checking if you are already logged.....', 'wpstream' ); ?>
                        </div>

                        <div class="wpstream_onboarding_notification"></div>
               
                        <div class="wpstream_option_wrapper wpstream_on_board_login_wrapper">
                            <h2><?php esc_html_e('Login with your WpStream Account','wpstream');?></h2>
                            <?php 
                                // Render each login field pre-filled with its stored value.
                                foreach ($wpstream_options_array as $key=>$option){
                                    print '<div class="wpstream_option">';

                                        $options_value =  esc_html( get_option('wpstream_'.$option['name'],'') );
                                        print '<label for="'.$option['name'].'">'.$option['label'].'</label>';
                                        print '<input id="'.$option['name'].'" type="'.$option['type'].'" size="36"  name="'.$option['name'].'" value="'.esc_html($options_value).'" />';

                                    print '</div>';
                                }
                            ?>
                            <input type="submit" name="submit"  class="wpstream_button wpstream_button_action wpstream_onboard_login" value="<?php esc_html_e('Login','wpstream');?>" />
                        </div>




                        <div class="wpstream_on_board_register_wrapper">
                            <h2><?php esc_html_e('Register for  a WpStream Account','wpstream');?></h2>
                            <div class="wpstream_option">
                                <label for="wpstream_register_email"><?php esc_html_e('Your Email','wpstream');?></label>
                                <input id="wpstream_register_email" type="text" size="36"  name="wpstream_register_email" value="<?php echo get_option('admin_email'); ?>" />

                            </div>

                            <div class="wpstream_option">
<!--                                <label for="wpstream_register_password">--><?php //esc_html_e('Your Password','wpstream');?><!--</label>-->
                                <input id="wpstream_register_password" hidden type="text" size="36"  name="wpstream_register_password" value="<?php echo $this->randomPassword();?>" />
                                <span class="" ><?php esc_html_e('We\'ll send the password to the email you attached. ', 'wpstream') ?></span>
                            </div>

                        
                            <!-- Altcha Widget (requires HTTPS/secure context) -->
                            <?php if ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ) : ?>
                            <script async defer src="https://cdn.jsdelivr.net/gh/altcha-org/altcha/dist/altcha.min.js" type="module"></script>
                            <div class="wpstream_option" style="display:none;">
                                <altcha-widget
                                    challengeurl="<?php echo esc_url( WPSTREAM_API . '/v2/user/getcaptcha' ); ?>"
                                    name="altcha"
                                    auto="onload"
                                    hidefooter
                                    hidelogo
                                    strings='{"label": "<?php esc_html_e('I am not a robot', 'wpstream'); ?>", "error": "<?php esc_html_e('Verification failed', 'wpstream'); ?>", "wait": "<?php esc_html_e('Verifying...', 'wpstream'); ?>"}'
                                ></altcha-widget>
                            </div>
                            <?php endif; ?>

                            <div class="wpstream_option wpstream_terms_agreement">
                                <!-- Add "by registering you agree to the privacy terms" checkbox-->
                                <input id="wpstream_register_privacy" type="checkbox" name="wpstream_register_privacy" />
                                <label for="wpstream_register_privacy">
                                <?php printf(
                                    esc_html__('By registering you agree to the %sPrivacy Policy%s','wpstream'),
                                    '<a href="https://wpstream.net/privacy-policy/" target="_blank">',
                                    '</a>'
                                );?>
                                </label>
                            </div>
                            <input type="submit" name="submit"  class="wpstream_button wpstream_button_action wpstream_onboard_register" value="<?php esc_html_e('register','wpstream');?>" />
                        </div>


                        <div id="wpstream_onboarding_action_login"><?php esc_html_e('I already have a WpStream Account','wpstream');?></div>

                        <div id="wpstream_onboarding_action_register"><?php esc_html_e('Back to Registration','wpstream');?></div>
    
                </div>


                <?php 
                // Nonce protecting the onboarding AJAX actions.
                $ajax_nonce = wp_create_nonce( "wpstream_onboarding_nonce");
                print'<input type="hidden" id="wpstream_onboarding_nonce" value="'.esc_html($ajax_nonce).'" />    ';

                ?>
            
        
                <div id="wpstream_on_board" class="wpstream_action_next_step" data-nextthing="wpstream_step_2" style="display:none;"  >Move to step 2</div>
            </div>
            <?php

            // If already authenticated, emit a marker the wizard JS uses to skip login.
            if( !is_null( $token ) && trim( $token ) !== '' ) {
                print '<div id="wpstream_have_token"></div>';
            }
        }
  









        /*
        *
        * Generate random pass
        *
        */
        /**
         * Generate a random 16-character alphanumeric password (used to pre-fill
         * the onboarding registration form).
         *
         * @return string The generated password.
         */
        public function randomPassword() {
            // Character pool for the password.
            $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
            $pass = array(); //remember to declare $pass as an array
            $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
            // Pick 16 cryptographically-random characters.
            for ($i = 0; $i < 16; $i++) {
                $n = random_int(0, $alphaLength);
                $pass[] = $alphabet[$n];
            }
            return implode($pass); //turn the array into a string
        }


        /*
        *
        *
        *
        */
        /**
         * Onboarding step 2: choose a starting path (Go Live vs create a VOD). Echoes HTML.
         *
         * @return void
         */
        public function wpstream_onboarding_step2(){
            // Below: the path-choice step markup.
            ?>
          
            <div class="wpstream_step_wrapper wpstream_step_2" id="wpstream_step_2"> 
                <h1>Welcome to <span class="header_special">WpStream</span>! How would you like to start?</h1>

                <div class="wpstream_accordion_header wpstream_action_next_step" data-nextthing="wpstream_step_3a" ><?php esc_html_e('Go LIVE!','wpstream');?></div>
                <div class="wpstram_or">or</div>
                <div class="wpstream_accordion_header wpstream_action_next_step wpstream_step_2_create_vod" data-nextthing="wpstream_step_4a"  ><?php esc_html_e('Create a Video-On-Demand (VOD)','wpstream');?></div>
        
            </div>


            <?php
        }

        /*
        *
        *
        *
        */
        /**
         * Onboarding step 3 (live): choose Free-To-View vs Pay-Per-View. Echoes HTML.
         *
         * @return void
         */
        public function wpstream_onboarding_step3_live_streaming(){
            // Below: the FTV/PPV choice step for live streaming.
            ?>
            
            <div class="wpstream_step_wrapper wpstream_step_3 wpstream_onboarding_live" id="wpstream_step_3">
            <h1><?php esc_html_e('Do you want to charge a fee for watching?','wpstream'); ?></h1>

                <div class="wpstream_accordion_header wpstream_action_next_step wpstream_step_3a" data-nextthing="wpstream_step_3a"><?php esc_html_e('No - Free-To-View (FTV)','wpstream');?></div>
                <div class="wpstram_or">or</div>
                <div class="wpstream_accordion_header wpstream_action_next_step" data-nextthing="wpstream_step_3b" ><?php esc_html_e('Yes - Pay-Per-View (PPV)','wpstream');?></div>
            
                <div class="wpstream_initial_onboarding_controls_wrapper">
                    <span class="wpstream_onboard_initial_bubble_prev" data-step="wpstream_step_2"><?php esc_html_e('Prev','wpstream');?></span>
                </div>

            </div>

         
            <?php
        }
        
        
        /*
        *
        *
        *
        */
        /**
         * Onboarding step 3A: create the first Free-To-View live channel (name +
         * create button). Echoes HTML.
         *
         * @return void
         */
        public function wpstream_onboarding_step_3_A_live_streaming_free_view(){
            // Below: the create-FTV-channel step markup.
            ?>
            
            <div class="wpstream_step_wrapper wpstream_step_3a wpstream_onboarding_live" id="wpstream_step_3a"> 
            <h1><?php esc_html_e('Let’s create your first FTV live channel','wpstream');?></h1>

                <div id="wpstream_onboard_live_notice" class="wpstream_onboarding_notification"></div>
                <div id="wpstream_onboard_live" class="wpstream_accordion_container">
                    <label>Name your first Free-To-View channel</label>
                    <input type="text" name="channel_name" id="wpstream_onboarding_channel_name" class="wpstream_onboarding_channel_name" value="<?php esc_html_e('My first FTV channel','wpstream');?>"  >
                    <input type="submit" name="submit" id="wpstream_on_board_create_channel" class="wpstream_button wpstream_button_action wpstream_onboard_live_action" value="<?php esc_html_e('Create Channel','wpstream');?>" />
              
                </div>
          
                <div class="wpstream_initial_onboarding_controls_wrapper">
                    <span class="wpstream_onboard_initial_bubble_prev" data-step="wpstream_step_2"><?php esc_html_e('Prev','wpstream');?></span>
                </div>

            </div>

            
            <?php
        }

           
        /*
        * 
        *
        *
        */

        /**
         * Onboarding step 3B: create the first Pay-Per-View live channel (name +
         * price). Requires WooCommerce; otherwise shows the Woo warning. Echoes HTML.
         *
         * @return void
         */
        public function wpstream_onboarding_step_3_B_live_streaming_pay_per_view(){
            // Below: the create-PPV-channel step markup.
            ?>
            
            <div class="wpstream_step_wrapper wpstream_step_3b wpstream_onboarding_live" id="wpstream_step_3b">
            <h1><?php esc_html_e('Make your live stream Pay Per View','wpstream');?></h1>
                <?php
                // PPV needs WooCommerce for the product; show a warning when missing.
                if ( class_exists( 'WooCommerce' ) ) {
                ?>
                <div id="wpstream_onboard_live_ppv_notice" class="wpstream_onboarding_notification"></div>
                <div id="wpstream_onboard_live_ppv" class="wpstream_accordion_container">
                    <label><?php esc_html_e('Choose a name for your channel','wpstream');?></label>
                    <input type="text" name="channel_name" id="wpstream_onboarding_channel_name_ppv" class="wpstream_onboarding_channel_name" value="<?php esc_html_e('My First PPV Channel','wpstream');?>">
                    <label><?php esc_html_e('Pay-Per-View Price ($)','wpstream');?></label>
                    <input type="text" name="channel_name" id="wpstream_onboarding_event_price_ppv" class="wpstream_onboarding_event_price" value="10">
                    <input type="submit" name="submit" id="wpstream_onboard_live_ppv_action" class="wpstream_button wpstream_button_action wpstream_onboard_live_ppv_action" value="<?php esc_html_e('Create Channel','wpstream');?>" />
        
                </div>
                <?php 
                } else {
                    $this->wpstream_onboarding_woo_warning();
                } ?>   


                <div class="wpstream_initial_onboarding_controls_wrapper">
                    <span class="wpstream_onboard_initial_bubble_prev" data-step="wpstream_step_2"><?php esc_html_e('Prev','wpstream');?></span>
                </div>
            </div>
            <?php
        }





        
        /*
        *  WooCommerce not installed Warning
        *
        *
        */
        /**
         * Print the "PPV needs WooCommerce" warning with install / try-again actions.
         *
         * @return void
         */
        public function wpstream_onboarding_woo_warning(){
            print'<div class="wpstream_warning_onboarding">
                '.esc_html__('Pay-Per-View streaming requires WooCommerce. Please install the WooCommerce plugin and try again.','wpstream').'
                </br>
                <div class="wpstream_install_plugin">'. esc_html__('Install WooCommerce','wpstream').'</div>

                <div class="wpstream_onboarding_tryagain">'.esc_html__('Try Again','wpstream').'</div>
            </div>';
        }


        /*
        * 
        *
        *
        */

        /**
         * Onboarding step 4 (VOD): choose Free-To-View vs Pay-Per-View. Echoes HTML.
         *
         * @return void
         */
        public function wpstream_onboarding_step4_vod(){
            // Below: the FTV/PPV choice step for VOD.
            ?>
            <div class="wpstream_step_wrapper wpstream_step_4 wpstream_onboarding_vod" id="wpstream_step_4">
            <h1><?php esc_html_e('Do you want to charge a fee for watching?','wpstream');?></h1>

                <div class="wpstream_accordion_header wpstream_action_next_step wpstream_step_4a" data-control="wpstream_onboard_vod_free"  data-nextthing="wpstream_step_4a" ><?php esc_html_e('No - Free-To-View (FTV)','wpstream'); ?></div>
                <div class="wpstram_or">or</div>
                <input type="hidden" id="wpstream_onboarding_video_list_nonce" value="<?php echo wp_create_nonce( "wpstream_onboarding_video_list_nonce" ); ?>">
                <div class="wpstream_accordion_header wpstream_action_next_step wpstream_step_4b" data-control="wpstream_onboard_vod_ppv"   data-nextthing="wpstream_step_4b" ><?php esc_html_e('Yes - Pay-Per-View (PPV)','wpstream'); ?></div>
                <div class="wpstream_initial_onboarding_controls_wrapper">
                    <span class="wpstream_onboard_initial_bubble_prev" data-step="wpstream_step_2"><?php esc_html_e('Prev','wpstream');?></span>
                </div>
      
            </div>
            <?php
        }


        /*
        * 
        *
        *
        */
        /**
         * Onboarding step 4A: create the first Free-To-View VOD (name + recording
         * chooser). Only rendered on the dedicated onboarding page. Echoes HTML.
         *
         * @return void
         */
        public function wpstream_onboarding_step_4_free_vod(){

            // Only render on the standalone onboarding admin page.
            $current_screen=get_current_screen();
            if($current_screen->base !=='wpstream_page_wpstream_onboard'){
                return;
            }
            // Below: the create-FTV-VOD step markup.
            ?>

            <div class="wpstream_step_wrapper wpstream_step_4a wpstream_onboarding_live" id="wpstream_step_4a">
            <h1><?php esc_html_e('Let’s create your first Free-To-View VOD','wpstream');?></h1>
                <div id="wpstream_onboard_vod_free_notice" class="wpstream_onboarding_notification"></div>
                <div id="wpstream_onboard_vod_free" class="wpstream_accordion_container">
                    <div class="spinner"></div>
                    <div class="wpstream-step-container" style="display: none">
                        <label><?php esc_html_e('Name your FTV Video-On-Demand','wpstream')?></label>
                        <input type="text" name="channel_name" class="wpstream_onboarding_vod_name" id="wpstream_onboarding_vod_name" value="<?php esc_html_e('My First FTV VOD','wpstream');?>" >
                        <div id="wpstream_free_vod_dropdown_videos_list"></div>
                        <input type="submit" name="submit"  class="wpstream_button wpstream_button_action wpstream_onboard_vod_free_action" id="wpstream_onboard_vod_free_action" value="Create FTV VOD">
                    </div>
                </div>
                <?php $this->wpstream_obboarding_file_warning(); ?>

                <div class="wpstream_initial_onboarding_controls_wrapper">
                    <span class="wpstream_onboard_initial_bubble_prev" data-step="wpstream_step_4"><?php esc_html_e('Prev','wpstream');?></span>
                </div>

            </div>

            
            <?php
        }

        /*
        * 
        *
        *
        */
        /**
         * Print the "no recordings available for a VOD" warning with upload /
         * try-again actions (hidden until the JS detects no recordings).
         *
         * @return void
         */
        public function wpstream_obboarding_file_warning(){
            print   '<div class="wpstream_warning_onboarding" style="display: none">
                        '.esc_html__('A recording is needed to create a VOD from. There are no recordings under your account. You can create new recordings by recording a live channel or uploading video files directly.','wpstream').'
                        </br>
                        <div class="wpstream_upload_video">'.esc_html__('Upload Video','wpstream').'</div>

                        <div class="wpstream_onboarding_tryagain">'.esc_html__('Try Again','wpstream').'</div>
                    </div>';
        }

        /*
        * 
        *
        *
        */
        /**
         * Onboarding step 4B: create the first Pay-Per-View VOD (name + recording
         * chooser + price). Requires WooCommerce and the onboarding page. Echoes HTML.
         *
         * @return void
         */
        public function wpstream_onboarding_step_4_ppv_vod(){
            // Only render on the standalone onboarding admin page.
            $current_screen=get_current_screen();

            if($current_screen->base !=='wpstream_page_wpstream_onboard'){
                return;
            }
            // Below: the create-PPV-VOD step markup.
            ?>

            <div class="wpstream_step_wrapper wpstream_step_4b wpstream_onboarding_live" id="wpstream_step_4b">
            <h1><?php esc_html_e('Let\'s create your first Pay-Per-View VOD','wpstream');?></h1>

            <div id="wpstream_onboard_vod_ppv_notice" class="wpstream_onboarding_notification"></div>
                <?php
                // PPV VOD needs WooCommerce; otherwise show the Woo warning.
                if ( class_exists( 'WooCommerce' ) ) { ?>
                <div id="wpstream_onboard_vod_ppv" class="wpstream_accordion_container">
                    <div class="spinner"></div>
                    <div class="wpstream-step-container" style="display: none">
                        <label><?php esc_html_e('Name your PPV Video-On-Demand','wpstream'); ?></label>
                        <input type="text" name="channel_name" class="wpstream_onboarding_ppv_vod_name" id="wpstream_onboarding_ppv_vod_name" value="<?php esc_html_e('My First PPV VOD','wpstream');?>">
                        <div id="wpstream_ppv_vod_dropdown_videos_list"></div>
                        <label><?php esc_html_e('Pay-Per-View Price','wpstream');?></label>
                        <input type="text" name="channel_name" class="wpstream_onboarding_vod_price" id="wpstream_onboarding_vod_price" value="10">
                        <input type="submit" name="submit"  class="wpstream_button wpstream_button_action wpstream_onboard_vod_ppv_action" id="wpstream_onboard_vod_ppv_action" value="<?php esc_html_e('Create PPV VOD','wpstream');?>" />
                    </div>
                </div>
                <?php $this->wpstream_obboarding_file_warning(); ?>
                <?php } else {
                    $this->wpstream_onboarding_woo_warning();
                } ?>
                <div class="wpstream_initial_onboarding_controls_wrapper">
                    <span class="wpstream_onboard_initial_bubble_prev" data-step="wpstream_step_4"><?php esc_html_e('Prev','wpstream');?></span>
                </div>
            </div>
            <?php
        }




        /*
        *
        * On boarding Header
        *
        */
        /**
         * Open the onboarding wizard wrapper (logo + close controls). Echoes HTML.
         *
         * @return void
         */
        public function onboarding_wizard_header() {
            $thumb= plugin_dir_url( dirname( __FILE__ ) ). 'img/logo_onboarding.svg';
            // Below: the wizard wrapper opening markup (closed by onboarding_wizard_footer()).
            ?>

            <div class="wpstream_on_boarding_wrapper">
                <div class="wpstream_close_onboarding wpstream_close_initial_onboarding"></div>
                <img class="wpstream_onboarding_logo" src="<?php echo esc_url($thumb); ?>" />
                <div class="wpstream_close_onboarding_warning"></div>

            <?php
        }





        /*
        *
        * On Boarding Footer
        *
        */
        /**
         * Close the onboarding wizard wrapper and print its modal background. Echoes HTML.
         *
         * @return void
         */
        public function onboarding_wizard_footer() {
            // Below: closes the wrapper opened by onboarding_wizard_header() and adds the modal backdrop.
            ?>
                </div>
                <div class="wpstream_modal_background_onboard"></div>
            <?php
        }



        /*
        *
        * On Boarding create PPV channel
        *
        */


        /**
         * AJAX: create a Pay-Per-View live channel during onboarding.
         *
         * Inserts a WooCommerce `product`, sets its price, marks it live_stream,
         * and returns the edit link (with onboarding query args) as JSON.
         * Nonce- and administrator-gated.
         *
         * @return void Ends with die().
         */
        public function wpstream_on_board_create_channel_ppv(){
            check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );
            $current_user           =   wp_get_current_user();

            if(current_user_can('administrator')){
                // Sanitize the submitted channel name and price.
                $channel_name   =   sanitize_text_field($_POST['channel_name']);
                $channel_price  =   floatval($_POST['channel_price']);
                $my_post = array(
                    'post_title'    => $channel_name,
                    'post_content'  => '',
                    'post_status'   => 'publish',
                    'post_type'     =>  'product',
                    'post_author'   => $current_user->ID
                );

                // Insert the post into the database
                $post_id = wp_insert_post( $my_post );

                if(is_wp_error($post_id)){
                    // Log the failure and report it to the client.
                    $logger = new WPStream_Logger();
                    $log_entry = new WpStream_Log_Entry([
                        'type'          => 'error',
                        'description'   => 'Couldn\'t create channel during onboarding because of error: ' . $post_id->get_error_message(),
                    ]);
                    $logger->add( $log_entry );
                    echo json_encode( array('succes'=>false) );
                }else{

                    // Apply the price to the new product.
                    $product    =   wc_get_product($post_id);
                    $price      =   wc_format_decimal($channel_price);

                    $product = wc_get_product( $post_id );
                    $product->set_price( $price );
                    $product->set_regular_price( $price ); // To be sure
                    $product->save();
                    // Reset event flag and mark it as a live stream product.
                    update_post_meta ($post_id,'event_passed',0);
                    wp_set_object_terms( $post_id, 'live_stream', 'product_type' );




                    // Return the edit link carrying the onboarding continuation args.
                    $permalink = get_edit_post_link($post_id);

                    $permalink= add_query_arg( 'onboard', 'yes', $permalink );
                    $permalink= add_query_arg( 'branch', '2', $permalink );


                    echo json_encode( array(
                        'success'=>  true,
                        'link'  =>  ($permalink)
                    ));
                }

            }
            die();
        }

        /*
        *
        * On Boarding create channel
        *
        */


        /**
         * AJAX: create a Free-To-View live channel during onboarding.
         *
         * Inserts a `wpstream_product` post and returns its edit link (with
         * onboarding query args) as JSON. Nonce- and administrator-gated.
         *
         * @return void Ends with die().
         */
        public function wpstream_on_board_create_channel(){
            check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );
            $current_user           =   wp_get_current_user();

            if(current_user_can('administrator')){
                // Sanitize the submitted channel name.
                $channel_name=sanitize_text_field($_POST['channel_name']);
                $my_post = array(
                    'post_title'    => $channel_name,
                    'post_content'  => '',
                    'post_status'   => 'publish',
                    'post_type'     =>  'wpstream_product',
                    'post_author'   => $current_user->ID
                  );

                  // Insert the post into the database
                $post_id = wp_insert_post( $my_post );

                if( is_wp_error( $post_id ) ) {
                    // Log the failure and report it to the client.
                    $logger = new WPStream_Logger();
                    $log_entry = new WpStream_Log_Entry([
                        'type'          => 'error',
                        'description'   => 'Couldn\'t create channel during onboarding because of error: ' . $post_id->get_error_message(),
                    ]);
                    $logger->add( $log_entry );
                    echo json_encode( array('succes'=>false) );
                } else {
                    // Return the edit link carrying the onboarding continuation args.
                    $permalink = get_edit_post_link($post_id);

                    $permalink= add_query_arg( 'onboard', 'yes', $permalink );
                    $permalink= add_query_arg( 'branch', '1', $permalink );

                    echo json_encode( array(
                        'success'=>  true,
                        'link'  =>  ($permalink)
                    ));
                }
            }
            die();
        }

        /*
        *
        * On Boarding create free vod
        *
        */
        /**
         * AJAX: create a Free-To-View VOD during onboarding.
         *
         * Inserts a `wpstream_product_vod` post, links the chosen recording, and
         * returns the edit link (with onboarding query args). Nonce/admin gated.
         *
         * @return void Ends with die().
         */
        public function wpstream_on_board_create_free_vod(){
            $current_user           =   wp_get_current_user();
            check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );
            if(current_user_can('administrator')){
                // Sanitize the VOD name and the source recording file name.
                $channel_name   =sanitize_text_field($_POST['channel_name']);
                $file_name      =sanitize_text_field($_POST['file_name']);
                $my_post = array(
                    'post_title'    => $channel_name,
                    'post_content'  => '',
                    'post_status'   => 'publish',
                    'post_type'     =>  'wpstream_product_vod',
                    'post_author'   => $current_user->ID
                  );

                // Insert the post into the database
                $post_id = wp_insert_post( $my_post );

                if(is_wp_error($post_id)){
                    $logger = new WPStream_Logger();
                    $log_entry = new WpStream_Log_Entry([
                        'type'          => 'error',
                        'description'   => 'Couldn\'t create free VOD during onboarding because of error: ' . $post_id->get_error_message(),
                    ]);
                    $logger->add( $log_entry );
                    echo json_encode( array('succes'=>false) );
                }else{
                    // Mark as a recording-type VOD and attach the chosen recording.
                    update_post_meta($post_id, 'wpstream_product_type', 2);
                    update_post_meta($post_id, 'wpstream_free_video', $file_name);


                    // Return the edit link carrying the onboarding continuation args.
                    $permalink = get_edit_post_link($post_id);

                    $permalink= add_query_arg( 'onboard', 'yes', $permalink );
                    $permalink= add_query_arg( 'branch', '3', $permalink );
                  
                    
                    echo json_encode( array(
                        'success'=>  true, 
                        'link'  =>  ($permalink) 
                    ));
                }
               
            }
            die();
        }


        /*
        *
        * On Boarding create ppv vod
        *
        */


        /**
         * AJAX: create a Pay-Per-View VOD during onboarding.
         *
         * Inserts a WooCommerce `product`, sets its price, links the recording,
         * marks it video_on_demand, and returns the edit link (with onboarding
         * query args). Nonce- and administrator-gated.
         *
         * @return void Ends with die().
         */
        public function wpstream_on_board_create_ppv_vod(){
            $current_user           =   wp_get_current_user();
            check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );

            if(current_user_can('administrator')){
                // Sanitize the VOD name, price and source recording file name.
                $channel_name   =   sanitize_text_field($_POST['channel_name']);
                $vod_price      =   floatval($_POST['vod_price']);
                $file_name      =   sanitize_text_field($_POST['file_name']);

                $my_post = array(
                    'post_title'    => $channel_name,
                    'post_content'  => '',
                    'post_status'   => 'publish',
                    'post_type'     =>  'product',
                    'post_author'   => $current_user->ID
                );

                // Insert the post into the database
                $post_id = wp_insert_post( $my_post );

                if( is_wp_error( $post_id ) ) {
                    $logger = new WPStream_Logger();
                    $log_entry = new WpStream_Log_Entry([
                        'type'          => 'error',
                        'description'   => 'Couldn\'t create PPV VOD during onboarding because of error: ' . $post_id->get_error_message(),
                    ]);
                    $logger->add( $log_entry );
                    echo json_encode( array('succes'=>false) );
                } else {

                    // Apply the price to the new product.
                    $product    =   wc_get_product($post_id);
                    $price      =   wc_format_decimal($vod_price);

                    $product = wc_get_product( $post_id );
                    $product->set_price( $price );
                    $product->set_regular_price( $price ); // To be sure
                    $product->save();
                    // Reset event flag, attach the recording, and mark it VOD.
                    update_post_meta ($post_id,'event_passed',0);
                    update_post_meta ($post_id,'_movie_url', $file_name);
                    wp_set_object_terms( $post_id, 'video_on_demand', 'product_type' );




                    // Return the edit link carrying the onboarding continuation args.
                    $permalink = get_edit_post_link($post_id);

                    $permalink= add_query_arg( 'onboard', 'yes', $permalink );
                    $permalink= add_query_arg( 'branch', '4', $permalink );
                  
                    
                    echo json_encode( array(
                        'success'=>  true, 
                        'link'  =>  ($permalink) ,
                        ' $file_name'=> $file_name,
                    ));
                }
               
            }
            die();
        }


        /*
        *
        * On Boarding login
        *
        */
        /**
         * AJAX: log in to WpStream during onboarding.
         *
         * Stores the submitted credentials, clears the token cache, then attempts
         * to fetch a token; returns success/failure JSON without ever exposing the
         * token to the client. Nonce- and administrator-gated.
         *
         * @return void Ends with die().
         */
        public function wpstream_on_board_login(){
            check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );

            if(current_user_can('administrator')){
                // Persist the submitted credentials (password stored raw).
                $username       = sanitize_text_field($_POST['api_username']);
                $password       = $_POST['api_password'];
                update_option('wpstream_api_username',$username);
                update_option('wpstream_api_password',$password);

                // Force a fresh token fetch.
                delete_transient( 'wpstream_token_api' );

                $token          =   $this->main->wpstream_live_connection->wpstream_get_token();
                $videos_list    =   $this->main->wpstream_live_connection->wpstream_get_videos();
                // cleanup any previous echo before sending json
                ob_end_clean();
                // !DO NOT SEND TOKEN TO THE CLIENT!
                if ($token){
                    // Authentication succeeded.
                    echo json_encode( array(
                        'success'=>  true
                    ));
                }
                else {
                    // Distinguish a hard CURL failure from bad credentials.
                    $text = get_option('wpstream_curl_failed') ?
                        'Login failed with critical error: ' . get_option('wpstream_curl_failed') :
                        'Wrong username or password!';
                    echo json_encode( array(
                        'success'=>  false,
                        'error' => $text,
                    ));
                }

            }else{
                // Non-admins may not connect the account.
                echo json_encode( array(
                    'success'=>  false,
                    'token'  =>  esc_html('You are not an administrator','wpstream')
                ));
            }
            die();
        }

        /**
         * Placeholder AJAX callback to refresh the captcha (currently a no-op).
         *
         * @return void
         */
        public function wpstream_register_refresh_capthca(){

            if(current_user_can('administrator')){

            }
        }

		/**
		 * AJAX proxy: fetch a captcha challenge from the baker API and return it.
		 * Used on HTTP sites where the Altcha widget cannot run (requires Web Crypto / HTTPS).
		 * The JS side solves the PoW locally and sends back the full base64 Altcha payload.
		 */
		public function wpstream_get_captcha_challenge() {
			// Fetch a challenge from the captcha ("baker") API.
			$api_url  = WPSTREAM_API . '/v2/user/getcaptcha';
			$response = wp_remote_get( $api_url, array( 'timeout' => 10 ) );

			// On transport failure, return an error JSON.
			if ( is_wp_error( $response ) ) {
				echo json_encode( array( 'success' => false, 'error' => 'Could not reach captcha service.' ) );
				die();
			}

			$body = wp_remote_retrieve_body( $response );
			// Forward the challenge JSON directly to the browser
			header( 'Content-Type: application/json' );
			echo $body;
			die();
		}

        /**
        * Solve a simple proof-of-work: find a nonce whose sha256(challenge.nonce)
        * hash begins with $difficulty leading zeros. (Server-side helper.)
        *
        * @param  string $challenge  Challenge string to hash against.
        * @param  int    $difficulty Number of required leading "0" hex chars.
        * @return int|null The solving nonce, or null if none found within the cap.
         */
        private function solve_pow( $challenge, $difficulty ) {
            // Start at nonce 0 and require this many leading zeros.
            $nonce = 0;
            $target = str_repeat( "0", $difficulty );

            // Brute-force up to a hard cap of attempts.
            while ( $nonce < 5000000 ) {
                $hash = hash( 'sha256', $challenge . $nonce );
                // Accept the first nonce whose hash starts with the target zeros.
                if ( strpos( $hash, $target ) === 0 ) {
                    return $nonce;
                }
                $nonce++;
            }
            return null; // Return null if no solution is found within the max attempts
        }
       
        /*
        *
        * On Boarding login
        *
        */
        /**
         * AJAX: register a new WpStream account during onboarding.
         *
         * Validates the email/password, requires an ALTCHA solution, calls the
         * account-create API, and on success stores the credentials and fetches a
         * token. Returns JSON. Nonce- and administrator-gated.
         *
         * @return void Ends with die().
         */
        public function wpstream_on_board_register(){
            check_ajax_referer( 'wpstream_onboarding_nonce', 'security' );
            if(current_user_can('administrator')){
                // Read submitted email/password (password kept raw).
                $wpstream_register_email            = sanitize_text_field($_POST['wpstream_register_email']);
                $wpstream_register_password         = $_POST['wpstream_register_password'];

                // Field-level validation; bail with the error payload if invalid.
                $validate = $this->wpstream_validate_onboard_register($wpstream_register_email,$wpstream_register_password);
                if(!$validate['success']){
                    // cleanup any previous echo before sending json
                    ob_end_clean();
                    echo json_encode($validate);
                    die();
                }

                // Require a captcha solution; message differs on HTTPS vs HTTP (widget readiness).
                $wpstream_altcha = isset($_POST['wpstream_altcha']) ? trim( $_POST['wpstream_altcha'] ) : '';
                $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

                if ( empty($wpstream_altcha) ) {
                    if ( $is_https ) {
                        $message = esc_html__('Captcha verification failed. Please try again.', 'wpstream');
                    } else {
                        $message = esc_html__('Security check not ready yet. Please wait a moment and try again.', 'wpstream');
                    }
                    echo json_encode( array( 'success' => false, 'message' => $message ) );
                    die();
                }

                // Call the account-create API with the captcha solution.
                $url='v2/user/create';
                $curl_post_fields=array(
                    'email'         =>     $wpstream_register_email,
                    'password'      =>     $wpstream_register_password,
                    'solution'      =>     $wpstream_altcha,
                    'captcha_id'    =>     '',
                );

                $curl_response          =   $this->main->wpstream_live_connection->wpstream_baker_do_curl_base($url,$curl_post_fields,true);
                $curl_response_decoded  =   json_decode($curl_response,JSON_OBJECT_AS_ARRAY);

                if($curl_response_decoded['success']){

                    if($curl_response_decoded['request']['registred']){
                        // we are registerd

                        // Store the new credentials and obtain a token.
                        update_option('wpstream_api_username',$wpstream_register_email);
                        update_option('wpstream_api_password',$wpstream_register_password);

                        $token          =   $this->main->wpstream_live_connection->wpstream_get_token();

                        // cleanup any previous echo before sending json
                        ob_end_clean();
                        echo json_encode( array(
                            'success'   =>  true,
                            'token'     =>  $token ,
                            'message'   =>  esc_html__('Your Account was created. Please stand by...','wpstream'),

                        ));
                        die();
                    }else{
                        // API reachable but registration was rejected (e.g. email taken).

                        // cleanup any previous echo before sending json
                        ob_end_clean();
                        echo json_encode( array(
                            'success'=>  false, 
                            'message'=> $curl_response_decoded['request']['message'],
                            'curl'=>$curl_response_decoded,
                            
                        ));
                        die();
                    }




                }else{
                    // API call itself failed.
                    // cleanup any previous echo before sending json
                    ob_end_clean();
                    echo json_encode( array(
                        'success'=>  false,
                        'message'=> esc_html__('Registration could not be completed. Please try again or register on wpstream.net','wpstream'),

                    ));
                    die();
                }







            }else{
                // Non-admins may not register the account.
                // cleanup any previous echo before sending json
                ob_end_clean();
                echo json_encode( array(
                    'success'=>  false,
                    'message'  =>  esc_html('You are not an administrator','wpstream')
                ));
                die();
            }

            die();
        }


        /*
        *
        * Validate for register
        *
        */
        /**
         * Validate the onboarding registration email + password.
         *
         * Checks for a non-empty, well-formed email whose domain has DNS records,
         * and a password of at least 5 characters.
         *
         * @param  string $wpstream_register_email    Submitted email.
         * @param  string $wpstream_register_password Submitted password.
         * @return array{success: bool, message?: string} Validation result.
         */
        public function wpstream_validate_onboard_register($wpstream_register_email,$wpstream_register_password){

            // Default to valid; each check below can override with an error.
            $return= array(
                'success'=>true
            );

            // Email must not be empty.
            if ($wpstream_register_email=='' ){
                $return= array(
                    'success'=>  false,
                    'message'  =>  esc_html('The email Field is Empty','wpstream')
                );
                return $return;die();
            }

            // Email must be syntactically valid.
            if(filter_var($wpstream_register_email,FILTER_VALIDATE_EMAIL) === false) {
                $return= array(
                    'success'=>  false,
                    'message'  =>  esc_html__('The email doesn\'t look right !','wpstream')
                );
                return $return;die();
            }


            // The email domain must resolve in DNS.
            $domain = mb_substr(strrchr($wpstream_register_email, "@"), 1);
            if( $domain!='' && !checkdnsrr ($domain) ){
                $return= array(
                    'success'=>  false,
                    'message'  =>  esc_html__('The email doesn\'t look right !','wpstream')
                );
                return $return;die();
            }



            // Enforce a minimum password length.
            if(strlen($wpstream_register_password)<5){
                $return= array(
                    'success'=>  false,
                    'message'  =>  esc_html('The password is too short. Please use at least 5 characters.','wpstream')
                );
                return $return;die();
            }

            return $return;die();
        }

    /**
     * Handle the AJAX request to initiate a multipart upload
     *
     * Validates the request, builds a clean filename, and asks the cloud API to
     * open a multipart S3 upload; returns the upload id and per-part pre-signed
     * URLs as JSON. Nonce- and administrator-gated.
     *
     * @since  3.0.1
     * @return void Responds via wp_send_json_*.
     */
    public function handle_initiate_multipart_upload() {
        check_ajax_referer( 'wpstream_multipart_upload_nonce', 'security' );

        // Security check - only admins can do this
        if (!current_user_can('administrator')) {
            wp_send_json_error('Unauthorized access');
            return;
        }

        // Get file details from request
        $file_name = sanitize_text_field($_POST['file_name']);
        $file_size = intval($_POST['file_size']);
        $content_type = sanitize_text_field($_POST['content_type']);
        $num_parts = intval($_POST['parts']);

        // Reject incomplete/invalid file metadata.
        if (empty($file_name) || $file_size <= 0 || $num_parts <= 0) {
            wp_send_json_error('Invalid file information');
            return;
        }

        // Prepare a clean filename (similar to the standard upload process)
        // Keep the extension, slugify the base name (spaces -> _, strip non-word chars).
        $file_name_array = explode(".", $file_name);
        $file_extension = $file_name_array[count($file_name_array) - 1];
        $temp_file_name = $file_name_array[0];
        $temp_file_name = str_replace(' ', '_', $temp_file_name);
        $temp_file_name = preg_replace('/\W/', '', $temp_file_name);
        $clean_file_name = $temp_file_name . '.' . $file_extension;

        // Make API call to initiate multipart upload
        $url = 'video/upload';
        $access_token = $this->main->wpstream_live_connection->wpstream_get_token();

        // Need an authenticated token to talk to the cloud.
        if (!$access_token) {
            wp_send_json_error('Not connected to WPStream service');
            return;
        }

        // Request the multipart upload initiation.
        $api_params = array(
            'access_token' => $access_token,
            'size' => $file_size,
            'name' => $clean_file_name,
            'content_type' => $content_type,
            'parts' => $num_parts
        );

        $response = $this->main->wpstream_live_connection->wpstream_baker_do_curl_base($url, $api_params, true);
        $response_data = json_decode($response, true);

        // Bubble up any API error.
        if (!isset($response_data['success']) || $response_data['success'] !== true) {
            $error_message = isset($response_data['error']) ? $response_data['error'] : 'Failed to initiate multipart upload';
            wp_send_json_error($error_message);
            return;
        }

        // Return the upload ID and pre-signed URLs for each part
        wp_send_json_success($response_data);
    }

    /**
     * Handle the AJAX request to complete a multipart upload
     *
     * Validates the request and tells the cloud API to finalize the multipart S3
     * upload for the given parts/handle. Nonce- and administrator-gated.
     *
     * @since  3.0.1
     * @return void Responds via wp_send_json_*.
     */
    public function handle_complete_multipart_upload() {
        check_ajax_referer( 'wpstream_multipart_upload_nonce', 'security' );

        // Security check - only admins can do this
        if (!current_user_can('administrator')) {
            wp_send_json_error('Unauthorized access');
            return;
        }

        // Get completion details
        $parts = json_decode(stripslashes($_POST['parts']), true);
        $file_name = sanitize_text_field($_POST['file_name']);
        $handle = sanitize_text_field($_POST['handle']);

        // Reject incomplete completion metadata. (Note: is_numeric($parts) checks the decoded parts array — see report.)
        if (empty($parts) || !is_numeric($parts) || empty($file_name)) {
            wp_send_json_error('Invalid completion information');
            return;
        }

        // Make API call to complete the multipart upload
        $url = 'video/upload';
        $access_token = $this->main->wpstream_live_connection->wpstream_get_token();

        // Need an authenticated token to talk to the cloud.
        if (!$access_token) {
            wp_send_json_error('Not connected to WPStream service');
            return;
        }

        // Send the finalize ("complete") request with the uploaded part list.
        $api_params = array(
            'access_token' => $access_token,
            'parts' => $parts,
            'name' => $file_name,
            'handle' => $handle,
            'action' => 'complete'
        );

        $response = $this->main->wpstream_live_connection->wpstream_baker_do_curl_base($url, $api_params, true);
        $response_data = json_decode($response, true);

        // Bubble up any API error.
        if (!isset($response_data['success']) || $response_data['success'] !== true) {
            $error_message = isset($response_data['error']) ? $response_data['error'] : 'Failed to complete multipart upload';
            wp_send_json_error($error_message);
            return;
        }

        // Return success
        wp_send_json_success();
    }

    /**
     * AJAX: update the WpStream plugin in place from the Settings/Support tab.
     *
     * Loads the upgrader APIs, initializes WP_Filesystem, runs the plugin
     * upgrade, re-activates the plugin, and returns success/error JSON.
     * Requires the update_plugins capability.
     *
     * @return void Responds via wp_send_json_*.
     */
    public function wpstream_settings_tab_update_plugin() {
        // Capability gate.
        if ( !current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( __( 'Not enough permissions to make this change', 'wpstream' ) );
		}

		// Load the core upgrade/filesystem APIs.
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/file.php';

		// Initialize the filesystem abstraction (needed to write plugin files).
		$credentials = request_filesystem_credentials('');
		if ( !WP_Filesystem( $credentials ) ) {
			wp_send_json_error( __( 'Failed to connect to the filesystem', 'wpstream' ) );
		}

		// Run the in-place upgrade for this plugin, then re-activate it.
		$upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
		$plugin_path = plugin_basename( WPSTREAM_PLUGIN_PATH . 'wpstream.php' );
		$result = $upgrader->upgrade( $plugin_path );
		activate_plugin( $plugin_path );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( __( 'Update failed due to', 'wpstream' ) . $result->get_error_message() );
		}

		wp_send_json_success();
	}
}
