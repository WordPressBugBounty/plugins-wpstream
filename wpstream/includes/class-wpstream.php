<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://wpstream.net
 * @since      3.0.1
 *
 * @package    Wpstream
 * @subpackage Wpstream/includes
 */


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      3.0.1
 * @package    Wpstream
 * @subpackage Wpstream/includes
 * @author     wpstream <office@wpstream.net>
 */
class Wpstream {

        /**
        * Store plugin main class to allow public access.
        *
        * @since             3.0.1
        * @var object      The main class.
        */
        public $main;
        /** @var Wpstream_Admin The admin-area controller instance. */
        public $admin;

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    3.0.1
	 * @access   protected
	 * @var      Wpstream_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    3.0.1
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    3.0.1
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    3.0.1
	 */
        
        /** @var Wpstream_Live_Api_Connection Cloud API client for channels/quota. */
        public $wpstream_live_connection;
        /** @var Wpstream_Player Player/rendering service. */
        public $wpstream_player;
        /** @var Wpstream_Quota_Manager Quota cache manager. */
        public $quota_manager;
		/** @var Wpstream_Channel_Settings Deep Module for per-Channel streaming settings. */
		public $channel_settings;
		/** @var Wpstream_Streaming_Content_Creation Deep Module for Live Channel and VOD creation. */
		public $streaming_content_creation;
		/** @var Wpstream_Drm_Key_Delivery Deep Module for live and VOD DRM Key delivery. */
		public $drm_key_delivery;
		/** @var Wpstream_Media_List Deep Module for Channel and VOD lists. */
		public $media_list;
		/** @var Wpstream_Playback_Presentation Deep Module for approved player presentation. */
		public $playback_presentation;
        /** @var object User quota service resolved from the live connection. */
        public $user_quota_service;
        /** @var mixed Unused/reserved property. */
        public $xtest;
        /** @var Wpstream_Admin Admin controller (also stored in $admin). */
        public $plugin_admin;
        /** @var Wpstream_Onboarding|null Lazily-created onboarding workflow module. */
        private $onboarding;
		/** @var Wpstream_Live_Channel_Presentation|null Lazily-created Live Channel presentation module. */
		private $live_channel_presentation;

	/**
	 * Bootstrap the plugin: resolve version/name, load dependencies, then wire
	 * all admin, public, AJAX, template, and service objects together.
	 */
	public function __construct() {
		// Expose this instance as the shared "main" service locator.
		$this->main = $this;

		// Resolve the plugin version from the defined constant, else fall back.
		if ( defined( 'WPSTREAM_PLUGIN_VERSION' ) ) {
            $this->version = WPSTREAM_PLUGIN_VERSION;
		} else {
            $this->version = '3.0.1';
		}

		// Text-domain / unique plugin identifier.
		$this->plugin_name = 'wpstream';

		// Require class files and instantiate the hook loader.
		$this->load_dependencies();
		// Register admin-area hooks (menus, metaboxes, WooCommerce, onboarding).
		$this->define_admin_hooks();
		// Register public-facing hooks (scripts, endpoints, shortcodes).
		$this->define_public_hooks();
        // Register AJAX handlers.
        $this->define_ajax_hooks();
		// Register the front-end page/single template loader.
		$this->wpstream_load_page_templates();
        // Register the "theme update available" admin notice.
        $this->wpstream_load_theme_notice();

        // Instantiate the cloud API connection (and user quota service).
        $this->wpstream_connection();
		// Compose the deep Channel Settings Module with its WordPress and Baker Adapters.
		$this->channel_settings = new Wpstream_Channel_Settings(
			new Wpstream_WordPress_Channel_Settings_Storage(),
			new Wpstream_Baker_Channel_Settings_Adapter( $this->wpstream_live_connection )
		);
		$this->wpstream_live_connection->set_channel_settings( $this->channel_settings );
		$this->drm_key_delivery = new Wpstream_Drm_Key_Delivery(
			$this->channel_settings,
			new Wpstream_WordPress_Drm_Key_Remote_Fetch()
		);
		$this->media_list = new Wpstream_Media_List(
			new Wpstream_Baker_Media_List_Active_Catalog( $this->wpstream_live_connection )
		);
		$this->playback_presentation = new Wpstream_Playback_Presentation( $this );
		$this->streaming_content_creation = new Wpstream_Streaming_Content_Creation(
			$this,
			$this->channel_settings,
			new Wpstream_Baker_Streaming_Content_Provisioning( $this->wpstream_live_connection ),
			new Wpstream_Baker_Streaming_Content_Recording_Catalog( $this->wpstream_live_connection )
		);
		$this->wpstream_live_connection->set_streaming_content_creation( $this->streaming_content_creation );
        // Instantiate the player service.
        $this->wpstream_player();
        // Instantiate the quota cache manager.
        $this->wpstream_init_quota_manager();

	}





        /**
         * Convert a quota figure from megabytes to gigabytes.
         *
         * @param float|int $megabytes Value in megabytes.
         * @return float Value in gigabytes, floored to two decimals.
         */
        public function wpstream_convert_band( $megabytes ) {
            // Quota payloads use binary megabytes. Never round remaining allowance up.
            return $this->wpstream_floor_decimals( (float) $megabytes / 1024, 2 );
        }

        /**
         * Floor a number to a given number of decimal places.
         *
         * @param float $value    The number to floor.
         * @param int   $decimals Number of decimal places.
         * @return float
         */
        public function wpstream_floor_decimals( $value, $decimals = 2 ) {
            // Clamp decimals to a non-negative integer.
            $decimals = max( 0, (int) $decimals );
            // Scale factor for the requested precision.
            $factor   = pow( 10, $decimals );

            // Multiply, floor, divide back, then format to fixed precision.
            return floatval( sprintf( '%.' . $decimals . 'f', floor( (float) $value * $factor ) / $factor ) );
        }


        /** @var WpStream_Ajax The AJAX handler service. */
        public $wpstream_ajax;

