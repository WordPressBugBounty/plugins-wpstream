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


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
         * Most parts a multipart VOD upload may be split into.
         *
         * The storage backend assembles at most this many parts, so a larger
         * split uploads every byte and still can never complete.
         *
         * @var int
         */
        const MULTIPART_MAX_PARTS = 1000;

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
        );

        /**
         * Filter the catalog of per-channel options shown as toggles.
         *
         * Each entry is `key => array( name, details, defaults )`. An added key
         * renders a toggle on the settings and channel screens; it is stored
         * only if the Channel Settings Module knows the key.
         *
         * @since 4.14.0
         *
         * @param array $options Option catalog keyed by setting.
         */
        $this->global_event_options = (array) apply_filters( 'wpstream_default_event_options', $this->global_event_options );
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
        // No external webfont: the admin UI falls back to the system font stack
        // declared in the stylesheets, so no request leaves the site for fonts.
        // Main admin stylesheet; filemtime() is used as the cache-busting version.
        wp_enqueue_style(
                $this->plugin_name,
                plugin_dir_url( __FILE__ ) . 'css/wpstream-admin.css',
                array(),
                filemtime(plugin_dir_path(__FILE__) . 'css/wpstream-admin.css' ),
                'all'
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
                        'recordings_nonce'         => wp_create_nonce( 'wpstream_recordings_nonce' ),
                        'loading_url'              => WPSTREAM_PLUGIN_DIR_URL.'/img/loading.gif',
                        'download_mess'            => esc_html__('Click to download!','wpstream'),
                        'uploading'                => esc_html__('We are uploading your file. Do not close this window!','wpstream'),
                        'upload_complete2'         => esc_html__('Upload Complete! You can upload another file!','wpstream'),
                        'not_accepted'             => esc_html__('The file is not an accepted video format','wpstream'),
                        'upload_complete'          => esc_html__('Upload Complete!','wpstream'),
                        'no_band'                  => esc_html__('Not enough streaming data.','wpstream'),
                        'no_band_no_store'         => esc_html__('Not enough streaming data or storage.','wpstream'),
                        'no_streaming_hours'       => esc_html__('Not enough streaming hours.','wpstream'),
                        'exceeding_limit'          => esc_html__('File size exceeds 5GB. Initiating multipart upload...','wpstream'),
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
						'use_streaming_hours'      => $this->main->quota_manager->get_streaming_ui_flags_from_cache()['use_streaming_hours'],
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


                    // Identify the current admin screen so screen-specific bundles load only where needed.
                    $current_screen=get_current_screen();

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
		    // Gates the broadcaster's diagnostic console logging (see broadcaster.js).
		    'debug' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
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
            add_menu_page( __('WpStream','wpstream'), __('WpStream ','wpstream'), 'administrator', 'wpstream_credentials', array($this,'wpstream_set_wpstream_credentials'), WPSTREAM_PLUGIN_DIR_URL.'img/wpstream-icon-menu_2.png',20 );
            // Credentials sub-page (same slug as the parent so it is the default view).
            add_submenu_page( 'wpstream_credentials', __('WpStream Credentials','wpstream'),          __('Credentials','wpstream'),          'administrator', 'wpstream_credentials',      array($this,'wpstream_set_wpstream_credentials') );
            // All Channels listing.
            add_submenu_page( 'wpstream_credentials', __('WpStream Live Channels','wpstream'),         __('All Channels','wpstream'),   'administrator', 'wpstream_live_channels',    array( $this,'wpstream_new_general_set'));
            // Recordings / media management.
            add_submenu_page( 'wpstream_credentials', __('WpStream Recordings','wpstream'), __('Recordings','wpstream'),  'administrator', 'wpstream_recordings',   array($this,'wpstream_media_management'));
            // Global plugin settings.
            add_submenu_page( 'wpstream_credentials', __('WpStream Settings','wpstream'),         __('Settings','wpstream'),  'administrator', 'wpstream_settings',   array($this,'wpstream_settings'));

            // Quick Start / onboarding entry point.
            add_submenu_page( 'wpstream_credentials', __('WpStream Quick Start','wpstream'),         __('WpStream Quick Start','wpstream'),  'administrator', 'wpstream_onboard',   array($this,'wpstream_pre_onboard_display'));




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
            return $this->main->get_live_channel_presentation()->render_channels_page();
        }
        public function wpstream_social_share($the_id){
            return $this->main->get_live_channel_presentation()->render_part( 'social_share', array( $the_id ) );
        }
        public function wpstream_live_stream_unit($the_id,$is_front=''){
            $context = '' === $is_front ? 'admin' : $is_front;
            return $this->main->get_live_channel_presentation()->render_channel( $the_id, $context );
        }
        public function wpstream_live_stream_unit_for_theme($the_id,$is_front=''){
            return $this->main->get_live_channel_presentation()->render_part( 'theme_channel', array( $the_id, $is_front ) );
        }
        public function wpstream_close_modal_button(){
            return $this->main->get_live_channel_presentation()->render_part( 'close_modal_button' );
        }
        public function wpstream_local_event_options_toggle( $is_basic_stream_mode = false ) {
            return $this->main->get_live_channel_presentation()->render_part( 'local_event_options_toggle', array( $is_basic_stream_mode ) );
        }
		public function wpstream_basic_stream_mode_message() {
			return $this->main->get_live_channel_presentation()->render_part( 'basic_stream_mode_message' );
		}
        public function wpstream_display_modal_seetings($the_id){
            return $this->main->get_live_channel_presentation()->render_part( 'settings_modal', array( $the_id ) );
        }
        public function wpstream_display_modal_share($the_id){
            return $this->main->get_live_channel_presentation()->render_part( 'share_modal', array( $the_id ) );
        }
        public function wpstream_display_modal_broadcast($the_id,$external_software_streaming_class,$obs_uri,$obs_stream){
            return $this->main->get_live_channel_presentation()->render_part(
                'broadcast_modal',
                array( $the_id, $external_software_streaming_class, $obs_uri, $obs_stream )
            );
        }
        public function wpstream_encoder_table(){
            return $this->main->get_live_channel_presentation()->render_part( 'encoder_table' );
        }
        public function wpstream_encoder_panel( $encoder, $obs_uri, $obs_stream, $visible = false ){
            return $this->main->get_live_channel_presentation()->render_part(
                'encoder_panel',
                array( $encoder, $obs_uri, $obs_stream, $visible )
            );
        }
        private function wpstream_settings_fields(){
            // Definition of every settings field, keyed and grouped by 'tab' (general, subscription, messages, default options, VOD defaults, support).
            return array(
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
        }

        /**
         * Stop the request unless the current user may manage plugin settings.
         *
         * Shared by the settings and credentials screens so both enforce the
         * same capability (`manage_options`).
         *
         * @return void
         */
        private function wpstream_require_settings_capability(){
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'Not enough permissions to make this change', 'wpstream' ) );
            }
        }

        /**
         * Render the WpStream Settings admin screen and persist submitted values.
         *
         * On POST it verifies the nonce, then stores only the fields declared for
         * the active tab as `wpstream_*` options (the streamer role is checked
         * against the site's roles, the "default options" tab folds its checkboxes
         * into one map), and flushes rewrite rules. It then renders the tabbed
         * form from the same field definitions. Echoes HTML.
         *
         * @return void
         */
        public function wpstream_settings(){

            // Admin-only screen.
            $this->wpstream_require_settings_capability();


            $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general_options';

            /**
             * Filter the settings field definitions.
             *
             * Field definitions drive both the save allow-list and the rendered
             * form, so an added field (`tab`, `label`, `name`, `type`, `details`)
             * is rendered on its tab and saved as the `wpstream_{name}` option
             * through sanitize_text_field().
             *
             * @since 4.14.0
             *
             * @param array  $fields     Field definitions.
             * @param string $active_tab Tab being rendered or saved.
             */
            $wpstream_settings_array = (array) apply_filters( 'wpstream_settings_fields', $this->wpstream_settings_fields(), $active_tab );

            /**
             * Filter the settings tabs.
             *
             * Added tabs render their fields from `wpstream_settings_fields`
             * and fire `wpstream_settings_tab_{slug}` for a custom body.
             *
             * @since 4.14.0
             *
             * @param array  $tabs       Tab slug => label.
             * @param string $active_tab Tab being rendered or saved.
             */
            $tabs = (array) apply_filters(
                'wpstream_settings_tabs',
                array(
                    'general_options'      => esc_html__( 'General Options', 'wpstream' ),
                    'default_options'      => esc_html__( 'Default Channel Settings', 'wpstream' ),
                    'default_options_vod'  => esc_html__( 'VOD Settings', 'wpstream' ),
                    'subscription_options' => esc_html__( 'Subscription Options', 'wpstream' ),
                    'messages_options'     => esc_html__( 'Customize Messages', 'wpstream' ),
                    'support_tab'          => esc_html__( 'Support', 'wpstream' ),
                ),
                $active_tab
            );

            // Handle a settings submission.
            if($_SERVER['REQUEST_METHOD'] === 'POST'){
                // CSRF protection.
                check_admin_referer( 'wpstream-settings-nonce', 'wpstream-settings-nonce' );

                // Only the fields declared for the active tab may be persisted.
                $allowed = array();
                foreach ( $wpstream_settings_array as $field ) {
                    if ( isset( $field['tab'], $field['name'] ) && $field['tab'] === $active_tab ) {
                        $allowed[] = $field['name'];
                    }
                }

                // Option values as they were before this submission, and as written by it
                // (both keyed by option name) — announced together once the save is complete.
                $previous_options = array();
                $saved_options    = array();

                // If the "channel type" section was shown but no streamer role was chosen, clear the stored role.
                if( isset($_POST['user_streaming_channel_type_hidden']) && intval($_POST['user_streaming_channel_type_hidden'])==1  && !isset($_POST['stream_role'])    ){
                     $previous_options['wpstream_stream_role'] = get_option( 'wpstream_stream_role' );
                     $saved_options['wpstream_stream_role']    = '';
                     update_option( sanitize_key('wpstream_stream_role'), '' );
                }

                // Persist each declared field as a sanitized wpstream_* option; anything else is ignored.
                foreach ( $allowed as $variable ) {
                    if ( ! isset( $_POST[ $variable ] ) ) {
                        continue;
                    }
                    $value = $_POST[ $variable ];

                    // Streamer role is a list of role slugs; keep only the roles the form offers
                    // (editable roles minus administrator, who can always broadcast).
                    if ( 'stream_role' === $variable ) {
                        $known = array_diff( array_keys( get_editable_roles() ), array( 'administrator' ) );
                        $roles = array_values( array_intersect( (array) $value, $known ) );
                        $previous_options['wpstream_stream_role'] = get_option( 'wpstream_stream_role' );
                        $saved_options['wpstream_stream_role']    = $roles;
                        update_option( 'wpstream_stream_role', $roles );
                        continue;
                    }

                    $option_name                      = sanitize_key( 'wpstream_' . $variable );
                    $previous_options[ $option_name ] = get_option( $option_name );
                    $saved_options[ $option_name ]    = sanitize_text_field( $value );
                    update_option( $option_name, $saved_options[ $option_name ] );
                }


                // On the "default options" tab, fold the per-option checkboxes into a single 1/0 map.
                if ( 'default_options' === $active_tab ) {
                    $event_settings=array();
                    foreach($this->global_event_options as $key=>$option){
                        $event_settings[$key]='';
                        if(isset($_POST['wpstream_event_set_'.$key]) && $_POST['wpstream_event_set_'.$key]=='on'){
                            $event_settings[$key]=1;
                        }else{
                            $event_settings[$key]=0;
                        }
                    }
					$this->main->channel_settings->apply(
						array(
							'type'    => 'save_defaults',
							'options' => $event_settings,
						)
					);
                }




                // reset permalinkgs
                // Slug settings can change permalinks, so drop and rebuild the rewrite rules.
                global $wp_rewrite;

                update_option( "rewrite_rules", FALSE );
                $wp_rewrite->flush_rules( true );

                /**
                 * Fires after a WpStream Settings tab was saved and the rewrite
                 * rules rebuilt. Default Channel Settings saved from the
                 * "default options" tab are announced separately by
                 * wpstream_default_channel_settings_saved.
                 *
                 * @since 4.14.0
                 *
                 * @param string $active_tab Settings tab that was submitted.
                 * @param array  $saved      Option values written, keyed by option name.
                 * @param array  $previous   The values those options held before.
                 */
                do_action( 'wpstream_settings_saved', $active_tab, $saved_options, $previous_options );

            }




                // Open the settings panel and the form.
                print '<div class="theme_options_tab_wpstream" style="display:block;" >
                    <h1>'.__('WpStream Settings','wpstream').'</h1>
                    <form method="post" action="" >';

                // Tab navigation bar; the current tab gets the nav-tab-active class.
                print '<h2 class="nav-tab-wrapper">';
                foreach ( $tabs as $tab_slug => $tab_label ) {
                    $tab_slug = sanitize_key( $tab_slug );
                    print '<a href="' . esc_url( add_query_arg( array( 'page' => 'wpstream_settings', 'tab' => $tab_slug ), admin_url( 'admin.php' ) ) ) . '" class="nav-tab ' . ( $active_tab === $tab_slug ? 'nav-tab-active' : '' ) . '">' . esc_html( $tab_label ) . '</a>';
                }
                print '</h2>';
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
                                                print '<label for="'.esc_attr( $option['name'] ).'">'.wp_kses_post( $option['label'] ).'</label>';
                                                print $this->wpstream_select_user_roles(esc_attr( $option['name'] ),$options_value);
                                                print '<div class="settings_details">'.wp_kses_post( $option['details'] ).'</div>';
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

                                                print '<label for="'.esc_attr( $option['name'] ).'">'.wp_kses_post( $option['label'] ).'</label>';
                                                print '<input class="wpstream-text-input-setting" id="'.esc_attr( $option['name'] ).'" type="'.$option['type'].'" size="36"  name="'.esc_attr( $option['name'] ).'" value="'.esc_attr($options_value).'" />';
                                                print '<div class="settings_details">'.wp_kses_post( $option['details'] ).'</div>';
                                                break;
                                            // Dropdown built from the field's select_values map; a hidden mirror marks the field as present.
                                            case 'select':
                                                print '<label for="'.esc_attr( $option['name'] ).'">'.wp_kses_post( $option['label'] ).'</label>';
                                                print '<select id="'.esc_attr( $option['name'] ).'"  name="'.esc_attr( $option['name'] ).'"  >';
                                                    foreach($option['select_values'] as $key=>$value){
                                                        print '<option value="'.$key.'" ';
                                                        // Pre-select the currently stored value.
                                                        if( $key == esc_html($options_value) ){
                                                            print ' selected ';
                                                        }
                                                        print '>'.$value.'</option>';
                                                    }
                                                print '</select>';
                                                print '<input type="hidden" name="'.esc_attr( $option['name'] ).'_hidden" value="1" >';
                                                print '<div class="settings_details">'.wp_kses_post( $option['details'] ).'</div>';
                                                break;
                                            // On/off switch; a hidden 0-valued twin ensures "off" posts a value.
                                            case 'slidertoogle':
                                                print '<label for="'.esc_attr( $option['name'] ).'">'.wp_kses_post( $option['label'] ).'</label>';
                                                print '<div style="display: flex; gap: 25px; justify-content: space-between;">';
                                                print '<div class="settings_details">'.wp_kses_post( $option['details'] ).'</div>';
                                                print '<label class="wpstream_switch">
                                                      <input type="hidden" class="wpstream_event_option_itemc" value="0" name="'.esc_attr( $option['name'] ).'" >
                                                      <input type="checkbox" class="wpstream_event_option_itemc" value="1" name="'.esc_attr( $option['name'] ).'" ';
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

                                                print '<label for="' . esc_attr( $option['name'] ) . '">' . wp_kses_post( $option['label'] ) . '</label>';
                                                print '<div class="wpstream-image-upload-wrapper">';
                                                print '<input type="hidden" id="' . esc_attr( $option['name'] ) . '" name="' . esc_attr( $option['name'] ) . '" value="' . $image_url . '" />';

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
                                                print '<div class="settings_details">' . wp_kses_post( $option['details'] ) . '</div>';
                                                break;
                                            // 0-100 range slider.
                                            case 'range':
                                                print '<label for="'.esc_attr( $option['name'] ).'">'.wp_kses_post( $option['label'] ).'</label>';
                                                print '<input class="wpstream-range-input" type="range" id="'.esc_attr( $option['name'] ).'" name="'.esc_attr( $option['name'] ).'" min="0" max="100" step="10" value="'.esc_attr($options_value).'" />';
                                                print '<div class="settings_details">'.wp_kses_post( $option['details'] ).'</div>';
                                                break;
                                            // Delegates to the Support tab renderer (plugin error log table).
                                            case 'logs_table':
                                                $this->wpstream_support_tab();
                                                break;
                                        }
                                   print '</div>';
                               }

                                /**
                                 * Fires inside the options wrapper of a settings tab, after its fields.
                                 *
                                 * The dynamic part is the tab slug. Callbacks echo their own markup
                                 * and own its escaping; fields they add to the form are saved only
                                 * when declared through `wpstream_settings_fields`.
                                 *
                                 * @since 4.14.0
                                 *
                                 * @param string $active_tab Tab being rendered.
                                 */
                                do_action( 'wpstream_settings_tab_' . $active_tab, $active_tab );
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
        // API is "connected" when a WpStream cloud token can be obtained.
        $api_status = false;
        if (method_exists($this->main->wpstream_live_connection, 'is_connected')) {
            $api_status = $this->main->wpstream_live_connection->is_connected();
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
                        // Check state: only a stored value of 1 renders the switch on.
                        // An absent key resolves to off everywhere the settings are
                        // consumed, so the screen must not promise otherwise.
                        if( isset($value[$key]) && intval($value[$key]) !== 0 ){
                            print ' checked ';
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
			$credentials_from_constants = wpstream_api_credentials_from_constants();

            // Handle a credentials submission.
            if($_SERVER['REQUEST_METHOD'] === 'POST'){
                // CSRF/permission gate: only an admin submitting the credentials
                // form (with its nonce) may overwrite the stored API credentials.
                $this->wpstream_require_settings_capability();
                check_admin_referer( 'wpstream_save_credentials', 'wpstream_credentials_nonce' );

				// wp-config owns the complete pair. A forged/manual POST must be the
				// same no-op as the disabled form presented to the administrator.
				if ( ! $credentials_from_constants ) {

                $allowed_html   =   array();
                $exclude_array  =   array();
                $allowed_html   =   array();

                // Username before this submission: tells a cleared form apart from a new account.
                $previous_username = (string) get_option( 'wpstream_api_username', '' );

                // Persist only the recognised credential fields.
                foreach($_POST as $variable=>$value){
                    if ($variable!='submit'){
                        if (!in_array($variable, $exclude_array) ){
                            switch ( $variable ) {
                                case 'api_username':
                                    // Unslashed first: WordPress slashes every $_POST value,
                                    // so a username containing a quote would otherwise be
                                    // stored with a stray backslash and never authenticate.
                                    // Then sanitized as text.
                                    update_option( sanitize_key('wpstream_api_username'), sanitize_text_field( wp_unslash( $value ) ) );
                                    break;
                                case 'api_password':
                                    // Only unslashed, never sanitized: the password is a secret
                                    // sent verbatim to the API, and sanitizing would silently
                                    // change it. Unslashing undoes the escaping WordPress adds
                                    // to every $_POST value, without which a password holding a
                                    // quote or a backslash is stored — and sent — wrong, so the
                                    // customer is told their correct password is incorrect.
                                    // Not autoloaded: it is only needed when authenticating.
                                    // An empty submission keeps the current password, since the
                                    // form never pre-fills the field with the stored secret.
                                    $password = wp_unslash( $value );
                                    if ( $password !== '' ) {
                                        update_option( 'wpstream_api_password', $password, false );
									} elseif (
										array_key_exists( 'api_username', $_POST )
										&& '' === sanitize_text_field( wp_unslash( $_POST['api_username'] ) )
									) {
										// Clearing the account is an explicit disconnect, not a
										// password-only edit. Do not retain an orphaned secret.
										delete_option( 'wpstream_api_password' );
                                    }
                                    break;
                            }
                        }
                    }
                }



                // Invalidate any cached token/quota so the new credentials are used immediately.
                update_option('wpstream_token_expire',0);
                update_option('wpstream_current_token',' ');
                delete_transient( 'wpstream_token_api');
                delete_transient('wpstream_token_request_30');
                $this->main->quota_manager->invalidate();

                // Announce the account change. Only the username travels: the
                // password and any token stay server-side.
                $username = (string) get_option( 'wpstream_api_username', '' );
                if ( '' !== $username ) {
                    /**
                     * Fires after WpStream account credentials were saved and the
                     * cached token/quota dropped; the connection itself is verified
                     * on the next API call.
                     *
                     * @since 4.14.0
                     *
                     * @param string $username WpStream.net username or e-mail.
                     */
                    do_action( 'wpstream_account_connected', $username );
                } elseif ( '' !== $previous_username ) {
                    /**
                     * Fires after the WpStream account username was cleared.
                     *
                     * @since 4.14.0
                     *
                     * @param string $username The username that was removed.
                     */
                    do_action( 'wpstream_account_disconnected', $previous_username );
                }
				}
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


            // Existing installs saved the password as an autoloaded option; flip it
            // to non-autoloaded so the secret is not loaded on every request.
            if ( function_exists( 'wp_set_option_autoload' ) ) {
                wp_set_option_autoload( 'wpstream_api_password', false );
            }

            // Connectivity and quota data drive the connection-status banner below.
            $connected    = $this->main->wpstream_live_connection->is_connected();
            $pack_details = $this->main->quota_manager->get_live_quota_data( 'wpstream_set_wpstream_credentials' );

            $this->main->show_user_data($pack_details);

            print   '<form method="post" action="" >';
                        // CSRF nonce for the credentials save handled at the top of this method.
                        wp_nonce_field( 'wpstream_save_credentials', 'wpstream_credentials_nonce' );
                        print '<div  class="theme_options_tab_wpstream" style="display:block;" >
                                <h1>'.__('WpStream Credentials','wpstream').'</h1>';

                                // Credentials supplied via wp-config.php constants take
                                // precedence over anything saved here — tell the admin.
                                if( $credentials_from_constants ){
                                    echo '<div class="api_conected">'.esc_html__('Your WpStream credentials are defined in wp-config.php; values saved on this screen are ignored.','wpstream').'</div>';
                                }

                                // Connection status banner: no credentials, bad credentials, connected, or CURL failure.
                                if( wpstream_get_api_username()=='' ||  wpstream_get_api_password()== '' ){
                                    echo '<div class="api_not_conected wpstream_orange">';
                                        $admin_url_onboard=get_admin_url().'admin.php?page=wpstream_onboard';
                                        printf ( __('To connect your plugin, enter your WpStream credentials below or go <a href="%s" target="_blank">here</a> to create an account.','wpstream'),$admin_url_onboard);
                                    echo '</div>';

                                }else if(!$connected){
                                    // Translate the literals at definition — gettext cannot extract variable strings.
                                    $text = get_option('wpstream_curl_failed') === "0" ?
                                        __( ' Incorrect username or password. Please check your credentials or go <a href="https://wpstream.net/my-account/edit-account/" target="_blank">here</a> to reset your password.', 'wpstream' ) :
                                        __( 'Not connected to WpStream. Please note the errors above and contact support.', 'wpstream' );
                                    echo '<div class="api_not_conected">'.wp_kses_post($text).'</div>';
                                }else{
                                    // Credentials present and a token was obtained: connected.
                                    echo '<div class="api_conected">'.__('Connected to WpStream.net!','wpstream').'</div>';
                                }
                                // Render the credential inputs. The username is pre-filled;
                                // the password field is always left blank so the stored
                                // secret is never echoed into the page source (an empty
                                // submission keeps the saved password). Inputs are disabled
                                // when the wp-config constants are in charge.
                                $inputs_disabled = $credentials_from_constants ? ' disabled' : '';
                                print '<div class="wpstream_option_wrapper">';
                                    foreach ($wpstream_options_array as $key=>$option){
                                        print '<div class="wpstream_option">';

                                            print '<label for="'.$option['name'].'">'.$option['label'].'</label>';
                                            if( $option['type']==='password' ){
                                                $placeholder = get_option('wpstream_'.$option['name'],'') !== '' ? '*****' : '';
                                                print '<input id="'.$option['name'].'" type="password" size="36"  name="'.$option['name'].'" value="" autocomplete="new-password" placeholder="'.$placeholder.'"'.$inputs_disabled.' />';
                                            }else{
                                                $options_value =  esc_html( get_option('wpstream_'.$option['name'],'') );
                                                print '<input id="'.$option['name'].'" type="'.$option['type'].'" size="36"  name="'.$option['name'].'" value="'.esc_html($options_value).'"'.$inputs_disabled.' />';
                                            }

                                        print '</div>';
                                    }
                                print '</div>';


                            print '<input type="submit"'.$inputs_disabled.' name="submit"  class="wpstream_button wpstream_button_action" value="'.__('Save Changes','wpstream').'" />';

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
                print '<a href="'.esc_url($link_new).'"      class="wpstream_no_chanel_add_channel">'.esc_html__('My Channels','wpstream').'</a>';        
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
                        // Keep the service-provided Source Recording identity
                        // separate from its context-specific HTML rendering.
                        $video_size        = intval($video['size']/1048576);
                        $video_source_name = (string) $video['name'];
                        $video_name        = esc_html($video_source_name);
                        if($video_name!=''):
                            $to_return.='<div class="wpstream_video_wrapper">';

                                $to_return.='<div class="wpstream_video_title">';
                                $to_return.='<div class="wpstream_video_notice"></div></div>';
                                $to_return.='<div class="wpstream_video_title"><strong class="storage_file_name">'.esc_html__('File Name:','wpstream').'</strong>'.'<span class="storage_file_name_real">'.$video_name.'</span><span class="storage_file_size">'.$video_size.' MB </span></div>';
                                // Delete control. The shared JS utility owns the
                                // confirmation; the renderer supplies inert data
                                // only so any valid recording name remains safe.
                                $to_return.='<div class="wpstream_delete_media" data-filename="'.esc_attr($video_source_name).'">'.esc_html__('delete file','wpstream').'</div>';
                                // Download trigger (JS fetches a time-limited signed URL) and the resulting link.
                                $to_return.='<div class="wpstream_get_download_link" data-filename="'.$video_name.'">'.esc_html__('download','wpstream').'</div>';
                                $to_return.='<a href="" class="wpstream_download_link">'.esc_html__('Click to download! The url will work for the next 20 minutes!','wpstream').'</a>';

                                // Pre-fill new-VOD links (free and, if WooCommerce present, paid) with this recording's name.
                                $add_free_video_url=admin_url('post-new.php?post_type=wpstream_product_vod').'&new_video_name='. rawurlencode($video_source_name);
                                $add_paid_video_url=admin_url('post-new.php?post_type=product').'&new_video_name='. rawurlencode($video_source_name);



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
         * Preserve the legacy free-Channel publish marker.
         *
         * Channel Settings initialization is coordinated by the Streaming Content
         * Creation Module; this compatibility hook only maintains historical meta.
         *
         * @since  3.0.1
         * @param  int     $post_id Post ID being published.
         * @param  WP_Post $post    Post object being published.
         * @return void
         */
        public function wpstream_publish_wpstream_product($post_id,$post){
            // Only applies to free channel posts.
            if( $post->post_type == 'wpstream_product' ){
                // Preserve the legacy marker used by existing installations.
                update_post_meta ($post_id,'local_event_options_test','working_on_'.$post_id);
            }
        }

        /**
         * Initialize existing Streaming Content when it is published or saved.
         *
         * The shared Module performs safe legacy repair and retries cloud provisioning
         * for Live Channels. VODs receive only their applicable local initialization.
         *
         * @param  int     $post_id Post ID being published.
         * @param  WP_Post $post    Post object being published.
         * @return void
         */
        public function wpstream_create_remote_channel_on_publish( $post_id, $post ) {
            // Skip autosaves and revisions (they are not real publishes).
            if ( defined( 'DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
                return;
            }
            if ( wp_is_post_revision( $post_id ) ) {
                return;
            }
            $this->main->streaming_content_creation->initialize_existing( $post_id );
        }

        /**
         * Initialize a manually saved paid streaming product after WooCommerce
         * has persisted its product-type taxonomy. The earlier publish hook may
         * run while a new product still looks like `simple`; this later adapter
         * enters the same idempotent creation policy once `live_stream` or
         * `video_on_demand` is observable.
         *
         * @param int $post_id WooCommerce product ID.
         * @return void
         */
        public function wpstream_initialize_paid_streaming_product( $post_id ) {
            if ( 'product' !== get_post_type( $post_id ) ) {
                return;
            }
            if ( ! has_term( array( 'live_stream', 'video_on_demand' ), 'product_type', $post_id ) ) {
                return;
            }
            $this->main->streaming_content_creation->initialize_existing( $post_id );
        }

        /**
         * Delete the remote channel when its Live Channel post is deleted for good.
         *
         * Hooked to before_delete_post: fires only on permanent deletion, never on
         * trash, so trashing stays reversible and a restored channel keeps its
         * cloud identity. Only a Live Channel that was actually Provisioned
         * (`channelId` meta present) has anything at the service to remove.
         *
         * The local deletion proceeds whatever the service answers — this hook
         * cannot cancel it, and a channel the customer removed must leave the
         * site either way.
         *
         * @param  int     $post_id Post ID being permanently deleted.
         * @param  WP_Post $post    Post object being permanently deleted.
         * @return void
         */
        public function wpstream_delete_remote_channel_on_delete( $post_id, $post ) {
            // Only Live Channel posts: the free post type, or a WooCommerce
            // product of the live_stream type (paid channels).
            $is_live_channel = $post instanceof WP_Post
                && ( 'wpstream_product' === $post->post_type
                    || ( 'product' === $post->post_type && has_term( 'live_stream', 'product_type', $post_id ) ) );
            if ( ! $is_live_channel ) {
                return;
            }

            // A channel that never obtained a cloud identity has nothing to delete.
            if ( '' === (string) get_post_meta( $post_id, 'channelId', true ) ) {
                return;
            }

            $this->main->wpstream_live_connection->wpstream_delete_channel( $post_id );
        }

        /**
         * Restore a paid Live Channel as a draft instead of immediately putting
         * it back on sale. WooCommerce restores ordinary products to their
         * previous status; a channel requires the seller to republish it.
         *
         * @param string $new_status      Status selected by earlier filters.
         * @param int    $post_id         Restored post ID.
         * @param string $previous_status Status before trashing.
         * @return string
         */
        public function wpstream_paid_live_untrash_status( $new_status, $post_id, $previous_status ) {
            if ( 'product' === get_post_type( $post_id ) && has_term( 'live_stream', 'product_type', $post_id ) ) {
                return 'draft';
            }
            return $new_status;
        }

		/** Invalidate VOD DRM lookup state whenever publication visibility changes. */
		public function wpstream_invalidate_vod_drm_on_status_change( $new_status, $old_status, $post ) {
			if ( $post instanceof WP_Post && 'wpstream_product_vod' === $post->post_type && $new_status !== $old_status ) {
				$this->main->drm_key_delivery->invalidate_vod_lookup( $post->ID );
			}
		}

		/** Invalidate VOD DRM lookup state before its key-index metadata is deleted. */
		public function wpstream_invalidate_vod_drm_on_delete( $post_id, $post ) {
			if ( $post instanceof WP_Post && 'wpstream_product_vod' === $post->post_type ) {
				$this->main->drm_key_delivery->invalidate_vod_lookup( $post_id );
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
				$previous_vod_type = 'wpstream_product_vod' === $post->post_type
					? intval( get_post_meta( $post_id, 'wpstream_product_type', true ) )
					: 0;
				$previous_recording = 'wpstream_product_vod' === $post->post_type
					? (string) get_post_meta( $post_id, 'wpstream_free_video', true )
					: '';

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

				if ( 'wpstream_product_vod' === $post->post_type && isset( $_POST['wpstream_product_type'] ) ) {
					$new_vod_type = intval( $_POST['wpstream_product_type'] );
					if ( 3 === $new_vod_type ) {
						$this->wpstream_retire_vod_recording_identity( $post_id );
						delete_post_meta( $post_id, 'wpstream_free_video' );
					} elseif ( 2 === $new_vod_type ) {
						$new_recording = isset( $_POST['wpstream_free_video'] ) && is_scalar( $_POST['wpstream_free_video'] )
							? sanitize_text_field( wp_unslash( $_POST['wpstream_free_video'] ) )
							: $previous_recording;
						if ( 2 !== $previous_vod_type || $new_recording !== $previous_recording ) {
							$this->wpstream_retire_vod_recording_identity( $post_id );
						}
						delete_post_meta( $post_id, 'wpstream_free_video_external' );
					}
				}

            endif;

        }

		/** Retire key and hosted-player metadata owned by a VOD's old recording. */
		private function wpstream_retire_vod_recording_identity( $post_id ) {
			$this->main->drm_key_delivery->invalidate_vod_lookup( $post_id );
			foreach ( array( 'hlsDecryptionKey', 'hlsDecryptionKeyIndex', 'wpstream_vod_video_data', 'wpstream_vod_embed_key', 'wpstream_vod_embed_url' ) as $meta_key ) {
				delete_post_meta( $post_id, $meta_key );
			}
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

            /**
             * Fires after the plugin registered its metaboxes for the edit screen.
             *
             * @since 4.14.0
             *
             * @param int     $post_id Post being edited.
             * @param WP_Post $post    The post.
             */
            do_action( 'wpstream_registered_metaboxes', intval( $post_id ), $post );
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
                    isset($term_list[0]->slug) &&
                    in_array($term_list[0]->slug, ['live_stream', 'video_on_demand', 'wpstream_bundle'])
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
				print '</div>';

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

                /**
                 * Fires at the end of the Video On Demand Settings metabox.
                 *
                 * Callbacks echo their own fields and own their escaping; saving
                 * them is the callback's job (hook `save_post`).
                 *
                 * @since 4.14.0
                 *
                 * @param int     $post_id VOD post ID.
                 * @param WP_Post $post    The post.
                 */
                do_action( 'wpstream_vod_meta_options_after', intval( $post->ID ), $post );
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
            $types[ 'live_stream' ]             = __( 'Live Channel','wpstream' );
            $types[ 'video_on_demand' ]         = __( 'Video On Demand','wpstream' );

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
			/**
			 * Filter the product-type slug => WC_Product subclass map.
			 *
			 * A mapped class must exist; unknown classes leave WooCommerce's own
			 * resolution in place.
			 *
			 * @since 4.14.0
			 *
			 * @param array $map Slug => class name.
			 */
			$map = (array) apply_filters(
				'wpstream_product_class_map',
				array(
					'live_stream'     => 'WC_Product_Live_Stream',
					'video_on_demand' => 'WC_Product_Video_On_Demand',
				)
			);
			if ( isset( $map[ $product_type ] ) && class_exists( $map[ $product_type ] ) ) {
				$classname = $map[ $product_type ];
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
					require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-product-live-stream.php';
					require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-product-video-on-demand.php';
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

            // On the first product save WooCommerce has not necessarily made
            // the selected taxonomy term observable through wc_get_product()
            // yet. Trust only its allow-listed product-type form field here;
            // the save handler remains scoped to WooCommerce product metadata.
            if ( isset( $_POST['product-type'] ) && is_scalar( $_POST['product-type'] ) ) {
                $submitted_type = sanitize_key( wp_unslash( $_POST['product-type'] ) );
                if ( in_array( $submitted_type, array( 'live_stream', 'video_on_demand', 'wpstream_bundle', 'subscription' ), true ) ) {
                    return true;
                }
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

            // Stable type slug is '' when the product has no term (never a crash).
            $product_type_slug      =       wpstream_get_product_type_slug($product_id);

            // Global subscription ("Netflix") mode flag.
            $subscription_model     =       intval( get_option('wpstream_global_sub','')) ;

            if($subscription_model==1){ // if we have Neflix mode
                // WpStream media types are not directly purchasable in this mode.
                if( $product_type_slug=='live_stream' || $product_type_slug=='video_on_demand' || $product_type_slug=='wpstream_bundle' ){
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

            // A new WooCommerce product is still an auto-draft `simple` product
            // when this hook renders. Emit the type-scoped controls there so
            // WooCommerce can reveal them after the administrator selects a
            // WpStream type; the show_if_* classes keep them hidden otherwise.
            $post_id        = isset( $post->ID ) ? (int) $post->ID : 0;
            $is_new_product = $post_id && 'auto-draft' === get_post_status( $post_id );
            if ( ! $is_new_product && ! $this->wpstream_is_wpstream_wc_product_context( $post_id ) ) {
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
                    print '<div style="margin:10px;">'.esc_html__('You need to be an administrator in order to assign videos','wpstream').'</div>';
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

			// A Source Recording owns the cached DRM and hosted-player identity.
			// Retire that identity before replacing the source so its old key index
			// is still available to invalidate the lookup caches.
			if (
				has_term( 'video_on_demand', 'product_type', $post_id )
				&& ! empty( $_POST['_movie_url'] )
				&& is_scalar( $_POST['_movie_url'] )
			) {
				$previous_recording = (string) get_post_meta( $post_id, '_movie_url', true );
				$new_recording      = sanitize_text_field( wp_unslash( $_POST['_movie_url'] ) );
				if ( $new_recording !== $previous_recording ) {
					$this->wpstream_retire_vod_recording_identity( $post_id );
				}
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
                ( isset($wpstream_notices['wpstream_woo_notice']) && $wpstream_notices['wpstream_woo_notice']!='yes')  ){

     
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
            // This runs on every admin page while an update is pending, so the
            // answer is cached for 12h — including a negative one, otherwise a
            // failed lookup would retry per page load. Keyed by version so a
            // newer release triggers a fresh lookup.
            $cache_key = 'wpstream_release_date_' . md5( $plugin_slug . '|' . (string) $version );
            $cached    = get_transient( $cache_key );
            if ( false !== $cached ) {
                // 'none' is the stored form of a negative result.
                return 'none' === $cached ? false : $cached;
            }

            // Query the WordPress.org plugins info API (short timeout: this is
            // cosmetic notice logic and must never stall the admin).
            $api_url = 'https://api.wordpress.org/plugins/info/1.0/' . $plugin_slug . '.json';
            $response = wp_remote_get( $api_url, array( 'timeout' => 3 ) );

            // Resolve the answer (false = no usable date), then cache it once.
            $release_date = false;

            if ( ! is_wp_error( $response ) ) {
                // Decode the JSON body; a date needs a versions list to validate against.
                $data = json_decode( wp_remote_retrieve_body( $response ), true );

                if ( $data && isset( $data['versions'] ) ) {
                    // no version provided, get the latest version
                    if ( ! $version ) {
                        $version = $data['version'];
                    }

                    // The version must exist in the versions array.
                    // (note: last_updated is the plugin's overall date, not per-version)
                    if ( isset( $data['versions'][ $version ] ) && isset( $data['last_updated'] ) ) {
                        $release_date = date( 'Y-m-d', strtotime( $data['last_updated'] ) );
                    }
                }
            }

            set_transient( $cache_key, false === $release_date ? 'none' : $release_date, 12 * HOUR_IN_SECONDS );
            return $release_date;
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

            // CSRF gate: the dismiss JS sends the nonce printed next to the notice.
            check_ajax_referer( 'wpstream_notice_nonce', 'security' );
            // Dismissals are stored in a site-wide option, so require admin rights.
            if ( ! current_user_can( 'manage_options' ) ) {
                die();
            }

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
            return $this->main->get_live_channel_presentation()->register_metaboxes();
        }
        public function wpstream_start_stream_meta(){
            return $this->main->get_live_channel_presentation()->render_sidebar();
        }
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
		return $this->main->get_live_channel_presentation()->get_localization_flags();
	}



   /**
        * Register a hidden dashboard page used as the onboarding wizard endpoint.
        *
        * @return void
        */
        public function add_dashboard_page() {
			$this->main->get_onboarding()->render_part( 'dashboard_page' );
        }




        /**
         * Bootstrap the full-screen onboarding wizard screen (non-AJAX requests).
         *
         * @return void
         */
        public function wpstream_load_onboarding_wizard() {
			$this->main->get_onboarding()->render_part( 'standalone' );
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
			$this->main->get_onboarding()->render_part( 'page_footer' );
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
			echo $this->main->get_onboarding()->render();
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
			$this->main->get_onboarding()->render_part( 'wizard' );
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
			$this->main->get_onboarding()->render_part( 'account' );
        }

        public function randomPassword() {
			return $this->main->get_onboarding()->render_part( 'password' );
        }

        public function wpstream_onboarding_step2(){
			$this->main->get_onboarding()->render_part( 'path' );
        }

        public function wpstream_onboarding_step3_live_streaming(){
			$this->main->get_onboarding()->render_part( 'live' );
        }

        public function wpstream_onboarding_step_3_A_live_streaming_free_view(){
			$this->main->get_onboarding()->render_part( 'live_free' );
        }

        public function wpstream_onboarding_step_3_B_live_streaming_pay_per_view(){
			$this->main->get_onboarding()->render_part( 'live_ppv' );
        }

        public function wpstream_onboarding_woo_warning(){
			$this->main->get_onboarding()->render_part( 'woo_warning' );
        }

        public function wpstream_onboarding_step4_vod(){
			$this->main->get_onboarding()->render_part( 'vod' );
        }

        public function wpstream_onboarding_step_4_free_vod(){
			$this->main->get_onboarding()->render_part( 'vod_free' );
        }

        public function wpstream_obboarding_file_warning(){
			$this->main->get_onboarding()->render_part( 'file_warning' );
        }

        public function wpstream_onboarding_step_4_ppv_vod(){
			$this->main->get_onboarding()->render_part( 'vod_ppv' );
        }

        public function onboarding_wizard_header() {
			$this->main->get_onboarding()->render_part( 'wizard_header' );
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
			$this->main->get_onboarding()->render_part( 'wizard_footer' );
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
			return $this->main->get_onboarding()->handle_ajax( 'wpstream_on_board_create_channel_ppv' );
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
			return $this->main->get_onboarding()->handle_ajax( 'wpstream_on_board_create_channel' );
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
			return $this->main->get_onboarding()->handle_ajax( 'wpstream_on_board_create_free_vod' );
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
			return $this->main->get_onboarding()->handle_ajax( 'wpstream_on_board_create_ppv_vod' );
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
			return $this->main->get_onboarding()->handle_ajax( 'wpstream_on_board_login' );
        }

		/**
		 * AJAX proxy: fetch a captcha challenge from the baker API and return it.
		 * Used on HTTP sites where the Altcha widget cannot run (requires Web Crypto / HTTPS).
		 * The JS side solves the PoW locally and sends back the full base64 Altcha payload.
		 * Logged-in administrators only: every call proxies an outbound request, so the
		 * endpoint is gated like the rest of the onboarding wizard.
		 */
		public function wpstream_get_captcha_challenge() {
			return $this->main->get_onboarding()->handle_ajax( 'wpstream_get_captcha_challenge' );
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
			return $this->main->get_onboarding()->handle_ajax( 'wpstream_on_board_register' );
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
			return $this->main->get_onboarding()->validate_registration( $wpstream_register_email, $wpstream_register_password );
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

        // The part count is caller-supplied: refuse a split the backend can
        // never assemble before spending a cloud call on it.
        if ($num_parts > self::MULTIPART_MAX_PARTS) {
            wp_send_json_error(
                sprintf(
                    /* translators: 1: requested part count, 2: maximum part count */
                    __('The file would be split into %1$d parts but at most %2$d are allowed. Reload the page to get the current uploader and try again.', 'wpstream'),
                    $num_parts,
                    self::MULTIPART_MAX_PARTS
                )
            );
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

        // Make API call to initiate multipart upload; the auth token is
        // injected by the transport (not-connected surfaces as a WP_Error
        // whose message is the legacy "Not connected to WPStream service").
        $api_params = array(
            'size' => $file_size,
            'name' => $clean_file_name,
            'content_type' => $content_type,
            'parts' => $num_parts
        );

        $response_data = $this->main->wpstream_live_connection->authorized_request('video/upload', $api_params);

        // Bubble up any API error.
        if (is_wp_error($response_data)) {
            wp_send_json_error($response_data->get_error_message());
            return;
        }
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

        // Get completion details. The client sends 'parts' as the plain part
        // COUNT (not a part list), so parse it as a positive integer.
        $parts = isset($_POST['parts']) ? absint(wp_unslash($_POST['parts'])) : 0;
        $file_name = sanitize_text_field($_POST['file_name']);
        $handle = sanitize_text_field($_POST['handle']);

        // Reject incomplete completion metadata: no parts uploaded or no file name.
        if ($parts < 1 || empty($file_name)) {
            wp_send_json_error('Invalid completion information');
            return;
        }

        // Send the finalize ("complete") request with the uploaded part list;
        // the auth token is injected by the transport (not-connected surfaces
        // as a WP_Error whose message is the legacy "Not connected to WPStream
        // service").
        $api_params = array(
            'parts' => $parts,
            'name' => $file_name,
            'handle' => $handle,
            'action' => 'complete'
        );

        $response_data = $this->main->wpstream_live_connection->authorized_request('video/upload', $api_params);

        // Bubble up any API error.
        if (is_wp_error($response_data)) {
            wp_send_json_error($response_data->get_error_message());
            return;
        }
        if (!isset($response_data['success']) || $response_data['success'] !== true) {
            $error_message = isset($response_data['error']) ? $response_data['error'] : 'Failed to complete multipart upload';
            wp_send_json_error($error_message);
            return;
        }

        /**
         * Fires after the cloud accepted a completed multipart VOD upload.
         *
         * @since 4.14.0
         *
         * @param string $file_name Uploaded file name.
         * @param string $handle    Upload handle issued at initiation.
         * @param int    $parts     Number of parts uploaded.
         */
        do_action( 'wpstream_multipart_upload_completed', $file_name, $handle, $parts );

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
        // CSRF gate: the update button JS sends the settings-page nonce as 'security'.
        check_ajax_referer( 'wpstream-settings-nonce', 'security' );
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