        /**
         * Load and instantiate the AJAX handler service.
         */
        private function define_ajax_hooks() {
            // Construct the AJAX service with the main instance (class autoloads).
            $this->wpstream_ajax = new WpStream_Ajax( $this->main );
        }


        /**
         * Load the cloud API connection and resolve the user quota service.
         */
        private function wpstream_connection(){
            // Instantiate the live/cloud API client (class autoloads).
            $this->wpstream_live_connection = new Wpstream_Live_Api_Connection();
            // Cache the user quota service exposed by the connection.
            $this->user_quota_service = $this->wpstream_live_connection->get_user_quota_service();
        }


        /**
         * Load and instantiate the player service.
         */
        private function wpstream_player(){
            // Build the player service with the main instance (class autoloads).
            $this->wpstream_player = new Wpstream_Player($this->main);
        }

        /**
         * Load and instantiate the quota cache manager.
         */
        private function wpstream_init_quota_manager() {
            // Build the quota cache manager with this main instance (class autoloads).
            $this->quota_manager = new Wpstream_Quota_Manager( $this );
        }


        /**
         * Load and register the front-end template loader.
         */
        private function wpstream_load_page_templates() {
	        // Register the template loader's hooks by constructing it (class autoloads).
	        new WpStream_Template_Loader();
        }

        /**
         * Load and register the companion-theme update admin notice.
         */
        private function wpstream_load_theme_notice() {
            // Register the notice by constructing it (class autoloads).
            new WPStream_Theme_Notice();
        }
        
        
	/**
	 * Load the dependencies the classmap autoloader cannot serve.
	 *
	 * Plugin classes resolve through Wpstream_Autoloader on first use, so this
	 * only requires what an autoloader can never load: the WooCommerce product
	 * types (kept behind a WooCommerce guard because they extend WC_Product)
	 * and the function-only helper files. It then creates the hook loader that
	 * the define_*_hooks() methods populate.
	 *
	 * @since    3.0.1
	 * @access   private
	 */
	private function load_dependencies() {

		// WooCommerce product-type classes extend WC_Product, so loading them
		// without WooCommerce active would fatal at class-link time; they stay
		// behind this guard instead of the classmap.
		if(  class_exists( 'WooCommerce' ) ){
			// Paid live-stream product type.
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-product-live-stream.php';
			// Paid video-on-demand product type.
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wc-product-video-on-demand.php';
		}

		// Function-only helper files: no class inside, so the autoloader can
		// never serve them and they must be required eagerly.

		// Channel-ownership authorization helper (wpstream_can_manage_channel),
		// used by every live/broadcast handler to enforce per-channel ownership.
		require_once plugin_dir_path(__FILE__) . 'Helpers/wpstream-authorization.php';

		// Viewing-entitlement helper (wpstream_user_entitled_for_product),
		// the single rule every playback access gate must call.
		require_once plugin_dir_path(__FILE__) . 'Helpers/wpstream-entitlement.php';

		// WpStream account credential accessors (constant override + options).
		require_once plugin_dir_path(__FILE__) . 'Helpers/wpstream-credentials.php';

		// Viewer-facing presentation strings (wpstream_not_live_message), shared by
		// the player, the public JS config and the bundled theme framework.
		require_once plugin_dir_path(__FILE__) . 'Helpers/wpstream-presentation.php';

		// My Account list collector (wpstream_account_list_products) shared by the
		// event-list and video-list templates.
		require_once plugin_dir_path(__FILE__) . 'Helpers/wpstream-account-lists.php';

		// Tunables (wpstream_tunable): cache TTLs, poll intervals and limits run
		// through their filter and are clamped in one place.
		require_once plugin_dir_path(__FILE__) . 'Helpers/wpstream-tunables.php';

		// Create the hook loader that other define_*_hooks() methods populate.
		$this->loader = new Wpstream_Loader();

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    3.0.1
	 * @access   private
	 */
	/**
	 * Whether a post edit route can present Live Channel controls.
	 *
	 * @param string       $post_type Current post type.
	 * @param WP_Post|null $post      Current post, when one exists.
	 * @return bool
	 */
	private function is_live_channel_admin_post( $post_type, $post = null ) {
		if ( 'wpstream_product' === $post_type ) {
			return true;
		}

		if ( 'product' !== $post_type ) {
			return false;
		}

		$post_id = $post instanceof WP_Post ? intval( $post->ID ) : 0;
		if ( ! $post_id && isset( $_GET['post'] ) ) {
			$post_id = absint( wp_unslash( $_GET['post'] ) );
		}

		if ( $post_id ) {
			$product_type = wpstream_get_product_type_slug( $post_id );
			return 'live_stream' === $product_type
				|| ( 'subscription' === $product_type && 'yes' === get_post_meta( $post_id, '_subscript_live_event', true ) );
		}

		return isset( $_GET['new_stream'] ) && 'new' === sanitize_key( wp_unslash( $_GET['new_stream'] ) );
	}

	/**
	 * Whether the current wp-admin screen presents Live Channel controls.
	 *
	 * @param WP_Screen|null $screen Current WordPress screen.
	 * @return bool
	 */
	private function is_live_channel_admin_screen( $screen ) {
		if ( ! $screen ) {
			return false;
		}

		if ( 'wpstream_page_wpstream_live_channels' === $screen->base ) {
			return true;
		}

		global $post;
		return $this->is_live_channel_admin_post( $screen->post_type, $post instanceof WP_Post ? $post : null );
	}

	private function define_admin_hooks() {

                // Build the admin controller and keep a reference to it.
                $plugin_admin = new Wpstream_Admin( $this->get_plugin_name(), $this->get_version(), $this->main );

		        $this->admin  = $plugin_admin;

			    // Admin CSS/JS and the plugin's admin menu (late priority).
			    $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin,  'enqueue_styles' );
		        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin,  'enqueue_scripts' );
				add_action(
					'admin_enqueue_scripts',
					function () {
						$screen = get_current_screen();
						if ( $this->is_live_channel_admin_screen( $screen ) ) {
							$this->get_live_channel_presentation()->enqueue_assets();
						}
					},
					20
				);
                $this->loader->add_action( 'admin_menu',            $plugin_admin,  'wpstream_manage_admin_menu',999);

                // "Chat Room" metabox: bind an existing Better Messages room to a channel.
                new Wpstream_Chat_Room_Binding();

                // Register the custom post types on init.
                $plugin_post_types = new Wpstream_Product();
                $this->loader->add_action( 'init', $plugin_post_types, 'create_custom_post_type', 999 );

                // save and render metaboxed
                // Register product metaboxes; persist their values on save; and,
                // on publish, create the remote streaming channel.
                $this->loader->add_action( 'add_meta_boxes',    $plugin_admin, 'add_wpstream_product_metaboxes' );
                $this->loader->add_action( 'save_post',     $plugin_admin, 'wpstream_free_product_update_post',1,2 );
                $this->loader->add_action( 'publish_wpstream_product',     $plugin_admin, 'wpstream_publish_wpstream_product',1,2 );
                $this->loader->add_action( 'publish_wpstream_product',     $plugin_admin, 'wpstream_create_remote_channel_on_publish',20,2 );
		        $this->loader->add_action( 'publish_wpstream_product_vod', $plugin_admin, 'wpstream_create_remote_channel_on_publish', 20, 2 );
		        $this->loader->add_action( 'publish_product', $plugin_admin, 'wpstream_create_remote_channel_on_publish', 20, 2 );
		        // Mirror of the publish hooks above: a permanently deleted Live
		        // Channel is deleted at the service too (trash is untouched).
		        $this->loader->add_action( 'before_delete_post', $plugin_admin, 'wpstream_delete_remote_channel_on_delete', 10, 2 );
		        $this->loader->add_action( 'before_delete_post', $plugin_admin, 'wpstream_invalidate_vod_drm_on_delete', 5, 2 );
		        $this->loader->add_action( 'transition_post_status', $plugin_admin, 'wpstream_invalidate_vod_drm_on_status_change', 10, 3 );
		        // WooCommerce restores products to their previous published status.
		        // Paid Live Channels follow the safer channel lifecycle instead:
		        // restore as draft and require an explicit republish.
		        $this->loader->add_filter( 'wp_untrash_post_status', $plugin_admin, 'wpstream_paid_live_untrash_status', 20, 3 );



                // make product virtual
                $this->loader->add_action( 'save_post',  $plugin_admin, 'wpstream_make_product_virtual',  99999, 2 );



				// Add Live Channel controls only on edit screens that can own them.
				add_action(
					'add_meta_boxes',
					function ( $post_type, $post ) {
						if ( $this->is_live_channel_admin_post( $post_type, $post ) ) {
							$this->get_live_channel_presentation()->register_metaboxes();
						}
					},
					10,
					2
				);

				// Load onboarding only when its page assets or one of its AJAX routes runs.
				add_action(
					'admin_enqueue_scripts',
					function () {
						$screen       = get_current_screen();
						$is_quickstart = $screen && 'wpstream_page_wpstream_onboard' === $screen->base;
						$is_post_edit  = isset( $_GET['onboard'] ) && 'yes' === $_GET['onboard'];

						if ( $is_quickstart || $is_post_edit ) {
							$this->get_onboarding()->enqueue_assets();
						}
					},
					20
				);

				$onboarding_ajax_actions = array(
					'wpstream_on_board_create_channel',
					'wpstream_on_board_create_channel_ppv',
					'wpstream_on_board_create_free_vod',
					'wpstream_on_board_create_ppv_vod',
					'wpstream_on_board_login',
					'wpstream_on_board_register',
					'wpstream_get_captcha_challenge',
				);
				foreach ( $onboarding_ajax_actions as $onboarding_ajax_action ) {
					add_action(
						'wp_ajax_' . $onboarding_ajax_action,
						function () use ( $onboarding_ajax_action ) {
							return $this->get_onboarding()->handle_ajax( $onboarding_ajax_action );
						}
					);
				}
                
                // Register AJAX actions for multipart uploads
                $this->loader->add_action( 'wp_ajax_wpstream_initiate_multipart_upload', $plugin_admin, 'handle_initiate_multipart_upload' );
                $this->loader->add_action( 'wp_ajax_wpstream_complete_multipart_upload', $plugin_admin, 'handle_complete_multipart_upload' );

                // General and plugin-update admin notices.
                $this->loader->add_action( 'admin_notices',                             $plugin_admin,'wpstream_admin_notice' );
                $this->loader->add_action( 'admin_notices',      $plugin_admin,'wpstream_plugin_update_available_notice' );

                // Dismiss-cache-notice AJAX endpoint.
                $this->loader->add_action( 'wp_ajax_wpstream_update_cache_notice',      $plugin_admin,'wpstream_update_cache_notice' );
//		        $this->loader->add_action( 'wp_ajax_wpstream_get_videos_list',  $plugin_admin,'wpstream_get_videos_list' );


        // Settings-tab "update plugin" AJAX endpoint.
        $this->loader->add_action( 'wp_ajax_wpstream_settings_tab_update_plugin', $plugin_admin, 'wpstream_settings_tab_update_plugin' );

                // add and save category extra fields
                // The same add/edit/create/save callbacks are shared across every taxonomy below.
                $this->loader->add_action( 'category_edit_form_fields',  $plugin_post_types,   'wpstream_category_callback_function', 10, 2);
                $this->loader->add_action( 'category_add_form_fields',   $plugin_post_types,   'wpstream_category_callback_add_function' );
                $this->loader->add_action( 'created_category',           $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);
                $this->loader->add_action( 'edited_category',            $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);

                // Same extra fields on the WooCommerce product category taxonomy.
                $this->loader->add_action( 'product_cat_edit_form_fields',  $plugin_post_types,  'wpstream_category_callback_function', 10, 2);
                $this->loader->add_action( 'product_cat_add_form_fields',   $plugin_post_types,  'wpstream_category_callback_add_function' );
                $this->loader->add_action( 'created_product_cat',           $plugin_post_types,  'wpstream_category_save_extra_fields_callback', 10, 2);
                $this->loader->add_action( 'edited_product_cat',            $plugin_post_types,  'wpstream_category_save_extra_fields_callback', 10, 2);


                // Same extra fields on the plugin's own wpstream_category taxonomy.
                $this->loader->add_action( 'wpstream_category_edit_form_fields', $plugin_post_types,   'wpstream_category_callback_function', 10, 2);
                $this->loader->add_action( 'wpstream_category_add_form_fields',  $plugin_post_types,   'wpstream_category_callback_add_function' );
                $this->loader->add_action( 'created_wpstream_category',          $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);
                $this->loader->add_action( 'edited_wpstream_category',           $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);


                // Same extra fields on the wpstream_actors taxonomy.
                $this->loader->add_action( 'wpstream_actors_edit_form_fields',  $plugin_post_types,   'wpstream_category_callback_function', 10, 2);
                $this->loader->add_action( 'wpstream_actors_add_form_fields',   $plugin_post_types,   'wpstream_category_callback_add_function' );
                $this->loader->add_action( 'created_wpstream_actors',           $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);
                $this->loader->add_action( 'edited_wpstream_actors',            $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);

                // Same extra fields on the wpstream_movie_rating taxonomy.
                $this->loader->add_action( 'wpstream_movie_rating_edit_form_fields',  $plugin_post_types,   'wpstream_category_callback_function', 10, 2);
                $this->loader->add_action( 'wpstream_movie_rating_add_form_fields',   $plugin_post_types,   'wpstream_category_callback_add_function' );
                $this->loader->add_action( 'created_wpstream_movie_rating',           $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);
                $this->loader->add_action( 'edited_wpstream_movie_rating',            $plugin_post_types,   'wpstream_category_save_extra_fields_callback', 10, 2);
          
                       
                // WooCommerce-only admin hooks: register the custom product types,
                // their data tabs/fields, pricing visibility, and add-to-cart handling.
                // Deferred to plugins_loaded because WooCommerce may load after this
                // plugin, and detection uses class_exists (not the active_plugins
                // option) so network-activated WooCommerce on multisite is seen too.
                // Registered directly (not via the loader) because the loader runs
                // before plugins_loaded fires; every hook below fires later than
                // plugins_loaded, so nothing is missed by the deferral.
                add_action( 'plugins_loaded', function () use ( $plugin_admin ) {
                    // Without WooCommerce none of these hooks have anything to do.
                    if ( ! class_exists( 'WooCommerce' ) ) {
                        return;
                    }

                    // Register product types and map them to their PHP classes.
                    add_action( 'init',                          array( $plugin_admin, 'wpstream_add_custom_wc_products' ) );
                    add_filter( 'product_type_selector',         array( $plugin_admin, 'wpstream_add_products' ) );
                    add_filter( 'woocommerce_product_class',     array( $plugin_admin, 'wpstream_add_products_class' ), 10, 2 );
                    // Pricing-field visibility, tab hiding, and purchasability rules.
                    add_action( 'admin_enqueue_scripts',         array( $plugin_admin, 'wpstream_enqueue_wc_product_pricing_visibility' ), 20 );
                    add_filter( 'woocommerce_product_data_tabs', array( $plugin_admin, 'wpstream_hide_attributes_data_panel' ), 10, 1 );
                    add_filter( 'woocommerce_is_purchasable',    array( $plugin_admin, 'wpstream_hide_buy_now_subscription_mode' ), 10, 2 );

                    // Custom general-tab fields (render + save) and add-to-cart wiring.
                    add_action( 'woocommerce_product_options_general_product_data', array( $plugin_admin, 'wpstream_add_custom_general_fields' ), 20 );
                    add_filter( 'woocommerce_process_product_meta', array( $plugin_admin, 'wpstream_add_custom_general_fields_save' ), 10, 1 );
					add_action( 'woocommerce_process_product_meta', array( $plugin_admin, 'wpstream_initialize_paid_streaming_product' ), 30, 1 );
                    add_action( 'woocommerce_live_stream_add_to_cart', array( $plugin_admin, 'wpstream_add_to_cart' ), 10, 1 );
                    add_action( 'woocommerce_video_on_demand_add_to_cart', array( $plugin_admin, 'wpstream_add_to_cart' ), 10, 1 );
                    add_filter( 'woocommerce_loop_add_to_cart_link', array( $plugin_admin, 'replacing_add_to_cart_button' ), 10, 2 );
                } );
	}





	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    3.0.1
	 * @access   private
	 */
	private function define_public_hooks() {

		// Build the public-facing controller.
		$plugin_public = new Wpstream_Public( $this->get_plugin_name(), $this->get_version(), $this->main );

		// Front-end styles.
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		// Registered on `wp` (not `wp_enqueue_scripts`/`wp_head`) because block themes render
		// the Post Content block - and thus our `the_content` filter that enqueues/localizes
		// these scripts - before `wp_head` ever fires, leaving the handles unregistered.
		$this->loader->add_action( 'wp', $plugin_public, 'enqueue_scripts' );

		// Custom rewrite endpoints and their query vars.
		$this->loader->add_action( 'init', $plugin_public,'wpstream_my_custom_endpoints' );
		$this->loader->add_filter( 'query_vars',$plugin_public, 'wpstream_my_custom_query_vars', 0 );

		//live stream action
		// Streaming cookies plus the RTMP/3rd-party/VOD streaming-key handlers.
		$this->loader->add_action('init',$plugin_public,'wpstream_set_cookies',0);
		$this->loader->add_action('init',$plugin_public,'wpstream_live_streaming_key');
		$this->loader->add_action('init',$plugin_public,'wpstream_live_streaming_key_for_3rdparty');
		$this->loader->add_action('init',$plugin_public,'wpstream_live_streaming_key_vod',10);

		// woo action
		// Product-page content wrappers and order/email extras.
		$this->loader->add_action( 'woocommerce_before_single_product', $plugin_public,'wpstream_non_image_content_wrapper_start', 20 );
		$this->loader->add_action( 'woocommerce_after_single_product', $plugin_public,'wpstream_non_image_content_wrapper_end', 20 );
		$this->loader->add_action( 'woocommerce_thankyou_order_received_text', $plugin_public,'wpstream_thankyou_extra', 20,2 );
		$this->loader->add_action( 'woocommerce_email_order_details', $plugin_public,'wpstream_email_order_details', 20,4 );

		// My Account: add the Events/Videos menu items and their endpoint content.
		// Run after themes have built their account navigation so a theme that
		// replaces the menu cannot discard the plugin-owned Events/Videos links.
		$this->loader->add_filter( 'woocommerce_account_menu_items', $plugin_public, 'wpstream_custom_my_account_menu_items', 99 );
		$this->loader->add_action( 'woocommerce_account_event-list_endpoint', $plugin_public,'wpstream_custom_endpoint_content_event_list' );
		$this->loader->add_action( 'woocommerce_account_video-list_endpoint', $plugin_public,'wpstream_custom_endpoint_video_list' );

		// Flush rewrite rules on theme switch; register plugin and WPBakery shortcodes.
		$this->loader->add_action( 'after_switch_theme', $plugin_public,'wpstream_custom_flush_rewrite_rules' );
		$this->loader->add_action('init', $plugin_public,'wpstream_shortcodes');
		$this->loader->add_action('vc_before_init', $plugin_public,'wpstream_bakery_shortcodes');

		// CORS preflight check for the API (uses a plain function callback).
		$this->loader->add_action('wo_before_api', 'wpstream_cors_check_and_response',10,1);

		// Theme-integration filters/actions: search, sidebars, archives, and
		// author/episode/past-broadcast content resolution by post type.
		$this->loader->add_filter( 'wpstream_search_template_item_post_type', $plugin_public, 'wpstream_search_template_add_item_post_type' );
		$this->loader->add_filter( 'wpstream_sidebar_id_by_post_type', $plugin_public, 'wpstream_sidebar_id_by_post_type' );
		$this->loader->add_filter( 'wpstream_header_search_values', $plugin_public, 'wpstream_header_search_values' );
		$this->loader->add_filter( 'wpstream_extend_category_archive_query_filter', $plugin_public, 'wpstream_extend_category_archive_query_filter_callback' );
		$this->loader->add_filter( 'wpstream_archives_lists_taxonomy_labels', $plugin_public, 'wpstream_archives_lists_taxonomy_labels_callback' );
		$this->loader->add_filter( 'wpstream_author_archive_list_taxonomy_labels', $plugin_public, 'wpstream_author_archive_list_taxonomy_labels_callback' );
		$this->loader->add_action( 'wpstream_vod_attached_to_channel', $plugin_public, 'wpstream_vod_attached_to_channel' );
		$this->loader->add_action( 'wpstream_additional_content_post_type', $plugin_public, 'wpstream_additional_content_post_type_callback' );
		$this->loader->add_action( 'wpstream_post_author_content_post_type_list', $plugin_public, 'wpstream_post_author_content_post_type_list_callback' );
		$this->loader->add_action( 'wpstream_author_content_simple_post_type_message', $plugin_public, 'wpstream_author_content_simple_post_type_message_callback', 10, 2 );
		$this->loader->add_action( 'wpstream_author_content_post_type_message', $plugin_public, 'wpstream_author_content_post_type_message_callback', 10, 2 );
		$this->loader->add_action( 'wpstream_show_sidebar_for_post_type', $plugin_public, 'wpstream_show_sidebar_for_post_type_callback', 10, 2 );
		$this->loader->add_action( 'wpstream_video_episodes_post_type', $plugin_public, 'wpstream_video_episodes_post_type_callback' );
		$this->loader->add_action( 'wpstream_video_past_broadcast_post_type', $plugin_public, 'wpstream_video_past_broadcast_post_type_callback' );
		$this->loader->add_action( 'wpstream_additional_content_post_type_label', $plugin_public, 'wpstream_additional_content_post_type_label_callback', 10, 2 );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    3.0.1
	 */
	public function run() {
		// Hand off to the loader, which calls add_action/add_filter for every hook.
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     3.0.1
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		// Return the stored text-domain / identifier string.
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     3.0.1
	 * @return    Wpstream_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		// Return the hook loader instance.
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     3.0.1
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
            // Return the resolved plugin version string.
            return $this->version;
	}

	/**
	 * Return the onboarding workflow, creating it only when an onboarding route
	 * actually needs it.
	 *
	 * @return Wpstream_Onboarding
	 */
	public function get_onboarding() {
		if ( ! $this->onboarding ) {
			$this->onboarding = new Wpstream_Onboarding( $this );
		}

		return $this->onboarding;
	}

	/**
	 * Return the Live Channel admin presentation, creating it only when a
	 * relevant screen or shared go-live card needs it.
	 *
	 * @return Wpstream_Live_Channel_Presentation
	 */
	public function get_live_channel_presentation() {
		if ( ! $this->live_channel_presentation ) {
			$this->live_channel_presentation = new Wpstream_Live_Channel_Presentation( $this );
		}

		return $this->live_channel_presentation;
	}

    /**
     * Whether WordPress currently reports a pending update for this plugin.
     *
     * @return bool True when an update is available in the update_plugins transient.
     */
    public function is_plugin_outdated(){
	    // WordPress caches pending plugin updates in this site transient.
	    $update_data = get_site_transient('update_plugins');
	    // The plugin's basename key within that transient.
	    $plugin_path = 'wpstream/wpstream.php';

	    // Presence of a response entry means an update is offered.
	    if (isset($update_data->response[$plugin_path])) {
		    return true;
	    }

	    // No entry -> up to date.
	    return false;
    }


      
        /**
         * Print the account resource summary (data/storage or hours) shown in the UI.
         *
         * @param array $pack_details Quota payload for the current account.
         */
        public function show_user_data($pack_details){
			// Branch A: data/storage (megabyte) plans - when NOT using streaming hours.
			if ( ! isset( $pack_details['use_streaming_hours'] ) || $pack_details['use_streaming_hours'] !== true ) {
				// Only render when both data and storage figures are present.
				if ( isset( $pack_details['available_data_mb'] ) && isset( $pack_details['available_storage_mb'] ) ) {
					// Convert available data MB to GB, clamping negatives to zero.
					$wpstream_convert_band = $this->wpstream_convert_band( $pack_details['available_data_mb'] );
					if ( $wpstream_convert_band < 0 ) {
						$wpstream_convert_band = 0;
					}

					// Convert available storage MB to GB, clamping negatives to zero.
					$wpstream_convert_storage = $this->wpstream_convert_band( $pack_details['available_storage_mb'] );
					if ( $wpstream_convert_storage < 0 ) {
						$wpstream_convert_storage = 0;
					}

					// Output the cloud data + storage summary line.
					print '<div class="pack_details_wrapper">'
						  . '<strong>' . __( 'Your account information: ', 'wpstream' ) . '</strong> '
						  . __( 'You have ', 'wpstream' ) . '<strong id="wpstream_available_data">' . abs( $wpstream_convert_band ) . ' GB</strong> '
						  . __( 'available cloud data and ', 'wpstream' )
						  . '<strong id="wpstream_available_storage">' . abs( $wpstream_convert_storage ) . ' GB</strong> '
						  . __( 'available cloud storage', 'wpstream' ) . '.';

					// Upgrade link plus hidden inputs carrying the raw MB figures for JS.
					print '<a href="https://wpstream.net/pricing/" class="wpstream_upgrade_topbar" target="_blank">' . esc_html__( 'Upgrade Plan', 'wpstream' ) . '</a>';
					print '</div>';
					print '<input type="hidden" id="wpstream_band" value="' . esc_attr( $pack_details['available_data_mb'] ) . '">';
					print '<input type="hidden" id="wpstream_storage" value="' . esc_attr( $pack_details['available_storage_mb'] ) . '">';
				}
			} else {
				// Branch B: streaming-hours plans (viewer/broadcast/storage hours).
				if ( isset( $pack_details['available_viewer_hours'] ) && isset( $pack_details['available_broadcast_hours'] ) && isset( $pack_details['available_storage_hours'] ) ) {
					// Normalize viewer hours to a non-negative float.
					$available_viewer_hours = floatval( $pack_details['available_viewer_hours'] );
					if ( $available_viewer_hours < 0 ) {
						$available_viewer_hours = 0;
					}

					// Normalize broadcast hours to a non-negative float.
					$available_broadcast_hours = floatval( $pack_details['available_broadcast_hours'] );
					if ( $available_broadcast_hours < 0 ) {
						$available_broadcast_hours = 0;
					}

					// Normalize storage hours to a non-negative float.
					$available_storage_hours = floatval( $pack_details['available_storage_hours'] );
					if ( $available_storage_hours < 0 ) {
						$available_storage_hours = 0;
					}

					// Floor each figure to two decimals for display.
					$formatted_viewer_hours    = $this->wpstream_floor_decimals( $available_viewer_hours, 2 );
					$formatted_broadcast_hours = $this->wpstream_floor_decimals( $available_broadcast_hours, 2 );
					$formatted_storage_hours   = $this->wpstream_floor_decimals( $available_storage_hours, 2 );

					// Output the viewer/broadcast/storage hours summary line.
					print '<div class="pack_details_wrapper">'
						  . __( 'Available streaming resources: ', 'wpstream' )
						  . '<strong id="wpstream_available_viewer_hours">' . abs( $formatted_viewer_hours ) . ' viewer</strong> '
						  . __( 'hours, ', 'wpstream' )
						  . '<strong id="wpstream_available_broadcast_hours">' . abs( $formatted_broadcast_hours ) . ' broadcast</strong> '
						  . __( 'hours, ', 'wpstream' )
						  . '<strong id="wpstream_available_storage_hours">' . $formatted_storage_hours . ' storage</strong> '
						  . __( 'hours', 'wpstream' ) . '.';

					// Upgrade link plus hidden inputs carrying the raw hour figures for JS.
					print '<a href="https://wpstream.net/pricing/" class="wpstream_upgrade_topbar" target="_blank">' . esc_html__( 'Upgrade Plan', 'wpstream' ) . '</a>';
					print '</div>';
					print '<input type="hidden" id="wpstream_viewer_hours" value="' . esc_attr( $pack_details['available_viewer_hours'] ) . '">';
					print '<input type="hidden" id="wpstream_broadcast_hours" value="' . esc_attr( $pack_details['available_broadcast_hours'] ) . '">';
					print '<input type="hidden" id="wpstream_storage_hours" value="' . esc_attr( $pack_details['available_storage_hours'] ) . '">';
				}
			}
		}

        
        /**
	 * help function for media list elementor widget
	 *
	 * Compatibility delegate for customer themes that call the historical
	 * renderer on the main plugin object.
	 *
	 * @since     3.0.1
	 * @param     array       $attributes Widget attributes (counts, type, order, labels).
	 * @param     string|null $content    Unused shortcode inner content.
	 * @return    string      Rendered HTML markup for the media grid.
	 */

        public function wpstream_media_list_elementor_function($attributes, $content = null){
				$kind = isset( $attributes['product_type'] ) && 2 === intval( $attributes['product_type'] )
					? 'vod'
					: 'live_channel';
				return $this->media_list->render( $kind, is_array( $attributes ) ? $attributes : array() );
        }

        
        
        
        
        
        
        
        
        
        
        
        
        /**
	 * help function for player elementr widget
	 *
	 * Renders the standard video player for a given product id, or resolves the
	 * author's first channel when only a user_id is supplied.
	 *
	 * @since     3.0.1
	 * @param     array       $attributes Widget attributes ('id', 'user_id').
	 * @param     string|null $content    Unused shortcode inner content.
	 * @return    string      Rendered player markup.
	 */

        public function wpstream_insert_player_elementor($attributes, $content = null){
                // Normalize incoming attributes with defaults.
                $product_id     =   '';
                $return_string  =   '';
                $attributes =   shortcode_atts(
                    array(
                        'id'                       => 0,
                        'user_id'                  => 0,
                    ), $attributes) ;


                // Explicit product id, if provided.
                if ( isset($attributes['id']) ){
                    $product_id=$attributes['id'];
                }
                // Optional author id used to look up a channel.
                if ( isset($attributes['user_id']) ){
                    $user_id = intval( $attributes['user_id'] );
                }

                // No product id but a user id: resolve that author's first channel.
                if(intval($product_id)==0 && $user_id!=0 ){
                    $product_id= $this->wpstream_player_retrieve_first_id($user_id);
                }



                // Buffer the player output so it can be returned as a string.
                ob_start();
                // Render the player shortcode inside the wrapper markup below.
                ?>
                <div class="wpstream_insert_player_elementor_wrapper">
                    <?php
                    $this->main->wpstream_player->wpstream_video_player_shortcode($product_id);
                    ?>
                </div>
                <?php
                // Capture and return the buffered markup.
                $return_string= ob_get_contents();
                ob_end_clean();

                return $return_string;
        }
        

        /**
         * Resolve the first channel/event id belonging to a given author.
         *
         * @param string|int $received_user_id Author user id.
         * @return int|string Post id of the author's first channel, or 0 when none.
         */
        public function wpstream_player_retrieve_first_id($received_user_id=''){
            // Free vs paid channel type is a site-wide option.
            $channel_type   =   get_option ('wpstream_user_streaming_channel_type');
            // Look up the author's most relevant event id for that type.
            $product_id     =   $this->wpstream_get_current_event_per_author($received_user_id,$channel_type);
            return $product_id;
        }

        /**
        * Deprecated misspelled alias of wpstream_player_retrieve_first_id().
        *
        * Kept because the method is public and may be called by external code.
        *
        * @deprecated 4.13.4 Use wpstream_player_retrieve_first_id() instead.
        */
        public function wpstream_player_retrive_first_id($received_user_id=''){
            return $this->wpstream_player_retrieve_first_id($received_user_id);
        }
        
        
        
        /**
        * edited 4.0
        * 
        * Check if the current user is allowed to stream.
        *
        * The one broadcasting-permission rule: administrators always may; other
        * users need their primary role on the "streamer roles" list or the
        * "regular users may stream" option. The verdict passes through the
        * wpstream_user_can_stream filter, so membership plugins replace the
        * role rule in one place. Guests are refused before the filter.
        *
        * @since    3.7
        * @return bool
        */
        
        public function wpstream_check_user_can_stream(){
            // Current user for the role/capability checks below.
            $current_user       =   wp_get_current_user();

            // Guests can never stream.
            if( !is_user_logged_in() ){
                return false;
            }

            // Admins can always broadcast.
            $can = current_user_can( 'administrator' );

            if ( ! $can ) {
                // Site-configured list of roles that are allowed to stream.
                $extra_roles    =   get_option( 'wpstream_stream_role', true );
                // The user's primary role (first in the roles array).
                $user_role = '';
                if( is_array( $current_user->roles) && count( $current_user->roles ) > 0 ){
                    $user_role = $current_user->roles[0];
                }

                // Allow when the user's role is in the configured allow-list.
                if ( is_array($extra_roles) && in_array( $user_role, $extra_roles ) ) {
                    $can = true;
                }

                // Allow when the "regular users may stream" option is enabled.
                if(function_exists('wpstream_get_option') && intval(wpstream_get_option('allow_streaming_regular_users',''))==1 ){
                    $can = true;
                }
            }

            /**
             * Filters whether the current (logged-in) user may broadcast.
             *
             * Gates every go-live surface: the start/stop endpoints, the
             * start-streaming dashboard and channel creation.
             *
             * @since 4.14.0
             *
             * @param bool $can     The built-in verdict (administrator, streamer
             *                      role list, or the regular-users option).
             * @param int  $user_id Current user ID.
             */
            return (bool) apply_filters( 'wpstream_user_can_stream', $can, intval( $current_user->ID ) );
        }
        

         /**
        * Start Streaming wrapper
        *
        * Ensures a channel exists (creating one for front-end streamers when
        * needed), prints the error-modal scaffolding, then renders the start-
        * streaming unit.
        *
        * @since    3.7
        * @param int    $item_id Channel/product id, or 0 to auto-resolve.
        * @param string $type    Streaming unit type/context.
        */
        public function wpstream_live_stream_unit_wrapper($item_id,$type){
            // Coerce to an integer id.
            $item_id = intval($item_id);

            // The unit's controller script (+ its QR dependency chain) and the shared
            // admin stylesheet are registered on every page but only enqueued here,
            // where the go-live unit actually renders (scripts print in the footer).
            wp_enqueue_script( 'wpstream-start-streaming' );
            wp_enqueue_style( 'wpstream_front_style' );

            if($item_id == 0){
                //retrive or  create channel for front end streamers
                $item_id=$this->wpstream_retrieve_front_end_channel();
            }
            // Modal backdrop and reusable error-notification markup.
            print'<div class="wpstream_modal_background"></div>';
            print '<div class="wpstream_error_modal_notification"><div class="wpstream_error_content">er2</div>
            <div class="wpstream_error_ok wpstream_button" type="button">'.esc_html__('Close','wpstream').'</div>
            </div>';
			// Render through the shared Live Channel presentation module.
			$this->get_live_channel_presentation()->render_channel( $item_id, $type );
        }
        
        
         /**
        * Start Streaming wrapper for wpstream theme
        *
        * Same as wpstream_live_stream_unit_wrapper() but renders the theme's
        * variant of the start-streaming unit.
        *
        * @since    3.7
        * @param int    $item_id Channel/product id, or 0 to auto-resolve.
        * @param string $type    Streaming unit type/context.
        */

        public function wpstream_live_stream_unit_wrapper_for_theme($item_id,$type){
            // Coerce to an integer id.
            $item_id = intval($item_id);

            // Same render-time asset loading as wpstream_live_stream_unit_wrapper().
            wp_enqueue_script( 'wpstream-start-streaming' );
            wp_enqueue_style( 'wpstream_front_style' );

            if($item_id == 0){
                //retrive or  create channel for front end streamers
                $item_id=$this->wpstream_retrieve_front_end_channel();
            }
            // Modal backdrop and reusable error-notification markup.
            print'<div class="wpstream_modal_background"></div>';
            print '<div class="wpstream_error_modal_notification"><div class="wpstream_error_content">er2</div>
            <div class="wpstream_error_ok wpstream_button" type="button">'.esc_html__('Close','wpstream').'</div>
            </div>';
			// Render the companion-theme variant through the same presentation module.
			$this->get_live_channel_presentation()->render_channel( $item_id, 'theme' );
        }
        
        
        /**
        * retrive channel for front end streaming
        *
        * Returns the current user's front-end channel id, creating a new event
        * when the user does not yet have one.
        *
        * @since    3.7
        * @return int Channel/product id for the current user.
        */
        public function wpstream_retrieve_front_end_channel(){

            // Current user plus the site-wide channel type and default price.
            $current_user   = wp_get_current_user();
            $channel_type   = get_option ('wpstream_user_streaming_channel_type');
            $channel_price  = floatval( get_option ('wpstream_user_streaming_default_price') );

            // Look for an existing channel owned by this user.
            $front_end_streamin_channel = $this->wpstream_get_current_event_per_author($current_user->ID,$channel_type);

            // None found: create one on the fly.
            if(intval($front_end_streamin_channel) == 0){
                $front_end_streamin_channel= $this->wpstream_create_front_end_event($current_user->ID,$current_user->user_login ,$channel_type,$channel_price);
            }
            return $front_end_streamin_channel;

        }

        /**
        * Deprecated misspelled alias of wpstream_retrieve_front_end_channel().
        *
        * Kept because the method is public and may be called by external code.
        *
        * @deprecated 4.13.4 Use wpstream_retrieve_front_end_channel() instead.
        */
        public function wpstream_retrive_front_end_channel(){
            return $this->wpstream_retrieve_front_end_channel();
        }

        /**
        * create the event from front end
        *
        * Inserts a new channel post (free CPT or paid WooCommerce product),
        * setting price/terms for paid channels, and flags it as a live event.
        *
        * @since    3.7
        * @param int    $userID        Author user id.
        * @param string $userLogin     Author login, used in the channel title.
        * @param string $channel_type  'paid' for a WooCommerce product, else free.
        * @param float  $channel_price Price to set for paid channels.
        * @return int|void New post id, or void when the user may not stream.
        */

        public function wpstream_create_front_end_event($userID,$userLogin,$channel_type,$channel_price){
			$request = array(
				'actor_id' => intval( $userID ),
				'owner_id' => intval( $userID ),
				'kind'     => 'live_channel',
				'access'   => 'paid' === $channel_type ? 'paid' : 'free',
				'title'    => sprintf( esc_html__( '%s Channel', 'wpstream' ), $userLogin ),
			);
			if ( 'paid' === $request['access'] ) {
				$request['price'] = $channel_price;
			}

			$result = $this->streaming_content_creation->create( $request );
			return ! empty( $result['success'] ) ? intval( $result['content_id'] ) : null;
        }

        /**
        * Deprecated misspelled alias of wpstream_create_front_end_event().
        *
        * Kept because the method is public and may be called by external code.
        *
        * @deprecated 4.13.4 Use wpstream_create_front_end_event() instead.
        */
        public function wpstrea_create_front_end_event($userID,$userLogin,$channel_type,$channel_price){
            return $this->wpstream_create_front_end_event($userID,$userLogin,$channel_type,$channel_price);
        }



        /**
        * Find the first channel/event id owned by a given author.
        *
        * @param int    $userID       Author user id.
        * @param string $channel_type 'paid' for a WooCommerce product, else free.
        * @return int Post id of the author's first matching channel, or 0.
        */
        public function wpstream_get_current_event_per_author($userID,$channel_type){

            // Free channels are CPTs; paid channels are WooCommerce products.
            $post_type='wpstream_product';
            if($channel_type=='paid'){
                $post_type='product';
            }

            // Query for a single post id owned by this author.
            $args = array(

                'post_type'         =>  $post_type,
                'author'            =>  $userID,
                'posts_per_page'    =>  1,
                'fields'            =>  'ids'
            )
                    ;
            $author_posts = new WP_Query( $args );
            // Use the found id, or 0 when the author has no channel.
            if( $author_posts->have_posts() ) {
                $author_posts->the_post();
                $the_id= get_the_ID();
            }else{
                 $the_id= 0;
            }

            // Restore global post/query state after the custom query.
            wp_reset_query();
            wp_reset_postdata();
            return $the_id;
        }

		/**
		 * Cleanup old logs
		 */
		public function cleanup_logs() {
			// Delegate to the logger, which prunes entries past its retention window.
			$logger = new WPStream_Logger();
			$logger->clear_old_logs();
		}
}
